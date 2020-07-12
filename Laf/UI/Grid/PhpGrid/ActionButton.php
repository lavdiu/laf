<?php


namespace Laf\UI\Grid\PhpGrid;


class ActionButton
{
    /**
     * @var string
     */
    public $label = null;

    /**
     * @var string
     */
    public $href = null;

    /**
     * @var string
     */
    public $icon = null;

    /**
     * ActionButton constructor.
     * @param string|null $label
     * @param string|null $href
     * @param string|null $icon
     */
    public function __construct(?string $label, ?string $href, ?string $icon)
    {
        $this->label = $label;
        $this->href = $href;
        $this->icon = $icon;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     * @return ActionButton
     */
    public function setLabel(string $label): ActionButton
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return string
     */
    public function getHref(): string
    {
        return $this->href;
    }

    /**
     * @param string $href
     * @return ActionButton
     */
    public function setHref(string $href): ActionButton
    {
        $this->href = $href;
        return $this;
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
     * @return ActionButton
     */
    public function setIcon(string $icon): ActionButton
    {
        $this->icon = $icon;
        return $this;
    }
}