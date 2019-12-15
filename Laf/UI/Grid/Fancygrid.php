<?php


namespace Laf\UI\Grid;

use Laf\Database\Db;
use Laf\Util\Util;

/**
 * Class Fancygrid
 * @package Laf\UI\Grid
 */
class Fancygrid
{

	private $id;
	private $gridName;
	private $sql_query;
	private $columns_list = [];
	private $params_list = [];
	private $paramsCount = 0;
	private $filters = [];
	private $requiredParams = [];

	/**
	 * Fancygrid constructor.
	 * @param string $gridName
	 * @param array $params
	 * @param array $filters Just put $_GET here
	 */
	public function __construct(string $gridName, $params = [], $filters = [])
	{
		$this->setGridName($gridName);
		$this->setFilters($filters);
		$this->setParamsList($params);
	}

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
	protected function setId($id)
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
	public function getSqlQuery()
	{
		return $this->sql_query;
	}

	/**
	 * @param mixed $sql_query
	 * @return Fancygrid
	 */
	public function setSqlQuery($sql_query)
	{
		$this->sql_query = $sql_query;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getColumnsList(): array
	{
		return $this->columns_list;
	}

	/**
	 * @param array $columns_list
	 * @return Fancygrid
	 */
	public function setColumnsList(array $columns_list): Fancygrid
	{
		$this->columns_list = $columns_list;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getParamsList(): array
	{
		return $this->params_list;
	}

	/**
	 * @param array $params_list
	 * @return Fancygrid
	 */
	public function setParamsList(array $params_list): Fancygrid
	{
		$this->params_list = $params_list;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getRequiredParams(): array
	{
		return $this->requiredParams;
	}

	/**
	 * @param array $requiredParams
	 * @return Fancygrid
	 */
	public function setRequiredParams(array $requiredParams): Fancygrid
	{
		$this->requiredParams = $requiredParams;
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
	public function addParam(string $key, string $value): Fancygrid
	{
		$this->params[$key] = $value;
		return $this;
	}


	private function initialize(): Fancygrid
	{
		$gridInfo = Db::getRowAssoc("SELECT * FROM grid WHERE grid_name=:grid_name", [
			':grid_name' => $this->getGridName()
		]);

		if (count($gridInfo) < 4) {
			throw new \Exception('Missing Grid info');
		}
		if (Util::isJSON($gridInfo['params_list']))
			$this->setParamsList(json_decode($gridInfo['params_list'], true));
		if (Util::isJSON($gridInfo['columns_list']))
			$this->setColumnsList(json_decode($gridInfo['columns_list'], true));
		$this->setId($gridInfo['id']);
		$this->setGridName($gridInfo['grid_name']);
		$this->setSqlQuery($gridInfo['sql_query']);
		$this->setParamsCount(count($this->getParamsList()));

		/**
		 * if the grid requires a parameter and it wasn't supplied, throw an error
		 */

		$diff = array_diff($this->getParamsList(), array_keys($this->getFilters()));
		if (count($diff) > 0) {
			throw new \Exception("Missing Grid filters for " . join(', ', $diff));
		}

		return $this;
	}

	/**
	 * Parses the Grid's resposne and sends it to the browser, clearing any output coming before this
	 * @return void
	 * @throws \Exception
	 */
	public function handleJsonRequest(): void
	{
		ob_clean();
		header('Content-Type: application/json');
		try {
			echo $this->getJsonResponse();
		} catch (\Exception $ex) {
			$data = [
				'success' => false,
				'message' => 'Error has occurred',
				'data' => []
			];
			echo json_encode($data);
		}
		ob_flush();
	}

	/**
	 * Parses the grid response and returns it
	 * @return string
	 * @throws \Exception
	 */
	public function getJsonResponse(): string
	{
		try {
			$this->initialize();
		} catch (\Exception $ex) {
			$data = [
				'success' => false,
				'message' => 'Error has occurred while initializing grid',
				'data' => []
			];
			return json_encode($data);
		}

		if (!is_numeric($this->getId())) {
			$data = [
				'success' => false,
				'message' => 'Grid not found',
				'data' => []
			];
			return json_encode($data);
		}

		$sql = $this->generateSql($this->getFilters());


		$db = Db::getInstance();
		try {
			$stmt = $db->prepare($sql);
			foreach ($this->getParams() as $k => $v) {
				$stmt->bindValue($k, $v);
			}
			$stmt->execute();
			$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			if (!is_array($results)) {
				$results = [];
			}

			$data = [
				'success' => true,
				'data' => $results
			];
		} catch (\Exception $ex) {
			$data = [
				'success' => false,
				'message' => 'Failed to retrieve the data',
				'data' => []
			];
		}
		return json_encode($data);
	}

	private function generateSql($params = []): string
	{
		$page = 0;
		$sort = array_keys($this->getColumnsList())[0];
		$dir = 'ASC';
		$limit = 0;
		$sqlWhere = '';


		if (isset($params['page']) && is_numeric($params['page'])) {
			$page = $params['page'];
		}

		if (isset($params['start']) && is_numeric($params['start'])) {
			$start = $params['start'];
		}

		if (isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0) {
			$limit = $params['limit'] ?? 10;
		}

		if (isset($params['sort']) && in_array($params['sort'], array_keys($this->getColumnsList()))) {
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

				if (!in_array($property, array_keys($this->getColumnsList())))
					continue;

				$sqlWhere .= "\n\tAND`" . $property . "` " . $operator . " :" . $property;
				if ($operator == 'LIKE') {
					$this->addParam(':' . $property, '%' . $value . '%');
				} else {
					$this->addParam(':' . $property, $value);
				}
			}
		}

		$start = $page * $limit;


		$sqlWhere .= " ORDER BY `$sort` $dir \n";
		if ($limit > 0)
			$sqlWhere .= " LIMIT {$start}, {$limit} \n";

		$sql = "SELECT * FROM (\n {$this->getSqlQuery()} \n) {$this->getGridName()} \n{$sqlWhere}\n";
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

	public function exportToExcel(){

	}
}