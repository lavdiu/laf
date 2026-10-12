<?php

declare(strict_types=1);

use Laf\UI\Grid\PhpGrid\GridRequest;
use PHPUnit\Framework\TestCase;

class GridRequestTest extends TestCase
{
    /** @test */
    public function defaults_when_nothing_is_sent(): void
    {
        $r = GridRequest::fromArray([]);
        $this->assertSame(1, $r->page);
        $this->assertNull($r->limit);
        $this->assertNull($r->sort);
        $this->assertNull($r->dir);
        $this->assertNull($r->dirSql());
        $this->assertSame([], $r->filters);
        $this->assertNull($r->gridName);
        $this->assertNull($r->export);
        $this->assertFalse($r->isExport());
    }

    /** @test */
    public function page_is_an_integer_of_at_least_one(): void
    {
        $this->assertSame(3, GridRequest::fromArray(['page' => '3'])->page);
        $this->assertSame(1, GridRequest::fromArray(['page' => '0'])->page);
        $this->assertSame(1, GridRequest::fromArray(['page' => '-4'])->page);
        $this->assertSame(1, GridRequest::fromArray(['page' => '1.5'])->page);
        $this->assertSame(1, GridRequest::fromArray(['page' => 'abc'])->page);
        $this->assertSame(1, GridRequest::fromArray(['page' => ['2']])->page);
    }

    /** @test */
    public function limit_is_clamped_and_zero_means_all(): void
    {
        $this->assertSame(25, GridRequest::fromArray(['limit' => '25'])->limit);
        $this->assertSame(0, GridRequest::fromArray(['limit' => '0'])->limit);
        $this->assertSame(1000, GridRequest::fromArray(['limit' => '5000'])->limit);
        $this->assertSame(50, GridRequest::fromArray(['limit' => '5000'], 50)->limit);
        $this->assertNull(GridRequest::fromArray(['limit' => 'abc'])->limit);
        $this->assertNull(GridRequest::fromArray(['limit' => '-1'])->limit);
    }

    /** @test */
    public function sort_and_dir_are_kept_as_sent(): void
    {
        $r = GridRequest::fromArray(['sort' => ' name ', 'dir' => 'desc']);
        $this->assertSame('name', $r->sort);
        $this->assertSame('desc', $r->dir);
        $this->assertSame('DESC', $r->dirSql());

        $r = GridRequest::fromArray(['sort' => '', 'dir' => 'Asc']);
        $this->assertNull($r->sort);
        $this->assertSame('Asc', $r->dir);
        $this->assertSame('ASC', $r->dirSql());

        $r = GridRequest::fromArray(['dir' => 'DROP TABLE']);
        $this->assertNull($r->dir);
    }

    /** @test */
    public function search_params_are_decoded_once_with_a_fallback_for_pre_encoded_callers(): void
    {
        $json = json_encode([['property' => 'name', 'operator' => 'like', 'value' => 'a+b%25 c']]);

        $r = GridRequest::fromArray(['searchParams' => $json]);
        $this->assertSame([['property' => 'name', 'operator' => 'like', 'value' => 'a+b%25 c']], $r->filters);

        $r = GridRequest::fromArray(['searchParams' => urlencode($json)]);
        $this->assertSame('a+b%25 c', $r->filters[0]['value']);

        $r = GridRequest::fromArray(['searchParams' => [['property' => 'x', 'operator' => 'eq', 'value' => 1]]]);
        $this->assertSame([['property' => 'x', 'operator' => 'eq', 'value' => 1]], $r->filters);
    }

    /** @test */
    public function malformed_search_params_are_dropped_silently(): void
    {
        $this->assertSame([], GridRequest::fromArray(['searchParams' => 'not json'])->filters);
        $this->assertSame([], GridRequest::fromArray(['searchParams' => '"scalar"'])->filters);
        $this->assertSame([], GridRequest::fromArray(['searchParams' => '[1, "x", null]'])->filters);
        $this->assertSame([], GridRequest::fromArray(['searchParams' => '[{"operator":"eq","value":1}]'])->filters);
        $this->assertSame([], GridRequest::fromArray(['searchParams' => '[{"property":"","value":1}]'])->filters);
        $this->assertSame([], GridRequest::fromArray(['searchParams' => '[{"property":"a","value":{"x":1}}]'])->filters);
        $this->assertSame([], GridRequest::fromArray(['searchParams' => 42])->filters);
    }

    /** @test */
    public function operators_are_whitelisted_and_values_normalised(): void
    {
        $r = GridRequest::fromArray(['searchParams' => json_encode([
            ['property' => 'a', 'operator' => 'GTEQ', 'value' => 5],
            ['property' => 'b', 'operator' => 'bogus', 'value' => 'x'],
            ['property' => 'c', 'value' => 'no operator'],
            ['property' => 'd', 'operator' => 'in', 'value' => [1, 'two', null, [3]]],
            ['property' => 'e', 'operator' => 'isnull'],
            ['property' => 'f', 'operator' => 'eq', 'value' => null],
            ['property' => 'g', 'operator' => 'eq', 'value' => true],
        ])]);

        $this->assertSame(['property' => 'a', 'operator' => 'gteq', 'value' => 5], $r->filters[0]);
        $this->assertSame('like', $r->filters[1]['operator']);
        $this->assertSame('like', $r->filters[2]['operator']);
        $this->assertSame([1, 'two'], $r->filters[3]['value']);
        $this->assertSame(['property' => 'e', 'operator' => 'isnull', 'value' => null], $r->filters[4]);
        $this->assertNull($r->filters[5]['value']);
        $this->assertTrue($r->filters[6]['value']);
        $this->assertCount(7, $r->filters);
    }

    /** @test */
    public function grid_name_and_export_mode(): void
    {
        $this->assertSame('g', GridRequest::fromArray(['load_grid_by_name' => 'g'])->gridName);

        $this->assertSame('xlsx', GridRequest::fromArray(['export_grid_to_excel' => '1'])->export);
        $this->assertSame('xlsx', GridRequest::fromArray(['export_grid_to_excel' => '2'])->export);
        $this->assertSame('xlsx', GridRequest::fromArray(['export_grid_to_excel' => '1', 'export_grid_to_csv' => '1'])->export);
        $this->assertSame('csv', GridRequest::fromArray(['export_grid_to_csv' => '1'])->export);
        $this->assertSame('csv', GridRequest::fromArray(['export_grid_to_csv' => ''])->export);
        $this->assertNull(GridRequest::fromArray(['export_grid_to_excel' => '0'])->export);
        $this->assertNull(GridRequest::fromArray(['export_grid_to_excel' => 'yes'])->export);
        $this->assertTrue(GridRequest::fromArray(['export_grid_to_csv' => '1'])->isExport());
    }
}
