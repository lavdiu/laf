<?php

declare(strict_types=1);

namespace Laf\Exception;

/**
 * Thrown by BaseObject::loadOrFail() when no row has the given key.
 * A page answers it with a 404 (or a flash message and a redirect to the list).
 */
class RecordNotFoundException extends \RuntimeException
{
    private string $tableName;

    /** @var int|string|array|null */
    private $recordId;

    /**
     * @param int|string|array|null $recordId
     */
    public function __construct(string $tableName, $recordId, ?\Throwable $previous = null)
    {
        $this->tableName = $tableName;
        $this->recordId = $recordId;
        $shown = is_array($recordId) ? json_encode($recordId) : (string)$recordId;
        parent::__construct(sprintf('No %s record with id %s', $tableName, $shown === false ? '?' : $shown), 404, $previous);
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    /** @return int|string|array|null */
    public function getRecordId()
    {
        return $this->recordId;
    }
}
