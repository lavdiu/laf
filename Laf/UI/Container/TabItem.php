<?php


namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

class TabItem extends GenericContainer implements ComponentInterface
{
	/**
	 * @var bool
	 */
	public $active = false;
	/**
	 * @var string
	 */
	protected $title = "";

	/**
	 * @var string
	 */
	protected $icon = "";

	/**
	 * TabItem constructor.
	 * @param bool $active
	 * @param string $title
	 * @param string $icon
	 */
	public function __construct(string $title = "", string $icon = "", bool $active = false)
	{
		$this->active = $active;
		$this->title = $title;
		$this->icon = $icon;
	}


	public function draw(): ?string
	{
		$this->addCssClass(static::getComponentCssControlClass());
		$active = '';
		if ($this->isActive()) $active = ' active';

		$html = "\n\t\t\t<div class='tab-pane fade show{$active}' id='{$this->getTitleNoSpaces()}-content' role='tabpanel' aria-labelledby='{$this->getId()}'>";
		$html .= "\n\t\t\t\t<div class='container-fluid py-4'>";
		foreach ($this->getComponents() as $component) {
            if ($component->getDrawMode() == ''){
                $component->setDrawMode($this->getDrawMode());
            }
			$component->setFormRowDisplayMode($this->getFormRowDisplayMode());
			$html .= "\n\t\t\t\t\t" . $component->draw();
		}
		$html .= "\n\t\t\t\t</div>";
		$html .= "\n\t\t\t</div>";
		return $html;
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @param bool $active
	 * @return TabItem
	 */
	public function setActive(bool $active): TabItem
	{
		$this->active = $active;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitleNoSpaces(): string
	{
		return preg_replace('/[^\da-z]/i', '', $this->title);
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return "{$this->getTitleNoSpaces()}-tab";
	}

	public function drawTitle()
	{
		$active = '';
		if ($this->isActive()) $active = ' active';
		return "\n\t\t\t<li class='nav-item'><a class='nav-link{$active}' 
			id='{$this->getTitleNoSpaces()}-tab' 
			data-toggle='tab' 
			href='#{$this->getTitleNoSpaces()}-content' 
			role='tab' 
			style='text-decoration:none;color:#000000'
			aria-controls='{$this->getTitleNoSpaces()}-content'>{$this->drawIcon()}{$this->getTitle()}</a></li>";
	}

	/**
	 * @return string
	 */
	private function drawIcon()
	{
		$html = "";
		if ($this->icon != '') {
			$html = "<i class='{$this->getIcon()}'></i> ";
		}
		return $html;
	}

	/**
	 * @return string
	 */
	public function getIcon(): string
	{
		return $this->icon;
	}

	/**
	 * @param string $icon
	 * @return TabItem
	 */
	public function setIcon(string $icon): TabItem
	{
		$this->icon = $icon;
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
	 * @return TabItem
	 */
	public function setTitle(string $title): TabItem
	{
		$this->title = $title;
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
