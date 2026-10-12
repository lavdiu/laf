<?php

use Laf\Database\Pagination;
use PHPUnit\Framework\TestCase;

/**
 * Pagination value object: the counts a pager needs, computed from items/total/page/perPage.
 */
class PaginationTest extends TestCase
{
    /** @test */
    public function counts_positions_and_navigation_flags()
    {
        $page = new Pagination([['id' => 26], ['id' => 27], ['id' => 28]], 57, 2, 25);
        $this->assertSame(3, count($page));
        $this->assertSame(57, $page->total);
        $this->assertSame(2, $page->page);
        $this->assertSame(25, $page->perPage);
        $this->assertSame(3, $page->lastPage);
        $this->assertSame(26, $page->from);
        $this->assertSame(28, $page->to);
        $this->assertTrue($page->hasMore());
        $this->assertTrue($page->hasPrevious());
        $this->assertFalse($page->isEmpty());
        $this->assertSame([26, 27, 28], array_map(fn ($row) => $row['id'], iterator_to_array($page)));
        $this->assertSame(['items', 'total', 'page', 'perPage', 'lastPage', 'from', 'to'], array_keys($page->toArray()));
        $this->assertSame(57, json_decode((string)json_encode($page), true)['total']);
    }

    /** @test */
    public function an_empty_result_is_one_empty_page()
    {
        $page = new Pagination([], 0, 1, 25);
        $this->assertSame(1, $page->lastPage);
        $this->assertSame(0, $page->from);
        $this->assertSame(0, $page->to);
        $this->assertFalse($page->hasMore());
        $this->assertFalse($page->hasPrevious());
        $this->assertTrue($page->isEmpty());

        $beyond = new Pagination([], 57, 9, 25);
        $this->assertSame(3, $beyond->lastPage);
        $this->assertSame(0, $beyond->from, 'a page past the end is empty');
        $this->assertFalse($beyond->hasMore());
        $this->assertTrue($beyond->hasPrevious());
    }

    /** @test */
    public function bad_numbers_are_clamped()
    {
        $page = new Pagination(['a'], -5, 0, 0);
        $this->assertSame(0, $page->total);
        $this->assertSame(1, $page->page);
        $this->assertSame(1, $page->perPage);
        $this->assertSame(1, $page->lastPage);
        $this->assertSame(['a'], $page->items);
    }
}
