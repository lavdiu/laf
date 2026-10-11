<?php

use Laf\Database\AuditLog;
use Laf\Database\BaseObject;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBlob;
use Laf\Database\Field\TypeVarchar;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * DB-free tests for the audit payload builders and the configuration switches.
 * Writing and reading real rows is covered in BaseObjectTest (Integration suite).
 */
class AuditLogTest extends TestCase
{
	protected function setUp(): void
	{
		AuditLog::setEnabled(true);
		AuditLog::setStrict(false);
		AuditLog::setMaxValueLength(0);
		AuditLog::setTableName('audit_log');
		BaseObject::setActiveUserId(null);
	}

	protected function tearDown(): void
	{
		AuditLog::setEnabled(true);
		AuditLog::setStrict(false);
		AuditLog::setMaxValueLength(0);
		AuditLog::setTableName('audit_log');
		BaseObject::setActiveUserId(null);
	}

	/** @test */
	public function audit_log_is_a_model_of_its_own_table()
	{
		$entry = new AuditLog();
		$this->assertSame('audit_log', $entry->getTable()->getName());
		$this->assertSame(['id', 'user_id', 'table_name', 'record_id', 'action', 'changes', 'created_on'], array_keys($entry->getTable()->getFields()));
		$this->assertSame('id', $entry->getTable()->getPrimaryKey()->getFirstField()->getName());
		$this->assertFalse($entry->isAuditLoggingEnabled(), 'the audit table never audits itself');
	}

	/** @test */
	public function collect_changes_lists_only_modified_non_timestamp_fields()
	{
		$row = new DummyTable();
		$row->getField('integer_field')->loadValueFromDb(1);
		$row->getField('varchar_field45')->loadValueFromDb('same');
		$row->setIntegerFieldVal(2)->setVarcharField45Val('same')->setUpdatedOnVal('2026-01-01 00:00:00');

		$this->assertSame([['field' => 'integer_field', 'old_value' => 1, 'new_value' => 2]], AuditLog::collectChanges($row));
	}

	/** @test */
	public function collect_inserted_values_skips_keys_and_empty_columns()
	{
		$row = new DummyTable();
		$row->setRequiredFieldVal('req')->setBoolFieldVal(false)->setDecimalFieldVal('1.5')->setNullFieldVal(null)->setEmptyFieldVal('');
		$row->getField('id')->setValue(5);

		$values = AuditLog::collectInsertedValues($row);
		$fields = array_column($values, 'field');
		$this->assertSame(['decimal_field', 'bool_field', 'required_field'], $fields);
		$this->assertSame(false, $values[1]['new_value'], 'false is a value, not "empty"');
		$this->assertNull($values[0]['old_value']);
	}

	/** @test */
	public function collect_snapshot_holds_every_column_as_old_value()
	{
		$row = new DummyTable();
		$row->getField('id')->loadValueFromDb(9);
		$row->getField('text_field')->loadValueFromDb('bye');
		$snapshot = AuditLog::collectSnapshot($row);
		$this->assertCount(count($row->getTable()->getFields()), $snapshot);
		$byField = array_column($snapshot, null, 'field');
		$this->assertSame(9, $byField['id']['old_value']);
		$this->assertSame('bye', $byField['text_field']['old_value']);
		$this->assertNull($byField['text_field']['new_value']);
	}

	/** @test */
	public function normalize_value_keeps_scalars_and_describes_binary_and_objects()
	{
		$this->assertNull(AuditLog::normalizeValue(null));
		$this->assertSame(true, AuditLog::normalizeValue(true));
		$this->assertSame(12, AuditLog::normalizeValue(12));
		$this->assertSame(1.5, AuditLog::normalizeValue(1.5));
		$this->assertSame('12.30', AuditLog::normalizeValue('12.30'));
		$this->assertSame('2026-02-03 04:05:06', AuditLog::normalizeValue(new \DateTime('2026-02-03 04:05:06')));
		$this->assertSame([1, 'a', null], AuditLog::normalizeValue([1, 'a', null]));

		$blob = (new Field())->setName('b')->setType(new TypeBlob());
		$this->assertSame('[binary, 3 bytes]', AuditLog::normalizeValue("\x00\x01\x02", $blob));
		$stream = fopen('php://memory', 'r+');
		$this->assertSame('[binary stream]', AuditLog::normalizeValue($stream, $blob));
		fwrite($stream, 'streamed');
		rewind($stream);
		$this->assertSame('streamed', AuditLog::normalizeValue($stream), 'a stream on a non-binary column is read');
		fclose($stream);

		$untyped = (new Field())->setName('u');
		$this->assertSame('x', AuditLog::normalizeValue('x', $untyped), 'a field without a type is fine');
		$this->assertSame('[object stdClass]', AuditLog::normalizeValue(new \stdClass()));
	}

	/** @test */
	public function long_values_are_cut_when_a_limit_is_set()
	{
		AuditLog::setMaxValueLength(5);
		$this->assertSame(5, AuditLog::getMaxValueLength());
		$this->assertSame('abcde…[+3 chars]', AuditLog::normalizeValue('abcdefgh'));
		$this->assertSame('abc', AuditLog::normalizeValue('abc'));
		AuditLog::setMaxValueLength(0);
		$this->assertSame('abcdefgh', AuditLog::normalizeValue('abcdefgh'));
	}

	/** @test */
	public function encode_changes_never_fails()
	{
		$json = AuditLog::encodeChanges([['field' => 'f', 'old_value' => "bad \xB1 utf8", 'new_value' => 1.0]]);
		$this->assertJson($json);
		$decoded = json_decode($json, true);
		$this->assertSame('f', $decoded[0]['field']);
		$this->assertSame(1.0, $decoded[0]['new_value'], 'floats keep their zero fraction');
		$this->assertStringContainsString('bad', $decoded[0]['old_value']);
		$this->assertSame('[]', AuditLog::encodeChanges([]));
		$this->assertSame('["é/x"]', AuditLog::encodeChanges(['é/x']), 'unicode and slashes are not escaped');
	}

	/** @test */
	public function record_ids_become_strings()
	{
		$this->assertSame('7', AuditLog::recordIdToString(7));
		$this->assertSame('abc', AuditLog::recordIdToString('abc'));
		$this->assertSame('1,ABC', AuditLog::recordIdToString(['left_id' => 1, 'code' => 'ABC']));
		$this->assertSame('1,0', AuditLog::recordIdToString([true, false]));
	}

	/** @test */
	public function user_id_comes_from_the_active_user_first()
	{
		BaseObject::setActiveUserId(77);
		$this->assertSame(77, AuditLog::resolveUserId());
		BaseObject::setActiveUserId(null);
		$this->assertNull(AuditLog::resolveUserId(), 'LafShell has no Person class to fall back to');
	}

	/** @test */
	public function log_change_refuses_when_disabled_or_action_is_unknown()
	{
		AuditLog::disable();
		$this->assertFalse(AuditLog::isEnabled());
		$this->assertFalse(AuditLog::logChange(AuditLog::ACTION_INSERT, 't', 1));
		AuditLog::enable();

		$this->assertFalse(AuditLog::logChange('TRUNCATE', 't', 1));
		$this->assertStringContainsString('Unknown audit action', AuditLog::getLastError());

		AuditLog::setStrict(true);
		$this->expectException(\RuntimeException::class);
		AuditLog::logChange('TRUNCATE', 't', 1);
	}

	/** @test */
	public function table_name_is_sanitized_and_used_by_new_entries()
	{
		AuditLog::setTableName('audit_log; DROP TABLE x');
		$this->assertSame('audit_logDROPTABLEx', AuditLog::getTableName());
		AuditLog::setTableName('audit_archive');
		$this->assertSame('audit_archive', (new AuditLog())->getTable()->getName());
	}

	/** @test */
	public function entries_decode_their_payload()
	{
		$entry = AuditLog::fromRow([
			'id' => 3,
			'user_id' => null,
			'table_name' => 'dummy_table',
			'record_id' => '12',
			'action' => 'UPDATE',
			'changes' => '[{"field":"a","old_value":1,"new_value":2},{"field":"b","old_value":null,"new_value":"x"}]',
			'created_on' => '2026-10-11 10:00:00',
		]);
		$this->assertSame(3, $entry->getRecordId());
		$this->assertTrue($entry->isRecordSelected());
		$this->assertTrue($entry->isUpdate());
		$this->assertFalse($entry->isInsert());
		$this->assertSame(['a', 'b'], $entry->getChangedFieldNames());
		$this->assertSame(['field' => 'b', 'old_value' => null, 'new_value' => 'x'], $entry->getChangeFor('b'));
		$this->assertNull($entry->getChangeFor('zzz'));
		$this->assertSame('12', $entry->getRecordIdVal());

		$blank = AuditLog::fromRow(['id' => 4, 'changes' => 'not json']);
		$this->assertSame([], $blank->getChanges());
	}

	/** @test */
	public function find_by_rejects_unknown_columns_before_touching_the_database()
	{
		$this->expectException(\InvalidArgumentException::class);
		AuditLog::findBy(['no_such_column' => 1]);
	}

	/** @test */
	public function field_has_type_reports_assignment()
	{
		$field = new Field();
		$this->assertFalse($field->hasType());
		$field->setType(new TypeVarchar());
		$this->assertTrue($field->hasType());
	}
}
