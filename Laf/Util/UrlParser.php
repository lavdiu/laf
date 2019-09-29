<?php

namespace Laf\Util;
/**
 * Class Url
 * Parses the pretty-url and breaks it down to main parts
 * module, submodule, action and id: /module/submodule/action/id
 */
class UrlParser
{
	/**
	 * @var UrlParser $instance
	 */
	private static $instance;
	/**
	 * @var string $fullUrl
	 */
	private $fullUrl;
	/**
	 * @var string[] $pieces
	 */
	private $pieces = [];
	/**
	 * @var string $module
	 */
	private $module;
	/**
	 * @var string $submodule
	 */
	private $submodule;

	/**
	 * @var string $action
	 */
	private $action;

	/**
	 * @var int $id
	 */
	private $id;
	/**
	 * If set to true, uses host/module/submodule/id
	 * if set to false, uses host/?module=module&submodule=submodule&id=id
	 * @var bool
	 */
	private $usePrettyUrl = true;


	/**
	 * UrlParser constructor.
	 */
	private function __construct()
	{
		/**
		 * Check if the use_pretty_url is set in settings
		 */
		$settings = Settings::getInstance();
		try {
			$this->usePrettyUrl = (bool) $settings->getProperty('settings.use_pretty_url');
		} catch (\Exception $ex) {
		}

		$this->buildFromUri();
	}

	/**
	 *
	 */
	private function buildFromUri()
	{
		$this->module = 'home';
		$this->submodule = null;
		$this->action = null;
		$this->id = null;

		if (isset($_GET['uriRewrite'])) {
			$this->fullUrl = $_GET['uriRewrite'];
		}

		if (!$this->isUsePrettyUrl()) {
			$this->module = $_GET['module']??($_GET['mod']??'');
			$this->submodule = filter_input(INPUT_GET, 'submodule');
			$this->action = filter_input(INPUT_GET, 'action');
			$this->id = filter_input(INPUT_GET, 'id');
		} else {

			$this->fullUrl = str_replace('?', '', $this->fullUrl);

			if (stripos($this->fullUrl, '/') !== false) {
				$this->pieces = explode('/', $this->fullUrl);

				if (isset($this->pieces[0])) {
					$this->module = $this->pieces[0];
				}
				if (isset($this->pieces[1])) {
					$this->submodule = $this->pieces[1];
				}
				if (isset($this->pieces[2])) {
					$this->action = $this->pieces[2];
				}
				if (isset($this->pieces[3])) {
					$this->id = $this->pieces[3];
				}
			} else if (trim($this->fullUrl) != '') {
				$this->module = $this->fullUrl;
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isUsePrettyUrl(): bool
	{
		return $this->usePrettyUrl;
	}

	/**
	 * @param bool $usePrettyUrl
	 * @return UrlParser
	 */
	public function setUsePrettyUrl(bool $usePrettyUrl): UrlParser
	{
		$this->usePrettyUrl = $usePrettyUrl;
		return $this;
	}

	public static function getFullUri()
	{
		$instance = self::getInstance();
		if ($instance->isUsePrettyUrl())
			return '/' . $instance->getModule() . '/' . $instance->getSubmodule() . '/' . $instance->_getAction() . '/' . $instance->getId() . '/';
		else
			return sprintf('?module=%s&submodule=%s&action=%s&id=%s', $instance->getModule(), $instance->getSubmodule(), $instance->_getAction(), $instance->getId());
	}

	/**
	 * @return UrlParser
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return string
	 */
	public static function getModule()
	{
		$instance = self::getInstance();
		return $instance->_getModule();
	}

	/**
	 * @return string
	 */
	public function _getModule()
	{
		return $this->module;
	}

	/**
	 * @return string
	 */
	public static function getSubmodule()
	{
		$instance = self::getInstance();
		return $instance->_getSubmodule();
	}

	/**
	 * @return string
	 */
	public function _getSubmodule()
	{
		return $this->submodule;
	}

	/**
	 * @return string
	 */
	public function _getAction()
	{
		return $this->action;
	}

	/**
	 * @return int
	 */
	public static function getId()
	{
		$instance = self::getInstance();
		return $instance->_getId();
	}

	/**
	 * @return int
	 */
	public function _getId()
	{
		if (!is_numeric($this->id))
			return null;
		return (int)$this->id;
	}

	/**
	 * @return string
	 */
	public static function getAction()
	{
		$instance = self::getInstance();
		return $instance->_getAction();
	}

	/**
	 * Redirects to the View page for the same module & id
	 * @param int $id
	 */
	public static function redirectToViewPage($id = null)
	{

		header("location:" . static::getViewLink($id));
		exit;

	}

	/**
	 * @param int $id
	 * @return string
	 */
	public static function getViewLink($id = null)
	{
		$instance = self::getInstance();
		if (!$instance->isUsePrettyUrl()) {
			$url = '?module=%s&submodule=%s&action=%s&id=%s';
		} else {
			$url = '/%s/%s/%s/%s';
		}
		return sprintf($url, $instance->_getModule(), $instance->_getSubmodule(), 'view', (is_numeric($id) ? $id : $instance->_getId()));
	}

	/**
	 * Redirects to the List Page for the same module
	 */
	public static function redirectToListPage()
	{
		header("location:" . static::getListLink());
		exit;
	}

	/**
	 * @return string
	 */
	public static function getListLink()
	{
		$instance = self::getInstance();
		if (!$instance->isUsePrettyUrl()) {
			$url = '?module=%s&submodule=%s&action=%s&id=%s';
		} else {
			$url = '/%s/%s/%s/%s';
		}
		return sprintf($url, $instance->_getModule(), $instance->_getSubmodule(), 'list', 'all');
	}

	/**
	 * @return string
	 */
	public static function getNewLink()
	{
		$instance = self::getInstance();
		if (!$instance->isUsePrettyUrl()) {
			$url = '?module=%s&submodule=%s&action=%s';
		} else {
			$url = '/%s/%s/%s';
		}
		return sprintf($url, $instance->_getModule(), $instance->_getSubmodule(), 'new');
	}

	/**
	 * @return string
	 */
	public static function getInsertLink()
	{
		$instance = self::getInstance();
		if (!$instance->isUsePrettyUrl()) {
			$url = '?module=%s&submodule=%s&action=%s';
		} else {
			$url = '/%s/%s/%s';
		}
		return sprintf($url, $instance->_getModule(), $instance->_getSubmodule(), 'new');
	}

	/**
	 * @param null $id
	 * @return string
	 */
	public static function getUpdateLink($id = null)
	{
		$instance = self::getInstance();
		if (!$instance->isUsePrettyUrl()) {
			$url = '?module=%s&submodule=%s&action=%s&id=%s';
		} else {
			$url = '/%s/%s/%s/%s';
		}
		return sprintf($url, $instance->_getModule(), $instance->_getSubmodule(), 'update', (is_numeric($id) ? $id : $instance->_getId()));
	}

	/**
	 * @param $id
	 * @return string
	 */
	public static function getDeleteLink($id = null)
	{
		$instance = self::getInstance();
		if (!$instance->isUsePrettyUrl()) {
			$url = '?module=%s&submodule=%s&action=%s&id=%s';
		} else {
			$url = '/%s/%s/%s/%s';
		}
		return sprintf($url, $instance->_getModule(), $instance->_getSubmodule(), 'delete', (is_numeric($id) ? $id : $instance->_getId()));
	}


}