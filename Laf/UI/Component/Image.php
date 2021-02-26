<?php

namespace Laf\UI\Component;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;

class Image implements ComponentInterface, FormElementInterface
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
     * @param int $value
     * @return Text
     */
    public function setHeight(?int $value): Text
    {
        $this->setAttribute('height', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return Text
     */
    public function setWidth(?int $value): Text
    {
        $this->setAttribute('width', $value);
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
            $this->addStyle('height', $this->getHeight() . 'px');
            unset($attributes['height']);
        }
        if ($this->getWidth()) {
            $this->addStyle('width', $this->getWidth() . 'px');
            unset($attributes['width']);
        }

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';


        $html = "\n<img 
            {$params}
            class='" . join(' ', $this->getClasses()) . " bg-light rounded p-2' 
            style='{$this->getCssStyleForHtml()}'
            />\n";
        return $html;
    }

    /**
     * @return int
     */
    public function getHeight(): ?int
    {
        return $this->getAttribute('height');
    }

    /**
     * @return int
     */
    public function getWidth(): ?int
    {
        return $this->getAttribute('width');
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
     * @param Field $field
     * @return mixed
     * @throws \Exception
     */
    public function setField(Field $field): ComponentInterface
    {
        throw new \Exception('Invalid method call');
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getField(): ?Field
    {
        throw new \Exception('Invalid method call');
    }
}