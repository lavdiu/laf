<?php

declare(strict_types=1);

use Laf\UI\Grid\PhpGrid\ActionButton;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use PHPUnit\Framework\TestCase;

/**
 * PhpGrid without a database: markup, configuration and request handling
 */
class PhpGridUnitTest extends TestCase
{
    private function grid(string $name = 'people', array $filters = []): PhpGrid
    {
        $grid = new PhpGrid($name, [], $filters);
        $grid->setSqlQuery('SELECT id, name FROM people');
        $grid->addColumn(new Column('id', 'ID'));
        $grid->addColumn(new Column('name', 'Name'));
        return $grid;
    }

    /** @test */
    public function draw_renders_bootstrap_5_markup_with_the_known_ids(): void
    {
        $html = $this->grid()->setRowsPerPage(20)->draw();

        foreach (['people_container', 'people_thead', 'people_title_row', 'people_title', 'people_buttons', 'people_tbody', 'people_tfoot',
                     'people_paginationFirstPage', 'people_paginationPrevPage', 'people_paginationCurrPage', 'people_paginationNextPage',
                     'people_paginationLastPage', 'people_paginationInfoSection', 'people_rowsPerPageSelector', 'people_loader'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $id);
        }
        $this->assertStringContainsString('<table id="people" data-component-type="Grid"', $html);
        $this->assertStringContainsString('class="table-light"', $html);
        $this->assertStringContainsString('class="text-end"', $html);
        $this->assertStringContainsString('data-bs-toggle="dropdown"', $html);
        $this->assertStringNotContainsString('data-toggle=', $html);
        $this->assertStringNotContainsString('thead-light', $html);
        $this->assertStringNotContainsString('text-right', $html);
        $this->assertStringNotContainsString("javascript:;", $html);
        $this->assertStringContainsString('data-rows-per-page="20"', $html);
        $this->assertStringContainsString('data-show-title-bar="true"', $html);
        $this->assertStringContainsString('data-show-search-bar="true"', $html);
        $this->assertStringContainsString('data-defer-initialize="false"', $html);
        $this->assertStringContainsString('data-rows-per-page-options="[10,50,100]"', $html);
        $this->assertStringContainsString('<button type="button" id="people_rowsPerPageSelector"', $html);
        $this->assertStringContainsString('>20</button>', $html);
        // the header and search rows are the 2nd and 3rd rows of the thead (old grid.js finds them by index)
        $this->assertSame(3, substr_count(substr($html, strpos($html, '<thead'), strpos($html, '</thead>') - strpos($html, '<thead')), '<tr'));
    }

    /** @test */
    public function draw_inline_script_registers_the_grid_and_falls_back_without_auto_init(): void
    {
        $html = $this->grid()->setRowsPerPage(7)->setShowTitle(false)->setShowSearchBar(false)->setDeferInitialize(true)->draw();

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('var gridName = "people";', $html);
        $this->assertStringContainsString('new Grid(gridName)', $html);
        $this->assertStringContainsString('grid._rowsPerPage = 7;', $html);
        $this->assertStringContainsString('grid.showTitleBar = false;', $html);
        $this->assertStringContainsString('grid.showSearchBar = false;', $html);
        $this->assertStringContainsString('grid.deferInitialize = true;', $html);
        $this->assertStringContainsString('typeof Grid.autoInit === "function"', $html);
        $this->assertStringContainsString('grid.initialize();', $html);
        $this->assertStringContainsString('data-show-title-bar="false"', $html);
        $this->assertStringContainsString('data-defer-initialize="true"', $html);
        // the rows-per-page menu keeps working with an old grid.js through its inline handlers
        $this->assertStringContainsString('onclick="if (window.grid && window.grid[&quot;people&quot;]) { window.grid[&quot;people&quot;].setRowsPerPage(50); } return false;"', $html);
    }

    /** @test */
    public function draw_without_inline_script_has_no_script_and_no_handlers(): void
    {
        $html = $this->grid()->setInlineScript(false)->setRowsPerPageOptions([10, 25, 0])->draw();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringContainsString('data-rows-per-page-options="[10,25,0]"', $html);
        $this->assertStringContainsString('data-rows-per-page="0">All</a>', $html);
        $this->assertStringContainsString('data-rows-per-page="25">25</a>', $html);
    }

    /** @test */
    public function draw_escapes_the_grid_name_everywhere(): void
    {
        $html = $this->grid('a"b<c&d')->draw();

        $this->assertStringContainsString('id="a&quot;b&lt;c&amp;d_container"', $html);
        $this->assertStringContainsString('<table id="a&quot;b&lt;c&amp;d"', $html);
        $this->assertStringContainsString('var gridName = "a\u0022b\u003Cc\u0026d";', $html);
        $this->assertStringContainsString('window.grid[&quot;a\&quot;b\u003Cc\u0026d&quot;]', $html);
        $this->assertStringNotContainsString('<c&d', $html);
    }

    /** @test */
    public function rows_per_page_options_are_cleaned(): void
    {
        $grid = $this->grid()->setRowsPerPageOptions(['10', 25, -5, 'x', 25, 0]);
        $this->assertSame([10, 25, 0], $grid->getRowsPerPageOptions());
    }

    /** @test */
    public function request_is_parsed_from_the_filters_and_reset_on_change(): void
    {
        $grid = $this->grid('people', ['load_grid_by_name' => 'people', 'limit' => '5000', 'page' => '3']);
        $this->assertTrue($grid->isReadyToHandleRequests());
        $this->assertSame(1000, $grid->getRequest()->limit);
        $this->assertSame(3, $grid->getRequest()->page);

        $grid->setMaxRowsPerPage(50);
        $this->assertSame(50, $grid->getRequest()->limit);

        $grid->setFilters(['load_grid_by_name' => 'other']);
        $this->assertFalse($grid->isReadyToHandleRequests());
        $this->assertNull($grid->getRequest()->limit);
    }

    /** @test */
    public function payload_before_a_run_is_not_a_success(): void
    {
        $grid = $this->grid();
        $grid->addActionButton(new ActionButton('View', '?id={id}'));
        $json = $grid->toArray();
        $this->assertFalse($json['success']);
        $this->assertSame([], $json['rows']);
        $this->assertSame([], $json['columnTotals']);
        $this->assertCount(1, $json['actionButtons']);
        $this->assertFalse($grid->isExecuted());
        $this->assertStringStartsWith('{"success":false', $grid->toJson());
    }

    /** @test */
    public function action_buttons_from_arrays_become_objects(): void
    {
        $grid = $this->grid()->setActionButtons([
            new ActionButton('A', '/a'),
            ['label' => 'B', 'href' => '/b', 'icon' => 'fa fa-b'],
            'junk',
        ]);
        $buttons = $grid->getActionButtons();
        $this->assertCount(2, $buttons);
        $this->assertInstanceOf(ActionButton::class, $buttons[1]);
        $this->assertSame('fa fa-b', $buttons[1]->getIcon());
    }

    /** @test */
    public function replacing_a_column_keeps_its_index(): void
    {
        $grid = $this->grid();
        $grid->addColumn(new Column('id', 'Identifier'));
        $this->assertSame(2, $grid->getColumnsCount());
        $this->assertSame(0, $grid->getColumn('id')->getIndex());
        $this->assertSame('Identifier', $grid->getColumn('id')->getLabel());
        $this->assertSame(['id', 'name'], array_keys($grid->getColumnsList()));
        $this->assertSame('', (new PhpGrid('empty', [], ['x' => 1]))->getFirstColumnName());
    }

    /** @test */
    public function deprecated_ilike_flag_is_kept_but_harmless(): void
    {
        $grid = $this->grid();
        $this->assertFalse($grid->getUseIlikeOnPg());
        $this->assertSame($grid, $grid->setUseIlikeOnPg(true));
        $this->assertTrue($grid->getUseIlikeOnPg());
    }
}
