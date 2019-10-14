<?php


namespace Laf\UI\Grid;


use Grid\Grid;
use Laf\Database\Db;
use Laf\Util\Util;
use mysql_xdevapi\Exception;

/**
 * Class Fancygrid
 * @package Laf\UI\Grid
 */
class Fancygrid
{

	private $id;
	private $gridName;
	private $sql;
	private $columns = [];
	private $params = [];
	private $paramsCount = 0;
	private $filters = [];

	/**
	 * @return mixed
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param mixed $id
	 * @return Fancygrid
	 */
	public function setId($id)
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getGridName()
	{
		return $this->gridName;
	}

	/**
	 * @param mixed $gridName
	 * @return Fancygrid
	 */
	public function setGridName($gridName)
	{
		$this->gridName = $gridName;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getSql()
	{
		return $this->sql;
	}

	/**
	 * @param mixed $sql
	 * @return Fancygrid
	 */
	public function setSql($sql)
	{
		$this->sql = $sql;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 * @param array $columns
	 * @return Fancygrid
	 */
	public function setColumns(array $columns): Fancygrid
	{
		$this->columns = $columns;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	/**
	 * @param array $params
	 * @return Fancygrid
	 */
	public function setParams(array $params): Fancygrid
	{
		$this->params = $params;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getParamsCount(): int
	{
		return $this->paramsCount;
	}

	/**
	 * @param int $paramsCount
	 * @return Fancygrid
	 */
	public function setParamsCount(int $paramsCount): Fancygrid
	{
		$this->paramsCount = $paramsCount;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getFilters(): array
	{
		return $this->filters;
	}

	/**
	 * @param array $filters
	 * @return Fancygrid
	 */
	public function setFilters(array $filters): Fancygrid
	{
		$this->filters = $filters;
		return $this;
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return Fancygrid
	 */
	public function addFilter(string $key, string $value): Fancygrid
	{
		$this->filters[$key] = $value;
		return $this;
	}


	private function initialize(array $gridInfo)
	{
		if (Util::isJSON($gridInfo['params']))
			$this->setParams(json_decode($gridInfo['params'], true));
		if (Util::isJSON($gridInfo['columns']))
			$this->setColumns(json_decode($gridInfo['columns'], true));
		$this->setId($gridInfo['id']);
		$this->setGridName($gridInfo['grid_name']);
		$this->setSql($gridInfo['sql']);
		$this->setParamsCount(count($this->getParams()));


		/**
		 * if the grid requires a filter and it wasn't supplied, throw an error
		 */
		$diff = array_diff($this->getParams(), array_keys($this->getFilters()));
		if (count($diff) > 0) {
			throw new \Exception("Missing Grid fiilters for " . join(', ', $diff));
		}


	}

	/**
	 * Parses the Grid's resposne and sends it to the browser, clearing any output coming before this
	 * @param string $grid_name
	 * @param array $filters
	 * @param array $params
	 * @return void
	 * @throws \Exception
	 */
	public function handleJsonRequest(string $grid_name, $filters = [], $params = []): void
	{
		ob_clean();
		header('Content-Type: application/json');
		echo $this->getJsonResponse($grid_name, $filters, $params);
		ob_flush();
	}

	/**
	 * Parses the grid response and returns it
	 * @param string $grid_name
	 * @param string[] $filters
	 * @param string[] $params
	 * @return string
	 * @throws \Exception
	 */
	public function getJsonResponse(string $grid_name, $filters = [], $params = []): string
	{
		$gridInfo = Db::getRowAssoc("SELECT * FROM grid WHERE grid_name=:grid_name", [
			':grid_name' => $grid_name
		]);
		$this->setFilters($filters);
		$this->initialize($gridInfo);
		$sql = $this->generateSql($params);


		if (is_numeric($gridInfo['id'])) {
			$db = Db::getInstance();
			try {
				$stmt = $db->prepare($gridInfo['sql']);
				foreach ($this->getFilters() as $k => $v) {
					$stmt->bindValue($k, $v);
				}

				$stmt->execute();

				$data = [
					'success' => true,
					'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
				];
			} catch (\Exception $ex) {
				$data = [
					'success' => false,
					'message' => 'Failed to retrieve the data',
					'data' => []
				];
			}
		} else {
			$data = [
				'success' => false,
				'message' => 'Table does not exist',
				'data' => []
			];
		}
		return json_encode($data);
	}

	private function generateSql($params = []): string
	{
		$page = 0;
		$sort = array_keys($this->getColumns())[0];
		$dir = 'ASC';
		$limit = 10;
		$sqlWhere = '';


		if (isset($params['page']) && is_numeric($params['page'])) {
			$page = $params['page'];
		}

		if (isset($params['start']) && is_numeric($params['start'])) {
			$start = $params['start'];
		}

		if (isset($params['limit']) && is_numeric($params['limit'])) {
			$limit = $params['limit'] ?? 10;
		}

		if (isset($params['sort']) && in_array($params['sort'], array_keys($this->getColumns()))) {
			$sort = $params['sort'];
		}

		if (isset($params['dir']) && in_array(strtolower($params['dir']), ['asc', 'desc'])) {
			$dir = $params['dir'];
		}

		if (isset($params['filter'])) {
			$sqlWhere .= " WHERE \n\t1=1 ";
			$_filter = urldecode($params['filter']);
			$_filter = json_decode($_filter);

			for ($i = 0; $i < count($_filter); $i++) {
				$filterItem = $_filter[$i];
				$operator = $this->getOperator($filterItem->operator);
				$value = $filterItem->value;
				$property = $filterItem->property;

				if (!in_array($property, array_keys($this->getColumns())))
					continue;

				$sqlWhere .= "\n\tAND`" . $property . "` " . $operator . ":" . $property;
				if ($operator == 'LIKE') {
					$this->addFilter(':' . $property, '%' . $value . '%');
				} else {
					$this->addFilter('.' . $property, $value);
				}
			}
		}

		$start = $page * $limit;


		$sqlWhere .= " ORDER BY `$sort` $dir \n";
		$sqlWhere .= " LIMIT {$start}, {$limit} \n";

		$sql = "SELECT * FROM (\n {$this->getSql()} \n) {$this->getGridName()} \n{$sqlWhere}\n";
		return $sql;
	}

	/**
	 * @param $operator
	 * @return string
	 */
	private function getOperator($operator): string
	{
		switch ($operator) {
			case 'lt':
				return '<';
				break;
			case 'gt':
				return '>';
				break;
			case '<=':
				return 'lteq';
				break;
			case '>=':
				return 'gteq';
				break;
			case 'eq':
			case 'stricteq':
				return '=';
				break;
				break;
			case 'noteq':
			case 'notstricteq':
				return '!=';
				break;
			case 'like':
				return 'LIKE';
				break;
		}
	}

	/**
	 *
	 */
	public function getJavaScriptSettings(): string
	{

	}
}