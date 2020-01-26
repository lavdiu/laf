<?php

namespace Laf\UI\Page;

/**
 * Class Html
 * @package Laf\UI\Page
 */
class Html
{
	protected $htmlHeader = "";
	/**
	 * @var HeaderComponent[]
	 */
	protected $headerComponents = [];
	protected $pageTitle = "";
	protected $components = [];
	protected $menu = "";

	/**
	 * @return HeaderComponent[]
	 */
	public function getHeaderComponents(): array
	{
		return $this->headerComponents;
	}

	/**
	 * @param HeaderComponent[] $headerComponents
	 * @return Html
	 */
	public function setHeaderComponents(array $headerComponents): Html
	{
		$this->headerComponents = $headerComponents;
		return $this;
	}

	/**
	 * @param HeaderComponent $h
	 * @return Html
	 */
	public function addHeaderComponent(HeaderComponent $h): Html
	{
		$this->headerComponents[] = $h;
		return $this;
	}

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
	<link rel='icon' href='data:;base64,='>
	<title>{$this->getPageTitle()}</title>
";

		foreach ($this->getHeaderComponents() as $hc) {
			$html .= "<" . $hc->getTagName();
			foreach ($hc->getAttributes() as $ak => $av) {
				$html .= " " . $ak . '="' . $av . '"';
			}
			if ($hc->isSelfClosingTag()) {
				$html .= " />\n";
			} else {
				$html .= ">" . $hc->getContent();
				$html .= "</" . $hc->getTagName() . ">\n";
			}
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