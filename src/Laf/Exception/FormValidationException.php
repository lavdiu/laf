<?php

declare(strict_types=1);

namespace Laf\Exception;

/**
 * Thrown by Form::processForm() when a submitted value is invalid or breaks a
 * field constraint. getErrors() has every message per field; the form's
 * elements show them when the form is drawn again. A ValidationException, so
 * one catch covers forms and direct store() calls.
 */
class FormValidationException extends ValidationException
{
}
