<?php

namespace Laf\UI\Component;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\Util\Util;

/**
 * An <a> element: the value is the caption (raw HTML, pages pass icons and
 * badges), setIcon() puts an icon (css class) before it, every attribute of the
 * bag is rendered (href, target, rel, title, onclick, data-*). Not bound to a
 * field: getField() is null. setConfirmationMessage() asks before following
 * the link; setDisabled() renders a Bootstrap disabled link.
 */
class Link implements FormElementInterface
{
    use ComponentTrait;
    use FormElementTrait;

    protected string $icon = '';

    /**
     * @param string|null $text caption, raw HTML
     * @param string|null $href
     * @param string|null $icon css class(es) of an icon drawn before the caption
     * @param array<string, string|null> $attributes
     * @param string[] $class css classes
     */
    public function __construct(?string $text = '', ?string $href = '', ?string $icon = '', array $attributes = [], array $class = [])
    {
        $this->setAttributes($attributes); // first: the setters below write into the bag
        $this->setValue($text ?? '');
        $this->setHref($href ?? '');
        $this->setIcon($icon ?? '');
        $this->setCssClasses($class);
    }

    /**
     * The caption (raw HTML)
     */
    public function setValue(?string $value): static
    {
        $this->addAttribute('value', $value);
        return $this;
    }

    public function setHref(?string $value): static
    {
        $this->addAttribute('href', $value);
        return $this;
    }

    public function getHref(): string
    {
        return $this->getAttribute('href') ?? '';
    }

    public function getTarget(): string
    {
        return $this->getAttribute('target') ?? '';
    }

    /**
     * @param string $value one of the Target constants, or a frame name
     */
    public function setTarget(?string $value): static
    {
        $this->addAttribute('target', $value);
        return $this;
    }

    public function getRel(): string
    {
        return $this->getAttribute('rel') ?? '';
    }

    /**
     * @param string $value Rel constants, space separated
     */
    public function setRel(?string $value): static
    {
        $this->addAttribute('rel', $value);
        return $this;
    }

    /**
     * A link has no form name
     */
    public function getName(): ?string
    {
        return '';
    }

    public function getId(): string
    {
        return $this->getAttribute('id') ?? '';
    }

    public function setId(?string $value): static
    {
        $this->addAttribute('id', $value);
        return $this;
    }

    public function getOnClick(): string
    {
        return $this->getAttribute('onclick') ?? '';
    }

    public function setOnClick(?string $value): static
    {
        $this->addAttribute('onclick', $value);
        return $this;
    }

    public function getTitle(): string
    {
        return $this->getAttribute('title') ?? '';
    }

    public function setTitle(?string $value): static
    {
        $this->addAttribute('title', $value);
        return $this;
    }

    /**
     * Asks the question before following the link (onclick = return confirm(...));
     * the text may contain quotes and line breaks.
     */
    public function setConfirmationMessage(string $message = ''): static
    {
        $js = strtr($message, ['\\' => '\\\\', "'" => "\\'", "\r\n" => '\\n', "\n" => '\\n', "\r" => '\\n', '</' => '<\\/']);
        $this->addAttribute('onclick', "return confirm('" . $js . "')");
        return $this;
    }

    /**
     * A disabled link (Bootstrap: class "disabled", aria-disabled, no tab stop); the href stays
     */
    public function setDisabled(bool $value): ComponentInterface
    {
        if ($value) {
            $this->addCssClass('disabled');
            $this->addAttribute('aria-disabled', 'true');
            $this->addAttribute('tabindex', '-1');
        } else {
            $this->removeCssClass('disabled');
            $this->removeAttribute('aria-disabled');
            $this->removeAttribute('tabindex');
        }
        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->hasCssClass('disabled') || $this->getAttribute('aria-disabled') === 'true';
    }

    public function __toString(): string
    {
        return $this->draw();
    }

    public function draw(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $params = '';
        foreach ($this->getAttributes() as $key => $value) {
            $key = (string)$key;
            if ($value === null || $value === '' || in_array($key, ['value', 'class', 'style'], true) || !preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $key)) {
                continue;
            }
            $params .= "\n\t" . $key . '="' . Util::attr($value) . '" ';
        }

        $value = (string)$this->getValue();
        if ($this->icon !== '') {
            $value = "<i class='" . Util::e($this->icon) . "'></i> " . $value;
        }
        return "\n<a {$params} style=\"" . Util::attr($this->getCssStyleForHtml()) . "\" \nclass=\"" . Util::attr($this->getCssClassesForHtml()) . "\"\n>{$value}</a>\n";
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Css class(es) of an icon drawn before the caption, '' for none
     */
    public function setIcon(?string $icon): static
    {
        $this->icon = $icon ?? '';
        return $this;
    }

    /**
     * A link is not bound to a field
     * @throws \LogicException always
     */
    public function setField(Field $field): ComponentInterface
    {
        throw new \LogicException('Invalid call: a link is not bound to a field');
    }

    /**
     * A link is not bound to a field
     */
    public function getField(): ?Field
    {
        return null;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /**
     * A link has no hint
     */
    public function getHint(): ?string
    {
        return '';
    }

    /**
     * A link has no hint; the value is ignored
     */
    public function setHint(?string $value): ComponentInterface
    {
        return $this;
    }
}
