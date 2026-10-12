<?php

namespace Laf\UI\Grid\PhpGrid;

use JsonSerializable;

/**
 * One entry of the per-row action menu of a PhpGrid. The href is a template: {column} is replaced by the
 * row's value and [param] by the page's query string parameter (grid.js formatLinkHref()). The public
 * properties are the JSON contract with grid.js.
 */
class ActionButton implements JsonSerializable
{
    public string $label;
    public string $href;
    public ?string $icon;
    /** @var array<string, string> attribute name => value, set on the rendered link */
    public array $attributeList = [];
    /** JS function name called per row before rendering (dot-notation allowed): fn(row, buttonDef) */
    public ?string $jsCallback = null;
    /** Extra CSS classes for the rendered link */
    public ?string $cssClass = null;

    public function __construct(string $label, string $href, ?string $icon = null)
    {
        $this->label = $label;
        $this->href = $href;
        $this->icon = $icon;
    }

    /**
     * Button from an array keyed by property name (label and href required); unknown keys are ignored
     */
    public static function createFromAssocArray(array $settings): ActionButton
    {
        $button = new self(
            isset($settings['label']) ? (string)$settings['label'] : '',
            isset($settings['href']) ? (string)$settings['href'] : '',
            isset($settings['icon']) && $settings['icon'] !== '' ? (string)$settings['icon'] : null
        );
        if (isset($settings['attributeList']) && is_array($settings['attributeList'])) {
            foreach ($settings['attributeList'] as $name => $value) {
                $button->addAttribute((string)$name, is_scalar($value) ? (string)$value : '');
            }
        }
        if (isset($settings['jsCallback']) && $settings['jsCallback'] !== '') {
            $button->setJsCallback((string)$settings['jsCallback']);
        }
        if (isset($settings['cssClass']) && $settings['cssClass'] !== '') {
            $button->setCssClass((string)$settings['cssClass']);
        }
        return $button;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'href' => $this->href,
            'icon' => $this->icon,
            'attributeList' => $this->attributeList,
            'jsCallback' => $this->jsCallback,
            'cssClass' => $this->cssClass,
        ];
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function setHref(string $href): static
    {
        $this->href = $href;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = ($icon === '') ? null : $icon;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributeList;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributeList = [];
        foreach ($attributes as $name => $value) {
            $this->addAttribute((string)$name, is_scalar($value) ? (string)$value : '');
        }
        return $this;
    }

    public function addAttribute(string $attribute, string $value): static
    {
        $this->attributeList[$attribute] = $value;
        return $this;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): static
    {
        $this->cssClass = ($cssClass === '') ? null : $cssClass;
        return $this;
    }

    public function getJsCallback(): ?string
    {
        return $this->jsCallback;
    }

    /**
     * @param string|null $callback JS function name (dot-notation allowed, e.g. "MyApp.buttons.check")
     */
    public function setJsCallback(?string $callback): static
    {
        $this->jsCallback = ($callback === '') ? null : $callback;
        return $this;
    }

    /**
     * @deprecated misspelled name kept for compatibility, use setJsCallback()
     */
    public function setJsCallbackk(string $callback): static
    {
        return $this->setJsCallback($callback);
    }
}
