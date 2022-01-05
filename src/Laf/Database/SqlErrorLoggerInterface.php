<?php

namespace Laf\Database;

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

    public function storeLogEntry(): bool;

}