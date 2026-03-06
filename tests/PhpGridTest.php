<?php

use Laf\Database\Db;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

class PhpGridTest extends TestCase
{
    private static bool $dbAvailable = false;
    private static string $engine = 'mysql';

    public static function setUpBeforeClass(): void
    {
        try {
            Db::getInstance();
            self::$dbAvailable = true;
            self::$engine = Settings::get('database.engine') ?: 'mysql';
        } catch (\Exception $e) {
            self::$dbAvailable = false;
        }
    }

    protected function tearDown(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            $drop = self::$engine === 'postgres'
                ? "DROP TABLE IF EXISTS phpgrid_test CASCADE"
                : "DROP TABLE IF EXISTS `phpgrid_test`";
            Db::run($drop);
        } catch (\Exception $e) {
            // ignore
        }
    }

    private function requireDatabase(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
        $this->createAndSeedTable();
    }

    private function createAndSeedTable(): void
    {
        if (self::$engine === 'postgres') {
            $sql = "
                DROP TABLE IF EXISTS phpgrid_test CASCADE;
                CREATE TABLE phpgrid_test (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    city VARCHAR(100) DEFAULT NULL,
                    age INTEGER DEFAULT NULL,
                    salary DECIMAL(10,2) DEFAULT NULL,
                    created_date DATE DEFAULT NULL
                );
            ";
        } else {
            $sql = "
                DROP TABLE IF EXISTS `phpgrid_test`;
                CREATE TABLE `phpgrid_test` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `city` VARCHAR(100) DEFAULT NULL,
                    `age` INT(11) DEFAULT NULL,
                    `salary` DECIMAL(10,2) DEFAULT NULL,
                    `created_date` DATE DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
        }
        Db::run($sql);

        $db = Db::getInstance();
        $stmt = $db->prepare("INSERT INTO phpgrid_test (name, city, age, salary, created_date) VALUES (:name, :city, :age, :salary, :created_date)");

        $rows = [
            ['Alice',   'New York',      30, 75000.00,  '2023-01-15'],
            ['Bob',     'Boston',        25, 55000.50,  '2023-02-20'],
            ['Charlie', 'New York',      35, 90000.00,  '2023-03-10'],
            ['Diana',   'Chicago',       28, 62000.75,  '2023-04-05'],
            ['Eve',     'Boston',        32, 80000.00,  '2023-05-12'],
            ['Frank',   'Chicago',       40, 95000.25,  '2023-06-18'],
            ['Grace',   'San Francisco', 27, 88000.00,  '2023-07-22'],
            ['Hank',    'New York',      33, 72000.00,  '2023-08-30'],
            ['Ivy',     'Boston',        29, 67000.50,  '2023-09-14'],
            ['Jack',    'Chicago',       45, 105000.00, '2023-10-01'],
            ['Karen',   'San Francisco', 31, 92000.00,  '2023-11-08'],
            ['Leo',     'New York',      38, 85000.00,  '2023-12-25'],
        ];

        foreach ($rows as $r) {
            $stmt->execute([
                ':name' => $r[0],
                ':city' => $r[1],
                ':age' => $r[2],
                ':salary' => $r[3],
                ':created_date' => $r[4],
            ]);
        }
    }

    private function buildGrid(array $filters = []): PhpGrid
    {
        $grid = new PhpGrid('phpgrid_test', [], $filters);
        $grid->setSqlQuery("SELECT id, name, city, age, salary, created_date FROM phpgrid_test");
        $grid->addColumn(new Column('id', 'ID'));
        $grid->addColumn(new Column('name', 'Name'));
        $grid->addColumn(new Column('city', 'City'));
        $grid->addColumn(new Column('age', 'Age'));
        $grid->addColumn(new Column('salary', 'Salary'));
        $grid->addColumn(new Column('created_date', 'Created Date'));
        return $grid;
    }

    // ─── Unit tests (no database required) ──────────────────────────────

    /** @test */
    public function grid_name_sanitizes_spaces(): void
    {
        $grid = new PhpGrid('my grid name', [], []);
        $this->assertEquals('my_grid_name', $grid->getGridName());
    }

    /** @test */
    public function column_management(): void
    {
        $grid = $this->buildGrid();

        $this->assertEquals(6, $grid->getColumnsCount());
        $this->assertTrue($grid->hasColumn('id'));
        $this->assertTrue($grid->hasColumn('name'));
        $this->assertFalse($grid->hasColumn('nonexistent'));
        $this->assertEquals('id', $grid->getFirstColumnName());

        $col = $grid->getColumn('name');
        $this->assertInstanceOf(Column::class, $col);
        $this->assertEquals('Name', $col->getLabel());

        $this->assertNull($grid->getColumn('nonexistent'));
    }

    /** @test */
    public function rows_per_page_setting(): void
    {
        $grid = $this->buildGrid();
        $grid->setRowsPerPage(25);
        $this->assertEquals(25, $grid->getRowsPerPage());
    }

    /** @test */
    public function sort_details_setting(): void
    {
        $grid = $this->buildGrid();
        $grid->setSortDetails('name', 'ASC');

        $details = $grid->getSortDetails();
        $this->assertEquals('name', $details['field']);
        $this->assertEquals('ASC', $details['dir']);
    }

    /** @test */
    public function params_management(): void
    {
        $grid = $this->buildGrid();
        $grid->addParam('status', 'active');

        $params = $grid->getParamsList();
        $this->assertArrayHasKey('status', $params);
        $this->assertEquals('active', $params['status']);
    }

    /** @test */
    public function debug_mode_setting(): void
    {
        $grid = $this->buildGrid();
        $this->assertFalse($grid->getDebug());

        $grid->setDebug(true);
        $this->assertTrue($grid->getDebug());
    }

    /** @test */
    public function wildcard_search_setting(): void
    {
        $grid = $this->buildGrid();
        $this->assertFalse($grid->isEnableWildCardSearch());

        $grid->setEnableWildCardSearch(true);
        $this->assertTrue($grid->isEnableWildCardSearch());
    }

    /** @test */
    public function allow_export_setting(): void
    {
        $grid = $this->buildGrid();
        $this->assertTrue($grid->getAllowExport());

        $grid->setAllowExport(false);
        $this->assertFalse($grid->getAllowExport());
    }

    /** @test */
    public function show_search_bar_setting(): void
    {
        $grid = $this->buildGrid();
        $this->assertTrue($grid->getShowSearchBar());

        $grid->setShowSearchBar(false);
        $this->assertFalse($grid->getShowSearchBar());
    }

    /** @test */
    public function show_title_setting(): void
    {
        $grid = $this->buildGrid();
        $this->assertTrue($grid->getShowTitle());

        $grid->setShowTitle(false);
        $this->assertFalse($grid->getShowTitle());
    }

    /** @test */
    public function title_setting(): void
    {
        $grid = $this->buildGrid();
        $grid->setTitle('My Test Grid');
        $this->assertEquals('My Test Grid', $grid->getTitle());
    }

    /** @test */
    public function row_level_js_callback(): void
    {
        $grid = $this->buildGrid();
        $this->assertNull($grid->getRowLevelJsCallback());

        $grid->setRowLevelJsCallback('MyApp.formatRow');
        $this->assertEquals('MyApp.formatRow', $grid->getRowLevelJsCallback());
    }

    /** @test */
    public function use_ilike_on_pg_setting(): void
    {
        $grid = $this->buildGrid();
        $this->assertFalse($grid->getUseIlikeOnPg());

        $grid->setUseIlikeOnPg(true);
        $this->assertTrue($grid->getUseIlikeOnPg());
    }

    /** @test */
    public function validation_fails_without_sql_query(): void
    {
        $grid = new PhpGrid('test_grid', [], []);
        $grid->addColumn(new Column('id', 'ID'));
        $grid->addColumn(new Column('name', 'Name'));

        $result = $grid->execute();

        $this->assertFalse($result);
        $this->assertEquals('Missing SQL Query', $grid->getErrorMessage());
    }

    /** @test */
    public function validation_fails_with_fewer_than_two_columns(): void
    {
        $grid = new PhpGrid('test_grid', [], []);
        $grid->setSqlQuery("SELECT id FROM phpgrid_test");
        $grid->addColumn(new Column('id', 'ID'));

        $result = $grid->execute();

        $this->assertFalse($result);
        $this->assertEquals('Missing column information', $grid->getErrorMessage());
    }

    /** @test */
    public function validation_fails_with_empty_grid_name(): void
    {
        $grid = new PhpGrid('', [], []);
        $grid->setSqlQuery("SELECT id, name FROM phpgrid_test");
        $grid->addColumn(new Column('id', 'ID'));
        $grid->addColumn(new Column('name', 'Name'));

        $result = $grid->execute();

        $this->assertFalse($result);
        $this->assertEquals('Missing grid name', $grid->getErrorMessage());
    }

    /** @test */
    public function draw_returns_html_with_grid_name(): void
    {
        $grid = $this->buildGrid();
        $html = $grid->draw();

        $this->assertStringContainsString('phpgrid_test', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('data-component-type=\'Grid\'', $html);
    }

    /** @test */
    public function draw_includes_pagination_and_js(): void
    {
        $grid = $this->buildGrid();
        $grid->setRowsPerPage(5);
        $html = $grid->draw();

        $this->assertStringContainsString('phpgrid_test', $html);
        $this->assertStringContainsString('new Grid(', $html);
        $this->assertStringContainsString('_rowsPerPage', $html);
    }

    /** @test */
    public function column_number_to_letter_conversion(): void
    {
        $grid = $this->buildGrid();

        $this->assertEquals('A', $grid->columnNumberToLetter(1));
        $this->assertEquals('B', $grid->columnNumberToLetter(2));
        $this->assertEquals('Z', $grid->columnNumberToLetter(26));
        $this->assertEquals('AA', $grid->columnNumberToLetter(27));
        $this->assertEquals('', $grid->columnNumberToLetter(0));
    }

    /** @test */
    public function is_ready_to_handle_requests(): void
    {
        $grid = $this->buildGrid(['load_grid_by_name' => 'phpgrid_test']);
        $this->assertTrue($grid->isReadyToHandleRequests());

        $grid2 = $this->buildGrid(['load_grid_by_name' => 'other_grid']);
        $this->assertFalse($grid2->isReadyToHandleRequests());

        $grid3 = $this->buildGrid([]);
        $this->assertFalse($grid3->isReadyToHandleRequests());
    }

    // ─── Integration tests (database required) ─────────────────────────

    /** @test */
    public function execute_returns_all_rows_when_get_all_rows_is_true(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->execute(true);

        $this->assertEquals(12, $grid->getRowCount());
    }

    /** @test */
    public function execute_returns_paginated_rows(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->setRowsPerPage(5);
        $grid->execute();

        $this->assertEquals(12, $grid->getRowCount());
        $this->assertEquals(3, $grid->getPageCount());
    }

    /** @test */
    public function execute_respects_page_filter(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['page' => '2']);
        $grid->setRowsPerPage(5);
        $grid->execute();

        $this->assertEquals(12, $grid->getRowCount());
        $this->assertNotEmpty($this->getGridData($grid));
    }

    /** @test */
    public function execute_respects_limit_filter(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['limit' => '3']);
        $grid->execute();

        $this->assertEquals(3, $grid->getRowsPerPage());
    }

    /** @test */
    public function sort_by_column_ascending(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['sort' => 'name', 'dir' => 'asc']);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertEquals('Alice', $data[0]['name']);
    }

    /** @test */
    public function sort_by_column_descending(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['sort' => 'name', 'dir' => 'desc']);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertEquals('Leo', $data[0]['name']);
    }

    /** @test */
    public function sort_by_age_ascending(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['sort' => 'age', 'dir' => 'asc']);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertEquals('Bob', $data[0]['name']);
    }

    /** @test */
    public function sort_ignores_invalid_column(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['sort' => 'nonexistent_column', 'dir' => 'asc']);
        $result = $grid->execute(true);

        $this->assertTrue($result);
        $this->assertEquals(12, $grid->getRowCount());
    }

    /** @test */
    public function sort_ignores_invalid_direction(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid(['sort' => 'name', 'dir' => 'DROP TABLE']);
        $result = $grid->execute(true);

        $this->assertTrue($result);
        $sortDetails = $grid->getSortDetails();
        $this->assertContains(strtolower($sortDetails['dir']), ['asc', 'desc']);
    }

    /** @test */
    public function search_with_like_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'like', 'value' => 'New York']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->setEnableWildCardSearch(true);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(4, $data);
        foreach ($data as $row) {
            $this->assertEquals('New York', $row['city']);
        }
    }

    /** @test */
    public function search_with_eq_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'age', 'operator' => 'eq', 'value' => '30']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(1, $data);
        $this->assertEquals('Alice', $data[0]['name']);
    }

    /** @test */
    public function search_with_gt_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'age', 'operator' => 'gt', 'value' => '35']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(3, $data);
        foreach ($data as $row) {
            $this->assertGreaterThan(35, (int)$row['age']);
        }
    }

    /** @test */
    public function search_with_lt_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'age', 'operator' => 'lt', 'value' => '28']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(2, $data);
        foreach ($data as $row) {
            $this->assertLessThan(28, (int)$row['age']);
        }
    }

    /** @test */
    public function search_with_gteq_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'age', 'operator' => 'gteq', 'value' => '40']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(2, $data);
    }

    /** @test */
    public function search_with_lteq_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'age', 'operator' => 'lteq', 'value' => '25']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(1, $data);
        $this->assertEquals('Bob', $data[0]['name']);
    }

    /** @test */
    public function search_with_noteq_operator(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'noteq', 'value' => 'Boston']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(9, $data);
        foreach ($data as $row) {
            $this->assertNotEquals('Boston', $row['city']);
        }
    }

    /** @test */
    public function search_with_multiple_filters(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'eq', 'value' => 'New York'],
            (object)['property' => 'age', 'operator' => 'gt', 'value' => '31'],
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(3, $data);
        foreach ($data as $row) {
            $this->assertEquals('New York', $row['city']);
            $this->assertGreaterThan(31, (int)$row['age']);
        }
    }

    /** @test */
    public function search_ignores_invalid_column_in_filter(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'nonexistent', 'operator' => 'eq', 'value' => 'test'],
            (object)['property' => 'city', 'operator' => 'eq', 'value' => 'Boston'],
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(3, $data);
    }

    /** @test */
    public function pagination_calculates_page_count_correctly(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->setRowsPerPage(5);
        $grid->execute();

        $this->assertEquals(3, $grid->getPageCount());
    }

    /** @test */
    public function pagination_with_exact_division(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->setRowsPerPage(4);
        $grid->execute();

        $this->assertEquals(3, $grid->getPageCount());
    }

    /** @test */
    public function pagination_single_page(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->setRowsPerPage(20);
        $grid->execute();

        $this->assertEquals(1, $grid->getPageCount());
    }

    /** @test */
    public function column_totals_are_computed(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->setColumnTotals(['salary']);
        $grid->execute(true);

        $totals = $grid->getColumnTotals();
        $this->assertArrayHasKey('salary', $totals);
        $this->assertEquals(966002.00, (float)$totals['salary'], '', 0.01);
    }

    /** @test */
    public function column_totals_with_search_filter(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'eq', 'value' => 'Boston'],
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->setColumnTotals(['salary']);
        $grid->execute(true);

        $totals = $grid->getColumnTotals();
        $this->assertEquals(202001.00, (float)$totals['salary'], '', 0.01);
    }

    /** @test */
    public function column_totals_with_unknown_column_sets_error(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->setDebug(true);
        $grid->setColumnTotals(['nonexistent_col']);
        $grid->execute(true);

        $this->assertNotEmpty($grid->getErrorMessage());
    }

    /** @test */
    public function generated_sql_queries_are_populated_after_execute(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->execute(true);

        $this->assertNotEmpty($grid->getGeneratedSqlQuery());
        $this->assertStringContainsString('phpgrid_test', $grid->getGeneratedSqlQuery());
    }

    /** @test */
    public function generated_count_query_is_populated(): void
    {
        $this->requireDatabase();

        $grid = $this->buildGrid();
        $grid->execute();

        $this->assertNotEmpty($grid->getGeneratedSqlCountQuery());
        $this->assertStringContainsString('COUNT', $grid->getGeneratedSqlCountQuery());
    }

    /** @test */
    public function wildcard_search_startswith_mode(): void
    {
        $this->requireDatabase();

        $grid = new PhpGrid('phpgrid_test', [], [
            'searchParams' => urlencode(json_encode([
                (object)['property' => 'name', 'operator' => 'like', 'value' => 'A']
            ]))
        ]);
        $grid->setSqlQuery("SELECT id, name, city, age, salary, created_date FROM phpgrid_test");
        $grid->addColumn(new Column('id', 'ID'));
        $nameCol = new Column('name', 'Name');
        $nameCol->wildcardMode = 'startswith';
        $grid->addColumn($nameCol);
        $grid->addColumn(new Column('city', 'City'));
        $grid->addColumn(new Column('age', 'Age'));
        $grid->addColumn(new Column('salary', 'Salary'));
        $grid->addColumn(new Column('created_date', 'Created Date'));
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(1, $data);
        $this->assertEquals('Alice', $data[0]['name']);
    }

    /** @test */
    public function wildcard_search_contains_mode(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'name', 'operator' => 'like', 'value' => 'an']
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->setEnableWildCardSearch(true);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $names = array_column($data, 'name');
        $this->assertContains('Diana', $names);
        $this->assertContains('Frank', $names);
        $this->assertContains('Hank', $names);
    }

    /** @test */
    public function execute_with_parameterized_query(): void
    {
        $this->requireDatabase();

        $grid = new PhpGrid('phpgrid_test', ['min_age' => 30], []);
        $grid->setSqlQuery("SELECT id, name, city, age, salary, created_date FROM phpgrid_test WHERE age >= :min_age");
        $grid->addColumn(new Column('id', 'ID'));
        $grid->addColumn(new Column('name', 'Name'));
        $grid->addColumn(new Column('city', 'City'));
        $grid->addColumn(new Column('age', 'Age'));
        $grid->addColumn(new Column('salary', 'Salary'));
        $grid->addColumn(new Column('created_date', 'Created Date'));
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(8, $data);
        foreach ($data as $row) {
            $this->assertGreaterThanOrEqual(30, (int)$row['age']);
        }
    }

    /** @test */
    public function search_combined_with_sort_and_pagination(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'eq', 'value' => 'New York'],
        ]);
        $grid = $this->buildGrid([
            'searchParams' => urlencode($searchParams),
            'sort' => 'age',
            'dir' => 'asc',
            'page' => '1',
        ]);
        $grid->setRowsPerPage(2);
        $grid->execute();

        $this->assertEquals(4, $grid->getRowCount());
        $this->assertEquals(2, $grid->getPageCount());

        $data = $this->getGridData($grid);
        $this->assertCount(2, $data);
        $this->assertEquals('Alice', $data[0]['name']);
        $this->assertEquals('Hank', $data[1]['name']);
    }

    /** @test */
    public function second_page_returns_remaining_rows(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'eq', 'value' => 'New York'],
        ]);
        $grid = $this->buildGrid([
            'searchParams' => urlencode($searchParams),
            'sort' => 'age',
            'dir' => 'asc',
            'page' => '2',
        ]);
        $grid->setRowsPerPage(2);
        $grid->execute();

        $data = $this->getGridData($grid);
        $this->assertCount(2, $data);
        $this->assertEquals('Charlie', $data[0]['name']);
        $this->assertEquals('Leo', $data[1]['name']);
    }

    /** @test */
    public function empty_result_set(): void
    {
        $this->requireDatabase();

        $searchParams = json_encode([
            (object)['property' => 'city', 'operator' => 'eq', 'value' => 'Nonexistent City'],
        ]);
        $grid = $this->buildGrid(['searchParams' => urlencode($searchParams)]);
        $grid->execute(true);

        $data = $this->getGridData($grid);
        $this->assertCount(0, $data);
        $this->assertEquals(0, $grid->getRowCount());
    }

    /**
     * Helper to access the protected $data property via reflection
     */
    private function getGridData(PhpGrid $grid): array
    {
        $ref = new \ReflectionProperty(PhpGrid::class, 'data');
        $ref->setAccessible(true);
        return $ref->getValue($grid);
    }
}
