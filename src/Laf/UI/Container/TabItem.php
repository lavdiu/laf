<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\Util\Util;

/**
 * One tab of a TabContainer: the link is drawn by drawTitle() in the container's
 * nav, the pane by draw(). DOM ids derive from the title; setIdPrefix() keeps two
 * tabs with the same title on one page apart. The title is escaped; the icon is
 * a css class.
 */
class TabItem extends GenericContainer implements ComponentInterface
{
    /** Kept public for compatibility; prefer isActive()/setActive() */
    public bool $active = false;

    protected string $title = '';

    protected string $icon = '';

    /** Prefix of the pane and link ids (letters, digits, _ and -) */
    protected string $idPrefix = '';

    public function __construct(string $title = '', string $icon = '', bool $active = false)
    {
        parent::__construct();
        $this->active = $active;
        $this->title = $title;
        $this->icon = $icon;
    }

    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        $active = $this->isActive() ? ' active' : '';

        $html = "\n\t\t\t<div class='tab-pane fade show{$active}' id='" . Util::e($this->getContentId()) . "' role='tabpanel' aria-labelledby='" . Util::e($this->getId()) . "'" . $this->drawAttributes() . ">";
        $html .= "\n\t\t\t\t<div class='container-fluid py-4'>";
        foreach ($this->getComponents() as $component) {
            if ((string)$component->getDrawMode() === '' && (string)$this->getDrawMode() !== '') {
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

    public function setActive(bool $active): static
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
    public function setIdPrefix(string $prefix): static
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

    /**
     * The nav link of this tab (Bootstrap 4 and 5 data attributes)
     */
    public function drawTitle(): string
    {
        $active = $this->isActive() ? ' active' : '';
        $contentId = Util::e($this->getContentId());
        return "\n\t\t\t<li class='nav-item'><a class='nav-link{$active}'
			id='" . Util::e($this->getId()) . "'
			data-toggle='tab'
			data-bs-toggle='tab'
			href='#{$contentId}'
			role='tab'
			style='text-decoration:none;color:#000000'
			aria-controls='{$contentId}'>{$this->drawIcon()}" . Util::e($this->getTitle()) . "</a></li>";
    }

    private function drawIcon(): string
    {
        if ($this->icon !== '') {
            return "<i class='" . Util::attr($this->getIcon()) . "'></i> ";
        }
        return '';
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
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
