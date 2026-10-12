<?php

namespace Laf\UI\Component;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\Util\Util;

/**
 * An <img>: src, alt, title, onclick and every other attribute of the bag;
 * setHeight()/setWidth() become inline style in px. Drawn with the Bootstrap
 * classes bg-light rounded p-2. Not bound to a field. setLazy(true) defers
 * loading until the image is near the viewport.
 */
class Image implements FormElementInterface
{
    use ComponentTrait;
    use FormElementTrait;

    public function __construct(string $src = '', string $alt = '')
    {
        if ($src !== '') {
            $this->setSrc($src);
        }
        if ($alt !== '') {
            $this->setAlt($alt);
        }
    }

    public function getSrc(): string
    {
        return $this->getAttribute('src') ?? '';
    }

    public function setSrc(string $value): static
    {
        $this->setAttribute('src', $value);
        return $this;
    }

    public function getId(): string
    {
        return $this->getAttribute('id') ?? '';
    }

    public function setId(?string $value): static
    {
        $this->setAttribute('id', $value);
        return $this;
    }

    public function getAlt(): string
    {
        return $this->getAttribute('alt') ?? '';
    }

    public function setAlt(string $value): static
    {
        $this->setAttribute('alt', $value);
        return $this;
    }

    public function getOnClick(): string
    {
        return $this->getAttribute('onclick') ?? '';
    }

    public function setOnClick(string $value): static
    {
        $this->setAttribute('onclick', $value);
        return $this;
    }

    public function getTitle(): string
    {
        return $this->getAttribute('title') ?? '';
    }

    public function setTitle(?string $value): static
    {
        $this->setAttribute('title', $value);
        return $this;
    }

    /**
     * loading="lazy": the browser fetches the image only when it comes near the viewport
     */
    public function setLazy(bool $lazy): static
    {
        $this->setAttribute('loading', $lazy ? 'lazy' : null);
        return $this;
    }

    public function isLazy(): bool
    {
        return $this->getAttribute('loading') === 'lazy';
    }

    public function __toString(): string
    {
        return $this->draw();
    }

    public function draw(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $style = $this->getCssStyles();
        $params = '';
        foreach ($this->getAttributes() as $key => $value) {
            $key = (string)$key;
            if ($value === null || $value === '' || in_array($key, ['class', 'style', 'value'], true) || !preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $key)) {
                continue;
            }
            if ($key === 'height' || $key === 'width') {
                if (is_numeric($value)) {
                    $style[$key] = $value . 'px';
                    continue;
                }
            }
            $params .= "\n\t\t\t\t" . $key . '="' . Util::attr($value) . '" ';
        }
        $styleItems = [];
        foreach ($style as $property => $value) {
            if ($value !== null && $value !== '') {
                $styleItems[] = $property . ':' . $value;
            }
        }

        return "\n<img
            {$params}
            class='" . Util::e($this->getCssClassesForHtml()) . " bg-light rounded p-2'
            style='" . Util::e(join(';', $styleItems)) . "'
            />\n";
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /**
     * Not bound to a field
     * @throws \LogicException always
     */
    public function setField(Field $field): ComponentInterface
    {
        throw new \LogicException('Invalid call: an image is not bound to a field');
    }

    /**
     * Not bound to a field
     */
    public function getField(): ?Field
    {
        return null;
    }
}
