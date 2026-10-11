<?php

namespace Laf\UI\Component;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;

class Image implements FormElementInterface
{
    use ComponentTrait;
    use FormElementTrait;

    /**
     * Image constructor
     */
    public function __construct()
    {

    }

    /**
     * @return string
     */
    public function getSrc(): string
    {
        return $this->getAttribute('src');
    }

    /**
     * @param string $value
     * @return Image
     */
    public function setSrc(string $value): Image
    {
        $this->setAttribute('src', $value);
        return $this;
    }


    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->getAttribute('id');
    }

    /**
     * @param string $value
     * @return Image
     */
    public function setId(string $value): Image
    {
        $this->setAttribute('id', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getAlt(): string
    {
        return $this->getAttribute('alt');
    }

    /**
     * @param string $value
     * @return Image
     */
    public function setAlt(string $value): Image
    {
        $this->setAttribute('alt', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getOnClick(): string
    {
        return $this->getAttribute('onclick');
    }

    /**
     * @param string $value
     * @return Image
     */
    public function setOnClick(string $value): Image
    {
        $this->setAttribute('onclick', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getAttribute('title');
    }

    /**
     * @param string $value
     * @return Image
     */
    public function setTitle(string $value): Image
    {
        $this->setAttribute('title', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->draw();
    }

    /**
     * @return string
     */
    public function draw(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = [];
        foreach ($this->getAttributes() as $key => $value) {
            if ($value != '') {
                $attributes[$key] = $value;
            }
        }

        if ($this->getHeight()) {
            $this->addCssStyleItem('height', $this->getHeight() . 'px');
            unset($attributes['height']);
        }
        if ($this->getWidth()) {
            $this->addCssStyleItem('width', $this->getWidth() . 'px');
            unset($attributes['width']);
        }

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . \Laf\Util\Util::attr($value) . '" ';


        $html = "\n<img 
            {$params}
            class='" . $this->getCssClassesForHtml() . " bg-light rounded p-2' 
            style='{$this->getCssStyleForHtml()}'
            />\n";
        return $html;
    }

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /**
     * Not bound to a field
     * @param Field $field
     * @return ComponentInterface
     * @throws \Exception always
     */
    public function setField(Field $field): ComponentInterface
    {
        throw new \Exception('Invalid method call');
    }

    /**
     * Not bound to a field
     * @return Field|null
     * @throws \Exception always
     */
    public function getField(): ?Field
    {
        throw new \Exception('Invalid method call');
    }
}