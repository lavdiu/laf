<?php

namespace Laf\Util;

use Laf\Exception\MissingConfigParamException;

class Settings
{
    /**
     * @var Settings
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
        if (!self::$instance) {
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
     * @return string|null
     * @throws MissingConfigParamException
     */
    public function getProperty($key): ?string
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
     * @return string|null
     * @throws MissingConfigParamException
     */
    public static function get($key): ?string
    {
        return self::getInstance()->getProperty($key);
    }


    /**
     * @param string $key
     * @param string|null $value
     */
    public static function set(string $key, ?string $value): void
    {
        self::getInstance()->setProperty($key, $value);
    }
}