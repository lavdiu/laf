<?php

namespace Laf\UI\Traits;

use Laf\UI\ComponentInterface;
use Laf\UI\Container\GenericContainer;
use Laf\UI\Form\DrawMode;

trait ComponentTrait
{

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $wrapperAttributes = [];

    /**
     * @var ComponentInterface[]
     */
    protected $components = [];

    /**
     * @var array
     */
    protected $cssClass = [];

    /**
     * @var array
     */
    protected $cssStyle = [];

    /**
     * @var array
     */
    protected $wrapperCssClass = [];

    /**
     * @var array
     */
    protected $wrapperCssStyle = [];

    /**
     * @var string
     */
    protected $drawMode = null;

    /**
     * @var string
     */
    protected $formRowDisplayMode = "row";

    /**
     * @var string
     */
    protected $containerType = "container";

    /**
     * @param $class
     * @return ComponentInterface
     */
    public function addCssClass(string $class): ComponentInterface
    {
        if (!$this->hasCssClass($class))
            $this->cssClass[] = $class;
        return $this;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function hasCssClass(string $class): bool
    {
        return in_array($class, $this->cssClass);
    }

    /**
     * @param array $classes
     * @return ComponentInterface
     */
    public function setCssClasses(array $classes): ComponentInterface
    {
        $this->cssClass = $classes;
        return $this;
    }

    /**
     * @param string $class
     * @return ComponentInterface
     */
    public function removeCssClass(string $class): ComponentInterface
    {
        if ($this->hasCssClass($class)) {
            $key = array_search($class, $this->cssClass);
            unset($this->cssClass[$key]);
        }
        return $this;
    }

    /**
     * @param string $property
     * @param string|null $value
     * @alias addCssStyleItem
     * @return ComponentInterface
     */
    public function setCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        return $this->addCssStyleItem($property, $value);
    }

    /**
     * @param string $property
     * @param string|null $value
     * @return ComponentInterface
     */
    public function addCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        $this->cssStyle[$property] = $value;
        return $this;
    }

    /**
     * @param string $property
     * @return array
     */
    public function getCssStyleItem(string $property): array
    {
        return $this->cssStyle[$property];
    }

    /**
     * @param array $style
     * @return ComponentInterface
     */
    public function setCssStyles(array $style): ComponentInterface
    {
        $this->cssStyle = $style;
        return $this;
    }

    /**
     * @param string $property
     * @return ComponentInterface
     */
    public function removeCssStyle(string $property): ComponentInterface
    {
        if ($this->hasCssStyleItem($property))
            unset($this->cssStyle[$property]);
        return $this;
    }

    /**
     * @param string $property
     * @return bool
     */
    public function hasCssStyleItem(string $property): bool
    {
        return array_key_exists($property, $this->cssStyle) && $this->cssStyle[$property] != '';
    }

    /**
     * @return string|null
     */
    public function getDrawMode(): ?string
    {
        return $this->drawMode;
    }

    /**
     * @param string $mode Options in DrawMode::view insert update
     * @return ComponentInterface
     */
    public function setDrawMode(string $mode): ComponentInterface
    {
        $this->drawMode = $mode;
        return $this;
    }

    /**
     * @param ComponentInterface $component
     * @return ComponentInterface
     */
    public function addComponent(ComponentInterface $component): ComponentInterface
    {
        $this->components[] = $component;
        return $this;
    }

    public function getFormRowDisplayMode(): ?string
    {
        return $this->formRowDisplayMode;
    }

    /**
     * @param string $mode
     * @return ComponentInterface
     */
    public function setFormRowDisplayMode(string $mode): ComponentInterface
    {
        $this->formRowDisplayMode = $mode;
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
     * @param array $components
     * @return ComponentInterface
     */
    public function setComponents(array $components): ComponentInterface
    {
        $this->components = $components;
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
     * @return ComponentInterface
     */
    public function setAttributes(array $attributes): ComponentInterface
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @param $class
     * @return ComponentInterface
     */
    public function addWrapperCssClass(string $class): ComponentInterface
    {
        $this->wrapperCssClass[] = $class;
        return $this;
    }

    /**
     * @return array
     */
    public function getWrapperCssClasses(): array
    {
        return $this->wrapperCssClass;
    }

    /**
     * Returns the css classes as a string, combined together to write in html
     * @return string
     */
    protected function getWrapperCssClassesForHtml()
    {
        return join(' ', $this->getWrapperCssClasses());
    }

    /**
     * @param array $classes
     * @return ComponentInterface
     */
    public function setWrapperCssClasses(array $classes): ComponentInterface
    {
        $this->wrapperCssClass = $classes;
        return $this;
    }

    /**
     * @param string $class
     * @return ComponentInterface
     */
    public function removeWrapperCssClass(string $class): ComponentInterface
    {
        if ($this->hasWrapperCssClass($class)) {
            $key = array_search($class, $this->wrapperCssClass);
            unset($this->wrapperCssClass[$key]);
        }
        return $this;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function hasWrapperCssClass(string $class): bool
    {
        return in_array($class, $this->wrapperCssClass);
    }

    /**
     * @param string $property
     * @param string|null $value
     * @alias addWrapperCssStyleItem
     * @return ComponentInterface
     */
    public function setWrapperCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        return $this->addWrapperCssStyleItem($property, $value);
    }

    /**
     * @param string $property
     * @param string|null $value
     * @return ComponentInterface
     */
    public function addWrapperCssStyleItem(string $property, ?string $value): ComponentInterface
    {
        $this->wrapperCssStyle[$property] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getWrapperCssStyles(): array
    {
        return $this->wrapperCssStyle;
    }


    /**
     * Returns the css style as a string, combined together to write in html
     * @return string
     */
    protected function getWrapperCssStyleForHtml()
    {
        $_style = [];
        foreach ($this->getWrapperCssStyles() as $k => $v) {
            $_style[] = $k . ':' . $v;
        }
        return join(';', $_style);
    }

    /**
     * @param string $property
     * @return array
     */
    public function getWrapperCssStyleItem(string $property): array
    {
        if ($this->hasWrapperCssStyleItem($property))
            return $this->wrapperCssStyle[$property];
        else
            return [];
    }

    /**
     * @param string $property
     * @return bool
     */
    public function hasWrapperCssStyleItem(string $property): bool
    {
        return array_key_exists($property, $this->wrapperCssStyle) && $this->wrapperCssStyle[$property] != '';
    }

    /**
     * @param array $style
     * @return ComponentInterface
     */
    public function setWrapperCssStyles(array $style): ComponentInterface
    {
        $this->wrapperCssStyle = $style;
        return $this;
    }

    /**
     * @param string $property
     * @return ComponentInterface
     */
    public function removeWrapperCssStyle(string $property): ComponentInterface
    {
        if ($this->hasWrapperCssStyleItem($property))
            unset($this->wrapperCssStyle[$property]);
        return $this;
    }

    /**
     * @param string $key
     * @param string|null $value
     * @alias addWrapperAttribute
     * @return ComponentInterface
     */
    public function setWrapperAttribute(string $key, ?string $value): ComponentInterface
    {
        return $this->addWrapperAttribute($key, $value);
    }

    /**
     * @param string $key
     * @param string|null $value
     * @return ComponentInterface
     */
    public function addWrapperAttribute(string $key, ?string $value): ComponentInterface
    {
        $this->wrapperAttributes[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getWrapperAttribute(string $key): ?string
    {
        if ($this->hasWrapperAttribute($key)) {
            return $this->wrapperAttributes[$key];
        } else {
            return null;
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasWrapperAttribute(string $key): bool
    {
        return array_key_exists($key, $this->wrapperAttributes) && $this->wrapperAttributes[$key] != '';
    }

    /**
     * @return array
     */
    public function getWrapperAttributes(): array
    {
        return $this->wrapperAttributes;
    }

    /**
     * @param array $attributes
     * @return mixed
     */
    public function setWrapperAttributes(array $attributes): ComponentInterface
    {
        $this->wrapperAttributes = $attributes;
        return $this;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getAttribute(string $key): ?string
    {
        if ($this->hasAttribute($key)) {
            return $this->attributes[$key];
        } else {
            return null;
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes) && $this->attributes[$key] != '';
    }

    /**
     * @param string $key
     * @param string|null $value
     * @alias addAttribute
     * @return ComponentInterface
     */
    public function setAttribute(string $key, ?string $value): ComponentInterface
    {
        return $this->addAttribute($key, $value);
    }

    /**
     * @param string $key
     * @param string $value
     * @return mixed
     */
    public function addAttribute(string $key, ?string $value): ComponentInterface
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * @param string $attribute
     * @return ComponentInterface
     */
    public function removeAttribute(string $attribute): ComponentInterface
    {
        if ($this->hasAttribute($attribute)) {
            unset($this->attributes[$attribute]);
        }
        return $this;
    }

    /**
     * @param string $attribute
     * @return ComponentInterface
     */
    public function removeWrapperAttribute(string $attribute): ComponentInterface
    {
        if ($this->hasWrapperAttribute($attribute)) {
            unset($this->wrapperAttributes[$attribute]);
        }
        return $this;
    }

    /**
     * Use ContainerType::
     * @return string
     */
    public function getContainerType(): string
    {
        return $this->containerType;
    }

    /**
     * Use ContainerType::
     * @param string $containerType
     * @return GenericContainer
     */
    public function setContainerType(string $containerType): ComponentInterface
    {
        $this->containerType = $containerType;
        return $this;
    }

    /**
     * Returns the css classes as a string, combined together to write in html
     * @return string
     */
    protected function getCssClassesForHtml()
    {
        return join(' ', $this->getCssClasses());
    }

    /**
     * @return array
     */
    public function getCssClasses(): array
    {
        return $this->cssClass;
    }

    /**
     * Returns the css style as a string, combined together to write in html
     * @return string
     */
    protected function getCssStyleForHtml()
    {
        $_style = [];
        foreach ($this->getCssStyles() as $k => $v) {
            $_style[] = $k . ':' . $v;
        }
        return join(';', $_style);
    }

    /**
     * @return array
     */
    public function getCssStyles(): array
    {
        return $this->cssStyle;
    }
}