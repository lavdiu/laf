<?php

namespace Laf\UI\Traits;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormRowDisplayMode;

/**
 * The state every UI component carries (see ComponentInterface): an attribute
 * bag, css classes and inline style for the element itself and for its wrapper,
 * child components, the draw mode and the form row display mode.
 *
 * Rules: an attribute or style item set to null or '' counts as absent
 * (hasAttribute() is false, getAttribute() is null, nothing is rendered), so a
 * boolean attribute is switched off by storing ''; css classes are kept unique;
 * getDrawMode() never returns null ('' = not set, inherit from the parent).
 */
trait ComponentTrait
{
    /** @var array<string, string|null> */
    protected array $attributes = [];

    /** @var array<string, string|null> */
    protected array $wrapperAttributes = [];

    /** @var ComponentInterface[] */
    protected array $components = [];

    /** @var string[] */
    protected array $cssClass = [];

    /** @var array<string, string|null> property => value */
    protected array $cssStyle = [];

    /** @var string[] */
    protected array $wrapperCssClass = [];

    /** @var array<string, string|null> property => value */
    protected array $wrapperCssStyle = [];

    /** DrawMode::*, null = not set */
    protected ?string $drawMode = null;

    /** FormRowDisplayMode::* */
    protected string $formRowDisplayMode = FormRowDisplayMode::ROW;

    /** ContainerType::* css class, '' = none */
    protected string $containerType = '';

    /* ───────────── css classes ───────────── */

    /**
     * Adds a css class once (a class already present is not duplicated)
     */
    public function addCssClass(string $class): ComponentInterface
    {
        if (!$this->hasCssClass($class)) {
            $this->cssClass[] = $class;
        }
        return $this;
    }

    /**
     * Adds several css classes, each once
     */
    public function addCssClasses(string ...$classes): ComponentInterface
    {
        foreach ($classes as $class) {
            $this->addCssClass($class);
        }
        return $this;
    }

    public function hasCssClass(string $class): bool
    {
        return in_array($class, $this->cssClass, true);
    }

    /**
     * @param string[] $classes
     */
    public function setCssClasses(array $classes): ComponentInterface
    {
        $this->cssClass = $classes;
        return $this;
    }

    public function removeCssClass(string $class): ComponentInterface
    {
        $this->cssClass = array_values(array_filter($this->cssClass, fn($c) => $c !== $class));
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCssClasses(): array
    {
        return $this->cssClass;
    }

    /**
     * The css classes joined for a class attribute
     */
    protected function getCssClassesForHtml(): string
    {
        return join(' ', $this->getCssClasses());
    }

    /* ───────────── inline style ───────────── */

    /**
     * @alias addCssStyleItem
     */
    public function setCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        return $this->addCssStyleItem($property, $value);
    }

    public function addCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        $this->cssStyle[$property] = $value;
        return $this;
    }

    /**
     * @return string|null the style value, null when the property is not set
     */
    public function getCssStyleItem(string $property): ?string
    {
        return $this->hasCssStyleItem($property) ? $this->cssStyle[$property] : null;
    }

    /**
     * @param array<string, string|null> $style property => value
     */
    public function setCssStyles(array $style): ComponentInterface
    {
        $this->cssStyle = $style;
        return $this;
    }

    public function removeCssStyle(string $property): ComponentInterface
    {
        unset($this->cssStyle[$property]);
        return $this;
    }

    public function hasCssStyleItem(string $property): bool
    {
        return isset($this->cssStyle[$property]) && $this->cssStyle[$property] !== '';
    }

    /**
     * @return array<string, string|null>
     */
    public function getCssStyles(): array
    {
        return $this->cssStyle;
    }

    /**
     * The style items joined for a style attribute ("a:1;b:2"); empty items are left out
     */
    protected function getCssStyleForHtml(): string
    {
        return self::styleToString($this->getCssStyles());
    }

    /* ───────────── draw mode and row display ───────────── */

    /**
     * @return string|null DrawMode::*, '' when not set (never null)
     */
    public function getDrawMode(): ?string
    {
        return $this->drawMode ?? '';
    }

    /**
     * @param null|string $mode DrawMode::VIEW / INSERT / UPDATE, null = not set
     */
    public function setDrawMode(?string $mode): ComponentInterface
    {
        $this->drawMode = $mode;
        return $this;
    }

    public function getFormRowDisplayMode(): ?string
    {
        return $this->formRowDisplayMode;
    }

    /**
     * @param string $mode FormRowDisplayMode::ROW or INLINE
     */
    public function setFormRowDisplayMode(string $mode): ComponentInterface
    {
        $this->formRowDisplayMode = $mode;
        return $this;
    }

    /* ───────────── child components ───────────── */

    public function addComponent(ComponentInterface $component): ComponentInterface
    {
        $this->components[] = $component;
        return $this;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * @param ComponentInterface[] $components
     */
    public function setComponents(array $components): ComponentInterface
    {
        $this->components = $components;
        return $this;
    }

    public function hasComponents(): bool
    {
        return $this->components !== [];
    }

    /* ───────────── attributes ───────────── */

    /**
     * @return array<string, string|null>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, string|null> $attributes
     */
    public function setAttributes(array $attributes): ComponentInterface
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Merges attributes over the current ones
     * @param array<string, string|null> $attributes
     */
    public function addAttributes(array $attributes): ComponentInterface
    {
        foreach ($attributes as $key => $value) {
            $this->addAttribute((string)$key, $value === null ? null : (string)$value);
        }
        return $this;
    }

    /**
     * @return string|null the value, null when the attribute is not set or empty
     */
    public function getAttribute(string $key): ?string
    {
        return $this->hasAttribute($key) ? $this->attributes[$key] : null;
    }

    /**
     * Whether the attribute is set to a non-empty value
     */
    public function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]) && $this->attributes[$key] !== '';
    }

    /**
     * @alias addAttribute
     */
    public function setAttribute(string $key, ?string $value): ComponentInterface
    {
        return $this->addAttribute($key, $value);
    }

    public function addAttribute(string $key, ?string $value): ComponentInterface
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function removeAttribute(string $attribute): ComponentInterface
    {
        unset($this->attributes[$attribute]);
        return $this;
    }

    /* ───────────── wrapper css classes and style ───────────── */

    /**
     * Adds a wrapper css class once
     */
    public function addWrapperCssClass(string $class): ComponentInterface
    {
        if (!$this->hasWrapperCssClass($class)) {
            $this->wrapperCssClass[] = $class;
        }
        return $this;
    }

    /**
     * @return string[]
     */
    public function getWrapperCssClasses(): array
    {
        return $this->wrapperCssClass;
    }

    /**
     * The wrapper css classes joined for a class attribute
     */
    protected function getWrapperCssClassesForHtml(): string
    {
        return join(' ', $this->getWrapperCssClasses());
    }

    /**
     * @param string[] $classes
     */
    public function setWrapperCssClasses(array $classes): ComponentInterface
    {
        $this->wrapperCssClass = $classes;
        return $this;
    }

    public function removeWrapperCssClass(string $class): ComponentInterface
    {
        $this->wrapperCssClass = array_values(array_filter($this->wrapperCssClass, fn($c) => $c !== $class));
        return $this;
    }

    public function hasWrapperCssClass(string $class): bool
    {
        return in_array($class, $this->wrapperCssClass, true);
    }

    /**
     * @alias addWrapperCssStyleItem
     */
    public function setWrapperCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        return $this->addWrapperCssStyleItem($property, $value);
    }

    public function addWrapperCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        $this->wrapperCssStyle[$property] = $value;
        return $this;
    }

    /**
     * @return array<string, string|null>
     */
    public function getWrapperCssStyles(): array
    {
        return $this->wrapperCssStyle;
    }

    /**
     * The wrapper style items joined for a style attribute; empty items are left out
     */
    protected function getWrapperCssStyleForHtml(): string
    {
        return self::styleToString($this->getWrapperCssStyles());
    }

    /**
     * @return string|null the style value, null when the property is not set
     */
    public function getWrapperCssStyleItem(string $property): ?string
    {
        return $this->hasWrapperCssStyleItem($property) ? $this->wrapperCssStyle[$property] : null;
    }

    public function hasWrapperCssStyleItem(string $property): bool
    {
        return isset($this->wrapperCssStyle[$property]) && $this->wrapperCssStyle[$property] !== '';
    }

    /**
     * @param array<string, string|null> $style property => value
     */
    public function setWrapperCssStyles(array $style): ComponentInterface
    {
        $this->wrapperCssStyle = $style;
        return $this;
    }

    public function removeWrapperCssStyle(string $property): ComponentInterface
    {
        unset($this->wrapperCssStyle[$property]);
        return $this;
    }

    /* ───────────── wrapper attributes ───────────── */

    /**
     * @alias addWrapperAttribute
     */
    public function setWrapperAttribute(string $key, ?string $value): ComponentInterface
    {
        return $this->addWrapperAttribute($key, $value);
    }

    public function addWrapperAttribute(string $key, ?string $value): ComponentInterface
    {
        $this->wrapperAttributes[$key] = $value;
        return $this;
    }

    /**
     * @return string|null the value, null when the attribute is not set or empty
     */
    public function getWrapperAttribute(string $key): ?string
    {
        return $this->hasWrapperAttribute($key) ? $this->wrapperAttributes[$key] : null;
    }

    public function hasWrapperAttribute(string $key): bool
    {
        return isset($this->wrapperAttributes[$key]) && $this->wrapperAttributes[$key] !== '';
    }

    /**
     * @return array<string, string|null>
     */
    public function getWrapperAttributes(): array
    {
        return $this->wrapperAttributes;
    }

    /**
     * @param array<string, string|null> $attributes
     */
    public function setWrapperAttributes(array $attributes): ComponentInterface
    {
        $this->wrapperAttributes = $attributes;
        return $this;
    }

    public function removeWrapperAttribute(string $attribute): ComponentInterface
    {
        unset($this->wrapperAttributes[$attribute]);
        return $this;
    }

    /* ───────────── container type ───────────── */

    /**
     * Use ContainerType::
     */
    public function getContainerType(): string
    {
        return $this->containerType;
    }

    /**
     * Use ContainerType::
     */
    public function setContainerType(string $containerType): ComponentInterface
    {
        $this->containerType = $containerType;
        return $this;
    }

    /* ───────────── internals ───────────── */

    /**
     * "property:value;property:value" from a style map, leaving out null and empty values
     * @param array<string, string|null> $style
     */
    private static function styleToString(array $style): string
    {
        $items = [];
        foreach ($style as $property => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $items[] = $property . ':' . $value;
        }
        return join(';', $items);
    }
}
