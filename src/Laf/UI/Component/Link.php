<?php

namespace Laf\UI\Component;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;

class Link implements FormElementInterface, ComponentInterface
{
    use ComponentTrait;
    use FormElementTrait;

    protected $icon = '';

    /**
     * Link constructor.
     * @param string $text
     * @param string $href
     * @param string $icon
     * @param array $attributes
     * @param array $class
     */
    public function __construct($text = "", $href = '', $icon = '', $attributes = [], $class = [])
    {
        $this->attributes = $attributes;#this needs to be first here
        $this->setValue($text);
        $this->setHref($href);
        $this->setIcon($icon);
        $this->setCssClasses($class);
    }

    /**
     * @param string $value
     * @return Link
     */
    public function setValue(string $value): Link
    {
        $this->addAttribute('value', $value);
        return $this;
    }

    /**
     * @param string $value
     * @return Link
     */
    public function setHref(string $value): Link
    {
        $this->addAttribute('href', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getHref(): string
    {
        return $this->getAttribute('href');
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->getAttribute('target');
    }

    /**
     * @param string $value
     * @return Link
     */
    public function setTarget(string $value): Link
    {
        $this->addAttribute('target', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getRel(): string
    {
        return $this->getAttribute('rel');
    }

    /**
     * @param string $value
     * @return Link
     */
    public function setRel(string $value): Link
    {
        $this->addAttribute('rel', $value);
        return $this;
    }

    public function getName()
    {
        return '';
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
     * @return Link
     */
    public function setId(string $value): Link
    {
        $this->addAttribute('id', $value);
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
     * @return Link
     */
    public function setOnClick(string $value): Link
    {
        $this->addAttribute('onclick', $value);
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
     * @return Link
     */
    public function setTitle(string $value): Link
    {
        $this->addAttribute('title', $value);
        return $this;
    }

    /**
     * @param string $message
     * @return Link
     */
    public function setConfirmationMessage($message = ''): Link
    {
        $this->addAttribute('onclick', "return confirm('{$message}')");
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

        $this->addAttribute('class', '');
        $this->addAttribute('style', '');

        $params = '';
        foreach ($this->getAttributes() as $key => $value)
            if (mb_strlen($value) > 0 && $key != 'value')
                $params .= "\n\t" . $key . '="' . $value . '" ';

        $value = $this->getValue();
        if ($this->getIcon() != '') {
            $value = "<i class='{$this->getIcon()}'></i> " . $value;
        }
        $output = "\n<a {$params} style=\"{$this->getCssStyleForHtml()}\" \nclass=\"{$this->getCssClassesForHtml()}\"\n>{$value}</a>\n";
        return $output;
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
     * @return Link
     */
    public function setIcon(string $icon): Link
    {
        $this->icon = $icon;
        return $this;
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

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    public function getHint(): ?string
    {
        return "";
    }

    public function setHint(?string $value): ComponentInterface
    {
        return $this;
    }
}
