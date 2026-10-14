<?php

declare(strict_types=1);

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeDecimal;
use Laf\Database\Field\TypeFloat;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use Laf\Exception\FormValidationException;
use Laf\Exception\ValidationException;
use Laf\UI\Form\DrawMode;
use Laf\Util\Settings;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * Field rules (minimum length, pattern, validators, decimal bounds), the model's validateRecord()
 * and validation inside store(), without a database: the refused writes throw before any SQL.
 */
class ValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        Settings::remove('orm.validate_on_store');
        $_POST = [];
    }

    private function varchar(?string $value): Field
    {
        return (new Field())->setName('code')->setLabel('Code')->setMaxLength(10)->setType(new TypeVarchar())->setValue($value);
    }

    /** A DummyTable whose validateRecord() refuses an integer above the decimal, and exposes assertValidForStore() */
    private function model(): DummyTable
    {
        return new class extends DummyTable {
            protected function validateRecord(): array
            {
                $int = $this->getIntegerFieldVal();
                $dec = $this->getDecimalFieldVal();
                $errors = [];
                if ($int !== null && $dec !== null && (float)$int > (float)$dec) {
                    $errors['integer_field'] = 'Integer must not exceed the decimal';
                }
                if ($this->getVarcharField45Val() === 'record') {
                    $errors[''] = 'Whole record refused';
                }
                return $errors;
            }

            public function check(bool $inserting): void
            {
                $this->assertValidForStore($inserting);
            }
        };
    }

    /** @test */
    public function min_length_is_checked_for_a_non_empty_value()
    {
        $field = $this->varchar('ab')->setMinLength(3);
        $this->assertSame(['Code must be at least 3 characters'], $field->validate());
        $this->assertSame([], $field->setValue('abc')->validate());
        $this->assertSame([], $field->setValue(null)->validate(), 'empty is the required rule\'s business');
        $this->assertSame([], $this->varchar('çëa')->setMinLength(3)->validate(), 'multibyte characters count once');
    }

    /** @test */
    public function pattern_with_and_without_message()
    {
        $field = $this->varchar('ab12')->setPattern('/^[A-Z]{2}\d{2}$/');
        $this->assertSame(['Code has an invalid format'], $field->validate());
        $field->setPattern('/^[A-Z]{2}\d{2}$/', 'Two capitals and two digits');
        $this->assertSame(['Two capitals and two digits'], $field->validate());
        $this->assertSame([], $field->setValue('AB12')->validate());
        $this->assertSame('/^[A-Z]{2}\d{2}$/', $field->getPattern());
        $this->assertNull($field->setPattern(null)->getPattern());
    }

    /** @test */
    public function an_invalid_pattern_is_refused_when_set()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->varchar('x')->setPattern('/[unclosed/');
    }

    /** @test */
    public function validators_return_null_true_false_a_message_or_a_list()
    {
        $field = $this->varchar('abc');
        $seen = [];
        $field->addValidator(function ($value, Field $f) use (&$seen) {
            $seen[] = [$value, $f->getName()];
            return null;
        })
            ->addValidator(fn() => true)
            ->addValidator(fn() => false)
            ->addValidator(fn($v) => "Not $v")
            ->addValidator(fn() => ['one', 'two']);
        $this->assertSame(['Code is invalid', 'Not abc', 'one', 'two'], $field->validate());
        $this->assertSame([['abc', 'code']], $seen);

        $field->setInvalidValueErrorMessage('Bad code');
        $this->assertSame('Bad code', $field->validate()[0]);
        $this->assertCount(5, $field->getValidators());

        $this->assertSame([], $field->setValue('')->validate(), 'validators skip an empty value');
        $this->assertSame([], $field->setValue('x')->clearValidators()->validate());
    }

    /** @test */
    public function decimal_bounds_work_on_every_numeric_type()
    {
        $float = (new Field())->setName('f')->setLabel('Price')->setType(new TypeFloat())->setMinValue('0.01');
        $this->assertSame(['Price must be at least 0.01'], $float->setValue(0.001)->validate());
        $this->assertSame([], $float->setValue(0.01)->validate());

        $decimal = (new Field())->setName('d')->setLabel('Rate')->setType(new TypeDecimal(5, 2))->setMinValue('0.5')->setMaxValue(99.5);
        $this->assertSame(['Rate must be at least 0.5'], $decimal->setValue('0.25')->validate());
        $this->assertSame(['Rate must be at most 99.5'], $decimal->setValue('99.75')->validate());

        $int = (new Field())->setName('i')->setLabel('Qty')->setType(new TypeInteger())->setMinValue('0.5')->setMaxValue(10);
        $this->assertSame(['Qty must be at least 0.5'], $int->setValue(0)->validate(), 'a decimal bound on an integer column');
        $this->assertSame([], $int->setValue(1)->validate());
        $this->assertSame(['Qty must be at most 10'], $int->setValue(11)->validate());

        $this->assertSame('0.5', $int->getMinValue(), 'the bound is kept as given');
        $this->assertNull($int->setMinValue(null)->getMinValue());
    }

    /** @test */
    public function a_non_numeric_bound_is_refused()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Field())->setName('i')->setMinValue('abc');
    }

    /** @test */
    public function validate_includes_the_record_rules()
    {
        $row = $this->model();
        $row->setIntegerFieldVal(5)->setDecimalFieldVal('2.50')->setVarcharField45Val('record')->setRequiredFieldVal('r');
        $this->assertSame([
            'integer_field' => ['Integer must not exceed the decimal'],
            ValidationException::RECORD_KEY => ['Whole record refused'],
        ], $row->validate());
        $this->assertSame($row->validate(), $row->recordErrors());
    }

    /** @test */
    public function validation_on_store_is_off_by_default_and_follows_settings_and_the_object()
    {
        $row = new DummyTable();
        $this->assertFalse($row->isValidateOnStore());
        $this->assertNull($row->getValidateOnStore());
        Settings::set('orm.validate_on_store', true);
        $this->assertTrue($row->isValidateOnStore());
        $this->assertFalse($row->setValidateOnStore(false)->isValidateOnStore(), 'the object wins');
        $this->assertTrue($row->setValidateOnStore(null)->isValidateOnStore());
    }

    /** @test */
    public function insert_with_validation_on_throws_before_any_sql()
    {
        $row = $this->model()->setValidateOnStore(true);
        $row->getVarcharField45Fld()->setMinLength(5);
        $row->setVarcharField45Val('abc')->setIntegerFieldVal(9)->setDecimalFieldVal('1.00')->setRequiredFieldVal('r');
        try {
            $row->store();
            $this->fail('expected a ValidationException');
        } catch (ValidationException $ex) {
            $this->assertSame([
                'varchar_field45' => ['Varchar Field45 must be at least 5 characters'],
                'integer_field' => ['Integer must not exceed the decimal'],
            ], $ex->getErrors());
            $this->assertSame(['varchar_field45', 'integer_field'], $ex->getFieldNames());
            $this->assertSame('dummy_table', $ex->getTableName());
            $this->assertStringContainsString('at least 5 characters', $ex->getMessage());
            $this->assertNull($row->getLastSql(), 'nothing was sent to the database');
            $this->assertTrue($row->isNew());
        }
    }

    /** @test */
    public function settings_switch_validation_on_for_every_object()
    {
        Settings::set('orm.validate_on_store', '1');
        $row = new DummyTable();
        $row->getVarcharField45Fld()->setPattern('/^\d+$/', 'Digits only');
        $row->setVarcharField45Val('abc')->setRequiredFieldVal('r');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Digits only');
        $row->insert();
    }

    /** @test */
    public function an_update_checks_only_the_changed_fields()
    {
        $row = $this->model();
        $row->getVarcharField45Fld()->setMinLength(5);
        $loaded = $row::hydrate(['id' => 1, 'varchar_field45' => 'ab', 'integer_field' => 1, 'decimal_field' => '2.00']);
        $loaded->getVarcharField45Fld()->setMinLength(5);

        $loaded->setIntegerFieldVal(2);
        $loaded->check(false);   // the short legacy varchar is not touched: no exception
        $this->addToAssertionCount(1);

        $loaded->setIntegerFieldVal(3);
        try {
            $loaded->check(false);
            $this->fail('expected the record rule to fire');
        } catch (ValidationException $ex) {
            $this->assertSame(['integer_field'], array_keys($ex->getErrors()));
        }

        $this->expectException(ValidationException::class);
        $loaded->check(true);   // an insert checks every field
    }

    /** @test */
    public function a_form_shows_record_rules_and_record_level_messages_on_its_own_key()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $row = $this->model();
        $form = $row->getForm(DrawMode::INSERT);
        $form->draw();
        $request = $form->buildRequest(['integer_field' => '7', 'decimal_field' => '1.00', 'varchar_field45' => 'record', 'required_field' => 'r'], DrawMode::INSERT);
        $form->setRequest($request);
        try {
            $form->processForm();
            $this->fail('expected a FormValidationException');
        } catch (FormValidationException $ex) {
            $this->assertInstanceOf(ValidationException::class, $ex, 'one catch covers forms and store()');
            $errors = $ex->getErrors();
            $this->assertSame(['Integer must not exceed the decimal'], $errors['integer_field']);
            $this->assertSame(['Whole record refused'], $errors[\Laf\UI\Form\Form::FORM_ERROR_KEY]);
            $this->assertNull($row->getValidateOnStore(), 'the form restores the object setting');
        }
    }

    /** @test */
    public function form_validation_exception_keeps_its_old_api()
    {
        $ex = new FormValidationException(['a' => ['one', 'two'], 'b' => ['three']]);
        $this->assertSame('one; two; three', $ex->getMessage());
        $this->assertSame(['one', 'two', 'three'], $ex->getMessages());
        $this->assertSame(['a' => ['one', 'two'], 'b' => ['three']], $ex->getErrors());
    }
}
