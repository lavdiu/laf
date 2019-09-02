<?php


namespace Laf\UI\Grid;


use Grid\Grid;
use Laf\Database\Db;
use Laf\Util\Util;

/**
 * Class Fancygrid
 * @package Laf\UI\Grid
 */
class Fancygrid
{

	/**
	 * @param string $grid_name
	 * @param array $params
	 * @return string
	 */
	public function initialize(string $grid_name, array $params)
	{
		$rows = Grid::find(['name' => $grid_name]);
		if (isset($rows[0])) {
			/**
			 * @var Grid
			 */
			$grid = $rows[0];
			if ($grid->recordExists()) {
				$grid->setHashVal(Util::uuid());
				$grid->setHashExpirationVal(date('Y-m-d H:i', (time() + 1200)));
				$grid->setParamCountVal(json_encode($params));
				$grid->store();
				return $grid->getHashVal();
			}
			return "";
		}
		return "";
	}

	/**
	 * @param string $hash
	 * @return string
	 */
	public function handleJsonRequest(string $hash)
	{
		$gridId = Factory::findGridByHash($hash);
		if (is_numeric($gridId)) {
			$grid = new Grid($gridId);
			$db = Db::getInstance();
			try {
				$stmt = $db->prepare($grid->getSqlVal());
				foreach (json_decode($grid->getParamsCacheVal(), true) as $k => $v) {
					$stmt->bindValue(':' . $k, $v);
				}

				$stmt->execute();

				$data = [
					'success' => true,
					'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
				];
			} catch (\Exception $ex) {
				$data = [
					'success' => false,
					'message' => 'Failed to retrieve the data'
				];
			}
		} else {
			$data = [
				'success' => false,
				'message' => 'Table does not exist'
			];
		}
		return json_encode($data);
	}
}