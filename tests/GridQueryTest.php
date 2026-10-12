<?php

declare(strict_types=1);

use Laf\Database\Identifier;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\GridQuery;
use PHPUnit\Framework\TestCase;

class GridQueryTest extends TestCase
{
    private const SQL = "SELECT id, name, city, age, salary FROM people ORDER BY id;";

    /** @return array<string, Column> */
    private function columns(): array
    {
        $cols = [];
        foreach (['id', 'name', 'city', 'age', 'salary', 'order', 'firstName'] as $name) {
            $cols[$name] = new Column($name, ucfirst($name));
        }
        return $cols;
    }

    private function query(string $engine, ?array $columns = null): GridQuery
    {
        return new GridQuery(self::SQL, $columns ?? $this->columns(), 'my grid-1', $engine);
    }

    private static function squash(string $sql): string
    {
        return trim(preg_replace('/\s+/', ' ', $sql) ?? '');
    }

    /** @test */
    public function quoting_is_meaning_preserving_per_engine(): void
    {
        $this->assertSame('`order`', GridQuery::quoteColumn('order', Identifier::MYSQL));
        $this->assertSame('`firstName`', GridQuery::quoteColumn('firstName', Identifier::MYSQL));
        $this->assertSame('a.b', GridQuery::quoteColumn('a.b', Identifier::MYSQL));
        $this->assertSame('"order"', GridQuery::quoteColumn('order', Identifier::POSTGRES));
        $this->assertSame('firstName', GridQuery::quoteColumn('firstName', Identifier::POSTGRES));
        $this->assertSame('1abc', GridQuery::quoteColumn('1abc', Identifier::POSTGRES));
        $this->assertSame('CAST(x AS CHAR)', GridQuery::castToText('x', Identifier::MYSQL));
        $this->assertSame('CAST(x AS TEXT)', GridQuery::castToText('x', Identifier::POSTGRES));
        $this->assertSame('my_grid_1', GridQuery::sanitizeAlias('my grid-1'));
        $this->assertSame('grid_1x', GridQuery::sanitizeAlias('1x'));
        $this->assertSame('grid_', GridQuery::sanitizeAlias(''));
    }

    /** @test */
    public function data_count_and_totals_statements_on_mysql(): void
    {
        $built = $this->query(Identifier::MYSQL)->build([], [
            'sortField' => 'name', 'sortDir' => 'desc', 'page' => 3, 'rowsPerPage' => 10, 'totals' => ['salary', 'order'],
        ]);

        $this->assertSame(
            '-- grid query: my_grid_1 SELECT `id`, `name`, `city`, `age`, `salary`, `order`, `firstName` '
            . 'FROM ( SELECT id, name, city, age, salary FROM people ORDER BY id ) my_grid_1 ORDER BY `name` DESC LIMIT 10 OFFSET 20',
            self::squash($built['data'])
        );
        $this->assertSame(
            '-- grid counter: my_grid_1 SELECT COUNT(*) AS total_number_of_rows '
            . 'FROM ( SELECT id, name, city, age, salary FROM people ORDER BY id ) my_grid_1',
            self::squash($built['count'])
        );
        $this->assertSame(
            '-- grid totals: my_grid_1 SELECT SUM(`salary`) AS `salary`, SUM(`order`) AS `order` '
            . 'FROM ( SELECT id, name, city, age, salary FROM people ORDER BY id ) my_grid_1',
            self::squash($built['totals'])
        );
        $this->assertSame([], $built['bindings']);
    }

    /** @test */
    public function postgres_quotes_lower_case_names_only(): void
    {
        $built = $this->query(Identifier::POSTGRES)->build([], ['sortField' => 'order', 'sortDir' => 'asc', 'rowsPerPage' => 0]);
        $this->assertStringContainsString('SELECT "id", "name", "city", "age", "salary", "order", firstName', self::squash($built['data']));
        $this->assertStringEndsWith('ORDER BY "order" ASC', self::squash($built['data']));
        $this->assertStringNotContainsString('LIMIT', $built['data']);
    }

    /** @test */
    public function all_rows_and_unknown_sort_leave_out_limit_and_order_by(): void
    {
        $built = $this->query(Identifier::MYSQL)->build([], ['sortField' => 'nope', 'rowsPerPage' => 10, 'allRows' => true]);
        $this->assertStringNotContainsString('LIMIT', $built['data']);
        $this->assertStringEndsWith(') my_grid_1', self::squash($built['data']));   // no outer ORDER BY
        $this->assertNull($built['totals']);

        $built = $this->query(Identifier::MYSQL)->build([], ['sortField' => 'id', 'rowsPerPage' => 5, 'page' => 0]);
        $this->assertStringEndsWith('LIMIT 5 OFFSET 0', self::squash($built['data']));
    }

    /** @test */
    public function sort_on_a_non_sortable_column_is_ignored_and_case_insensitive_sort_only_on_postgres(): void
    {
        $columns = $this->columns();
        $columns['name']->setSortable(false);
        $columns['city']->setCaseInsensitiveSort(true);

        $built = $this->query(Identifier::MYSQL, $columns)->build([], ['sortField' => 'city', 'sortDir' => 'asc']);
        $this->assertStringEndsWith('ORDER BY `city` ASC', self::squash($built['data']));

        $built = $this->query(Identifier::POSTGRES, $columns)->build([], ['sortField' => 'city', 'sortDir' => 'desc']);
        $this->assertStringEndsWith('ORDER BY LOWER(CAST("city" AS TEXT)) DESC', self::squash($built['data']));
    }

    /** @test */
    public function comparison_operators_bind_unique_placeholders(): void
    {
        $filters = [
            ['property' => 'age', 'operator' => 'gt', 'value' => 30],
            ['property' => 'age', 'operator' => 'lteq', 'value' => '40'],
            ['property' => 'city', 'operator' => 'eq', 'value' => 'Boston'],
            ['property' => 'city', 'operator' => 'noteq', 'value' => 'X'],
            ['property' => 'name', 'operator' => 'stricteq', 'value' => 'A'],
            ['property' => 'name', 'operator' => 'notstricteq', 'value' => 'B'],
            ['property' => 'id', 'operator' => 'lt', 'value' => 9],
            ['property' => 'id', 'operator' => 'gteq', 'value' => 1],
        ];
        $built = $this->query(Identifier::MYSQL)->build($filters, []);
        $this->assertSame(
            'WHERE `age` > :f0 AND `age` <= :f1 AND `city` = :f2 AND `city` != :f3 AND `name` = :f4 AND `name` != :f5 AND `id` < :f6 AND `id` >= :f7',
            self::squash(substr($built['data'], strpos($built['data'], 'WHERE')))
        );
        $this->assertSame([':f0' => 30, ':f1' => '40', ':f2' => 'Boston', ':f3' => 'X', ':f4' => 'A', ':f5' => 'B', ':f6' => 9, ':f7' => 1], $built['bindings']);
        // the same WHERE goes into the count statement
        $this->assertStringContainsString('`age` > :f0', $built['count']);
    }

    /** @test */
    public function like_is_case_insensitive_on_postgres_through_a_text_cast(): void
    {
        $filters = [['property' => 'age', 'operator' => 'like', 'value' => '3']];

        $built = $this->query(Identifier::MYSQL)->build($filters, ['wildcardContains' => true]);
        $this->assertStringContainsString('WHERE `age` LIKE :f0', $built['data']);
        $this->assertSame([':f0' => '%3%'], $built['bindings']);

        $built = $this->query(Identifier::POSTGRES)->build($filters, []);
        $this->assertStringContainsString('WHERE CAST("age" AS TEXT) ILIKE :f0', $built['data']);
        $this->assertSame([':f0' => '3%'], $built['bindings']);

        $built = $this->query(Identifier::POSTGRES)->build([['property' => 'name', 'operator' => 'notlike', 'value' => 'x']], []);
        $this->assertStringContainsString('WHERE CAST("name" AS TEXT) NOT ILIKE :f0', $built['data']);
        $built = $this->query(Identifier::MYSQL)->build([['property' => 'name', 'operator' => 'notlike', 'value' => 'x']], []);
        $this->assertStringContainsString('WHERE `name` NOT LIKE :f0', $built['data']);
    }

    /** @test */
    public function wildcard_mode_comes_from_the_column_then_the_grid(): void
    {
        $columns = $this->columns();
        $columns['name']->setWildcardMode(Column::WILDCARD_ENDS_WITH);
        $filters = [
            ['property' => 'name', 'operator' => 'like', 'value' => 'a'],
            ['property' => 'city', 'operator' => 'like', 'value' => 'b'],
        ];
        $built = $this->query(Identifier::MYSQL, $columns)->build($filters, ['wildcardContains' => false]);
        $this->assertSame([':f0' => '%a', ':f1' => 'b%'], $built['bindings']);

        $built = $this->query(Identifier::MYSQL, $columns)->build($filters, ['wildcardContains' => true]);
        $this->assertSame([':f0' => '%a', ':f1' => '%b%'], $built['bindings']);
        // % and _ in the value are LIKE metacharacters, as before
        $built = $this->query(Identifier::MYSQL)->build([['property' => 'city', 'operator' => 'like', 'value' => '50%']], []);
        $this->assertSame([':f0' => '50%%'], $built['bindings']);
    }

    /** @test */
    public function in_between_null_operators(): void
    {
        $filters = [
            ['property' => 'city', 'operator' => 'in', 'value' => ['Boston', 'Chicago']],
            ['property' => 'age', 'operator' => 'in', 'value' => '30, 40,,'],
            ['property' => 'age', 'operator' => 'between', 'value' => '20..30'],
            ['property' => 'salary', 'operator' => 'between', 'value' => [1, 2, 3]],
            ['property' => 'name', 'operator' => 'isnull', 'value' => null],
            ['property' => 'name', 'operator' => 'notnull', 'value' => 'ignored'],
            ['property' => 'city', 'operator' => 'in', 'value' => []],
            ['property' => 'city', 'operator' => 'between', 'value' => '5'],
            ['property' => 'city', 'operator' => 'eq', 'value' => null],
            ['property' => 'city', 'operator' => 'noteq', 'value' => null],
            ['property' => 'city', 'operator' => 'gt', 'value' => null],
            ['property' => 'city', 'operator' => 'eq', 'value' => ['array', 'on', 'eq']],
        ];
        $built = $this->query(Identifier::MYSQL)->build($filters, []);
        $this->assertSame(
            'WHERE `city` IN (:f0, :f1) AND `age` IN (:f2, :f3) AND `age` BETWEEN :f4 AND :f5 AND `salary` BETWEEN :f6 AND :f7 '
            . 'AND `name` IS NULL AND `name` IS NOT NULL AND `city` IS NULL AND `city` IS NOT NULL',
            self::squash(substr($built['data'], strpos($built['data'], 'WHERE')))
        );
        $this->assertSame(
            [':f0' => 'Boston', ':f1' => 'Chicago', ':f2' => '30', ':f3' => '40', ':f4' => '20', ':f5' => '30', ':f6' => 1, ':f7' => 2],
            $built['bindings']
        );
    }

    /** @test */
    public function case_insensitive_equality_lower_cases_both_sides(): void
    {
        $columns = $this->columns();
        $columns['city']->setCaseInsensitive(true);
        $filters = [['property' => 'city', 'operator' => 'eq', 'value' => 'BoSton']];

        $built = $this->query(Identifier::MYSQL, $columns)->build($filters, []);
        $this->assertStringContainsString('WHERE LOWER(CAST(`city` AS CHAR)) = :f0', $built['data']);
        $this->assertSame([':f0' => 'boston'], $built['bindings']);

        $built = $this->query(Identifier::POSTGRES, $columns)->build([['property' => 'city', 'operator' => 'noteq', 'value' => 'ÄB']], []);
        $this->assertStringContainsString('WHERE LOWER(CAST("city" AS TEXT)) != :f0', $built['data']);
        $this->assertSame([':f0' => 'äb'], $built['bindings']);
    }

    /** @test */
    public function column_search_operator_overrides_the_request_and_unsearchable_columns_are_skipped(): void
    {
        $columns = $this->columns();
        $columns['age']->setSearchOperator('gteq');
        $columns['salary']->setSearchable(false);
        $columns['name']->setSearchOperator('bogus');
        $filters = [
            ['property' => 'age', 'operator' => 'like', 'value' => 30],
            ['property' => 'salary', 'operator' => 'eq', 'value' => 1],
            ['property' => 'missing', 'operator' => 'eq', 'value' => 1],
            ['property' => 'name', 'operator' => 'eq', 'value' => 'x'],
        ];
        $built = $this->query(Identifier::MYSQL, $columns)->build($filters, []);
        $this->assertSame('WHERE `age` >= :f0 AND `name` LIKE :f1', self::squash(substr($built['data'], strpos($built['data'], 'WHERE'))));
        $this->assertSame([':f0' => 30, ':f1' => 'x%'], $built['bindings']);
    }

    /** @test */
    public function developer_params_and_trailing_semicolon_are_handled(): void
    {
        $query = new GridQuery("SELECT id, name FROM t WHERE age >= :min_age ;\n", ['id' => new Column('id'), 'name' => new Column('name')], 'g', Identifier::MYSQL);
        $built = $query->build([['property' => 'name', 'operator' => 'eq', 'value' => 'x']], ['sortField' => 'id', 'rowsPerPage' => 10]);
        $this->assertStringContainsString("FROM (\nSELECT id, name FROM t WHERE age >= :min_age\n) g", $built['data']);
        $this->assertSame([':f0' => 'x'], $built['bindings']);
        $this->assertStringNotContainsString(';', $built['count']);
    }
}
