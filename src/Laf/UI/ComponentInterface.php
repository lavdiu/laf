<?php

namespace Laf\UI;

use Laf\UI\Traits\ComponentTrait;

/**
 * What every UI component offers: css classes and inline style, an attribute bag, a wrapper
 * (classes, style, attributes), child components, a draw mode and a form row display mode,
 * and draw(). ComponentTrait implements all of it; see its docblock for the rules.
 */
interface ComponentInterface
{

    /**
     * @param $class
     * @return ComponentInterface
     */
    public function addCssClass(string $class): ComponentInterface;

    /**
     * @return array
     */
    public function getCssClasses(): array;

    /**
     * @param array $classes
     * @return ComponentInterface
     */
    public function setCssClasses(array $classes): ComponentInterface;

    /**
     * @param string $class
     * @return ComponentInterface
     */
    public function removeCssClass(string $class): ComponentInterface;

    /**
     * @param string $class
     * @return bool
     */
    public function hasCssClass(string $class): bool;

    /**
     * @param string $property
     * @param string|null $value
     * @return ComponentInterface
     */
    public function addCssStyleItem(string $property, ?string $value): ComponentInterface;

    /**
     * @return array
     */
    public function getCssStyles(): array;

    /**
     * @param string $property
     * @return string|null
     */
    public function getCssStyleItem(string $property): ?string;

    /**
     * @param array $classes
     * @return ComponentInterface
     */
    public function setCssStyles(array $classes): ComponentInterface;

    /**
     * @param string $property
     * @return ComponentInterface
     */
    public function removeCssStyle(string $property): ComponentInterface;

    /**
     * @param string $property
     * @return bool
     */
    public function hasCssStyleItem(string $property): bool;

    /**
     * @param null|string $mode Options in DrawMode::
     * @return ComponentInterface
     */
    public function setDrawMode(?string $mode): ComponentInterface;

    /**
     * @return string|null
     */
    public function getDrawMode(): ?string;

    /**
     * @return string|null
     */
    public function draw(): ?string;

    /**
     * @param ComponentInterface $component
     * @return ComponentInterface
     */
    public function addComponent(ComponentInterface $component): ComponentInterface;

    /**
     * @return ComponentInterface[]
     */
    public function getComponents(): array;

    /**
     * @param array $components
     * @return ComponentInterface
     */
    public function setComponents(array $components): ComponentInterface;

    /**
     * @param string $key
     * @param string $value
     * @return ComponentInterface
     */
    public function addAttribute(string $key, ?string $value): ComponentInterface;

    /**
     * @param array $attributes
     * @return ComponentInterface
     */
    public function setAttributes(array $attributes): ComponentInterface;

    /**
     * @param string $key
     * @return string
     */
    public function getAttribute(string $key): ?string;

    /**
     * @return array
     */
    public function getAttributes(): array;

    /**
     * @param string $key
     * @return bool
     */
    public function hasAttribute(string $key): bool;

    /**
     * @param $class
     * @return ComponentInterface
     */
    public function addWrapperCssClass(string $class): ComponentInterface;

    /**
     * @return array
     */
    public function getWrapperCssClasses(): array;

    /**
     * @param array $classes
     * @return ComponentInterface
     */
    public function setWrapperCssClasses(array $classes): ComponentInterface;

    /**
     * @param string $class
     * @return ComponentInterface
     */
    public function removeWrapperCssClass(string $class): ComponentInterface;

    /**
     * @param string $class
     * @return bool
     */
    public function hasWrapperCssClass(string $class): bool;

    /**
     * @param string $property
     * @param string|null $value
     * @return ComponentInterface
     */
    public function addWrapperCssStyleItem(string $property, ?string $value): ComponentInterface;

    /**
     * @return array
     */
    public function getWrapperCssStyles(): array;

    /**
     * @param string $property
     * @return string|null
     */
    public function getWrapperCssStyleItem(string $property): ?string;

    /**
     * @param array $classes
     * @return ComponentInterface
     */
    public function setWrapperCssStyles(array $classes): ComponentInterface;

    /**
     * @param string $property
     * @return ComponentInterface
     */
    public function removeWrapperCssStyle(string $property): ComponentInterface;

    /**
     * @param string $property
     * @return bool
     */
    public function hasWrapperCssStyleItem(string $property): bool;

    /**
     * @param string $key
     * @param string|null $value
     * @return ComponentInterface
     */
    public function addWrapperAttribute(string $key, ?string $value): ComponentInterface;

    /**
     * @param array $attributes
     * @return ComponentInterface
     */
    public function setWrapperAttributes(array $attributes): ComponentInterface;

    /**
     * @param string $key
     * @return string
     */
    public function getWrapperAttribute(string $key): ?string;

    /**
     * @return array
     */
    public function getWrapperAttributes(): array;

    /**
     * @param string $key
     * @return bool
     */
    public function hasWrapperAttribute(string $key): bool;

    /**
     * @param string $mode FormRowDisplayMode::
     * @return ComponentInterface
     */
    public function setFormRowDisplayMode(string $mode);

    /**
     * @return string|null
     */
    public function getFormRowDisplayMode(): ?string;

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string;

    /**
     * @param string $attribute
     * @return ComponentInterface
     */
    public function removeAttribute(string $attribute): ComponentInterface;

    /**
     * @param string $attribute
     * @return ComponentInterface
     */
    public function removeWrapperAttribute(string $attribute): ComponentInterface;
}
