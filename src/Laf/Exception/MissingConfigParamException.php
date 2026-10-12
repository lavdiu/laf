<?php

namespace Laf\Exception;

/**
 * Settings::get() / getProperty() was asked for a key that is not configured. Use
 * Settings::has(), getBool(), getString() or getInt() for optional keys.
 */
class MissingConfigParamException extends \Exception
{
}
