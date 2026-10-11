<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\FieldType;
use Laf\Database\Field\FieldTypeFactory;
use Laf\Database\Field\TypeBlob;
use Laf\Database\Field\TypeBool;
use Laf\Database\Field\TypeChar;
use Laf\Database\Field\TypeDate;
use Laf\Database\Field\TypeDateTime;
use Laf\Database\Field\TypeDecimal;
use Laf\Database\Field\TypeEnum;
use Laf\Database\Field\TypeFloat;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeJson;
use Laf\Database\Field\TypeText;
use Laf\Database\Field\TypeTime;
use Laf\Database\Field\TypeUuid;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\Exception\InvalidValueException;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Input\Checkbox;
use Laf\UI\Form\Input\Hidden;
use Laf\UI\Form\Input\Select;
use Laf\UI\Form\Input\Text;
use Laf\UI\Form\Input\TextArea;
use Laf\Util\Locale;
use Laf\Util\Settings;
use Laf\Util\Util;
use PHPUnit\Framework\TestCase;

/**
 * Typed contract (normalize / fromDb / fromInput / toDb / equals / validate),
 * the new types (decimal, enum, uuid), locale parsing, and the factory's
 * column-aware resolution.
 */
class FieldTypeExtendedTest extends TestCase
{
	private array $savedSettings = [];

	protected function setUp(): void
	{
		foreach (['locale.date.format', 'locale.datetime.format', 'locale.time.format', 'locale.decimal_separator', 'locale.thousands_separator'] as $key) {
			$this->savedSettings[$key] = Settings::getInstance()->propertyExists($key) ? Settings::get($key) : null;
			Settings::set($key, '');
		}
	}

	protected function tearDown(): void
	{
		foreach ($this->savedSettings as $key => $value) {
			Settings::set($key, $value ?? '');
		}
	}

	private function fieldWithTable(string $name, $type, array $opts = []): Field
	{
		$table = new Table('t');
		$pk = new PrimaryKey();
		$id = (new Field('id'))->setType(new TypeInteger())->setAutoIncrement(true);
		$pk->addField($id);
		$table->setPrimaryKey($pk);
		$table->addField($id);
		$field = (new Field($name))->setType($type)->setLabel($opts['label'] ?? ucfirst($name));
		$table->addField($field);
		return $field;
	}

	// ── every type renders a form element (regression for the broken imports) ──

	/** @test */
	public function every_type_returns_a_form_element()
	{
		$types = [
			new TypeVarchar(), new TypeChar(), new TypeText(), new TypeInteger(), new TypeDecimal(10, 2),
			new TypeFloat(), new TypeBool(), new TypeDate(), new TypeDateTime(), new TypeTime(),
			new TypeJson(), new TypeBlob(), new TypeEnum(['a', 'b']), new TypeEnum(['a', 'b'], true), new TypeUuid(),
		];
		foreach ($types as $type) {
			$field = $this->fieldWithTable('f', $type);
			$element = $field->getFormElement();
			$this->assertInstanceOf(\Laf\UI\Form\FormElementInterface::class, $element, get_class($type));
			$this->assertSame($field, $element->getField(), get_class($type));
			$element->setDrawMode(DrawMode::UPDATE);
			$this->assertIsString($element->draw(), get_class($type) . ' update mode');
			$element->setDrawMode(DrawMode::VIEW);
			$this->assertIsString($element->draw(), get_class($type) . ' view mode');
		}
	}

	/** @test */
	public function natural_inputs_per_type()
	{
		$this->assertInstanceOf(Text::class, $this->fieldWithTable('f', new TypeChar())->getFormElement());
		$this->assertInstanceOf(TextArea::class, $this->fieldWithTable('f', new TypeJson())->getFormElement());
		$this->assertInstanceOf(Text::class, $this->fieldWithTable('f', new TypeBlob())->getFormElement());
		$this->assertInstanceOf(Checkbox::class, $this->fieldWithTable('f', new TypeBool())->getFormElement());
		$this->assertInstanceOf(Select::class, $this->fieldWithTable('f', new TypeEnum(['x']))->getFormElement());
		$this->assertInstanceOf(\Laf\UI\Form\Input\CheckboxCollection::class, $this->fieldWithTable('f', new TypeEnum(['x'], true))->getFormElement());
	}

	/** @test */
	public function field_role_decides_input_not_type()
	{
		// auto-increment PK is hidden whatever its type
		$table = new Table('t');
		$pk = new PrimaryKey();
		$id = (new Field('id'))->setType(new TypeInteger())->setAutoIncrement(true);
		$pk->addField($id);
		$table->setPrimaryKey($pk)->addField($id);
		$this->assertInstanceOf(Hidden::class, $id->getFormElement());

		// a string foreign key gets a Select, which only the integer type used to get
		$fkField = (new Field('country_code'))->setType(new TypeChar());
		$table->addField($fkField);
		$table->addForeignKey((new \Laf\Database\ForeignKey())->setField($fkField)->setReferencingTable('country')->setReferencingField('code'));
		$this->assertInstanceOf(Select::class, $fkField->getFormElement());
	}

	// ── integer ──

	/** @test */
	public function integer_rejects_fractions_instead_of_truncating()
	{
		$type = new TypeInteger();
		$this->expectException(InvalidValueException::class);
		$type->fromInput('1.7');
	}

	/** @test */
	public function integer_normalises_strings_floats_and_bools()
	{
		$type = new TypeInteger();
		$this->assertSame(42, $type->normalize(' 42 '));
		$this->assertSame(-7, $type->normalize('-007'));
		$this->assertSame(0, $type->normalize('000'));
		$this->assertSame(3, $type->normalize(3.0));
		$this->assertSame(1, $type->normalize(true));
		$this->assertNull($type->normalize(''));
	}

	/** @test */
	public function integer_keeps_bigint_unsigned_as_string()
	{
		$type = new TypeInteger('bigint', true);
		$big = '18446744073709551615';
		$this->assertSame($big, $type->normalize($big));
		$this->assertSame([$big, \PDO::PARAM_STR], $type->toDb($big));
		$this->assertSame([42, \PDO::PARAM_INT], $type->toDb('42'));
		$this->assertSame([], $type->validate($big));
	}

	/** @test */
	public function integer_validates_size_and_sign()
	{
		$tiny = new TypeInteger('tinyint');
		$this->assertSame([], $tiny->validate(127));
		$this->assertCount(1, $tiny->validate(128));
		$this->assertCount(1, $tiny->validate(-129));
		$unsigned = new TypeInteger('int', true);
		$this->assertCount(1, $unsigned->validate(-1));
		$this->assertSame([], $unsigned->validate('4294967295'));
		$this->assertCount(1, $unsigned->validate('4294967296'));
		$this->assertSame('0', $unsigned->getMinValue());
	}

	/** @test */
	public function integer_input_attributes_carry_range()
	{
		$attrs = (new TypeInteger('smallint'))->getInputAttributes();
		$this->assertSame('-32768', $attrs['min']);
		$this->assertSame('32767', $attrs['max']);
		$this->assertSame('1', $attrs['step']);
		$field = $this->fieldWithTable('qty', new TypeInteger('smallint'));
		$html = $field->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringContainsString('max="32767"', $html);
	}

	// ── decimal ──

	/** @test */
	public function decimal_keeps_exact_string_and_rounds_to_scale()
	{
		$type = new TypeDecimal(12, 2);
		$this->assertSame('1234.50', $type->normalize('1234.5'));
		$this->assertSame('0.10', $type->normalize(0.1));
		$this->assertSame('2.35', $type->normalize('2.345'));
		$this->assertSame('-2.35', $type->normalize('-2.345'));
		$this->assertSame('7.00', $type->normalize(7));
		$this->assertSame('0.30', $type->normalize('0.1 + 0.2' === '0.3' ? '0.3' : '0.30'));
		$this->assertSame([ '1234.50', \PDO::PARAM_STR ], $type->toDb('1234.50'));
	}

	/** @test */
	public function decimal_without_scale_keeps_what_was_given()
	{
		$type = new TypeDecimal();
		$this->assertSame('2.345', $type->normalize('2.345'));
		$this->assertSame('100', $type->normalize('100'));
		$this->assertSame('0.5', $type->normalize('.5'));
		$this->assertSame('1000', $type->normalize('1e3'));
	}

	/** @test */
	public function decimal_parses_locale_input()
	{
		$type = new TypeDecimal(12, 2);
		$this->assertSame('1234.56', $type->fromInput('1,234.56'));
		$this->assertSame('1234.56', $type->fromInput('1.234,56'));
		$this->assertSame('1.50', $type->fromInput('1,5'));
		$this->assertSame('1234.00', $type->fromInput('1,234'), 'single comma followed by 3 digits is a thousands separator in a dot-decimal locale');
		$this->assertSame('1234567.89', $type->fromInput('1 234 567,89'));

		Settings::set('locale.decimal_separator', ',');
		Settings::set('locale.thousands_separator', '.');
		$this->assertSame('1.23', $type->fromInput('1,234'), 'with a comma-decimal locale "1,234" is one point two three four');
		$this->assertSame('1234.56', $type->fromInput('1.234,56'));
	}

	/** @test */
	public function decimal_rejects_text()
	{
		$this->expectException(InvalidValueException::class);
		(new TypeDecimal(8, 2))->fromInput('12.3.4');
	}

	/** @test */
	public function decimal_validates_precision()
	{
		$type = new TypeDecimal(5, 2);
		$this->assertSame([], $type->validate('999.99'));
		$this->assertCount(1, $type->validate('1000.00'));
		$this->assertSame('0.01', $type->getInputAttributes()['step']);
		$this->assertSame('1', (new TypeDecimal(5, 0))->getInputAttributes()['step']);
	}

	/** @test */
	public function decimal_equality_ignores_formatting()
	{
		$type = new TypeDecimal(8, 2);
		$this->assertTrue($type->equals('2.10', '2.1'));
		$this->assertTrue($type->equals('2.10', 2.1));
		$this->assertFalse($type->equals('2.10', '2.11'));
		$this->assertFalse($type->equals(null, '0'));
	}

	// ── float ──

	/** @test */
	public function float_parses_locale_and_binds_as_string()
	{
		$type = new TypeFloat();
		$this->assertSame(1234.5, $type->fromInput('1,234.5'));
		$this->assertSame(1234.5, $type->fromInput('1.234,5'));
		$this->assertSame(['0.1', \PDO::PARAM_STR], $type->toDb(0.1));
		$this->assertSame(['1234567.5', \PDO::PARAM_STR], $type->toDb(1234567.5));
		$this->assertTrue($type->equals('0.30000000000000004', 0.3));
	}

	// ── dates ──

	/** @test */
	public function date_accepts_locale_display_format_round_trip()
	{
		$type = new TypeDate();
		$this->assertSame('2024-01-05', $type->fromInput('January 05, 2024'), 'default display format of the Date input');
		Settings::set('locale.date.format', 'd/m/Y');
		$this->assertSame('2024-01-05', $type->fromInput('05/01/2024'));
		$this->assertSame('2024-01-05', $type->fromInput('2024-01-05'), 'ISO always accepted');
		$this->assertSame('2024-01-05', $type->normalize(new \DateTimeImmutable('2024-01-05 13:00:00')));
		$this->assertSame('2024-01-05', $type->normalize('2024-01-05 13:00:00'));
	}

	/** @test */
	public function date_rejects_impossible_dates()
	{
		$type = new TypeDate();
		$this->assertFalse($type->isValid('2024-02-30'));
		$this->assertFalse($type->isValid('2023-02-29'));
		$this->assertTrue($type->isValid('2024-02-29'));
	}

	/** @test */
	public function datetime_accepts_fractional_seconds_offsets_and_t_separator()
	{
		$type = new TypeDateTime();
		$this->assertSame('2024-01-15 14:30:00.123456', $type->normalize('2024-01-15 14:30:00.123456'));
		$this->assertSame('2024-01-15 14:30:00', $type->normalize('2024-01-15T14:30:00'));
		$this->assertSame('2024-01-15 14:30:00', $type->normalize('2024-01-15 14:30'));
		$this->assertSame('2024-01-15 14:30:00', $type->normalize('2024-01-15 14:30:00+01:00'), 'offset is dropped for naive columns');

		$tz = new TypeDateTime(true);
		$this->assertSame('2024-01-15 14:30:00+01:00', $tz->normalize('2024-01-15 14:30:00+01'));
		$this->assertSame('2024-01-15 14:30:00+01:00', $tz->normalize('2024-01-15 14:30:00+01:00'));
		$this->assertSame('2024-01-15 14:30:00', $tz->normalize('2024-01-15 14:30:00'));
	}

	/** @test */
	public function datetime_does_not_fill_missing_seconds_from_now()
	{
		Settings::set('locale.datetime.format', 'd/m/Y H:i');
		$type = new TypeDateTime();
		$this->assertSame('2024-01-15 14:30:00', $type->fromInput('15/01/2024 14:30'));
	}

	/** @test */
	public function datetime_equality_tolerates_formatting()
	{
		$type = new TypeDateTime();
		$this->assertTrue($type->equals('2024-01-15 14:30:00', '2024-01-15T14:30:00'));
		$this->assertFalse($type->equals('2024-01-15 14:30:00', '2024-01-15 14:30:01'));
	}

	// ── json ──

	/** @test */
	public function json_empty_input_becomes_null_and_arrays_are_encoded()
	{
		$type = new TypeJson();
		$this->assertNull($type->fromInput(''));
		$this->assertNull($type->fromInput('   '));
		$this->assertSame('{"a":1,"b":"ü"}', $type->normalize(['a' => 1, 'b' => 'ü']));
		$this->assertSame('{"k":"v"}', $type->normalize(' {"k":"v"} '));
		$this->assertSame(['k' => 'v'], $type->decode('{"k":"v"}'));
		$this->assertTrue($type->equals('{"a":1, "b":2}', '{"b":2,"a":1}'));
	}

	// ── blob ──

	/** @test */
	public function blob_reads_streams_and_keeps_bytes()
	{
		$type = new TypeBlob();
		$bytes = "\x00\x01\xFF binary";
		$this->assertSame($bytes, $type->normalize($bytes));
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $bytes);
		rewind($stream);
		$this->assertSame($bytes, $type->fromDb($stream));
		$this->assertSame([$bytes, \PDO::PARAM_LOB], $type->toDb($bytes));
		$this->assertSame(' padded ', $type->normalize(' padded '), 'binary is never trimmed');
	}

	// ── enum ──

	/** @test */
	public function enum_matches_declared_spelling()
	{
		$type = new TypeEnum(['Active', 'Suspended']);
		$this->assertSame('Active', $type->normalize('active'));
		$this->assertSame('Suspended', $type->fromInput(' Suspended '));
		$this->assertFalse($type->isValid('Deleted'));
		$this->assertNull($type->normalize(''));
		$this->assertSame(['Active', 'Suspended'], $type->getOptions());
	}

	/** @test */
	public function set_type_normalises_to_declaration_order_without_duplicates()
	{
		$type = new TypeEnum(['red', 'green', 'blue'], true);
		$this->assertSame('red,blue', $type->normalize('blue, red ,blue'));
		$this->assertSame('green', $type->normalize(['green']));
		$this->assertFalse($type->isValid('red,purple'));
		$this->assertSame('set', $type->getTypeName());
	}

	/** @test */
	public function enum_select_renders_options_and_selected_label()
	{
		$field = $this->fieldWithTable('status', new TypeEnum(['open', 'closed']));
		$field->setValue('closed');
		$html = $field->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringContainsString("<option value='open'>open</option>", $html);
		$this->assertStringContainsString("<option value='closed' selected='selected'>closed</option>", $html);
		$view = $field->getFormElement()->setDrawMode(DrawMode::VIEW)->draw();
		$this->assertStringContainsString('closed', $view);
	}

	// ── uuid ──

	/** @test */
	public function uuid_normalises_case_braces_and_compact_form()
	{
		$type = new TypeUuid();
		$this->assertSame('123e4567-e89b-12d3-a456-426614174000', $type->normalize('123E4567-E89B-12D3-A456-426614174000'));
		$this->assertSame('123e4567-e89b-12d3-a456-426614174000', $type->normalize('{123e4567-e89b-12d3-a456-426614174000}'));
		$this->assertSame('123e4567-e89b-12d3-a456-426614174000', $type->normalize('123e4567e89b12d3a456426614174000'));
		$this->assertFalse($type->isValid('not-a-uuid'));
		$generated = $type->generatePrimaryKeyValue();
		$this->assertTrue($type->isValid($generated));
		$this->assertNull((new TypeInteger())->generatePrimaryKeyValue());
		$this->assertNotNull((new TypeVarchar())->generatePrimaryKeyValue());
	}

	// ── checkbox rendering with typed values ──

	/** @test */
	public function checkbox_renders_false_as_no_and_unchecked()
	{
		$field = $this->fieldWithTable('active', new TypeBool());
		$field->setValue(false);
		$view = $field->getFormElement()->setDrawMode(DrawMode::VIEW)->draw();
		$this->assertStringContainsString('No', $view);
		$field->clearFormElementCache();
		$update = $field->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringNotContainsString('checked', $update);

		$field->setValue('t');
		$field->clearFormElementCache();
		$update = $field->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringContainsString('checked="checked"', $update);
	}

	// ── locale helpers ──

	/** @test */
	public function locale_parse_number_cases()
	{
		$this->assertSame('1234.56', Locale::parseNumber('1,234.56'));
		$this->assertSame('1234.56', Locale::parseNumber('1.234,56'));
		$this->assertSame('1234', Locale::parseNumber('1,234'));
		$this->assertSame('1.5', Locale::parseNumber('1,5'));
		$this->assertSame('-0.5', Locale::parseNumber('-,5'));
		$this->assertSame('0', Locale::parseNumber('-0'));
		$this->assertSame('1500', Locale::parseNumber('1.5e3'));
		$this->assertNull(Locale::parseNumber('abc'));
		$this->assertNull(Locale::parseNumber('1.2.3.4'));
		$this->assertNull(Locale::parseNumber(''));
	}

	/** @test */
	public function locale_parse_date_is_strict()
	{
		$this->assertNull(Locale::parseDate('2024-02-30', ['Y-m-d']));
		$this->assertNull(Locale::parseDate('2024-01-15 extra', ['Y-m-d']));
		$dt = Locale::parseDate('2024-01-15', ['Y-m-d']);
		$this->assertSame('2024-01-15 00:00:00', $dt->format('Y-m-d H:i:s'), 'unspecified fields are zero, not "now"');
	}

	// ── factory: column-aware resolution ──

	/** @test */
	public function factory_resolves_from_mysql_column_type()
	{
		$dec = FieldTypeFactory::getClass('decimal', ['COLUMN_TYPE' => 'decimal(12,2)', 'NUMERIC_PRECISION' => '12', 'NUMERIC_SCALE' => '2']);
		$this->assertInstanceOf(TypeDecimal::class, $dec);
		$this->assertSame(2, $dec->getScale());
		$this->assertSame('new Database\Field\TypeDecimal(12, 2)', FieldTypeFactory::getClassLiteral('decimal', ['NUMERIC_PRECISION' => 12, 'NUMERIC_SCALE' => 2]));

		$enum = FieldTypeFactory::getClass('enum', ['COLUMN_TYPE' => "enum('a','b''c')"]);
		$this->assertInstanceOf(TypeEnum::class, $enum);
		$this->assertSame(['a', "b'c"], $enum->getOptions());
		$this->assertSame("new Database\Field\TypeEnum(['a', 'b\\'c'])", FieldTypeFactory::getClassLiteral('enum', ['COLUMN_TYPE' => "enum('a','b''c')"]));

		$set = FieldTypeFactory::getClass('set', ['COLUMN_TYPE' => "set('x','y')"]);
		$this->assertTrue($set->isMultiple());
		$this->assertStringContainsString('true)', FieldTypeFactory::getClassLiteral('set', ['COLUMN_TYPE' => "set('x','y')"]));

		$this->assertInstanceOf(TypeBool::class, FieldTypeFactory::getClass('bit', ['COLUMN_TYPE' => 'bit(1)']));
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass('bit', ['COLUMN_TYPE' => 'bit(8)']));
		$this->assertInstanceOf(TypeInteger::class, $year = FieldTypeFactory::getClass('year'));
		$this->assertSame('year', $year->getSize());
		$this->assertTrue(FieldTypeFactory::getClass('int', ['COLUMN_TYPE' => 'int(11) unsigned'])->isUnsigned());
		$this->assertInstanceOf(TypeBlob::class, FieldTypeFactory::getClass('varbinary'));
		$this->assertInstanceOf(TypeBlob::class, FieldTypeFactory::getClass('longblob'));
		$this->assertInstanceOf(TypeText::class, FieldTypeFactory::getClass('mediumtext'));
		$this->assertInstanceOf(TypeDateTime::class, FieldTypeFactory::getClass('timestamp'));
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass('mediumint'));
	}

	/** @test */
	public function factory_resolves_postgres_type_names()
	{
		$this->assertInstanceOf(TypeBool::class, FieldTypeFactory::getClass('boolean'));
		$this->assertInstanceOf(TypeJson::class, FieldTypeFactory::getClass('jsonb'));
		$this->assertInstanceOf(TypeUuid::class, FieldTypeFactory::getClass('uuid'));
		$this->assertInstanceOf(TypeBlob::class, FieldTypeFactory::getClass('bytea'));
		$this->assertInstanceOf(TypeFloat::class, FieldTypeFactory::getClass('double precision'));
		$this->assertInstanceOf(TypeChar::class, FieldTypeFactory::getClass('character'));
		$this->assertInstanceOf(TypeDateTime::class, FieldTypeFactory::getClass('timestamp without time zone'));
		$tz = FieldTypeFactory::getClass('timestamp with time zone');
		$this->assertInstanceOf(TypeDateTime::class, $tz);
		$this->assertTrue($tz->isWithTimezone());
		$this->assertSame('new Database\Field\TypeDateTime(true)', FieldTypeFactory::getClassLiteral('timestamp with time zone'));
		$this->assertInstanceOf(TypeTime::class, FieldTypeFactory::getClass('time with time zone'));
		$numeric = FieldTypeFactory::getClass('numeric', ['NUMERIC_PRECISION' => 10, 'NUMERIC_SCALE' => 3]);
		$this->assertSame(3, $numeric->getScale());
		$this->assertSame('bigint', FieldTypeFactory::getClass('bigint')->getSize());
		$this->assertSame('smallint', FieldTypeFactory::getClass('smallint')->getSize());

		$pgEnum = FieldTypeFactory::getClass('USER-DEFINED', ['UDT_NAME' => 'mood', 'ENUM_OPTIONS' => ['sad', 'ok', 'happy']]);
		$this->assertInstanceOf(TypeEnum::class, $pgEnum);
		$this->assertSame(['sad', 'ok', 'happy'], $pgEnum->getOptions());
		$this->assertInstanceOf(TypeText::class, FieldTypeFactory::getClass('USER-DEFINED', ['UDT_NAME' => 'some_domain']), 'unknown user-defined types fall back to text');
		$this->assertInstanceOf(TypeText::class, FieldTypeFactory::getClass('ARRAY'));
	}

	/** @test */
	public function factory_accepts_column_type_only_input()
	{
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass('int(11) unsigned'));
		$this->assertInstanceOf(TypeDecimal::class, $d = FieldTypeFactory::getClass('numeric(12,2)'));
		$this->assertSame(2, $d->getScale());
		$this->assertInstanceOf(TypeBool::class, FieldTypeFactory::getClass('tinyint(1)'));
		$this->assertInstanceOf(TypeInteger::class, FieldTypeFactory::getClass('TINYINT(3)'));
	}

	// ── output escaping (storage no longer encodes, so every sink must) ──

	/** @test */
	public function stored_markup_is_escaped_at_every_output_sink()
	{
		$payload = '"><script>alert(1)</script><b x="y">';

		$text = $this->fieldWithTable('name', new TypeVarchar());
		$text->setValue($payload);
		$this->assertSame($payload, $text->getValue(), 'stored verbatim');
		$update = $text->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringNotContainsString('<script>', $update);
		$this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;', $update);
		$view = $text->getFormElement()->setDrawMode(DrawMode::VIEW)->draw();
		$this->assertStringNotContainsString('<script>', $view);
		$this->assertStringNotContainsString('<script>', $text->getValueNl2Br());

		$area = $this->fieldWithTable('notes', new TypeText());
		$area->setValue('</textarea><script>alert(2)</script>');
		$html = $area->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringNotContainsString('</textarea><script>', $html);
		$this->assertStringContainsString('&lt;/textarea&gt;', $html);

		$enum = $this->fieldWithTable('mood', new TypeEnum(["<img src=x onerror=alert(3)>", 'ok']));
		$html = $enum->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringContainsString('&lt;img src=x', $html);

		$label = $this->fieldWithTable('f', new TypeVarchar(), ['label' => 'A <b>label</b>']);
		$label->setAttribute('title', 'say "hi"');
		$html = $label->getFormElement()->setDrawMode(DrawMode::UPDATE)->draw();
		$this->assertStringContainsString('title="say &quot;hi&quot;"', $html);
	}

	/** @test */
	public function legacy_entity_encoded_rows_are_not_double_encoded()
	{
		$field = $this->fieldWithTable('name', new TypeVarchar());
		$field->loadValueFromDb('Smith &amp; Sons');
		$view = $field->getFormElement()->setDrawMode(DrawMode::VIEW)->draw();
		$this->assertStringContainsString('Smith &amp; Sons', $view);
		$this->assertStringNotContainsString('&amp;amp;', $view);
		$this->assertSame('a &amp; b &lt;c&gt;', Util::e('a & b <c>'));
		$this->assertSame('x &amp; y', Util::e('x &amp; y'));
	}

	/** @test */
	public function allow_html_fields_render_raw_by_explicit_opt_in()
	{
		$field = $this->fieldWithTable('body', new TypeText());
		$field->setAllowHtml(true);
		$field->setValue('<b>bold</b>');
		$this->assertStringContainsString('<b>bold</b>', $field->getValueNl2Br());
		$view = $field->getFormElement()->setDrawMode(DrawMode::VIEW)->draw();
		$this->assertStringContainsString('<b>bold</b>', $view);
	}

	/** @test */
	public function legacy_methods_still_behave_for_old_callers()
	{
		$type = new TypeInteger();
		$this->assertTrue($type->isValid('5'));
		$this->assertFalse($type->isValid('5.5'));
		$this->assertSame(5, $type->getValueDbSanitized('5'));
		$this->assertNull($type->getValueDbSanitized('x'));
		$this->assertNull($type->formatForDb('x'));
		$this->assertSame(\PDO::PARAM_INT, $type->getPdoType());
	}
}
