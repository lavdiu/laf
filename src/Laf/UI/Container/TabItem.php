<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\Util\Util;

/**
 * One tab of a TabContainer: the link is drawn by drawTitle() in the container's
 * nav, the pane by draw(). DOM ids derive from the title; setIdPrefix() keeps two
 * tabs with the same title on one page apart.
 */
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

    /** Prefix of the pane and link ids (letters, digits, _ and -) */
    protected string $idPrefix = '';

    /**
     * TabItem constructor.
     * @param string $title
     * @param string $icon
     * @param bool $active
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
        $active = $this->isActive() ? ' active' : '';

        $html = "\n\t\t\t<div class='tab-pane fade show{$active}' id='{$this->getContentId()}' role='tabpanel' aria-labelledby='{$this->getId()}'>";
        $html .= "\n\t\t\t\t<div class='container-fluid py-4'>";
        foreach ($this->getComponents() as $component) {
            if ($component->getDrawMode() == '' && $this->getDrawMode() != '') {
                $component->setDrawMode($this->getDrawMode());
            }
            $component->setFormRowDisplayMode((string)$this->getFormRowDisplayMode());
            $html .= "\n\t\t\t\t\t" . $component->draw();
        }
        $html .= "\n\t\t\t\t</div>";
        $html .= "\n\t\t\t</div>";
        return $html;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): TabItem
    {
        $this->active = $active;
        return $this;
    }

    /**
     * The title reduced to letters and digits (the base of the DOM ids)
     */
    public function getTitleNoSpaces(): string
    {
        return (string)preg_replace('/[^\da-z]/i', '', $this->title);
    }

    /**
     * Prefix of the DOM ids, e.g. the TabContainer id, so equal titles on one page stay distinct
     */
    public function setIdPrefix(string $prefix): TabItem
    {
        $prefix = (string)preg_replace('/[^\da-z_-]/i', '', $prefix);
        $this->idPrefix = $prefix === '' ? '' : $prefix . '_';
        return $this;
    }

    public function getIdPrefix(): string
    {
        return $this->idPrefix;
    }

    /**
     * Id of the nav link
     */
    public function getId(): string
    {
        return $this->idPrefix . $this->getTitleNoSpaces() . '-tab';
    }

    /**
     * Id of the pane
     */
    public function getContentId(): string
    {
        return $this->idPrefix . $this->getTitleNoSpaces() . '-content';
    }

    public function drawTitle(): string
    {
        $active = $this->isActive() ? ' active' : '';
        return "\n\t\t\t<li class='nav-item'><a class='nav-link{$active}'
			id='{$this->getId()}'
			data-toggle='tab'
			data-bs-toggle='tab'
			href='#{$this->getContentId()}'
			role='tab'
			style='text-decoration:none;color:#000000'
			aria-controls='{$this->getContentId()}'>{$this->drawIcon()}" . Util::e($this->getTitle()) . "</a></li>";
    }

    private function drawIcon(): string
    {
        if ($this->icon != '') {
            return "<i class='" . Util::attr($this->getIcon()) . "'></i> ";
        }
        return '';
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): TabItem
    {
        $this->icon = $icon;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): TabItem
    {
        $this->title = $title;
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
