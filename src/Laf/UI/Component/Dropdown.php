<?php

namespace Laf\UI\Component;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\Util\Util;

/**
 * A Bootstrap dropdown button (text + icon) with a menu of Links; addDivider(),
 * addHeader() and addHtml() put separators, headings and raw entries between
 * them. Links in the menu are drawn as dropdown items (their btn classes are
 * removed). The toggle's id is the id attribute when set, else generated.
 * Bootstrap 4 and 5 data attributes are both rendered. Not bound to a field.
 */
class Dropdown implements FormElementInterface
{
    use ComponentTrait;
    use FormElementTrait;

    protected string $text = '';

    /** @var Link[] */
    protected array $links = [];

    /** @var array<int, array{0: string, 1: mixed}> menu entries in order: ['link', Link] | ['divider', null] | ['header', text] | ['html', raw] */
    protected array $items = [];

    protected string $icon = '';

    protected bool $rightAlign = false;

    /** @var string[] extra css classes of the menu (e.g. dropdown-menu-dark) */
    protected array $menuCssClasses = [];

    /**
     * @param string $text caption (escaped)
     * @param string $class a css class of the toggle button, e.g. 'btn-primary'
     * @param string $icon css class(es) of an icon before the caption
     * @param bool $rightAlign align the menu to the right edge of the button
     */
    public function __construct(string $text = '', string $class = '', string $icon = '', bool $rightAlign = false)
    {
        $this->text = $text;
        $this->icon = $icon;
        $this->rightAlign = $rightAlign;
        if ($class !== '') {
            $this->addCssClass($class);
        }
    }

    public function addLink(Link $link): static
    {
        $this->links[] = $link;
        $this->items[] = ['link', $link];
        return $this;
    }

    /**
     * A separator line between the entries
     */
    public function addDivider(): static
    {
        $this->items[] = ['divider', null];
        return $this;
    }

    /**
     * A heading (escaped) between the entries
     */
    public function addHeader(string $text): static
    {
        $this->items[] = ['header', $text];
        return $this;
    }

    /**
     * A raw HTML entry (e.g. a form button with class dropdown-item)
     */
    public function addHtml(string $html): static
    {
        $this->items[] = ['html', $html];
        return $this;
    }

    /**
     * @return array<int, array{0: string, 1: mixed}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        $this->addAttribute('href', '#');
        $this->addCssClass('btn')
            ->addCssClass('dropdown-toggle');
        $id = $this->getAttribute('id') ?? 'dd' . uniqid();

        $params = '';
        foreach ($this->getAttributes() as $key => $value) {
            $key = (string)$key;
            if ($value === null || $value === '' || in_array($key, ['value', 'id', 'class', 'style'], true) || !preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $key)) {
                continue;
            }
            $params .= "\n\t" . $key . '="' . Util::attr($value) . '" ';
        }
        $text = Util::e($this->getText());
        if ($this->icon !== '') {
            $text = "<i class='" . Util::e($this->icon) . "'></i> " . $text;
        }
        $menuClasses = join(' ', array_merge(['dropdown-menu'], $this->rightAlign ? ['dropdown-menu-right', 'dropdown-menu-end'] : [], $this->menuCssClasses));

        $html = "
		<a {$params}
			style='" . Util::e($this->getCssStyleForHtml()) . "'
			class='" . Util::e($this->getCssClassesForHtml()) . "'
			role='button' data-toggle='dropdown' data-bs-toggle='dropdown' id='" . Util::e($id) . "' aria-haspopup='true' aria-expanded='false'>
		    {$text}
		</a>
		<div class='" . Util::e($menuClasses) . "' aria-labelledby='" . Util::e($id) . "'>";
        foreach ($this->items as [$kind, $item]) {
            switch ($kind) {
                case 'link':
                    /** @var Link $item */
                    $item->addCssClass('dropdown-item');
                    $item->removeCssClass('btn');
                    $item->removeCssClass('btn-sm');
                    $html .= $item->draw();
                    break;
                case 'divider':
                    $html .= "\n\t\t\t<div class='dropdown-divider'></div>";
                    break;
                case 'header':
                    $html .= "\n\t\t\t<h6 class='dropdown-header'>" . Util::e((string)$item) . "</h6>";
                    break;
                default:
                    $html .= "\n\t\t\t" . (string)$item;
            }
        }

        $html .= "
		</div>
        ";
        return $html;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * The Links of the menu (without dividers and headers)
     * @return Link[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Replaces every entry of the menu with these links
     * @param Link[] $links
     */
    public function setLinks(array $links): static
    {
        $this->links = [];
        $this->items = [];
        foreach ($links as $link) {
            $this->addLink($link);
        }
        return $this;
    }

    public function hasLinks(): bool
    {
        return $this->links !== [];
    }

    /**
     * Extra css classes of the menu element, e.g. 'dropdown-menu-dark'
     */
    public function addMenuCssClass(string $class): static
    {
        if ($class !== '' && !in_array($class, $this->menuCssClasses, true)) {
            $this->menuCssClasses[] = $class;
        }
        return $this;
    }

    public function hasHrefParameter(): bool
    {
        return false;
    }

    /**
     * Not bound to a field
     * @throws \LogicException always
     */
    public function setField(Field $field): ComponentInterface
    {
        throw new \LogicException('Invalid call: a dropdown is not bound to a field');
    }

    /**
     * Not bound to a field
     */
    public function getField(): ?Field
    {
        return null;
    }

    public function getHint(): ?string
    {
        return '';
    }

    /**
     * A dropdown has no hint; the value is ignored
     */
    public function setHint(?string $value): ComponentInterface
    {
        return $this;
    }

    /**
     * Align the menu to the right edge of the button
     */
    public function rightAlign(): static
    {
        $this->rightAlign = true;
        return $this;
    }

    /**
     * Align the menu to the left edge of the button (the default)
     */
    public function leftAlign(): static
    {
        $this->rightAlign = false;
        return $this;
    }

    public function isRightAligned(): bool
    {
        return $this->rightAlign;
    }
}
