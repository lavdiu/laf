<?php

namespace Laf\UI\Grid;

use Intrepicure\Grid;
use Laf\Database\Db;

/**
 * Class Factory
 * @package Laf\UI\Grid
 */
class Factory
{
	/**
	 * @param string $grid_name
	 * @param array $params format ['column_name' => 'value]
	 * @return SimpleTable
	 */
	public static function getGridToSimpleTable(string $grid_name, array $params = []): ?SimpleTable
	{
		$rows = Grid::find(['name' => $grid_name]);
		if (isset($rows[0])) {
			$grid = $rows[0];
			$st = new SimpleTable();
			$st->setSql(vsprintf($grid->getSqlVal(), $params))
				->enableJsDynamic()
				->setRowsPerPage(10);

			if ($grid->getTitleVal() != '') {
				$st->setTitle($grid->getTitleVal());
			}

			$columns = json_decode(coalesce($grid->getColumnNamesVal(), '{}'), true);
			if (is_array($columns) && count($columns) > 0)
				$st->setColumns($columns);
			return $st;
		}
		return null;
	}

	/**
	 * @param $hash
	 * @return integer
	 */
	public static function findGridByHash($hash)
	{
		$hash = trim($hash);
		if ($hash == '') {
			return null;
		}
		$sql = "SELECT id FROM grid WHERE hash = '%s' and hash_expiration < NOW()";
		$db = Db::getInstance();
		return Db::getOne(sprintf($sql, filter_var($db->quote($hash), FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
	}

	/**
	 * @param $name
	 * @return integer
	 */
	public static function findGridByName($name)
	{
		$name = trim($name);
		if ($name == '') {
			return null;
		}
		$sql = "SELECT id FROM grid WHERE name = '%s' and hash_expiration < NOW()";
		$db = Db::getInstance();
		return Db::getOne(sprintf($sql, filter_var($db->quote($name), FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
	}
}