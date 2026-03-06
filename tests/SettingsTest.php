<?php

use Laf\Util\Settings;
use Laf\Exception\MissingConfigParamException;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
	public function setUp(): void
	{
		// Reset singleton by setting properties fresh each test
		Settings::set('test.key', 'test_value');
	}

	/** @test */
	public function set_and_get_property()
	{
		Settings::set('my.key', 'my_value');
		$this->assertSame('my_value', Settings::get('my.key'));
	}

	/** @test */
	public function get_nonexistent_property_throws_exception()
	{
		$this->expectException(MissingConfigParamException::class);
		Settings::get('nonexistent.key.' . uniqid());
	}

	/** @test */
	public function overwrite_existing_property()
	{
		Settings::set('overwrite.key', 'first');
		Settings::set('overwrite.key', 'second');
		$this->assertSame('second', Settings::get('overwrite.key'));
	}

	/** @test */
	public function property_exists_check()
	{
		$settings = Settings::getInstance();
		Settings::set('exists.key', 'value');
		$this->assertTrue($settings->propertyExists('exists.key'));
		$this->assertFalse($settings->propertyExists('does.not.exist.' . uniqid()));
	}

	/** @test */
	public function get_instance_returns_singleton()
	{
		$a = Settings::getInstance();
		$b = Settings::getInstance();
		$this->assertSame($a, $b);
	}

	/** @test */
	public function set_various_value_types()
	{
		Settings::set('type.int', 42);
		Settings::set('type.bool', true);
		Settings::set('type.array', ['a', 'b']);
		Settings::set('type.null', null);

		$this->assertSame(42, Settings::get('type.int'));
		$this->assertTrue(Settings::get('type.bool'));
		$this->assertSame(['a', 'b'], Settings::get('type.array'));
		$this->assertNull(Settings::get('type.null'));
	}

	/** @test */
	public function instance_method_set_and_get()
	{
		$settings = Settings::getInstance();
		$settings->setProperty('instance.key', 'instance_value');
		$this->assertSame('instance_value', $settings->getProperty('instance.key'));
	}

	/** @test */
	public function save_to_file_returns_false()
	{
		$settings = Settings::getInstance();
		$this->assertFalse($settings->saveToFile());
	}

	/** @test */
	public function load_from_file_returns_false()
	{
		$settings = Settings::getInstance();
		$this->assertFalse($settings->loadFromFile('any_file'));
	}
}
