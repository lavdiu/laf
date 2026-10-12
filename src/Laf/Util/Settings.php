<?php

namespace Laf\Util;

use Laf\Exception\MissingConfigParamException;

/**
 * Process-wide configuration: a flat key => value map (keys like 'database.hostname').
 *
 * get() throws MissingConfigParamException for an unknown key; has() and getBool()
 * are the forgiving readers for optional keys.
 */
class Settings
{
    /**
     * @var Settings|null
     */
    private static $instance;
    /**
     * @var array
     */
    private $properties = [];

    /**
     * Settings Class constructor.
     */
    private function __construct()
    {

    }

    /**
     * Get Settings class instance
     * @return Settings
     */
    public static function getInstance(): Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set property
     * @param $key
     * @param $value
     * @return void
     */
    public function setProperty($key, $value): void
    {
        $this->properties[$key] = $value;
    }

    /**
     * Get property
     * @param $key
     * @return mixed
     * @throws MissingConfigParamException
     */
    public function getProperty($key)
    {
        if ($this->propertyExists($key)) {
            return $this->properties[$key];
        } else {
            throw new MissingConfigParamException(sprintf("Error: Setting %s doesnt exist", $key));
        }
    }

    /**
     * @param $key
     * @return bool
     */
    public function propertyExists($key)
    {
        return array_key_exists($key, $this->properties);
    }

    /**
     * Save settings to file
     * @return bool
     */
    public function saveToFile()
    {
        #@TODO implement storing to ini/php files
        return false;
    }

    /**
     * Load settings from file
     * @param $file
     * @return bool
     */
    public function loadFromFile($file)
    {
        #@TODO implement parsing from ini/php files
        return false;
    }

    /**
     * @param $key
     * @return mixed
     * @throws MissingConfigParamException
     */
    public static function get($key)
    {
        return self::getInstance()->getProperty($key);
    }

    /**
     * Whether a key is configured (a null value counts as configured)
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->propertyExists($key);
    }

    /**
     * A boolean setting: true/false, 1/0, "1"/"0", "yes"/"no", "on"/"off" are accepted;
     * $default when the key is missing or the value is not a recognised boolean.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        if (!self::has($key)) {
            return $default;
        }
        $value = self::getInstance()->getProperty($key);
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || is_array($value) || is_object($value)) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::getInstance()->setProperty($key, $value);
    }
}
