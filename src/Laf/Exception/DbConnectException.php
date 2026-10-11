<?php

namespace Laf\Exception;

/**
 * The database connection could not be opened (or is not open when a
 * statement needs it). Extends PDOException so both catch (\PDOException)
 * and catch (\Exception) keep matching; the driver's exception is the previous one.
 */
class DbConnectException extends \PDOException
{
    public static function fromPdo(\PDOException $ex, string $dsn = ''): self
    {
        $message = 'Database connection failed' . ($dsn !== '' ? ' [' . $dsn . ']' : '') . ': ' . $ex->getMessage();
        $exception = new self($message, 0, $ex);
        $exception->errorInfo = $ex->errorInfo;
        return $exception;
    }
}
