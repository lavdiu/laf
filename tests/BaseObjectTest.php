<?php

use Laf\Database\AuditLog;
use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\Database\ForeignKey;
use Laf\Exception\InvalidForeignKeyValue;
use Laf\Exception\InvalidValueException;
use Laf\Exception\MissingFieldValueException;
use Laf\Util\Settings;
use LafShell\CodeTable;
use LafShell\DummyTable;
use LafShell\LinkTable;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Active Record layer. Runs against MariaDB/MySQL or
 * PostgreSQL depending on LAF_DB_ENGINE (see tests/bootstrap.php).
 *
 * Tables: dummy_table (auto-increment id, every column type, self FK),
 * code_table (VARCHAR primary key), link_table (composite key + int FK + varchar FK).
 */
class BaseObjectTest extends TestCase
{
	/** @var DummyTable */
	private $object = null;
	private static bool $dbAvailable = false;
	private static string $engine = 'mysql';

	public static function setUpBeforeClass(): void
	{
		try {
			Db::getInstance();
			self::$dbAvailable = true;
		} catch (\Exception $e) {
			self::$dbAvailable = false;
			return;
		}
		self::$engine = Settings::get('database.engine') ?: 'mysql';
		self::createSchema();
	}

	private static function createSchema(): void
	{
		if (self::$engine === 'postgres') {
			$sql = "
			DROP TABLE IF EXISTS link_table;
			DROP TABLE IF EXISTS code_table;
			DROP TABLE IF EXISTS dummy_table CASCADE;
			CREATE TABLE dummy_table (
			  id SERIAL PRIMARY KEY,
			  varchar_field45 VARCHAR(45) DEFAULT NULL,
			  text_field TEXT DEFAULT NULL,
			  integer_field INTEGER DEFAULT NULL,
			  decimal_field DECIMAL(8,2) DEFAULT NULL,
			  date_field DATE DEFAULT NULL,
			  datetime_field TIMESTAMP DEFAULT NULL,
			  time_field TIME DEFAULT NULL,
			  float_field REAL DEFAULT NULL,
			  json_field JSONB DEFAULT NULL,
			  null_field TEXT DEFAULT NULL,
			  empty_field TEXT DEFAULT NULL,
			  unique_field TEXT DEFAULT NULL,
			  bool_field BOOLEAN DEFAULT NULL,
			  parent_id INTEGER DEFAULT NULL,
			  deleted INTEGER DEFAULT NULL,
			  required_field VARCHAR(10) NOT NULL,
			  updated_on TIMESTAMP DEFAULT NULL,
			  CONSTRAINT dummy_table_unique_field_unique UNIQUE (unique_field),
			  CONSTRAINT dummy_table_parent_id_fk FOREIGN KEY (parent_id) REFERENCES dummy_table (id)
			);
			CREATE TABLE code_table (
			  code VARCHAR(40) PRIMARY KEY,
			  name VARCHAR(50) DEFAULT NULL
			);
			CREATE TABLE link_table (
			  left_id INTEGER NOT NULL,
			  code VARCHAR(40) NOT NULL,
			  note TEXT DEFAULT NULL,
			  PRIMARY KEY (left_id, code),
			  CONSTRAINT link_table_left_fk FOREIGN KEY (left_id) REFERENCES dummy_table (id) ON DELETE CASCADE,
			  CONSTRAINT link_table_code_fk FOREIGN KEY (code) REFERENCES code_table (code)
			);
			DROP TABLE IF EXISTS audit_log;
			" . file_get_contents(__DIR__ . '/../migrations/create_audit_logs_table_postgres.sql');
		} else {
			$sql = "
			DROP TABLE IF EXISTS `link_table`;
			DROP TABLE IF EXISTS `code_table`;
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
			  `required_field` varchar(10) NOT NULL,
			  `updated_on` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `dummy_table_unique_field_unique` (`unique_field`(255)),
			  KEY `dummy_table_parent_id_fk` (`parent_id`),
			  CONSTRAINT `dummy_table_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `dummy_table` (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
			CREATE TABLE `code_table` (
			  `code` varchar(40) NOT NULL,
			  `name` varchar(50) DEFAULT NULL,
			  PRIMARY KEY (`code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
			CREATE TABLE `link_table` (
			  `left_id` int(11) NOT NULL,
			  `code` varchar(40) NOT NULL,
			  `note` text DEFAULT NULL,
			  PRIMARY KEY (`left_id`, `code`),
			  KEY `link_table_code_fk` (`code`),
			  CONSTRAINT `link_table_left_fk` FOREIGN KEY (`left_id`) REFERENCES `dummy_table` (`id`) ON DELETE CASCADE,
			  CONSTRAINT `link_table_code_fk` FOREIGN KEY (`code`) REFERENCES `code_table` (`code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
			DROP TABLE IF EXISTS `audit_log`;
			" . file_get_contents(__DIR__ . '/../migrations/create_audit_logs_table.sql');
		}
		Db::run($sql);
	}

	private static function truncateAll(): void
	{
		$db = Db::getInstance();
		$db->execute("DELETE FROM link_table");
		$db->execute("DELETE FROM code_table");
		// MySQL checks the self-referencing FK row by row, so detach children before deleting
		$db->execute("UPDATE dummy_table SET parent_id = NULL WHERE parent_id IS NOT NULL");
		$db->execute("DELETE FROM dummy_table");
		$db->execute("DELETE FROM audit_log");
		AuditLog::setEnabled(true);
		AuditLog::setStrict(false);
		AuditLog::setTableName('audit_log');
		BaseObject::setActiveUserId(null);
	}

	public function setUp(): void
	{
		if (!self::$dbAvailable) {
			$this->markTestSkipped('Database not available');
		}
		self::truncateAll();
		ForeignKey::clearValidationCache();

		$rt = new DummyTable();
		$rt->auditLogDisable();
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
			->setRequiredFieldVal('req')
			->setJsonFieldVal('{"id":1}');
		$rt->store();
		$this->object = $rt;
		$this->object->reload();
	}

	public function tearDown(): void
	{
		if (self::$dbAvailable) {
			self::truncateAll();
		}
	}

	/** A second dummy row with the required column filled. */
	private function newDummy(string $unique): DummyTable
	{
		$row = new DummyTable();
		$row->auditLogDisable();
		$row->setRequiredFieldVal('req')->setUniqueFieldVal($unique);
		return $row;
	}

	// ── column round trips ──

	/** @test */
	public function orm_store_and_retreive_row_varchar_45()
	{
		$this->assertSame('varchar', $this->object->getVarcharField45Val());
	}

	/** @test */
	public function orm_store_and_retreive_row_text()
	{
		$this->assertSame('text', $this->object->getTextFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_int()
	{
		$this->assertSame(1, $this->object->getIntegerFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_decimal()
	{
		$this->assertSame('2.10', $this->object->getDecimalFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_datetime()
	{
		$this->assertSame('2019-12-04 21:32:32', $this->object->getDatetimeFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_date()
	{
		$this->assertSame('2019-12-04', $this->object->getDateFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_time()
	{
		$this->assertSame('21:32:32', $this->object->getTimeFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_json()
	{
		// PostgreSQL jsonb re-serialises with spaces ('{"id": 1}'); compare as JSON
		$this->assertJsonStringEqualsJsonString('{"id":1}', $this->object->getJsonFieldVal());
		$this->assertTrue($this->object->getField('json_field')->getType()->equals('{"id":1}', $this->object->getJsonFieldVal()));
	}

	/** @test */
	public function orm_store_and_retreive_row_float()
	{
		$this->assertEqualsWithDelta(2.1, $this->object->getFloatFieldVal(), 0.00001);
	}

	/** @test */
	public function orm_store_and_retreive_row_null()
	{
		$this->assertNull($this->object->getNullFieldVal());
	}

	/** @test */
	public function orm_store_and_retreive_row_empty()
	{
		$this->assertNull($this->object->getEmptyFieldVal());
	}

	/** @test */
	public function orm_integer_and_bool_load_as_php_types()
	{
		$this->assertSame(1, $this->object->getIntegerFieldVal());
		$this->assertIsInt($this->object->getIdVal());
		$this->assertNull($this->object->getBoolFieldVal());
	}

	/** @test */
	public function orm_bool_false_is_stored_as_zero_not_null()
	{
		$this->object->setBoolFieldVal(true);
		$this->object->store();
		$this->object->reload();
		$this->assertTrue($this->object->getBoolFieldVal());

		$this->object->setBoolFieldVal(false);
		$this->object->store();
		$raw = Db::getOne("SELECT bool_field FROM dummy_table WHERE id = ?", [$this->object->getIdVal()]);
		$this->assertNotNull($raw, 'false must be written as 0, not NULL');
		$this->assertSame('0', $raw);
		$this->object->reload();
		$this->assertFalse($this->object->getBoolFieldVal());

		$this->object->setBoolFieldVal(null);
		$this->object->store();
		$this->object->reload();
		$this->assertNull($this->object->getBoolFieldVal());
	}

	/** @test */
	public function orm_bool_accepts_zero_string_and_int()
	{
		$this->object->setBoolFieldVal('0');
		$this->assertFalse($this->object->getBoolFieldVal());
		$this->object->setBoolFieldVal(0);
		$this->assertFalse($this->object->getBoolFieldVal());
		$this->object->setBoolFieldVal('yes');
		$this->assertTrue($this->object->getBoolFieldVal());
	}

	/** @test */
	public function orm_decimal_round_trips_exactly()
	{
		$this->object->setDecimalFieldVal('1234.5');
		$this->assertSame('1234.50', $this->object->getDecimalFieldVal());
		$this->object->store();
		$this->object->reload();
		$this->assertSame('1234.50', $this->object->getDecimalFieldVal());

		$this->object->setDecimalFieldVal('0.1');
		$this->object->store();
		$fresh = new DummyTable($this->object->getIdVal());
		$this->assertSame('0.10', $fresh->getDecimalFieldVal());
		$fresh->setDecimalFieldVal('0.10');
		$this->assertFalse($fresh->getField('decimal_field')->hasChanged());
	}

	/** @test */
	public function orm_text_keeps_html_and_ampersands()
	{
		$this->object->setTextFieldVal('Bolt M8 <10mm & nut');
		$this->object->store();
		$fresh = new DummyTable($this->object->getIdVal());
		$this->assertSame('Bolt M8 <10mm & nut', $fresh->getTextFieldVal());
	}

	/** @test */
	public function orm_datetime_round_trips_from_locale_input()
	{
		$this->object->setDatetimeFieldVal('January 05, 2024 14:30');
		$this->assertSame('2024-01-05 14:30:00', $this->object->getDatetimeFieldVal());
		$this->object->store();
		$fresh = new DummyTable($this->object->getIdVal());
		$this->assertSame('2024-01-05 14:30:00', $fresh->getDatetimeFieldVal());
		$this->assertSame('2019-12-04', $fresh->getDateFieldVal());
		$this->assertSame('21:32:32', $fresh->getTimeFieldVal());
	}

	// ── constraints and validation ──

	/** @test */
	public function orm_attempt_to_store_duplicate_value_on_unique_field()
	{
		$this->expectException(\Exception::class);
		$newRow = $this->newDummy('unique'); // same as the row from setUp
		$newRow->store();
	}

	/** @test */
	public function orm_required_field_missing_throws()
	{
		$this->expectException(MissingFieldValueException::class);
		$row = new DummyTable();
		$row->auditLogDisable();
		$row->setUniqueFieldVal('needs-required')->store();
	}

	/** @test */
	public function orm_store_record_with_invalid_value()
	{
		$this->expectException(InvalidValueException::class);
		$this->object->setDatetimeFieldVal('asd');
		$this->object->store();
	}

	/** @test */
	public function orm_update_on_unselected_object_returns_false()
	{
		$row = $this->newDummy('never-stored');
		$this->assertFalse($row->update());
		$this->assertFalse($row->hardDelete());
	}

	// ── foreign keys ──

	/** @test */
	public function orm_fk_accepts_existing_parent_and_navigates_to_it()
	{
		$child = $this->newDummy('child');
		$child->setParentIdVal($this->object->getIdVal());
		$child->store();

		$fresh = new DummyTable($child->getIdVal());
		$this->assertSame($this->object->getIdVal(), $fresh->getParentIdVal());
		$parent = $fresh->getParentIdObj();
		$this->assertInstanceOf(DummyTable::class, $parent);
		$this->assertSame('varchar', $parent->getVarcharField45Val());
		$this->assertSame($this->object->getIdVal(), $fresh->getTable()->getForeignKey('parent_id')->getReferencedObject()->getIdVal());
	}

	/** @test */
	public function orm_fk_rejects_unknown_parent()
	{
		$this->expectException(InvalidForeignKeyValue::class);
		$this->newDummy('orphan')->setParentIdVal(999999999);
	}

	/** @test */
	public function orm_fk_accepts_null_and_empty()
	{
		$child = $this->newDummy('nullable-fk');
		$child->setParentIdVal(null);
		$child->setParentIdVal('');
		$child->store();
		$this->assertNull((new DummyTable($child->getIdVal()))->getParentIdVal());
	}

	/** @test */
	public function orm_fk_rejects_non_numeric_value_for_integer_key()
	{
		$this->expectException(InvalidForeignKeyValue::class);
		$this->newDummy('bad-fk')->setParentIdVal('abc');
	}

	/** @test */
	public function orm_varchar_fk_validates_against_referenced_column()
	{
		$code = new CodeTable();
		$code->auditLogDisable();
		$code->setCodeVal('ABC')->setNameVal('Alpha')->insert();

		$link = new LinkTable();
		$link->auditLogDisable();
		$link->setLeftIdVal($this->object->getIdVal());
		$link->setCodeVal('ABC'); // exists
		$link->setNoteVal('ok')->insert();
		$this->assertSame('Alpha', $link->getCodeObj()->getNameVal());

		$this->expectException(InvalidForeignKeyValue::class);
		$link->setCodeVal('ZZZ'); // does not exist
	}

	/** @test */
	public function orm_fk_validation_cache_survives_within_request_and_can_be_cleared()
	{
		$fk = $this->object->getTable()->getForeignKey('parent_id');
		$this->assertTrue($fk->isValidValue($this->object->getIdVal()));
		Db::getInstance()->execute("DELETE FROM dummy_table WHERE id = " . (int)$this->object->getIdVal());
		$this->assertTrue($fk->isValidValue($this->object->getIdVal()), 'positive answers are memoised for the request');
		ForeignKey::clearValidationCache('dummy_table');
		$this->assertFalse($fk->isValidValue($this->object->getIdVal()));
	}

	/** @test */
	public function orm_fk_metadata_helpers()
	{
		$fk = $this->object->getTable()->getForeignKey('parent_id');
		$this->assertSame('\\LafShell\\DummyTable', $fk->getReferencedClassName());
		$this->assertSame('dummy_table', $fk->getReferencedTable());
		$this->assertSame('id', $fk->getReferencedField());
		$this->assertSame('id', $fk->resolveReferencedFieldName());
		$this->assertInstanceOf(\Laf\Database\Field\TypeInteger::class, $fk->getReferencedFieldType());
		$this->assertTrue($fk->isNullable());
		$this->assertSame('dummy_table.parent_id -> dummy_table.id', (string)$fk);

		$linkFk = (new LinkTable())->getTable()->getForeignKey('left_id');
		$this->assertSame('CASCADE', $linkFk->getOnDelete());
		$this->assertTrue($linkFk->cascadesOnDelete());
		$this->assertFalse($linkFk->isNullable());
	}

	// ── string primary key ──

	/** @test */
	public function orm_varchar_primary_key_insert_load_update()
	{
		$code = new CodeTable();
		$code->auditLogDisable();
		$code->setCodeVal('ABC')->setNameVal('Alpha');
		$this->assertTrue($code->insert());
		$this->assertSame('ABC', $code->getRecordId());

		$loaded = new CodeTable('ABC');
		$this->assertTrue($loaded->recordExists());
		$this->assertSame('Alpha', $loaded->getNameVal());

		$loaded->auditLogDisable();
		$loaded->setNameVal('Alpha 2');
		$this->assertTrue($loaded->update());
		$this->assertSame('Alpha 2', (new CodeTable('ABC'))->getNameVal());
		$this->assertSame('Alpha 2', CodeTable::findOne(['code' => 'ABC'])->getNameVal());

		$loaded->hardDelete();
		$this->assertNull(CodeTable::findOne(['code' => 'ABC']));
	}

	/** @test */
	public function orm_varchar_primary_key_is_generated_when_empty()
	{
		$code = new CodeTable();
		$code->auditLogDisable();
		$code->setNameVal('Generated')->insert();
		$generated = $code->getCodeVal();
		$this->assertIsString($generated);
		$this->assertSame($generated, $code->getRecordId());
		$this->assertSame(36, strlen($generated), 'TypeVarchar generates a UUID for an empty non-auto-increment key');
		$this->assertSame('Generated', (new CodeTable($generated))->getNameVal());
	}

	// ── composite primary key ──

	private function seedLinks(): int
	{
		foreach (['ABC', 'DEF'] as $c) {
			$code = new CodeTable();
			$code->auditLogDisable();
			$code->setCodeVal($c)->setNameVal('Name ' . $c)->insert();
		}
		$parentId = $this->object->getIdVal();
		foreach (['ABC', 'DEF'] as $c) {
			$link = new LinkTable();
			$link->auditLogDisable();
			$link->setLeftIdVal($parentId)->setCodeVal($c)->setNoteVal('note ' . $c)->insert();
			$this->assertSame(['left_id' => $parentId, 'code' => $c], $link->getRecordId());
		}
		return $parentId;
	}

	/** @test */
	public function orm_composite_key_loads_by_array()
	{
		$parentId = $this->seedLinks();
		$link = new LinkTable(['left_id' => $parentId, 'code' => 'DEF']);
		$this->assertTrue($link->recordExists());
		$this->assertSame('note DEF', $link->getNoteVal());
		$this->assertSame(['left_id' => $parentId, 'code' => 'DEF'], $link->getRecordId());

		$positional = new LinkTable([$parentId, 'ABC']);
		$this->assertSame('note ABC', $positional->getNoteVal());

		$this->assertFalse((new LinkTable(['left_id' => $parentId, 'code' => 'NOPE']))->isRecordSelected());
	}

	/** @test */
	public function orm_composite_key_update_touches_exactly_one_row()
	{
		$parentId = $this->seedLinks();
		$link = new LinkTable(['left_id' => $parentId, 'code' => 'DEF']);
		$link->auditLogDisable();
		$link->setNoteVal('changed');
		$this->assertTrue($link->update());
		$this->assertSame(1, $link->getAffectedRows());

		$this->assertSame('changed', (new LinkTable(['left_id' => $parentId, 'code' => 'DEF']))->getNoteVal());
		$this->assertSame('note ABC', (new LinkTable(['left_id' => $parentId, 'code' => 'ABC']))->getNoteVal(), 'the sibling row sharing left_id must be untouched');
	}

	/** @test */
	public function orm_composite_key_delete_removes_exactly_one_row()
	{
		$parentId = $this->seedLinks();
		$link = new LinkTable(['left_id' => $parentId, 'code' => 'DEF']);
		$link->auditLogDisable();
		$this->assertTrue($link->hardDelete());
		$this->assertSame('1', Db::getOne("SELECT COUNT(*) FROM link_table"));
		$this->assertSame('note ABC', (new LinkTable(['left_id' => $parentId, 'code' => 'ABC']))->getNoteVal());
	}

	/** @test */
	public function orm_composite_key_finders_and_listing()
	{
		$parentId = $this->seedLinks();
		$one = LinkTable::findOne(['left_id' => $parentId, 'code' => 'ABC']);
		$this->assertInstanceOf(LinkTable::class, $one);
		$this->assertSame('note ABC', $one->getNoteVal());

		$all = LinkTable::find(['left_id' => $parentId]);
		$this->assertCount(2, $all);
		$this->assertSame(['note ABC', 'note DEF'], array_map(fn($l) => $l->getNoteVal(), $all));

		$this->assertCount(2, (new LinkTable())->listAllObjects());
		$this->assertCount(2, (new LinkTable())->listAllArray());
		$this->assertTrue((new LinkTable())->getTable()->getPrimaryKey()->isComposite());
	}

	/** @test */
	public function orm_composite_key_cascade_on_parent_delete()
	{
		$this->seedLinks();
		$this->object->hardDelete();
		$this->assertSame('0', Db::getOne("SELECT COUNT(*) FROM link_table"), 'ON DELETE CASCADE declared in the schema');
	}

	// ── finders, lifecycle, helpers ──

	/** @test */
	public function find_record_from_db()
	{
		$rowId = $this->object->getIdVal();
		$dt = new DummyTable($rowId);
		foreach ($dt->getTable()->getFields() as $field) {
			$this->assertEquals($this->object->getFieldValue($field->getName()), $dt->getFieldValue($field->getName()), $field->getName());
		}
	}

	/** @test */
	public function orm_find_returns_every_matching_object()
	{
		$this->newDummy('a')->setIntegerFieldVal(7)->store();
		$this->newDummy('b')->setIntegerFieldVal(7)->store();
		$this->newDummy('c')->setIntegerFieldVal(8)->store();

		$this->assertCount(2, DummyTable::find(['integer_field' => 7]));
		$this->assertCount(4, DummyTable::find());
		$this->assertCount(2, DummyTable::findAsArray(['integer_field' => 7]));
		$this->assertSame([], DummyTable::find(['no_such_column' => 1]), 'unknown columns are refused, not interpolated');
		$this->assertCount(4, (new DummyTable())->listAllObjects());
		$this->assertCount(4, (new DummyTable())->listAllArray());
	}

	/** @test */
	public function orm_find_row_by_id_using_findOne()
	{
		$row = DummyTable::findOne(['id' => $this->object->getIdVal()]);
		$this->assertSame($this->object->getVarcharField45Val(), $row->getVarcharField45Val());
		$this->assertNull(DummyTable::findOne(['id' => -1]));
	}

	/** @test */
	public function orm_clone_inserts_a_new_row()
	{
		$copy = clone $this->object;
		$copy->setUniqueFieldVal('copy');
		$copy->store();
		$this->assertNotSame($this->object->getIdVal(), $copy->getIdVal());
		$this->assertCount(2, DummyTable::find());
	}

	/** @test */
	public function orm_touch_sets_updated_on()
	{
		$this->assertNull($this->object->getUpdatedOnVal());
		$this->object->touch();
		$fresh = new DummyTable($this->object->getIdVal());
		$this->assertNotNull($fresh->getUpdatedOnVal());
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $fresh->getUpdatedOnVal());
	}

	/** @test */
	public function orm_record_exists_and_selected_flags()
	{
		$this->assertTrue($this->object->recordExists());
		$this->assertTrue($this->object->isRecordSelected());
		$missing = new DummyTable(999999999);
		$this->assertFalse($missing->isRecordSelected());
	}

	/** @test */
	public function orm_update_only_writes_changed_fields_with_typed_equality()
	{
		$fresh = new DummyTable($this->object->getIdVal());
		$fresh->setIntegerFieldVal('1');
		$fresh->setDecimalFieldVal('2.1');
		$fresh->setVarcharField45Val('varchar');
		$this->assertFalse($fresh->getField('integer_field')->hasChanged());
		$this->assertFalse($fresh->getField('decimal_field')->hasChanged());
		$this->assertFalse($fresh->getField('varchar_field45')->hasChanged());
		$this->assertTrue($fresh->update(), 'update with nothing changed returns true without touching the row');
	}

	/** @test */
	public function orm_soft_delete()
	{
		$this->object->softDelete();
		$this->object->reload();
		$this->assertSame(1, $this->object->getDeletedVal());
	}

	/** @test */
	public function orm_hard_delete_row()
	{
		$this->object->reload();
		$rowId = $this->object->getIdVal();
		$this->object->hardDelete();
		$this->assertNull(DummyTable::findOne(['id' => $rowId]));
	}

	// ── audit log ──

	/** @return AuditLog[] oldest first */
	private function auditRows(string $table = 'dummy_table'): array
	{
		return array_reverse(AuditLog::findBy(['table_name' => $table], 0));
	}

	/** @test */
	public function audit_insert_records_user_table_record_and_non_null_values()
	{
		BaseObject::setActiveUserId(42);
		$row = new DummyTable();
		$row->setRequiredFieldVal('req')->setUniqueFieldVal('audited')->setIntegerFieldVal(7)->setBoolFieldVal(true)->setDecimalFieldVal('3.50');
		$row->store();

		$rows = $this->auditRows();
		$this->assertCount(1, $rows);
		$entry = $rows[0];
		$this->assertTrue($entry->isInsert());
		$this->assertSame(42, (int)$entry->getUserIdVal());
		$this->assertSame('dummy_table', $entry->getTableNameVal());
		$this->assertSame((string)$row->getIdVal(), (string)$entry->getRecordIdVal());
		$this->assertNotEmpty($entry->getCreatedOnVal());

		$fields = $entry->getChangedFieldNames();
		$this->assertContains('required_field', $fields);
		$this->assertContains('integer_field', $fields);
		$this->assertNotContains('id', $fields, 'the key is in record_id, not in the payload');
		$this->assertNotContains('null_field', $fields, 'columns left null are not recorded on insert');
		$this->assertSame(['field' => 'integer_field', 'old_value' => null, 'new_value' => 7], $entry->getChangeFor('integer_field'));
		$this->assertSame(true, $entry->getChangeFor('bool_field')['new_value'], 'booleans stay booleans in the payload');
		$this->assertSame('3.50', $entry->getChangeFor('decimal_field')['new_value'], 'decimals keep their canonical string');
	}

	/** @test */
	public function audit_update_records_only_changed_fields_with_old_and_new_values()
	{
		$row = new DummyTable($this->object->getIdVal());
		$row->setIntegerFieldVal(99)->setVarcharField45Val('varchar');
		$row->store();

		$rows = $this->auditRows();
		$this->assertCount(1, $rows);
		$this->assertTrue($rows[0]->isUpdate());
		$this->assertSame([['field' => 'integer_field', 'old_value' => 1, 'new_value' => 99]], $rows[0]->getChanges());
	}

	/** @test */
	public function audit_second_store_after_update_logs_nothing_and_fields_are_clean()
	{
		$row = new DummyTable($this->object->getIdVal());
		$row->setIntegerFieldVal(5);
		$row->store();
		$this->assertFalse($row->getField('integer_field')->hasChanged(), 'old value is aligned after a successful update');
		$row->store();
		$this->assertCount(1, $this->auditRows(), 'the unchanged second store() must not log the same change again');

		$fresh = new DummyTable();
		$fresh->setRequiredFieldVal('req')->setUniqueFieldVal('clean-after-insert');
		$fresh->store();
		$this->assertFalse($fresh->getField('unique_field')->hasChanged(), 'old values are aligned after insert too');
		$fresh->store();
		$this->assertCount(2, $this->auditRows(), 'insert then an unchanged store() gives one INSERT entry only');
	}

	/** @test */
	public function audit_hard_delete_logs_snapshot_after_the_row_is_gone()
	{
		$row = new DummyTable($this->object->getIdVal());
		$row->hardDelete();

		$rows = $this->auditRows();
		$this->assertCount(1, $rows);
		$this->assertTrue($rows[0]->isDelete());
		$this->assertSame('varchar', $rows[0]->getChangeFor('varchar_field45')['old_value']);
		$this->assertNull($rows[0]->getChangeFor('varchar_field45')['new_value']);
		$this->assertContains('id', $rows[0]->getChangedFieldNames(), 'the delete snapshot holds every column');
	}

	/** @test */
	public function audit_failed_hard_delete_logs_nothing()
	{
		$child = $this->newDummy('child');
		$child->setParentIdVal($this->object->getIdVal());
		$child->store();

		$parent = new DummyTable($this->object->getIdVal());
		try {
			$parent->hardDelete();
			$this->fail('deleting a referenced parent must fail');
		} catch (\Throwable $e) {
		}
		$this->assertCount(0, $this->auditRows(), 'no DELETE entry for a row that is still there');
	}

	/** @test */
	public function audit_soft_delete_writes_one_delete_entry_with_the_status_change()
	{
		$row = new DummyTable($this->object->getIdVal());
		$row->softDelete();

		$rows = $this->auditRows();
		$this->assertCount(1, $rows, 'soft delete used to write an UPDATE and a DELETE entry');
		$this->assertTrue($rows[0]->isDelete());
		$this->assertSame([['field' => 'deleted', 'old_value' => null, 'new_value' => 1]], $rows[0]->getChanges());
	}

	/** @test */
	public function audit_composite_and_string_keys_are_stored_as_record_id()
	{
		$code = new CodeTable();
		$code->setCodeVal('AUD')->setNameVal('audited code');
		$code->store();
		$link = new LinkTable();
		$link->setLeftIdVal($this->object->getIdVal())->setCodeVal('AUD')->setNoteVal('n');
		$link->store();

		$codeRows = AuditLog::findForRecord('code_table', 'AUD');
		$this->assertCount(1, $codeRows);
		$this->assertSame('AUD', $codeRows[0]->getRecordIdVal());

		$linkRows = AuditLog::findForObject($link);
		$this->assertCount(1, $linkRows);
		$this->assertSame($this->object->getIdVal() . ',AUD', $linkRows[0]->getRecordIdVal());
		$this->assertSame(1, AuditLog::countForRecord('link_table', ['left_id' => $this->object->getIdVal(), 'code' => 'AUD']));
	}

	/** @test */
	public function audit_can_be_switched_off_per_object_and_process_wide()
	{
		$quiet = $this->newDummy('quiet');
		$quiet->store();
		$this->assertCount(0, $this->auditRows());

		AuditLog::disable();
		$loud = new DummyTable();
		$loud->setRequiredFieldVal('req')->setUniqueFieldVal('loud');
		$loud->store();
		$this->assertCount(0, $this->auditRows());
		AuditLog::enable();

		$loud->setIntegerFieldVal(3);
		$loud->store();
		$this->assertCount(1, $this->auditRows());
	}

	/** @test */
	public function audit_history_is_newest_first_and_entries_load_by_id()
	{
		$row = new DummyTable($this->object->getIdVal());
		$row->setIntegerFieldVal(2)->store();
		$row->setIntegerFieldVal(3)->store();
		$row->setIntegerFieldVal(4)->store();

		$history = AuditLog::findForObject($row);
		$this->assertCount(3, $history);
		$this->assertSame(4, $history[0]->getChangeFor('integer_field')['new_value']);
		$this->assertSame(2, $history[2]->getChangeFor('integer_field')['new_value']);

		$page = AuditLog::findForObject($row, 1, 1);
		$this->assertCount(1, $page);
		$this->assertSame(3, $page[0]->getChangeFor('integer_field')['new_value']);

		$loaded = new AuditLog($history[0]->getIdVal());
		$this->assertTrue($loaded->isRecordSelected());
		$this->assertSame('UPDATE', $loaded->getActionVal());
		$this->assertSame($history[0]->getChanges(), $loaded->getChanges());
	}

	/** @test */
	public function audit_failure_is_swallowed_unless_strict()
	{
		AuditLog::setTableName('audit_log_missing');
		$row = $this->newDummy('strictness');
		$row->auditLogEnable();
		$row->setIntegerFieldVal(1);
		$this->assertTrue($row->store(), 'the business write succeeds even when the audit write fails');
		$this->assertNotNull(AuditLog::getLastError());
		$this->assertStringContainsString('audit_log_missing', AuditLog::getLastError());

		AuditLog::setStrict(true);
		$row->setIntegerFieldVal(2);
		try {
			$row->store();
			$this->fail('strict mode must surface the failed audit write');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('audit_log_missing', $e->getMessage());
		}
		AuditLog::setStrict(false);
		AuditLog::setTableName('audit_log');

		$row->setIntegerFieldVal(3);
		$row->store();
		$this->assertCount(1, $this->auditRows(), 'writes resume once the table is right again');
		$this->assertNull(AuditLog::getLastError());
	}
}
