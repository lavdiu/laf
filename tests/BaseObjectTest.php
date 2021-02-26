<?php

use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

class BaseObjectTest extends TestCase
{
	private $object = null;

	public function setUp(): void
	{
	    $sql = "
	    DROP TABLE IF EXISTS `dummy_table`;
        CREATE TABLE `dummy_table` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `varchar_field45` varchar(45) DEFAULT NULL,
          `text_field` text DEFAULT NULL,
          `integer_field` int(11) DEFAULT NULL,
          `decimal_field` decimal(8,2) DEFAULT NULL,
          `date_field` date DEFAULT NULL,
          `datetime_field` datetime DEFAULT NULL,
          `time_field` time DEFAULT NULL,
          `float_field` float DEFAULT NULL,
          `json_field` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`json_field`)),
          `null_field` text DEFAULT NULL,
          `empty_field` text DEFAULT NULL,
          `unique_field` text DEFAULT NULL,
          `bool_field` tinyint(1) DEFAULT NULL,
          `parent_id` int(11) DEFAULT NULL,
          `deleted` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `replace_me_dt_unique_field_UNIQUE` (`unique_field`(255)),
          KEY `replace_me_dt_parent_id_fk` (`parent_id`),
          CONSTRAINT `replace_me_dt_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `dummy_table` (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4;
	    ";
	    \Laf\Database\Db::run($sql);



		$rt = new DummyTable();
		$rt->setVarcharField45Val('varchar')
			->setTextFieldVal('text')
			->setIntegerFieldVal(1)
			->setDecimalFieldVal(2.1)
			->setDatetimeFieldVal('2019-12-04 21:32:32')
			->setDateFieldVal('2019-12-04')
			->setTimeFieldVal('21:32:32')
			->setFloatFieldVal(2.1)
			->setNullFieldVal(null)
			->setUniqueFieldVal('unique')
			->setFieldValueRaw('json_field', '{"id":1}');
		$rt->store();
		$this->object = $rt;
		$this->object->reload();
	}

	public function tearDown(): void
	{
		$db = \Laf\Database\Db::getInstance();
		$db->execute("DELETE FROM dummy_table");
	}

	/** @test */
	public function orm_store_and_retreive_row_varchar_45()
	{
		$this->assertEquals($this->object->getVarcharField45Val(), 'varchar');
	}

	/** @test */
	public function orm_store_and_retreive_row_text()
	{
		$this->assertEquals($this->object->getTextFieldVal(), 'text');
	}

	/** @test */
	public function orm_store_and_retreive_row_int()
	{
		$this->assertEquals($this->object->getIntegerFieldVal(), 1);
	}

	/** @test */
	public function orm_store_and_retreive_row_decimal()
	{
		$this->assertEquals($this->object->getDecimalFieldVal(), 2.1);
	}

	/** @test */
	public function orm_store_and_retreive_row_datetime()
	{
		$this->assertEquals($this->object->getDatetimeFieldVal(), '2019-12-04 21:32:32');
	}

	/** @test */
	public function orm_store_and_retreive_row_date()
	{
		$this->assertEquals($this->object->getDateFieldVal(), '2019-12-04');
	}

	/** @test */
	public function orm_store_and_retreive_row_time()
	{
		$this->assertEquals($this->object->getTimeFieldVal(), '21:32:32');
	}

	/** @test */
	public function orm_store_and_retreive_row_json()
	{
		$this->assertEquals($this->object->getJsonFieldVal(), '{"id":1}');
	}

	/** @test */
	public function orm_store_and_retreive_row_float()
	{
		$this->assertEquals($this->object->getFloatFieldVal(), 2.1);
	}

	/** @test */
	public function orm_store_and_retreive_row_null()
	{
		$this->assertEquals($this->object->getNullFieldVal(), null);
	}

	/** @test */
	public function orm_store_and_retreive_row_empty()
	{
		$this->assertEquals($this->object->getEmptyFieldVal(), null);
	}

	/** @test */
	public function orm_attempt_to_store_duplicate_value_on_unique_field()
	{
		$this->expectException(\Exception::class);

		$newRow = clone $this->object;
		$newRow->setIdVal(null);
		$newRow->store();
	}

	/** @test */
	public function orm_store_and_retreive_row_with_fk()
	{
		$this->expectException(\Exception::class);

		$newRow = clone $this->object;
		$newRow->setIdVal(null);
		$newRow->store();
	}

	/** @test */
	public function orm_store_record_with_invalid_value()
	{
		$this->expectException(\Laf\Exception\InvalidValueException::class);
		$this->object->setDatetimeFieldVal('asd');
		$this->object->store();
	}

	/** @test */
	public function find_record_from_db()
	{
		$rowId = $this->object->getIdVal();
		$dt = new DummyTable($rowId);
		foreach ($dt->getTable()->getFields() as $field) {
			$this->assertEquals($dt->getFieldValue($field->getName()), $this->object->getFieldValue($field->getName()));
		}
	}

	/** @test */
	public function orm_soft_delete()
	{
		$this->object->softDelete();
		$this->object->reload();
		$this->assertEquals(1, $this->object->getDeletedVal());
	}

	/** @test */
	public function orm_find_row_by_id_using_findOne()
	{
		$row = DummyTable::findOne([
			'id' => $this->object->getIdVal()
		]);

		$this->assertEquals($row->getVarcharField45Val(), $this->object->getVarcharField45Val());
	}

	/** @test */
	public function orm_hard_delete_row()
	{
		$this->object->reload();
		$rowId = $this->object->getIdVal();
		$this->object->hardDelete();
		$row = DummyTable::findOne(['id' => $rowId]);
		$int = 1;

		$this->assertNull($row);
	}


}
