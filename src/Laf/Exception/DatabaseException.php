<?php

declare(strict_types=1);

namespace Laf\Exception;

/**
 * A statement issued by the ORM failed. Carries the SQL, the bound values and
 * the driver's SQLSTATE so a caller can react (duplicate key, FK violation)
 * without parsing the message. The original PDOException is the previous one.
 *
 * Extends \Exception so existing catch (\Exception) blocks keep working.
 */
class DatabaseException extends \Exception
{
    private string $sql;

    /** @var array<string, mixed> placeholder => bound value */
    private array $bindings;

    /**
     * @param array<string, mixed> $bindings placeholder => value, or placeholder => [value, PDO type]
     */
    public function __construct(string $message, string $sql = '', array $bindings = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sql = $sql;
        $this->bindings = self::stripPdoTypes($bindings);
    }

    /**
     * @param array<string, mixed> $bindings
     */
    public static function fromPdo(\PDOException $ex, string $sql = '', array $bindings = []): self
    {
        return new self($ex->getMessage(), $sql, $bindings, $ex);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Bound values keyed by placeholder (PDO type constants stripped).
     * @return array<string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /** Five-character SQLSTATE of the underlying driver error, when known. */
    public function getSqlState(): ?string
    {
        $previous = $this->getPrevious();
        if ($previous instanceof \PDOException) {
            $info = $previous->errorInfo;
            if (is_array($info) && isset($info[0]) && is_string($info[0]) && $info[0] !== '') {
                return $info[0];
            }
            $code = $previous->getCode();
            if (is_string($code) && strlen($code) === 5) {
                return $code;
            }
        }
        return null;
    }

    /** Driver-specific error number (MySQL 1062, 1451, ...), when known. */
    public function getDriverCode(): ?int
    {
        $previous = $this->getPrevious();
        if ($previous instanceof \PDOException && is_array($previous->errorInfo) && isset($previous->errorInfo[1]) && is_numeric($previous->errorInfo[1])) {
            return (int)$previous->errorInfo[1];
        }
        return null;
    }

    /** A UNIQUE / PRIMARY KEY constraint rejected the statement (PostgreSQL 23505, MySQL 1062). */
    public function isUniqueViolation(): bool
    {
        $state = $this->getSqlState();
        return $state === '23505' || ($state === '23000' && $this->getDriverCode() === 1062);
    }

    /** A FOREIGN KEY constraint rejected the statement (PostgreSQL 23503, MySQL 1451/1452). */
    public function isForeignKeyViolation(): bool
    {
        $state = $this->getSqlState();
        return $state === '23503' || ($state === '23000' && in_array($this->getDriverCode(), [1451, 1452], true));
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<string, mixed>
     */
    private static function stripPdoTypes(array $bindings): array
    {
        $plain = [];
        foreach ($bindings as $placeholder => $binding) {
            if (is_array($binding) && array_keys($binding) === [0, 1] && is_int($binding[1])) {
                $plain[$placeholder] = $binding[0];
            } else {
                $plain[$placeholder] = $binding;
            }
        }
        return $plain;
    }
}
