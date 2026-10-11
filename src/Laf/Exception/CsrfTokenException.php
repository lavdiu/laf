<?php

declare(strict_types=1);

namespace Laf\Exception;

/**
 * Thrown by Form::processForm() when the CSRF token is missing or does not match the session.
 */
class CsrfTokenException extends \Exception
{
}
