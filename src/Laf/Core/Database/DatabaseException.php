<?php

declare(strict_types=1);

namespace Laf\Core\Database;

use Exception;
use Throwable;

/**
 * Database Exception
 * 
 * Exception thrown for database-related errors
 */
class DatabaseException extends Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private readonly ?string $query = null,
        private readonly array $bindings = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the query that caused the exception
     *
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Get the query bindings
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get full error context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'query' => $this->query,
            'bindings' => $this->bindings,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}
