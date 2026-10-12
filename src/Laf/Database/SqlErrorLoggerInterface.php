<?php

namespace Laf\Database;

/**
 * A model that stores failed statements: Db::setSqlErrorLogger() takes one and fills it with
 * the SQL, the driver message, the file and line and the stack trace of every statement that
 * fails, then calls storeLogEntry(). getTableName() lets Db check that the log table exists
 * before it relies on the logger. Inside a transaction the entry is written after the
 * transaction ends so it is neither rolled back nor refused.
 */
interface SqlErrorLoggerInterface
{
    public function setSqlQuery(?string $value): void;

    public function setErrorMessage(?string $value): void;

    public function setFile(?string $value): void;

    public function setLineNumber(?string $value): void;

    public function setTraceAsString(?string $value): void;

    public function setCustomMessage(?string $value): void;

    public function getSqlQuery(): ?string;

    public function getErrorMessage(): ?string;

    public function getFile(): ?string;

    public function getLineNumber(): ?string;

    public function getTraceAsString(): ?string;

    public function getCustomMessage(): ?string;

    /** Persist the entry; false when it could not be written. */
    public function storeLogEntry(): bool;

    /** The table the entries go to. */
    public function getTableName(): string;
}
