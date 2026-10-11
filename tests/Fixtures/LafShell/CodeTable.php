<?php

namespace LafShell;

class CodeTable extends Base\BaseCodeTable
{
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	protected function returnLeafClass()
	{
		return $this;
	}

	public static function findOne(array $keyValuePairs): ?CodeTable
	{
		return parent::bOfindOne($keyValuePairs);
	}

	public static function find(array $keyValuePairs = []): array
	{
		return parent::bOfind($keyValuePairs);
	}
}
