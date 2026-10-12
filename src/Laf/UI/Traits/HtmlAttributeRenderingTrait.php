<?php

declare(strict_types=1);

namespace Laf\UI\Traits;

use Laf\Util\Util;

/**
 * Renders a component's attribute bag, css classes and inline style as HTML.
 *
 * Shared by the form inputs (Text and its subclasses) and the controls (Button);
 * expects ComponentTrait and FormElementTrait on the using class.
 */
trait HtmlAttributeRenderingTrait
{
    /**
     * Attributes rendered as key="value" (double quoted, escaped), each on its own line.
     * Empty and null values are skipped; arrays are joined with spaces; invalid names are dropped.
     * @param array<string, mixed> $attributes
     */
    protected function renderAttributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = join(' ', array_map('strval', $value));
            }
            if ($value === null || (string)$value === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', (string)$key)) {
                continue;
            }
            $html .= "\n\t\t\t\t" . $key . '="' . Util::attr($value) . '"';
        }
        return $html;
    }

    /**
     * The attribute bag without what is rendered separately (class, style) or turned into style (height, width).
     * @param string[] $without further attributes to drop
     * @return array<string, mixed>
     */
    protected function controlAttributes(array $without = []): array
    {
        $attributes = $this->getAttributes();
        foreach (array_merge(['class', 'style', 'height', 'width'], $without) as $key) {
            unset($attributes[$key]);
        }
        return $attributes;
    }

    /**
     * The css classes of the control, plus the given defaults, plus is-invalid when there are errors.
     * @return string[]
     */
    protected function controlClasses(string ...$defaults): array
    {
        $classes = $this->getCssClasses();
        foreach ($defaults as $class) {
            if ($class !== '' && !in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }
        if ($this->hasErrors() && !in_array('is-invalid', $classes, true)) {
            $classes[] = 'is-invalid';
        }
        return array_values($classes);
    }

    /**
     * The inline style of the control: the style items plus height/width attributes as px.
     */
    protected function controlStyle(): string
    {
        $style = $this->getCssStyles();
        if ($this->getHeight()) {
            $style['height'] = $this->getHeight() . 'px';
        }
        if ($this->getWidth()) {
            $style['width'] = $this->getWidth() . 'px';
        }
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
