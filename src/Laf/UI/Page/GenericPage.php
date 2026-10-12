<?php

namespace Laf\UI\Page;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * The card a page puts its components into.
 *
 * Draws, in this order: the header HTML (setHeader(), raw), a wrapper `div.p-2` that carries the
 * component's css classes, container type and attributes, the card (setCardCssClasses(), default
 * `card border-dark shadow`, with the component's css style) holding a header row (title, icon,
 * subtitle on the left, the action links on the right), the card body with the components, a card
 * footer when footer components were added, and the footer HTML (setFooter(), raw).
 *
 * The title and the subtitle are HTML, not text: pages pass an anchor as the title. Escape them
 * with Util::e() when they come from user data. A disabled page draws nothing.
 */
class GenericPage implements ComponentInterface
{
    use ComponentTrait;

    /** @var ComponentInterface[] */
    protected array $links = [];
    protected string $title = '';
    protected string $subtitle = '';
    protected string $titleIcon = '';
    protected bool $enabled = true;
    protected bool $showCardHeader = true;
    protected string $header = '';
    protected string $footer = '';
    /** @var ComponentInterface[] */
    protected array $footerComponents = [];
    /** @var string[] */
    protected array $cardCssClasses = ['card', 'border-dark', 'shadow'];
    /** @var string[] */
    protected array $cardHeaderCssClasses = ['card-header', 'bg-light', 'border-dark'];

    /**
     * Adds an action link (Link, Dropdown, any component) to the right side of the header row.
     */
    public function addLink(ComponentInterface $link): static
    {
        $this->links[] = $link;
        return $this;
    }

    public function enable(): static
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): static
    {
        $this->enabled = false;
        return $this;
    }

    public function draw(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        if ($this->getContainerType() !== '') {
            $this->addCssClass($this->getContainerType());
        }
        $this->addCssClass($this->getComponentCssControlClass());

        return $this->getHeader() . "
        <div class='p-2 " . $this->getCssClassesForHtml() . "'" . $this->drawWrapperAttributes() . ">
            <div class='" . Util::attr(join(' ', $this->getCardCssClasses())) . "' style='" . Util::attr($this->getCssStyleForHtml()) . "'>
                " . $this->drawHeaderRow() . "
                <div class='card-body'>" . $this->drawBody() . "</div>
                " . $this->drawFooter() . "
            </div>
        </div>
        " . $this->getFooter();
    }

    /**
     * The component's attributes (setAttribute('id', ...), data-*) as ` key="value"` pairs for the wrapper div.
     */
    protected function drawWrapperAttributes(): string
    {
        $html = '';
        foreach ($this->getAttributes() as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $html .= ' ' . $key . '="' . Util::attr($value) . '"';
        }
        return $html;
    }

    /**
     * The card header: icon + title (+ subtitle) on the left, the links on the right. Empty when hidden.
     */
    protected function drawHeaderRow(): string
    {
        if (!$this->isCardHeaderShown()) {
            return '';
        }
        $icon = $this->getTitleIcon() !== '' ? "<i class='" . Util::attr($this->getTitleIcon()) . "'></i>" : '';
        $subtitle = $this->getSubtitle() !== ''
            ? " <small class='text-muted fw-normal fs-6'>" . $this->getSubtitle() . "</small>"
            : '';
        return "<div class='" . Util::attr(join(' ', $this->getCardHeaderCssClasses())) . "'>
                    <div class='row'>
                        <div class='col-lg-8 col-sm-12'>&nbsp; <span class='fw-bold text-uppercase text-decoration-none fs-5'>" . $icon . " " . $this->getTitle() . "</span>" . $subtitle . "</div>
                        <div class='col-lg-4 col-sm-12 text-end'>" . $this->drawLinks() . "</div>
                    </div>
                </div>";
    }

    protected function drawLinks(): string
    {
        $html = [];
        foreach ($this->getLinks() as $link) {
            $html[] = (string)$link->draw();
        }
        return join(' ', $html);
    }

    protected function drawBody(): string
    {
        $html = '';
        foreach ($this->getComponents() as $component) {
            $html .= (string)$component->draw();
        }
        return $html;
    }

    /**
     * The card footer with the footer components; empty when there are none.
     */
    protected function drawFooter(): string
    {
        if (!$this->hasFooterComponents()) {
            return '';
        }
        $html = '';
        foreach ($this->getFooterComponents() as $component) {
            $html .= (string)$component->draw();
        }
        return "<div class='card-footer'>" . $html . "</div>";
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function hasLinks(): bool
    {
        return count($this->links) > 0;
    }

    public function getTitleIcon(): string
    {
        return $this->titleIcon;
    }

    /**
     * Icon css classes drawn before the title (`far fa-list-alt`).
     * Returns nothing to stay compatible with code written against the void signature; use the
     * fluent setters around it.
     */
    public function setTitleIcon(?string $titleIcon): void
    {
        $this->titleIcon = $titleIcon ?? '';
    }

    /**
     * @return ComponentInterface[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @param ComponentInterface[] $links
     */
    public function setLinks(array $links): static
    {
        $this->links = $links;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The title is HTML (an anchor is fine); escape user data with Util::e() first.
     */
    public function setTitle(?string $title): static
    {
        $this->title = $title ?? '';
        return $this;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    /**
     * Smaller text after the title, HTML like the title; empty = not drawn.
     */
    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle ?? '';
        return $this;
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    /**
     * Raw HTML drawn before the card (breadcrumbs, a banner, an opening container).
     */
    public function setHeader(?string $header): static
    {
        $this->header = $header ?? '';
        return $this;
    }

    public function getFooter(): string
    {
        return $this->footer;
    }

    /**
     * Raw HTML drawn after the card.
     */
    public function setFooter(?string $footer): static
    {
        $this->footer = $footer ?? '';
        return $this;
    }

    public function isCardHeaderShown(): bool
    {
        return $this->showCardHeader;
    }

    /**
     * false draws the card without its header row (title, icon and links are then not drawn).
     */
    public function setShowCardHeader(bool $show): static
    {
        $this->showCardHeader = $show;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCardCssClasses(): array
    {
        return $this->cardCssClasses;
    }

    /**
     * Classes of the card element itself (default `card border-dark shadow`).
     * @param string[] $classes
     */
    public function setCardCssClasses(array $classes): static
    {
        $this->cardCssClasses = array_values($classes);
        return $this;
    }

    public function addCardCssClass(string $class): static
    {
        if (!in_array($class, $this->cardCssClasses, true)) {
            $this->cardCssClasses[] = $class;
        }
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCardHeaderCssClasses(): array
    {
        return $this->cardHeaderCssClasses;
    }

    /**
     * Classes of the card header (default `card-header bg-light border-dark`).
     * @param string[] $classes
     */
    public function setCardHeaderCssClasses(array $classes): static
    {
        $this->cardHeaderCssClasses = array_values($classes);
        return $this;
    }

    public function addCardHeaderCssClass(string $class): static
    {
        if (!in_array($class, $this->cardHeaderCssClasses, true)) {
            $this->cardHeaderCssClasses[] = $class;
        }
        return $this;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getFooterComponents(): array
    {
        return $this->footerComponents;
    }

    /**
     * Components drawn in a `card-footer` under the body.
     */
    public function addFooterComponent(ComponentInterface $component): static
    {
        $this->footerComponents[] = $component;
        return $this;
    }

    /**
     * @param ComponentInterface[] $components
     */
    public function setFooterComponents(array $components): static
    {
        $this->footerComponents = $components;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    protected function hasFooterComponents(): bool
    {
        return count($this->footerComponents) > 0;
    }
}
