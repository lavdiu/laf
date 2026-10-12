<?php

namespace Laf\Exception;

class UniqueFieldDuplicateValueException extends \Exception
{
    /** @var string[] */
    private array $fieldNames = [];

    /**
     * The fields the message is about (empty when not known)
     * @param string[] $fieldNames
     */
    public function setFieldNames(array $fieldNames): static
    {
        $this->fieldNames = array_values(array_map('strval', $fieldNames));
        return $this;
    }

    /**
     * @return string[]
     */
    public function getFieldNames(): array
    {
        return $this->fieldNames;
    }
}