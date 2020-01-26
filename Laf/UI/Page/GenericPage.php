<?php

namespace Laf\UI\Page;


use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Settings;

/**
 * Class GenericPage
 * @package Laf\UI\Page
 */
class GenericPage implements ComponentInterface
{
	use ComponentTrait;

	/**
	 * @var array
	 */
	protected $links = [];
	protected $title = "";
	protected $titleIcon = "";
	protected $enabled = true;
	protected $header = "";
	protected $footer = "";
	protected $notification = "";

	/**
	 * @param $link
	 * @return GenericPage
	 */
	public function addLink($link): GenericPage
	{
		$this->links[] = $link;
		return $this;
	}

	/**
	 * @return GenericPage
	 */
	public function enable(): GenericPage
	{
		$this->enabled = true;
	}

	/**
	 * @return GenericPage
	 */
	public function disable(): GenericPage
	{
		$this->enabled = false;
	}

	/**
	 * @return string
	 */
	public function draw(): string
	{
		if (!$this->isEnabled())
			return "";

		$header = "";

		if ($this->hasLinks() || get_class($this) == 'Laf\UI\Page\AdminPage') {
			$icon = $this->getTitleIcon() != "" ? "<i class='{$this->getTitleIcon()}'></i>" : "";
			$links = "";
			foreach ($this->getLinks() as $link) {
				$links .= $link->draw();
			}

			$this->addCssClass($this->getContainerType())
				->addCssClass('pb-5')
				->addCssClass($this->getComponentCssControlClass());

			$header = "
        <div class='{$this->getCssClassesForHtml()}' style='{$this->getCssStyleForHtml()}'>
            <nav class='navbar navbar-expand navbar-light bg-light <!--sticky-top-->'>
                <div class=''>{$icon} <span class='navbar-brand'>{$this->getTitle()}</span></div>
                <ul class='navbar-nav mr-auto'></ul>
                <nav class='navbar-nav navbar-right btn-group'>
                    {$links}
                </nav>
            </nav> 
        ";
		}

		$html = $this->getHeader();
		$html .= $header;
		$html .= $this->getNotification();

		foreach ($this->getComponents() as $component) {

			$html .= $component->draw();
		}
		if ($this->hasLinks() || get_class($this) == 'Laf\UI\Page\AdminPage') {
			$html .= "</div>";
		}
		$html .= $this->getFooter();
		return $html;
	}

	/**
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * @return bool
	 */
	public function hasLinks()
	{
		return sizeof($this->links) > 0;
	}

	/**
	 * @return string
	 */
	public function getTitleIcon(): string
	{
		return $this->titleIcon;
	}

	/**
	 * @param string $titleIcon
	 */
	public function setTitleIcon(?string $titleIcon): void
	{
		$this->titleIcon = $titleIcon;
	}

	/**
	 * @return array
	 */
	public function getLinks(): array
	{
		return $this->links;
	}

	/**
	 * @param array $links
	 * @return GenericPage
	 */
	public function setLinks(array $links): GenericPage
	{
		$this->links = $links;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 * @return GenericPage
	 */
	public function setTitle(?string $title): GenericPage
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHeader(): string
	{
		return $this->header;
	}

	/**
	 * @param string $header
	 * @return GenericPage
	 */
	public function setHeader(string $header): GenericPage
	{
		$this->header = $header;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getNotification(): string
	{
		return $this->notification;
	}

	/**
	 * @param string $notification
	 * @return GenericPage
	 */
	public function setNotification(string $notification): GenericPage
	{
		$this->notification = $notification;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFooter(): string
	{
		return $this->footer;
	}

	/**
	 * @param string $footer
	 * @return GenericPage
	 */
	public function setFooter(string $footer): GenericPage
	{
		$this->footer = $footer;
		return $this;
	}

	/**
	 * Returns the CSS class unique to the UI component
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}
}