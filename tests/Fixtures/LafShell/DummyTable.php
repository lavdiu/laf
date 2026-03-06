<?php

namespace LafShell;

class DummyTable extends Base\BaseDummyTable
{
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	protected function returnLeafClass()
	{
		return $this;
	}

	public static function findOne(array $keyValuePairs): ?DummyTable
	{
		return parent::bOfindOne($keyValuePairs);
	}

	public static function find(array $keyValuePairs): array
	{
		return parent::bOfind($keyValuePairs);
	}
}
