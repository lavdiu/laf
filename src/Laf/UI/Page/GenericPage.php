<?php

namespace Laf\UI\Page;


use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Settings;

/**
 * Class GenericPage
 * @package Laf\UI\Page
 */
class GenericPage implements ComponentInterface
{
    use ComponentTrait;

    /**
     * @var array
     */
    protected $links = [];
    protected $title = "";
    protected $titleIcon = "";
    protected $enabled = true;
    protected $header = "";
    protected $footerComponents = [];
    protected $footerHtml = "";
    protected $bodyHtml = "";

    /**
     * @param $link
     * @return GenericPage
     */
    public function addLink($link): GenericPage
    {
        $this->links[] = $link;
        return $this;
    }

    /**
     * @return GenericPage
     */
    public function enable(): GenericPage
    {
        $this->enabled = true;
    }

    /**
     * @return GenericPage
     */
    public function disable(): GenericPage
    {
        $this->enabled = false;
    }

    /**
     * @return string
     */
    public function draw(): string
    {
        if (!$this->isEnabled())
            return "";

        foreach ($this->getComponents() as $component) {

            $this->bodyHtml .= $component->draw();
        }

        foreach ($this->getFooterComponents() as $footerComponent) {

            $this->footerHtml .= $footerComponent->draw();
        }

        if ($this->hasLinks() || get_class($this) == 'Laf\UI\Page\AdminPage') {
            $icon = $this->getTitleIcon() != "" ? "<i class='{$this->getTitleIcon()}'></i>" : "";
            $links = "";
            foreach ($this->getLinks() as $link) {
                $links .= $link->draw();
            }

            $this->addCssClass($this->getContainerType())
                ->addCssClass('pb-5')
                ->addCssClass($this->getComponentCssControlClass());

            $html = "
        <div class='p-2 {$this->getCssClassesForHtml()}'>
            <div class='card border-dark shadow' style='{$this->getCssStyleForHtml()}'>
                <div class='card-header bg-light border-dark'>
                    <div class='row'>
                        <div class='col'>&nbsp; <span class='fw-bold text-uppercase text-decoration-none fs-5'>{$icon} {$this->getTitle()}</span></div>
                        <div class='col'>&nbsp;</div>
                        <div class='col text-end'>{$links}</div>
                    </div>
                </div>
                <div class='card-body'>{$this->bodyHtml}</div>
                " . ($this->hasFooterComponents() ? "<div class='card-footer'>{$this->footerHtml}</div>" : "") . "
            </div>
        </div>
        ";
        }
        return $html;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool
     */
    public function hasLinks()
    {
        return sizeof($this->links) > 0;
    }

    /**
     * @return string
     */
    public function getTitleIcon(): string
    {
        return $this->titleIcon;
    }

    /**
     * @param string $titleIcon
     */
    public function setTitleIcon(?string $titleIcon): void
    {
        $this->titleIcon = $titleIcon;
    }

    /**
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @param array $links
     * @return GenericPage
     */
    public function setLinks(array $links): GenericPage
    {
        $this->links = $links;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return GenericPage
     */
    public function setTitle(?string $title): GenericPage
    {
        $this->title = $title;
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
     * @param ComponentInterface $component
     * @return ComponentInterface
     */
    public function addFooterComponent(ComponentInterface $component): ComponentInterface
    {
        $this->footerComponents[] = $component;
        return $this;
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
     * @return bool
     */
    protected function hasFooterComponents(): bool
    {
        return count($this->getFooterComponents()) > 0;
    }
}