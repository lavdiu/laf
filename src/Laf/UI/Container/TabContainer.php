<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;
use Laf\Util\Util;

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
        if (strlen($this->id) < 1) {
            $this->id = Util::uuid();
        }
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
            if ($component->getDrawMode() == '' && $this->getDrawMode() != '') {
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
            <div class='text-end'>
                <a href='javascript:;' class='btn btn-outline-success' onclick=\"" . Util::attr(self::navigationScript($this->getId(), 'previous')) . "\"><i class='fa fa-arrow-alt-circle-left'> </i> Previous</a>
                <a href='javascript:;' class='btn btn-outline-success' onclick=\"" . Util::attr(self::navigationScript($this->getId(), 'next')) . "\"><i class='fa fa-arrow-alt-circle-right'> </i> Next</a>
            </div>
            ";
        }
        $html .= "\n</div>";
        return $html;
    }

    /**
     * Plain-JavaScript click handler that activates the previous or next tab of the
     * container whose id is given ("{id}_tab_links" holds the tab links) and scrolls to the top.
     * @param string $direction 'previous' or 'next'
     */
    public static function navigationScript(string $containerId, string $direction): string
    {
        $sibling = $direction === 'next' ? 'nextElementSibling' : 'previousElementSibling';
        $selector = json_encode('#' . $containerId . '_tab_links > .nav-item > .active', JSON_UNESCAPED_SLASHES);
        return "(function(){var a=document.querySelector({$selector});"
            . "var li=a?a.closest('li'):null;var s=li?li.{$sibling}:null;var t=s?s.querySelector('a'):null;"
            . "if(t){t.click();window.scroll({top:0,left:0,behavior:'smooth'});}})();";
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
