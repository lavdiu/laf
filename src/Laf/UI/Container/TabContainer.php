<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\Util\Util;

/**
 * Bootstrap tabs: the nav ("{id}_tab_links") with one link per TabItem child and
 * the panes ("{id}_tab_content"). The first tab is active unless one was set
 * active explicitly. Optional Previous / Next buttons (plain JavaScript).
 * Children that are not TabItems are not drawn.
 */
class TabContainer extends GenericContainer implements ComponentInterface
{
    protected string $id = '';

    protected bool $showNavButtons = false;

    /**
     * @param string|null $id DOM id base; empty = generated ("tabs-" + uuid)
     */
    public function __construct(?string $id = '', bool $showNavButtons = false)
    {
        parent::__construct();
        $this->setId($id ?? '');
        $this->showNavButtons = $showNavButtons;
    }

    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        $id = Util::e($this->getId());
        $tabs = $this->getTabs();

        foreach ($this->getComponents() as $component) {
            if ((string)$component->getDrawMode() === '' && (string)$this->getDrawMode() !== '') {
                $component->setDrawMode($this->getDrawMode());
            }
            $component->setFormRowDisplayMode((string)$this->getFormRowDisplayMode());
        }

        /**
         * one tab is active: the one set active, else the first
         */
        $active = false;
        foreach ($tabs as $tab) {
            if ($tab->isActive()) {
                if ($active) {
                    $tab->setActive(false);
                }
                $active = true;
            }
        }
        if (!$active && $tabs !== []) {
            $tabs[0]->setActive(true);
        }

        $html = "<div style='" . Util::e($this->getCssStyleForHtml()) . "' class='" . Util::e($this->getCssClassesForHtml()) . "'" . $this->drawAttributes() . ">";

        $html .= "\n\t\t<ul class='nav nav-tabs' id='{$id}_tab_links' role='tablist'>";
        foreach ($tabs as $tab) {
            $html .= $tab->drawTitle();
        }
        $html .= "\n\t\t</ul>";

        $html .= "\n\t\t<div class='tab-content' id='{$id}_tab_content'>";
        foreach ($tabs as $tab) {
            $html .= "\n\t\t\t" . $tab->draw();
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
     * The TabItem children, in order
     * @return TabItem[]
     */
    public function getTabs(): array
    {
        $tabs = [];
        foreach ($this->getComponents() as $component) {
            if ($component instanceof TabItem) {
                $tabs[] = $component;
            }
        }
        return $tabs;
    }

    /**
     * Makes the tab with this title (or this position, 0-based) the active one
     */
    public function setActiveTab(int|string $tab): static
    {
        foreach ($this->getTabs() as $i => $item) {
            $item->setActive(is_int($tab) ? $i === $tab : $item->getTitle() === $tab);
        }
        return $this;
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

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id DOM id base; empty = generated
     */
    public function setId(string $id): static
    {
        $this->id = $id !== '' ? $id : 'tabs-' . Util::uuid();
        return $this;
    }

    public function isShowNavButtons(): bool
    {
        return $this->showNavButtons;
    }

    public function setShowNavButtons(bool $showNavButtons): static
    {
        $this->showNavButtons = $showNavButtons;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
