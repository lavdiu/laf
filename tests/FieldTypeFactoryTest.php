<?php

use Laf\Database\Field\FieldType;
use Laf\Database\Field\FieldTypeFactory;
use Laf\Database\Field\TypeBlob;
use Laf\Database\Field\TypeBool;
use Laf\Database\Field\TypeChar;
use Laf\Database\Field\TypeDate;
use Laf\Database\Field\TypeDateTime;
use Laf\Database\Field\TypeFloat;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeJson;
use Laf\Database\Field\TypeText;
use Laf\Database\Field\TypeTime;
use Laf\Database\Field\TypeVarchar;
use PHPUnit\Framework\TestCase;

class FieldTypeFactoryTest extends TestCase
{
	// ── getClass() ──

	/** @test */
	public function factory_returns_type_date()
	{
		$this->assertInstanceOf(TypeDate::class, FieldTypeFactory::getClass(FieldType::TYPE_DATE));
	}

	/** @test */
	public function factory_returns_type_blob()
	{
		$this->assertInstanceOf(TypeBlob::class, FieldTypeFactory::getClass(FieldType::TYPE_BLOB));
	}

	/** @test */
	public function factory_returns_type_time_for_time_and_datetime()
	{
		$this->assertInstanceOf(TypeTime::class, FieldTypeFactory::getClass(FieldType::TYPE_TIME));
		$this->assertInstanceOf(TypeTime::class, FieldTypeFactory::getClass(FieldType::TYPE_DATETIME));
	}

	/** @test */
	public function factory_returns_type_integer_for_int_variants()
	{
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass(FieldType::TYPE_INTEGER));
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass(FieldType::TYPE_BIG_INTEGER));
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass(FieldType::TYPE_SMALL_INTEGER));
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass(FieldType::TYPE_TINY_INTEGER));
	}

	/** @test */
	public function factory_returns_type_json()
	{
		$this->assertInstanceOf(TypeJson::class, FieldTypeFactory::getClass(FieldType::TYPE_JSON));
	}

	/** @test */
	public function factory_returns_type_varchar()
	{
		$this->assertInstanceOf(TypeVarchar::class, FieldTypeFactory::getClass(FieldType::TYPE_VARCHAR));
	}

	/** @test */
	public function factory_returns_type_float_for_numeric_variants()
	{
		$this->assertInstanceOf(TypeFloat::class, FieldTypeFactory::getClass(FieldType::TYPE_NUMERIC));
		$this->assertInstanceOf(TypeFloat::class, FieldTypeFactory::getClass(FieldType::TYPE_DOUBLE));
		$this->assertInstanceOf(TypeFloat::class, FieldTypeFactory::getClass(FieldType::TYPE_FLOAT));
		$this->assertInstanceOf(TypeFloat::class, FieldTypeFactory::getClass(FieldType::TYPE_REAL));
	}

	/** @test */
	public function factory_returns_type_text_for_text()
	{
		$this->assertInstanceOf(TypeText::class, FieldTypeFactory::getClass(FieldType::TYPE_TEXT));
	}

	/** @test */
	public function factory_returns_type_text_as_default()
	{
		$this->assertInstanceOf(TypeText::class, FieldTypeFactory::getClass('unknown_type'));
	}

	// ── getClassLiteral() ──

	/** @test */
	public function literal_returns_correct_strings()
	{
		$this->assertStringContainsString('TypeDate', FieldTypeFactory::getClassLiteral(FieldType::TYPE_DATE));
		$this->assertStringContainsString('TypeBlob', FieldTypeFactory::getClassLiteral(FieldType::TYPE_BLOB));
		$this->assertStringContainsString('TypeDateTime', FieldTypeFactory::getClassLiteral(FieldType::TYPE_DATETIME));
		$this->assertStringContainsString('TypeDateTime', FieldTypeFactory::getClassLiteral(FieldType::TYPE_DATETIME2));
		$this->assertStringContainsString('TypeDateTime', FieldTypeFactory::getClassLiteral(FieldType::TYPE_DATETIME3));
		$this->assertStringContainsString('TypeTime', FieldTypeFactory::getClassLiteral(FieldType::TYPE_TIME));
		$this->assertStringContainsString('TypeTime', FieldTypeFactory::getClassLiteral(FieldType::TYPE_TIME2));
		$this->assertStringContainsString('TypeTime', FieldTypeFactory::getClassLiteral(FieldType::TYPE_TIME3));
		$this->assertStringContainsString('TypeBool', FieldTypeFactory::getClassLiteral(FieldType::TYPE_TINY_INTEGER));
		$this->assertStringContainsString('TypeBool', FieldTypeFactory::getClassLiteral(FieldType::TYPE_BOOL));
		$this->assertStringContainsString('TypeInteger', FieldTypeFactory::getClassLiteral(FieldType::TYPE_INTEGER));
		$this->assertStringContainsString('TypeInteger', FieldTypeFactory::getClassLiteral(FieldType::TYPE_INTEGER2));
		$this->assertStringContainsString('TypeInteger', FieldTypeFactory::getClassLiteral(FieldType::TYPE_BIG_INTEGER));
		$this->assertStringContainsString('TypeInteger', FieldTypeFactory::getClassLiteral(FieldType::TYPE_SMALL_INTEGER));
		$this->assertStringContainsString('TypeJson', FieldTypeFactory::getClassLiteral(FieldType::TYPE_JSON));
		$this->assertStringContainsString('TypeVarchar', FieldTypeFactory::getClassLiteral(FieldType::TYPE_VARCHAR));
		$this->assertStringContainsString('TypeVarchar', FieldTypeFactory::getClassLiteral(FieldType::TYPE_VARCHAR2));
		$this->assertStringContainsString('TypeFloat', FieldTypeFactory::getClassLiteral(FieldType::TYPE_NUMERIC));
		$this->assertStringContainsString('TypeFloat', FieldTypeFactory::getClassLiteral(FieldType::TYPE_NUMERIC2));
		$this->assertStringContainsString('TypeFloat', FieldTypeFactory::getClassLiteral(FieldType::TYPE_FLOAT));
		$this->assertStringContainsString('TypeFloat', FieldTypeFactory::getClassLiteral(FieldType::TYPE_DOUBLE));
		$this->assertStringContainsString('TypeFloat', FieldTypeFactory::getClassLiteral(FieldType::TYPE_REAL));
		$this->assertStringContainsString('TypeText', FieldTypeFactory::getClassLiteral(FieldType::TYPE_TEXT));
	}

	/** @test */
	public function literal_returns_type_text_as_default()
	{
		$this->assertStringContainsString('TypeText', FieldTypeFactory::getClassLiteral('unknown'));
	}

	// ── getClass vs getClassLiteral discrepancy ──

	/** @test */
	public function factory_getclass_maps_tinyint_to_integer_not_bool()
	{
		// Note: getClass maps tinyint -> TypeInteger
		// but getClassLiteral maps tinyint -> TypeBool
		// This is an inconsistency in the factory
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass(FieldType::TYPE_TINY_INTEGER));
		$this->assertStringContainsString('TypeBool', FieldTypeFactory::getClassLiteral(FieldType::TYPE_TINY_INTEGER));
	}
}
