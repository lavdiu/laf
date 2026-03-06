<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeInteger;
use Laf\Database\ForeignKey;
use Laf\Database\Table;
use PHPUnit\Framework\TestCase;

class ForeignKeyTest extends TestCase
{
    /** @test */
    public function constructor_sets_properties(): void
    {
        $table = new Table('orders');
        $field = new Field('customer_id');
        $field->setType(new TypeInteger());

        $fk = new ForeignKey('fk_customer', $table, $field, 'customers');

        $this->assertEquals('fk_customer', $fk->getKeyName());
        $this->assertSame($table, $fk->getTable());
        $this->assertSame($field, $fk->getField());
        $this->assertEquals('customers', $fk->getReferencingTable());
    }

    /** @test */
    public function constructor_defaults_to_null(): void
    {
        $fk = new ForeignKey();

        $this->assertNull($fk->getKeyName());
        $this->assertNull($fk->getReferencingTable());
    }

    /** @test */
    public function setters_are_fluent(): void
    {
        $table = new Table('t');
        $field = new Field('f');
        $field->setType(new TypeInteger());

        $fk = new ForeignKey();
        $result = $fk->setKeyName('fk_test')
            ->setTable($table)
            ->setField($field)
            ->setReferencingTable('ref_table')
            ->setReferencingField('ref_id');

        $this->assertInstanceOf(ForeignKey::class, $result);
        $this->assertEquals('fk_test', $fk->getKeyName());
        $this->assertSame($table, $fk->getTable());
        $this->assertSame($field, $fk->getField());
        $this->assertEquals('ref_table', $fk->getReferencingTable());
        $this->assertEquals('ref_id', $fk->getReferencingField());
    }

    /** @test */
    public function is_valid_value_returns_true_for_empty_string(): void
    {
        $fk = new ForeignKey();
        $fk->setReferencingTable('some_table');
        $fk->setReferencingField('id');

        // Empty/null values are always valid (nullable FK)
        $this->assertTrue($fk->isValidValue(''));
        $this->assertTrue($fk->isValidValue(null));
        $this->assertTrue($fk->isValidValue('  '));
    }
}
