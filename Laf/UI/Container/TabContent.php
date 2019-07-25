<?php

namespace Laf\UI\Container;

/**
 * Class TabContent
 * @package Laf\UI\Container
 * @deprecated
 */
class TabContent
{
	/**
	 * @var string
	 */
	private $title = "";

	/**
	 * @var string
	 */
	private $content = "";

	/**
	 * @var string[]
	 */
	private $fields = [];

	/**
	 * TabContent constructor.
	 * @param string $title
	 * @param string $content
	 * @param string[] $fields ;
	 */
	public function __construct(string $title, string $content, array $fields = [])
	{
		$this->title = $title;
		$this->content = $content;
		$this->fields = $fields;
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
	 * @return TabContent
	 */
	public function setTitle(string $title): TabContent
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitleNoSpaces(): string
	{
		return preg_replace('/[^\da-z]/i', '', $this->title);
	}

	/**
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * @param string $content
	 * @return TabContent
	 */
	public function setContent(string $content): TabContent
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
	 * @return TabContent
	 */
	public function setFields(array $fields): TabContent
	{
		$this->fields = $fields;
		return $this;
	}

	/**
	 * @param string $field
	 * @return TabContent
	 */
	public function addField(string $field): TabContent
	{
		$this->fields[] = $field;
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
}
