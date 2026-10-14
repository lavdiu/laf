<?php

declare(strict_types=1);

namespace Laf\Exception;

/**
 * A record refused because values break their validation rules (type rules, minimum and
 * maximum, pattern, custom validators, the model's validateRecord()). getErrors() has the
 * messages per field; record-level messages use the key ValidationException::RECORD_KEY.
 * Thrown by BaseObject::store()/insert()/update() when validation on store is on.
 */
class ValidationException extends \Exception
{
    /** Key of messages that belong to the record rather than to one field */
    public const RECORD_KEY = '_record';

    /** @var array<string, string[]> field name => messages */
    private array $errors;

    private ?string $tableName = null;

    /**
     * @param array<string, string[]> $errors field name => messages
     */
    public function __construct(array $errors, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        if ($message === '') {
            $message = join('; ', $this->getMessages());
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, string[]> field name => messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string[] every message, in field order
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldMessages) {
            foreach ((array)$fieldMessages as $fieldMessage) {
                $messages[] = (string)$fieldMessage;
            }
        }
        return $messages;
    }

    /**
     * @return string[] the fields that have messages (RECORD_KEY left out)
     */
    public function getFieldNames(): array
    {
        return array_values(array_filter(array_map('strval', array_keys($this->errors)), fn(string $name) => $name !== self::RECORD_KEY));
    }

    public function setTableName(?string $tableName): static
    {
        $this->tableName = $tableName;
        return $this;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }
}
