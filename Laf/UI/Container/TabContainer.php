<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;

class TabContainer extends GenericContainer implements ComponentInterface
{
	/**
	 * @var string
	 */
	protected $id = "";

	/**
	 * @var bool
	 */
	protected $showNavButtons = false;


	public function __construct(?string $id = "", bool $showNavButtons = false)
	{
		$this->id = $id;
		$this->showNavButtons = $showNavButtons;
	}

	public function draw(): ?string
	{
		$this->addCssClass(static::getComponentCssControlClass());

		$html = "<div
			style='{$this->getCssStyleForHtml()}' 
			class='{$this->getCssClassesForHtml()}'
		>";

		/**
		 * Drawing the tab buttons
		 */
		$html .= "\n\t\t<ul class='nav nav-tabs' id='{$this->getId()}_tab_links' role='tablist'>";
		foreach ($this->getComponents() as $key => $component) {
            if ($component->getDrawMode() == ''){
                $component->setDrawMode($this->getDrawMode());
            }
			$component->setFormRowDisplayMode($this->getFormRowDisplayMode());
			if (!($component instanceof TabItem)) {
				continue;
			}
			/**
			 * Setting the first tab as active
			 */
			if ($key === array_key_first($this->getComponents())) {
				$component->setActive(true);
			}

			$html .= $component->drawTitle();
		}
		$html .= "\n\t\t</ul>";


		/**
		 * Draw content of the tab
		 */
		$html .= "\n\t\t<div class='tab-content' id='{$this->getId()}_tab_content'>";
		foreach ($this->getComponents() as $component) {
			if (!($component instanceof TabItem)) {
				continue;
			}
			$html .= "\n\t\t\t" . $component->draw();
		}
		$html .= "\n\t\t</div>";

		if ($this->isShowNavButtons()) {
			$html .= "
            <div class='text-right'>
                <a href='javascript:;' class='btn btn-outline-success' onclick=\"$('#{$this->getId()}_tab_links > .nav-item > .active').parent().prev('li').find('a').trigger('click');window.scroll({top:0,left:0,behavior:'smooth'});\"><i class='fa fa-arrow-alt-circle-left'> </i> Previous</a>
                <a href='javascript:;' class='btn btn-outline-success' onclick=\"$('#{$this->getId()}_tab_links > .nav-item > .active').parent().next('li').find('a').trigger('click');window.scroll({top:0,left:0,behavior:'smooth'});\"><i class='fa fa-arrow-alt-circle-right'> </i> Next</a>
            </div>
            ";
		}
		$html .= "\n</div>";
		return $html;
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 * @return TabContainer
	 */
	public function setId(string $id): TabContainer
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isShowNavButtons(): bool
	{
		return $this->showNavButtons;
	}

	/**
	 * @param bool $showNavButtons
	 * @return TabContainer
	 */
	public function setShowNavButtons(bool $showNavButtons): TabContainer
	{
		$this->showNavButtons = $showNavButtons;
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
