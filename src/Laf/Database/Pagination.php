<?php

declare(strict_types=1);

namespace Laf\Database;

/**
 * One page of a Query::paginate() result: the items of the page and the counts a
 * list page or an API needs to draw its pager.
 */
final class Pagination implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /** @var array<int, BaseObject|array<string, mixed>> */
    public array $items;

    /** rows matching the query, all pages together */
    public int $total;

    /** 1-based */
    public int $page;

    public int $perPage;

    /** 1 when there are no rows */
    public int $lastPage;

    /** 1-based position of the first item of this page in the whole result, 0 when the page is empty */
    public int $from;

    /** 1-based position of the last item, 0 when the page is empty */
    public int $to;

    /**
     * @param array<int, BaseObject|array<string, mixed>> $items
     */
    public function __construct(array $items, int $total, int $page, int $perPage)
    {
        $this->items = array_values($items);
        $this->total = max(0, $total);
        $this->page = max(1, $page);
        $this->perPage = max(1, $perPage);
        $this->lastPage = max(1, (int)ceil($this->total / $this->perPage));
        $count = count($this->items);
        $this->from = $count === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
        $this->to = $count === 0 ? 0 : $this->from + $count - 1;
    }

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** Items on this page */
    public function count(): int
    {
        return count($this->items);
    }

    /** @return \ArrayIterator<int, BaseObject|array<string, mixed>> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * The counts plus the items (objects through their own toArray() / jsonSerialize())
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'lastPage' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
