<?php


namespace Laf\UI\Page;


class Html
{
	protected $htmlHeader = "";
	protected $cssFiles = [];
	protected $jsFiles = [];
	protected $inlineCss = "";
	protected $inlineJs = "";
	protected $pageTitle = "";
	protected $components = [];
	protected $menu = "";

	/**
	 * @return string
	 */
	public function getHtmlHeader(): string
	{
		return $this->htmlHeader;
	}

	/**
	 * @param string $htmlHeader
	 * @return Html
	 */
	public function setHtmlHeader(string $htmlHeader): Html
	{
		$this->htmlHeader = $htmlHeader;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getCssFiles(): array
	{
		return $this->cssFiles;
	}

	/**
	 * @param array $cssFiles
	 * @return Html
	 */
	public function setCssFiles(array $cssFiles): Html
	{
		$this->cssFiles = $cssFiles;
		return $this;
	}

	/**
	 * @param string $file
	 * @return Html
	 */
	public function addCssFile(string $file): Html
	{
		$this->cssFiles[] = $file;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getJsFiles(): array
	{
		return $this->jsFiles;
	}

	/**
	 * @param array $jsFiles
	 * @return Html
	 */
	public function setJsFiles(array $jsFiles): Html
	{
		$this->jsFiles = $jsFiles;
		return $this;
	}

	/**
	 * @param string $file
	 * @return Html
	 */
	public function addJsFile(string $file): Html
	{
		$this->jsFiles[] = $file;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getInlineCss(): string
	{
		return $this->inlineCss;
	}

	/**
	 * @param string $inlineCss
	 * @return Html
	 */
	public function setInlineCss(string $inlineCss): Html
	{
		$this->inlineCss = $inlineCss;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getInlineJs(): string
	{
		return $this->inlineJs;
	}

	/**
	 * @param string $inlineJs
	 * @return Html
	 */
	public function setInlineJs(string $inlineJs): Html
	{
		$this->inlineJs = $inlineJs;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPageTitle(): string
	{
		return $this->pageTitle;
	}

	/**
	 * @param string $pageTitle
	 * @return Html
	 */
	public function setPageTitle(string $pageTitle): Html
	{
		$this->pageTitle = $pageTitle;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getComponents(): array
	{
		return $this->components;
	}

	/**
	 * @param array $components
	 * @return Html
	 */
	public function setComponents(array $components): Html
	{
		$this->components = $components;
		return $this;
	}

	/**
	 * @param $component
	 * @return Html
	 */
	public function addComponent($component): Html
	{
		$this->components[] = $component;
		return $this;
	}

	public function draw(): string
	{
		$html = "<!DOCTYPE html>
<html lang=\"en\">
<head>
	<meta charset='utf-8'>
	<meta http-equiv='X-UA-Compatible' content='IE=edge'>
	<meta name='viewport' content='width=device-width, initial-scale=1'>
	<title>{$this->getPageTitle()}</title>
";

		foreach ($this->getJsFiles() as $file) {
			$html .= "\n\t<script type='text/javascript' src='{$file}'></script>";
		}
		foreach ($this->getCssFiles() as $file) {
			$html .= "\n\t< rel='stylesheet' href='{$file}' >";
		}

		if ($this->getInlineCss() != '') {
			echo "\n\t<style type'text/css'>{$this->getInlineCss()}</style>";
		}
		if ($this->getInlineJs() != '') {
			echo "\n\t<script type='text/javascript'>{$this->getInlineJs()}</script>";
		}
		$html .= $this->getHtmlHeader();
		$html .= "\n</head>";

		$html .= "\n\n<body>";
		$html .= $this->getMenu();
		foreach ($this->getComponents() as $component) {
			$html .= $component->draw();
		}

		$html .= "\n</body>\n</html>";
		return $html;
	}

	/**
	 * @return string
	 */
	public function getMenu(): string
	{
		return $this->menu;
	}

	/**
	 * @param string $menu
	 * @return Html
	 */
	public function setMenu(string $menu): Html
	{
		$this->menu = $menu;
		return $this;
	}

}