<?php

declare(strict_types=1);

use Laf\Database\AuditLog;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\ForeignKey;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use PHPUnit\Framework\TestCase;

/**
 * Database core typing pass (2026-10-12): nullable getters on bare fields and tables, tables
 * without a primary key, the loaded-on timestamp, isValidValue(), fluent selection criteria,
 * the plain-name contract of Table::getNameRot13() and AuditLog record ids.
 */
class DatabaseCoreReviewTest extends TestCase
{
    /** @test */
    public function bare_field_getters_return_null_and_defaults_without_errors(): void
    {
        $field = new Field();
        $this->assertNull($field->getName());
        $this->assertNull($field->getLabel());
        $this->assertNull($field->getPlaceholder());
        $this->assertNull($field->getHint());
        $this->assertNull($field->getInvalidValueErrorMessage());
        $this->assertNull($field->getMaxLength());
        $this->assertNull($field->getUpdatedOnTs());
        $this->assertNull($field->getLoadedOnTs());
        $this->assertNull($field->getValue());
        $this->assertFalse($field->isRequired());
        $this->assertFalse($field->isUnique());
        $this->assertFalse($field->isAutoIncrement());
        $this->assertFalse($field->hasType());
        $this->assertFalse($field->hasTable());
        $this->assertSame('', $field->getNameRot13());
        $this->assertSame('', (string)$field);
        $this->assertSame(0, $field->getLength());
    }

    /** @test */
    public function loaded_and_updated_timestamps_follow_the_value(): void
    {
        $field = (new Field('name'))->setType(new TypeVarchar());
        $this->assertNull($field->getLoadedOnTs());
        $field->loadValueFromDb('x');
        $this->assertGreaterThan(0, $field->getLoadedOnTs());
        $this->assertNull($field->getUpdatedOnTs(), 'loading is not a change');
        $field->setValue('y');
        $this->assertGreaterThan(0, $field->getUpdatedOnTs());
        $this->assertTrue($field->hasChanged());
    }

    /** @test */
    public function is_valid_value_mirrors_validate(): void
    {
        $field = (new Field('name'))->setType(new TypeVarchar())->setRequired(true)->setMaxLength(3);
        $table = new Table('t');
        $table->addField($field);
        $this->assertFalse($field->isValidValue(), 'required and empty');
        $field->setValue('abc');
        $this->assertTrue($field->isValidValue());
        $field->setValue('abcd');
        $this->assertFalse($field->isValidValue(), 'too long');
    }

    /** @test */
    public function selection_criteria_are_fluent(): void
    {
        $field = new Field('status_id');
        $this->assertSame($field, $field->addDbSelectionCriteria('active', 1));
        $this->assertSame(['active' => 1], $field->getDbSelectionCriteria());
        $this->assertTrue($field->hasDbSelectionCriteria());
    }

    /** @test */
    public function a_table_without_primary_key_answers_instead_of_crashing(): void
    {
        $table = new Table('t');
        $field = (new Field('a'))->setType(new TypeInteger());
        $table->addField($field);
        $this->assertNull($table->getPrimaryKey());
        $this->assertSame(0, $table->getPrimaryKeyCount());
        $this->assertFalse($table->isPrimaryKey('a'));
        $this->assertFalse($field->isPrimaryKey());
        $this->assertNull($field->getPrimaryKey());
        $this->assertFalse($field->isDocumentField());
        $this->assertNull($table->getField(null));
        $this->assertSame(1, $table->getFieldCount());

        $pk = new PrimaryKey();
        $pk->addField($field);
        $table->setPrimaryKey($pk);
        $this->assertSame(1, $table->getPrimaryKeyCount());
        $this->assertTrue($table->isPrimaryKey('a'));
        $this->assertTrue($field->isPrimaryKey());
    }

    /** @test */
    public function table_name_rot13_is_the_plain_name_for_forms(): void
    {
        $table = new Table('customer');
        $this->assertSame('customer', $table->getNameRot13(), 'forms use it as id and marker; the request contract depends on it');
        $this->assertSame('Customer', $table->getNameAsClassname());
        $this->assertSame('customer', (string)$table);
        $this->assertSame('', (new Table())->getNameRot13());
        $this->assertNull((new Table())->getName());
    }

    /** @test */
    public function document_fields_are_detected_through_the_foreign_key(): void
    {
        $table = new Table('article');
        $field = (new Field('image_id'))->setType(new TypeInteger());
        $table->addField($field);
        $table->addForeignKey((new ForeignKey('fk_image'))->setField($field)->setReferencingTable('document')->setReferencingField('id'));
        $this->assertTrue($field->isDocumentField());
        $this->assertTrue($field->isForeignKey());
        $this->assertSame('document', $table->getForeignKey('image_id')->getReferencingTable());
        $this->assertNull((new ForeignKey())->getKeyName());
        $this->assertNull((new ForeignKey())->getReferencingField());
    }

    /** @test */
    public function audit_log_record_ids_and_changes_keep_their_shapes(): void
    {
        $this->assertSame('1', AuditLog::recordIdToString(true));
        $this->assertSame('0', AuditLog::recordIdToString(false));
        $this->assertSame('1,0', AuditLog::recordIdToString([true, false]));
        $this->assertSame('5', AuditLog::recordIdToString(5));

        $entry = AuditLog::fromRow(['id' => 1, 'changes' => json_encode([['old_value' => 1, 'new_value' => 2]])]);
        $this->assertInstanceOf(AuditLog::class, $entry);
        $this->assertNull($entry->getChanges()[0]['field']);
        $this->assertSame([], $entry->getChangedFieldNames());
        $this->assertNull($entry->getChangeFor('x'));
    }
}
