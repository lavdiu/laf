<?php


namespace Laf\UI\Grid\PhpGrid;

/**
 * Class ActionButton
 * @package Laf\UI\Grid\PhpGrid
 */
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
     * @var array
     */
    public $attributes = [];

    /**
     * ActionButton constructor.
     * @param string $label
     * @param string $href
     * @param string $icon
     */
    public function __construct(string $label, string $href, ?string $icon = null)
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

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     * @return ActionButton
     */
    public function setAttributes(array $attributes): ActionButton
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function addAttribute(string $attribute, string $value): ActionButton
    {
        $this->attributes[$attribute] = $value;
        return $this;
    }



}