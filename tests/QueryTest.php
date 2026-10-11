<?php

use Laf\Database\BaseObject;
use Laf\Database\Identifier;
use Laf\Database\Query;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * Query builds its SQL from the table metadata; these tests read the SQL and
 * the bindings without a database (engine passed explicitly).
 */
class QueryTest extends TestCase
{
    private function query(string $engine = Identifier::MYSQL): Query
    {
        return new Query((new DummyTable())->getTable(), DummyTable::class, $engine);
    }

    /** @test */
    public function selects_every_column_of_the_quoted_table()
    {
        $this->assertSame('SELECT * FROM `dummy_table`', $this->query()->getSql());
        $this->assertSame('SELECT * FROM "dummy_table"', $this->query(Identifier::POSTGRES)->getSql());
        $this->assertSame([], $this->query()->getBindings());
    }

    /** @test */
    public function two_argument_where_is_equality_null_is_is_null_and_array_is_in()
    {
        $q = $this->query()
            ->where('varchar_field45', 'abc')
            ->where('null_field', null)
            ->where('integer_field', [1, '2', 3]);

        $this->assertSame(
            'SELECT * FROM `dummy_table` WHERE `varchar_field45` = :v0 AND `null_field` IS NULL AND `integer_field` IN (:v1, :v2, :v3)',
            $q->getSql()
        );
        $this->assertSame(['abc', \PDO::PARAM_STR], $q->getBindings()[':v0']);
        $this->assertSame([1, \PDO::PARAM_INT], $q->getBindings()[':v1']);
        $this->assertSame([2, \PDO::PARAM_INT], $q->getBindings()[':v2'], 'bound through the column type');
        $this->assertSame([':v0' => 'abc', ':v1' => 1, ':v2' => 2, ':v3' => 3], $q->getBindingValues());
    }

    /** @test */
    public function three_argument_where_takes_an_operator()
    {
        $q = $this->query(Identifier::POSTGRES)->where('integer_field', '>', '5')->orWhere('decimal_field', '<=', 2.5);
        $this->assertSame('SELECT * FROM "dummy_table" WHERE "integer_field" > :v0 OR "decimal_field" <= :v1', $q->getSql());
        $this->assertSame([5, \PDO::PARAM_INT], $q->getBindings()[':v0']);
    }

    /** @test */
    public function booleans_bind_as_integers_on_both_engines()
    {
        foreach ([Identifier::MYSQL, Identifier::POSTGRES] as $engine) {
            $q = $this->query($engine)->where('bool_field', true)->where('deleted', '<>', false);
            $this->assertSame([1, \PDO::PARAM_INT], $q->getBindings()[':v0'], $engine);
            $this->assertSame([0, \PDO::PARAM_INT], $q->getBindings()[':v1'], $engine);
        }
    }

    /** @test */
    public function null_with_equality_operators_becomes_is_null_and_is_not_null()
    {
        $q = $this->query()->where('null_field', '=', null)->where('empty_field', '!=', null);
        $this->assertSame('SELECT * FROM `dummy_table` WHERE `null_field` IS NULL AND `empty_field` IS NOT NULL', $q->getSql());
        $this->assertSame([], $q->getBindings());
    }

    /** @test */
    public function like_binds_the_pattern_as_a_string_whatever_the_column_type()
    {
        $q = $this->query()->where('integer_field', 'like', '1%');
        $this->assertSame('SELECT * FROM `dummy_table` WHERE `integer_field` LIKE :v0', $q->getSql());
        $this->assertSame(['1%', \PDO::PARAM_STR], $q->getBindings()[':v0']);
    }

    /** @test */
    public function a_value_the_column_type_rejects_is_bound_as_a_string()
    {
        $q = $this->query()->where('integer_field', 'abc');
        $this->assertSame(['abc', \PDO::PARAM_STR], $q->getBindings()[':v0'], 'legacy finders bound raw strings; the database decides');
    }

    /** @test */
    public function in_helpers_between_and_null_helpers()
    {
        $q = $this->query()
            ->whereIn('integer_field', [])
            ->whereNotIn('integer_field', [])
            ->whereBetween('integer_field', 1, 9)
            ->whereNull('null_field')
            ->whereNotNull('empty_field')
            ->whereNotIn('varchar_field45', ['a']);
        $this->assertSame(
            'SELECT * FROM `dummy_table` WHERE 1 = 0 AND 1 = 1 AND `integer_field` BETWEEN :v0 AND :v1 AND `null_field` IS NULL AND `empty_field` IS NOT NULL AND `varchar_field45` NOT IN (:v2)',
            $q->getSql()
        );
    }

    /** @test */
    public function where_raw_keeps_the_developer_sql_and_its_bindings()
    {
        $q = $this->query()->where('deleted', 0)->whereRaw('LOWER(text_field) = :needle', [':needle' => 'x'], 'or');
        $this->assertSame('SELECT * FROM `dummy_table` WHERE `deleted` = :v0 OR (LOWER(text_field) = :needle)', $q->getSql());
        $this->assertSame(['x', \PDO::PARAM_STR], $q->getBindings()[':needle']);
    }

    /** @test */
    public function order_limit_offset_and_column_list()
    {
        $q = $this->query()->select(['id', 'varchar_field45'])->orderBy('varchar_field45', 'desc')->orderBy('id')->limit(10)->offset(20);
        $this->assertSame('SELECT `id`, `varchar_field45` FROM `dummy_table` ORDER BY `varchar_field45` DESC, `id` ASC LIMIT 10 OFFSET 20', $q->getSql());
        $this->assertSame('SELECT * FROM `dummy_table`', $this->query()->limit(null)->offset(0)->getSql());
    }

    /** @test */
    public function a_model_object_as_value_binds_its_primary_key()
    {
        $parent = new DummyTable();
        $parent->getTable()->getField('id')->setValueRaw(7);
        $q = $this->query()->where('parent_id', $parent);
        $this->assertSame('SELECT * FROM `dummy_table` WHERE `parent_id` = :v0', $q->getSql());
        $this->assertSame([7, \PDO::PARAM_INT], $q->getBindings()[':v0']);
    }

    /** @test */
    public function unknown_columns_operators_and_directions_are_refused()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->query()->where('no_such_column', 1);
    }

    /** @test */
    public function unknown_operator_is_refused()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->query()->where('id', 'REGEXP', 'x');
    }

    /** @test */
    public function bad_sort_direction_is_refused()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->query()->orderBy('id', 'ASC; DROP TABLE x');
    }

    /** @test */
    public function negative_limit_is_refused()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->query()->limit(-1);
    }

    /** @test */
    public function delete_refuses_to_run_without_a_condition()
    {
        $this->expectException(\LogicException::class);
        $this->query()->delete();
    }

    /** @test */
    public function get_needs_a_model_class()
    {
        $this->expectException(\LogicException::class);
        (new Query((new DummyTable())->getTable()))->get();
    }

    /** @test */
    public function the_model_class_must_extend_base_object()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Query((new DummyTable())->getTable(), \stdClass::class);
    }

    /** @test */
    public function model_query_is_bound_to_the_model_table()
    {
        $this->assertSame('dummy_table', DummyTable::query()->getTable()->getName());
        $this->assertInstanceOf(Query::class, DummyTable::getQueryBuilder());
        $this->assertInstanceOf(BaseObject::class, new DummyTable());
    }
}
