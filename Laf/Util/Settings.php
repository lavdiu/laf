<?php

namespace Laf\Util;

use Exception;
use Laf\Exception\MissingConfigParamException;

class Settings
{
	private static $instance;
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
	public static function getInstance()
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
	 */
	public function setProperty($key, $value)
	{
		$this->properties[$key] = $value;
	}

	/**
	 * Get property
	 * @param $key
	 * @return mixed
	 * @throws Exception
	 */
	public function getProperty($key)
	{
		if (array_key_exists($key, $this->properties)) {
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
		return false;
	}

	/**
	 * Load settings from file
	 * @param $file
	 * @return bool
	 */
	public function loadFromFile($file)
	{
		return false;
	}
}