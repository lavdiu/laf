<?php

namespace Laf\UI\Container;

/**
 * A title, a block of HTML and a list of field names: the tab description the
 * old Form::addTab() took. Not a component and never drawn.
 * @deprecated use TabContainer + TabItem (FormLayout::tab() for forms)
 */
class TabContent
{
    private string $title = '';

    private string $content = '';

    /** @var string[] */
    private array $fields = [];

    /**
     * @param string[] $fields
     */
    public function __construct(string $title, string $content, array $fields = [])
    {
        $this->title = $title;
        $this->content = $content;
        $this->fields = $fields;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * The title reduced to letters and digits
     */
    public function getTitleNoSpaces(): string
    {
        return (string)preg_replace('/[^\da-z]/i', '', $this->title);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string[] $fields
     */
    public function setFields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }

    public function addField(string $field): static
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
