<?php

namespace LafShell;

class LinkTable extends Base\BaseLinkTable
{
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	protected function returnLeafClass()
	{
		return $this;
	}

	public static function findOne(array $keyValuePairs): ?LinkTable
	{
		return parent::bOfindOne($keyValuePairs);
	}

	public static function find(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0): array
	{
		return parent::bOfind($keyValuePairs, $orderBy, $limit, $offset);
	}
}
