<?php

use Laf\Util\UrlParser;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

class UrlParserTest extends TestCase
{
	public function setUp(): void
	{
		// Reset the singleton between tests
		$ref = new \ReflectionClass(UrlParser::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	/** @test */
	public function parses_pretty_url_with_all_segments()
	{
		$_GET['uriRewrite'] = 'admin/users/view/42';
		$instance = UrlParser::getInstance();

		$this->assertSame('admin', UrlParser::getModule());
		$this->assertSame('users', UrlParser::getSubmodule());
		$this->assertSame('view', UrlParser::getAction());
		$this->assertSame(42, UrlParser::getId());
	}

	/** @test */
	public function parses_url_with_only_module()
	{
		$_GET['uriRewrite'] = 'dashboard';
		$instance = UrlParser::getInstance();

		$this->assertSame('dashboard', UrlParser::getModule());
		$this->assertNull(UrlParser::getSubmodule());
		$this->assertNull(UrlParser::getAction());
		$this->assertNull(UrlParser::getId());
	}

	/** @test */
	public function parses_url_with_module_and_submodule()
	{
		$_GET['uriRewrite'] = 'admin/settings';
		$instance = UrlParser::getInstance();

		$this->assertSame('admin', UrlParser::getModule());
		$this->assertSame('settings', UrlParser::getSubmodule());
		$this->assertNull(UrlParser::getAction());
		$this->assertNull(UrlParser::getId());
	}

	/** @test */
	public function defaults_to_home_module_when_empty()
	{
		unset($_GET['uriRewrite']);
		$instance = UrlParser::getInstance();

		$this->assertSame('home', UrlParser::getModule());
	}

	/** @test */
	public function non_numeric_id_returns_null()
	{
		$_GET['uriRewrite'] = 'admin/users/view/abc';
		$instance = UrlParser::getInstance();

		$this->assertNull(UrlParser::getId());
	}

	/** @test */
	public function get_view_link_pretty_url()
	{
		$_GET['uriRewrite'] = 'admin/users/list/all';
		$instance = UrlParser::getInstance();

		$link = UrlParser::getViewLink(99);
		$this->assertSame('/admin/users/view/99', $link);
	}

	/** @test */
	public function get_list_link_pretty_url()
	{
		$_GET['uriRewrite'] = 'admin/users/view/1';
		$instance = UrlParser::getInstance();

		$link = UrlParser::getListLink();
		$this->assertSame('/admin/users/list/all', $link);
	}

	/** @test */
	public function get_new_link_pretty_url()
	{
		$_GET['uriRewrite'] = 'admin/users/list/all';
		$instance = UrlParser::getInstance();

		$link = UrlParser::getNewLink();
		$this->assertSame('/admin/users/new', $link);
	}

	/** @test */
	public function get_update_link_pretty_url()
	{
		$_GET['uriRewrite'] = 'admin/users/view/5';
		$instance = UrlParser::getInstance();

		$link = UrlParser::getUpdateLink(5);
		$this->assertSame('/admin/users/update/5', $link);
	}

	/** @test */
	public function get_delete_link_pretty_url()
	{
		$_GET['uriRewrite'] = 'admin/users/view/5';
		$instance = UrlParser::getInstance();

		$link = UrlParser::getDeleteLink(5);
		$this->assertSame('/admin/users/delete/5', $link);
	}

	/** @test */
	public function non_pretty_url_mode()
	{
		Settings::set('settings.use_pretty_url', false);

		$_GET['module'] = 'admin';
		$_GET['submodule'] = 'users';
		$_GET['action'] = 'view';
		$_GET['id'] = '10';
		unset($_GET['uriRewrite']);

		$instance = UrlParser::getInstance();

		$this->assertSame('admin', UrlParser::getModule());
		$this->assertSame('users', UrlParser::getSubmodule());
		$this->assertSame('view', UrlParser::getAction());
		$this->assertSame(10, UrlParser::getId());

		// Reset to default
		Settings::set('settings.use_pretty_url', true);
	}

	/** @test */
	public function get_view_link_non_pretty_url()
	{
		Settings::set('settings.use_pretty_url', false);

		$_GET['module'] = 'admin';
		$_GET['submodule'] = 'users';
		$_GET['action'] = 'list';
		$_GET['id'] = '';
		unset($_GET['uriRewrite']);

		$instance = UrlParser::getInstance();

		$link = UrlParser::getViewLink(42);
		$this->assertSame('?module=admin&submodule=users&action=view&id=42', $link);

		Settings::set('settings.use_pretty_url', true);
	}

	/** @test */
	public function get_full_uri_pretty_url()
	{
		$_GET['uriRewrite'] = 'admin/users/view/42';
		$instance = UrlParser::getInstance();

		$uri = UrlParser::getFullUri();
		$this->assertSame('/admin/users/view/42/', $uri);
	}

	/** @test */
	public function url_with_question_mark_stripped()
	{
		$_GET['uriRewrite'] = '?admin/users/view/1';
		$instance = UrlParser::getInstance();

		$this->assertSame('admin', UrlParser::getModule());
		$this->assertSame('users', UrlParser::getSubmodule());
	}

	public function tearDown(): void
	{
		unset($_GET['uriRewrite'], $_GET['module'], $_GET['submodule'], $_GET['action'], $_GET['id'], $_GET['mod']);
	}
}
