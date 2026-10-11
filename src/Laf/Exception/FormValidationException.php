<?php

declare(strict_types=1);

namespace Laf\Exception;

/**
 * Thrown by Form::processForm() when a submitted value is invalid or breaks a
 * field constraint. getErrors() has every message per field; the form's
 * elements show them when the form is drawn again.
 */
class FormValidationException extends \Exception
{
    /** @var array<string, string[]> field name => messages */
    private array $errors;

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
}
