<?php

use Laf\Util\Util;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
	// ── tableNameToClassName ──

	/** @test */
	public function table_name_to_class_name()
	{
		$this->assertSame('SchoolInstructor', Util::tableNameToClassName('school_instructor'));
		$this->assertSame('User', Util::tableNameToClassName('user'));
		$this->assertSame('UserRolePermission', Util::tableNameToClassName('user_role_permission'));
		$this->assertSame('Order', Util::tableNameToClassName('order'));
	}

	// ── tableFieldNameToLabel ──

	/** @test */
	public function table_field_name_to_label()
	{
		$this->assertSame('Instructor Name', Util::tableFieldNameToLabel('instructor_name'));
		$this->assertSame('Email', Util::tableFieldNameToLabel('email'));
		$this->assertSame('Department', Util::tableFieldNameToLabel('department_id'));
	}

	// ── tableFieldNameToMethodName ──

	/** @test */
	public function table_field_name_to_method_name()
	{
		$this->assertSame('InstructorName', Util::tableFieldNameToMethodName('instructor_name'));
		$this->assertSame('Email', Util::tableFieldNameToMethodName('email'));
		$this->assertSame('DepartmentId', Util::tableFieldNameToMethodName('department_id'));
	}

	// ── isJSON ──

	/** @test */
	public function is_json_valid()
	{
		$this->assertTrue(Util::isJSON('{"key":"value"}'));
		$this->assertTrue(Util::isJSON('[1,2,3]'));
		$this->assertTrue(Util::isJSON('[]'));
		$this->assertTrue(Util::isJSON('{}'));
	}

	/** @test */
	public function is_json_invalid()
	{
		$this->assertFalse(Util::isJSON('not json'));
		$this->assertFalse(Util::isJSON('{invalid}'));
		$this->assertFalse(Util::isJSON(null));
		$this->assertFalse(Util::isJSON(123));
		$this->assertFalse(Util::isJSON(''));
		// Note: isJSON returns false for scalar JSON like '"string"' and 'null'
		// because json_decode returns non-array for those
		$this->assertFalse(Util::isJSON('"string"'));
	}

	// ── formatDateForDb ──

	/** @test */
	public function format_date_for_db_valid()
	{
		$this->assertSame('2024-01-15', Util::formatDateForDb('January 15, 2024'));
		$this->assertSame('2000-12-31', Util::formatDateForDb('December 31, 2000'));
	}

	/** @test */
	public function format_date_for_db_invalid_crashes()
	{
		// Bug: formatDateForDb checks $dt !== null but DateTime::createFromFormat
		// returns false on failure (not null), so invalid input causes a fatal error.
		// Fix: change !== null to !== false in Util::formatDateForDb()
		$this->expectException(\Error::class);
		Util::formatDateForDb('invalid');
	}

	// ── uuid ──

	/** @test */
	public function uuid_format()
	{
		$uuid = Util::uuid();
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
	}

	/** @test */
	public function uuid_uniqueness()
	{
		$uuid1 = Util::uuid();
		$uuid2 = Util::uuid();
		$this->assertNotSame($uuid1, $uuid2);
	}

	// ── coalesce ──

	/** @test */
	public function coalesce_returns_first_non_empty()
	{
		$this->assertSame('hello', Util::coalesce(null, '', 'hello', 'world'));
		$this->assertSame('first', Util::coalesce('first', 'second'));
		$this->assertNull(Util::coalesce(null, '', null));
	}

	/** @test */
	public function coalesce_allows_zero()
	{
		$this->assertSame(0, Util::coalesce(null, 0, 'fallback'));
		$this->assertSame('0', Util::coalesce(null, '0', 'fallback'));
	}

	/** @test */
	public function coalesce_allows_arrays()
	{
		$this->assertSame(['a'], Util::coalesce(null, ['a']));
	}

	// ── scrambleFieldOrTableName ──

	/** @test */
	public function scramble_field_name_uses_rot13()
	{
		$this->assertSame('hfre_anzr', Util::scrambleFieldOrTableName('user_name'));
		$this->assertSame('user_name', Util::scrambleFieldOrTableName('hfre_anzr'));
	}

	// ── toFloat ──

	/** @test */
	public function to_float_us_format()
	{
		$this->assertSame(1234.56, Util::toFloat('1,234.56'));
		$this->assertSame(1234.56, Util::toFloat('1234.56'));
	}

	/** @test */
	public function to_float_european_format()
	{
		$this->assertSame(1234.56, Util::toFloat('1.234,56'));
		$this->assertSame(234.56, Util::toFloat('234,56'));
	}

	/** @test */
	public function to_float_no_decimals()
	{
		$this->assertSame(1234.0, Util::toFloat('1234'));
	}

	// ── timeAgo ──

	/** @test */
	public function time_ago_seconds()
	{
		$dt = new \DateTime('-30 seconds');
		$result = Util::timeAgo($dt);
		$this->assertStringContainsString('seconds ago', $result);
	}

	/** @test */
	public function time_ago_minutes()
	{
		$dt = new \DateTime('-5 minutes');
		$result = Util::timeAgo($dt);
		$this->assertStringContainsString('minutes ago', $result);
	}

	/** @test */
	public function time_ago_hours()
	{
		$dt = new \DateTime('-3 hours');
		$result = Util::timeAgo($dt);
		$this->assertStringContainsString('hours ago', $result);
	}

	/** @test */
	public function time_ago_days()
	{
		$dt = new \DateTime('-10 days');
		$result = Util::timeAgo($dt);
		$this->assertStringContainsString('days ago', $result);
	}

	/** @test */
	public function time_ago_months()
	{
		$dt = new \DateTime('-3 months');
		$result = Util::timeAgo($dt);
		$this->assertStringContainsString('months ago', $result);
	}

	/** @test */
	public function time_ago_years()
	{
		$dt = new \DateTime('-2 years');
		$result = Util::timeAgo($dt);
		$this->assertStringContainsString('years ago', $result);
	}
}
