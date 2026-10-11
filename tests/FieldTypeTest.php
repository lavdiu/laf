<?php

use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeFloat;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\Field\TypeText;
use Laf\Database\Field\TypeChar;
use Laf\Database\Field\TypeDate;
use Laf\Database\Field\TypeDateTime;
use Laf\Database\Field\TypeTime;
use Laf\Database\Field\TypeBool;
use Laf\Database\Field\TypeJson;
use Laf\Database\Field\TypeBlob;
use PHPUnit\Framework\TestCase;

class FieldTypeTest extends TestCase
{
	// ── TypeInteger ──

	/** @test */
	public function integer_valid_values()
	{
		$type = new TypeInteger();
		$this->assertTrue($type->isValid(0));
		$this->assertTrue($type->isValid(1));
		$this->assertTrue($type->isValid(-1));
		$this->assertTrue($type->isValid('42'));
		$this->assertTrue($type->isValid('-100'));
		$this->assertTrue($type->isValid(null));
		$this->assertTrue($type->isValid(''));
	}

	/** @test */
	public function integer_invalid_values()
	{
		$type = new TypeInteger();
		$this->assertFalse($type->isValid('abc'));
		$this->assertFalse($type->isValid('1.5'));
		$this->assertFalse($type->isValid('12abc'));
	}

	/** @test */
	public function integer_sanitize_returns_int()
	{
		$type = new TypeInteger();
		$this->assertSame(42, $type->getValueDbSanitized('42'));
		$this->assertSame(0, $type->getValueDbSanitized('0'));
		$this->assertNull($type->getValueDbSanitized(null));
		$this->assertNull($type->getValueDbSanitized(''));
	}

	/** @test */
	public function integer_format_for_db()
	{
		$type = new TypeInteger();
		$this->assertSame(5, $type->formatForDb('5'));
		$this->assertNull($type->formatForDb('abc'));
		$this->assertNull($type->formatForDb(null));
	}

	/** @test */
	public function integer_pdo_type()
	{
		$type = new TypeInteger();
		$this->assertSame(\PDO::PARAM_INT, $type->getPdoType());
	}

	// ── TypeFloat ──

	/** @test */
	public function float_valid_values()
	{
		$type = new TypeFloat();
		$this->assertTrue($type->isValid(0));
		$this->assertTrue($type->isValid(1.5));
		$this->assertTrue($type->isValid('3.14'));
		$this->assertTrue($type->isValid('-2.7'));
		$this->assertTrue($type->isValid('100'));
		$this->assertTrue($type->isValid(null));
		$this->assertTrue($type->isValid(''));
	}

	/** @test */
	public function float_invalid_values()
	{
		$type = new TypeFloat();
		$this->assertFalse($type->isValid('abc'));
		$this->assertFalse($type->isValid('12.3.4'));
	}

	/** @test */
	public function float_sanitize_returns_float()
	{
		$type = new TypeFloat();
		$this->assertSame(3.14, $type->getValueDbSanitized('3.14'));
		$this->assertSame(0.0, $type->getValueDbSanitized('0'));
		$this->assertNull($type->getValueDbSanitized(null));
	}

	/** @test */
	public function float_format_for_db()
	{
		$type = new TypeFloat();
		$this->assertSame(5.5, $type->formatForDb('5.5'));
		$this->assertNull($type->formatForDb(null));
	}

	/** @test */
	public function float_pdo_type()
	{
		$type = new TypeFloat();
		$this->assertSame(\PDO::PARAM_STR, $type->getPdoType());
	}

	// ── TypeVarchar ──

	/** @test */
	public function varchar_always_valid()
	{
		$type = new TypeVarchar();
		$this->assertTrue($type->isValid('anything'));
		$this->assertTrue($type->isValid(''));
		$this->assertTrue($type->isValid(null));
		$this->assertTrue($type->isValid(123));
	}

	/** @test */
	public function varchar_keeps_tags_verbatim()
	{
		// escaping belongs to the output layer; storing encoded text corrupts exports, APIs and searches
		$type = new TypeVarchar();
		$this->assertSame('<b>hello</b>', $type->getValueDbSanitized('<b>hello</b>'));
	}

	/** @test */
	public function varchar_keeps_special_chars_verbatim()
	{
		$type = new TypeVarchar();
		$this->assertSame('a & b < c', $type->getValueDbSanitized('a & b < c'));
		$this->assertSame('Bolt M8 <10mm', $type->getValueDbSanitized('Bolt M8 <10mm'));
		$this->assertSame("O'Reilly & Sons", $type->getValueDbSanitized("O'Reilly & Sons"));
	}

	/** @test */
	public function varchar_sanitize_null_returns_null()
	{
		$type = new TypeVarchar();
		$this->assertNull($type->getValueDbSanitized(null));
		$this->assertNull($type->getValueDbSanitized(''));
		$this->assertNull($type->getValueDbSanitized('   '));
	}

	/** @test */
	public function varchar_validate_checks_max_length()
	{
		$type = new TypeVarchar();
		$field = (new \Laf\Database\Field\Field('name'))->setType($type)->setMaxLength(5)->setLabel('Name');
		$this->assertSame([], $type->validate('abcde', $field));
		$this->assertCount(1, $type->validate('abcdef', $field));
		$this->assertSame([], $type->validate(null, $field));
	}

	/** @test */
	public function varchar_format_for_db()
	{
		$type = new TypeVarchar();
		$this->assertSame('hello', $type->formatForDb('hello'));
		$this->assertNull($type->formatForDb(''));
		$this->assertNull($type->formatForDb(null));
	}

	// ── TypeText ──

	/** @test */
	public function text_always_valid()
	{
		$type = new TypeText();
		$this->assertTrue($type->isValid('anything'));
		$this->assertTrue($type->isValid(''));
		$this->assertTrue($type->isValid(null));
	}

	/** @test */
	public function text_keeps_tags_verbatim()
	{
		$type = new TypeText();
		$this->assertSame('<script>hello</script>', $type->getValueDbSanitized('<script>hello</script>'));
	}

	/** @test */
	public function text_format_for_db()
	{
		$type = new TypeText();
		$this->assertSame('hello', $type->formatForDb('hello'));
		$this->assertSame('trimmed', $type->formatForDb('  trimmed  '));
		$this->assertNull($type->formatForDb(''));
		$this->assertNull($type->formatForDb(null));
	}

	// ── TypeChar ──

	/** @test */
	public function char_always_valid()
	{
		$type = new TypeChar();
		$this->assertTrue($type->isValid('a'));
		$this->assertTrue($type->isValid(''));
		$this->assertTrue($type->isValid(null));
	}

	/** @test */
	public function char_keeps_value_verbatim()
	{
		$type = new TypeChar();
		$this->assertSame('<b>a</b>', $type->getValueDbSanitized('<b>a</b>'));
		$this->assertSame('KS', $type->getValueDbSanitized(' KS '));
	}

	/** @test */
	public function char_format_for_db()
	{
		$type = new TypeChar();
		$this->assertSame('Y', $type->formatForDb('Y'));
	}

	// ── TypeDate ──

	/** @test */
	public function date_valid_values()
	{
		$type = new TypeDate();
		$this->assertTrue($type->isValid('2024-01-15'));
		$this->assertTrue($type->isValid('2000-12-31'));
		$this->assertTrue($type->isValid(null));
		$this->assertTrue($type->isValid(''));
	}

	/** @test */
	public function date_invalid_values()
	{
		$type = new TypeDate();
		$this->assertFalse($type->isValid('not-a-date'));
		$this->assertFalse($type->isValid('2024-13-01'));
		$this->assertFalse($type->isValid('2024-01-32'));
		$this->assertFalse($type->isValid('01/15/2024'));
	}

	/** @test */
	public function date_sanitize()
	{
		$type = new TypeDate();
		$this->assertSame('2024-01-15', $type->getValueDbSanitized('2024-01-15'));
		$this->assertNull($type->getValueDbSanitized('invalid'));
		$this->assertNull($type->getValueDbSanitized(null));
	}

	/** @test */
	public function date_format_for_db()
	{
		$type = new TypeDate();
		$this->assertSame('2024-01-15', $type->formatForDb('2024-01-15'));
		$this->assertNull($type->formatForDb('invalid'));
		$this->assertNull($type->formatForDb(null));
	}

	/** @test */
	public function date_pdo_type()
	{
		$type = new TypeDate();
		$this->assertSame(\PDO::PARAM_STR, $type->getPdoType());
	}

	// ── TypeDateTime ──

	/** @test */
	public function datetime_valid_values()
	{
		$type = new TypeDateTime();
		$this->assertTrue($type->isValid('2024-01-15 14:30:00'));
		$this->assertTrue($type->isValid(null));
		$this->assertTrue($type->isValid(''));
	}

	/** @test */
	public function datetime_invalid_values()
	{
		$type = new TypeDateTime();
		$this->assertFalse($type->isValid('not-a-datetime'));
		$this->assertFalse($type->isValid('2024-13-15 10:00:00'));
		$this->assertFalse($type->isValid('2024-01-15 25:00:00'));
	}

	/** @test */
	public function datetime_accepts_date_only_as_midnight()
	{
		$type = new TypeDateTime();
		$this->assertSame('2024-01-15 00:00:00', $type->normalize('2024-01-15'));
	}

	/** @test */
	public function datetime_sanitize()
	{
		$type = new TypeDateTime();
		$this->assertSame('2024-01-15 14:30:00', $type->getValueDbSanitized('2024-01-15 14:30:00'));
		$this->assertNull($type->getValueDbSanitized('invalid'));
		$this->assertNull($type->getValueDbSanitized(null));
	}

	/** @test */
	public function datetime_format_for_db()
	{
		$type = new TypeDateTime();
		$this->assertSame('2024-01-15 14:30:00', $type->formatForDb('2024-01-15 14:30:00'));
		$this->assertNull($type->formatForDb('invalid'));
	}

	// ── TypeTime ──

	/** @test */
	public function time_valid_values()
	{
		$type = new TypeTime();
		$this->assertTrue($type->isValid(null));
		$this->assertTrue($type->isValid(''));
		$this->assertTrue($type->isValid('14:30:00'));
		$this->assertTrue($type->isValid('00:00:00'));
		$this->assertTrue($type->isValid('23:59:59'));
		$this->assertTrue($type->isValid('14:30'));
		$this->assertTrue($type->isValid('2:30 PM'));
	}

	/** @test */
	public function time_invalid_values()
	{
		$type = new TypeTime();
		$this->assertFalse($type->isValid('25:00:00'));
		$this->assertFalse($type->isValid('14:61'));
		$this->assertFalse($type->isValid('noon'));
	}

	/** @test */
	public function time_normalises_short_and_12_hour_forms()
	{
		$type = new TypeTime();
		$this->assertSame('14:30:00', $type->normalize('14:30'));
		$this->assertSame('14:30:00', $type->normalize('2:30 PM'));
		$this->assertSame('09:05:07', $type->normalize('9:05:07 am'));
		$this->assertNull($type->normalize(''));
	}

	/** @test */
	public function time_sanitize()
	{
		$type = new TypeTime();
		$this->assertSame('14:30:00', $type->getValueDbSanitized('14:30:00'));
		$this->assertNull($type->getValueDbSanitized('invalid'));
	}

	/** @test */
	public function time_format_for_db()
	{
		$type = new TypeTime();
		$this->assertSame('14:30:00', $type->formatForDb('14:30:00'));
		$this->assertNull($type->formatForDb('invalid'));
	}

	// ── TypeBool ──

	/** @test */
	public function bool_valid_values()
	{
		$type = new TypeBool();
		$this->assertTrue($type->isValid(true));
		$this->assertTrue($type->isValid(false));
		$this->assertTrue($type->isValid(1));
		$this->assertTrue($type->isValid('1'));
		$this->assertTrue($type->isValid('true'));
		$this->assertTrue($type->isValid('yes'));
		$this->assertTrue($type->isValid('on'));
		$this->assertTrue($type->isValid(null));
	}

	/** @test */
	public function bool_falsy_string_values_are_valid_and_false()
	{
		$type = new TypeBool();
		foreach (['0', 'false', 'no', 'off', 'n', 'f', 0, false] as $falsy) {
			$this->assertTrue($type->isValid($falsy), var_export($falsy, true) . ' should be valid');
			$this->assertFalse($type->normalize($falsy), var_export($falsy, true) . ' should normalise to false');
		}
		$this->assertFalse($type->isValid('maybe'));
		$this->assertFalse($type->isValid('2x'));
	}

	/** @test */
	public function bool_empty_string_means_unchecked_and_null_stays_null()
	{
		$type = new TypeBool();
		$this->assertFalse($type->normalize(''));
		$this->assertFalse($type->fromInput(''));
		$this->assertNull($type->normalize(null));
		$this->assertNull($type->fromInput(null));
	}

	/** @test */
	public function bool_to_db_binds_false_as_int_zero_not_null()
	{
		$type = new TypeBool();
		$this->assertSame([0, \PDO::PARAM_INT], $type->toDb(false));
		$this->assertSame([1, \PDO::PARAM_INT], $type->toDb(true));
		$this->assertSame([0, \PDO::PARAM_INT], $type->toDb('0'));
		$this->assertSame([null, \PDO::PARAM_NULL], $type->toDb(null));
	}

	/** @test */
	public function bool_from_db_accepts_every_driver_spelling()
	{
		$type = new TypeBool();
		$this->assertTrue($type->fromDb('1'));
		$this->assertFalse($type->fromDb('0'));
		$this->assertTrue($type->fromDb(1));
		$this->assertFalse($type->fromDb(0));
		$this->assertTrue($type->fromDb(true));
		$this->assertTrue($type->fromDb('t'));
		$this->assertFalse($type->fromDb('f'));
		$this->assertNull($type->fromDb(null));
	}

	/** @test */
	public function bool_equals_distinguishes_null_from_false()
	{
		$type = new TypeBool();
		$this->assertFalse($type->equals(null, false));
		$this->assertTrue($type->equals('1', true));
		$this->assertTrue($type->equals('0', false));
		$this->assertFalse($type->equals('0', true));
	}

	/** @test */
	public function bool_sanitize()
	{
		$type = new TypeBool();
		$this->assertTrue($type->getValueDbSanitized(true));
		$this->assertTrue($type->getValueDbSanitized(1));
		$this->assertTrue($type->getValueDbSanitized('1'));
		$this->assertFalse($type->getValueDbSanitized(false));
		$this->assertFalse($type->getValueDbSanitized(0));
		$this->assertNull($type->getValueDbSanitized(null));
	}

	/** @test */
	public function bool_pdo_type()
	{
		$type = new TypeBool();
		$this->assertSame(\PDO::PARAM_BOOL, $type->getPdoType());
	}

	// ── TypeJson ──

	/** @test */
	public function json_valid_values()
	{
		$type = new TypeJson();
		$this->assertTrue($type->isValid('{"key":"value"}'));
		$this->assertTrue($type->isValid('[1,2,3]'));
		$this->assertTrue($type->isValid('"string"'));
		$this->assertTrue($type->isValid('null'));
		$this->assertTrue($type->isValid(null));
	}

	/** @test */
	public function json_invalid_values()
	{
		$type = new TypeJson();
		$this->assertFalse($type->isValid('{invalid}'));
		$this->assertFalse($type->isValid('not json'));
	}

	/** @test */
	public function json_sanitize_passes_through()
	{
		$type = new TypeJson();
		$json = '{"key":"value"}';
		$this->assertSame($json, $type->getValueDbSanitized($json));
	}

	/** @test */
	public function json_format_for_db()
	{
		$type = new TypeJson();
		$json = '{"key":"value"}';
		$this->assertSame($json, $type->formatForDb($json));
		$this->assertNull($type->formatForDb(null));
	}

	// ── TypeBlob ──

	/** @test */
	public function blob_always_valid()
	{
		$type = new TypeBlob();
		$this->assertTrue($type->isValid('binary data'));
		$this->assertTrue($type->isValid(null));
	}

	/** @test */
	public function blob_sanitize_passes_through()
	{
		$type = new TypeBlob();
		$data = 'raw binary';
		$this->assertSame($data, $type->getValueDbSanitized($data));
	}

	/** @test */
	public function blob_pdo_type()
	{
		$type = new TypeBlob();
		$this->assertSame(\PDO::PARAM_LOB, $type->getPdoType());
	}

	/** @test */
	public function blob_format_for_db_passes_through()
	{
		$type = new TypeBlob();
		$this->assertSame('data', $type->formatForDb('data'));
		$this->assertNull($type->formatForDb(null));
	}
}
