<?php


namespace Laf\UI\Page;

/**
 * Class HeaderComponent
 * @package Laf\UI\Page
 */
class HeaderComponent
{
	protected $tagName = "";
	protected $attributes = [];
	protected $content = "";
	protected $selfClosingTag = true;

	/**
	 * HeaderComponent constructor.
	 * @param string $tagName
	 * @param array $attributes
	 * @param string $content
	 * @param bool $selfClosingTag
	 */
	public function __construct(string $tagName, array $attributes, bool $selfClosingTag = false, ?string $content = null)
	{
		$this->tagName = $tagName;
		$this->attributes = $attributes;
		$this->content = $content;
		$this->selfClosingTag = $selfClosingTag;
	}


	/**
	 * @return string
	 */
	public function getTagName(): string
	{
		return $this->tagName;
	}

	/**
	 * @param string $tagName
	 * @return HeaderComponent
	 */
	public function setTagName(string $tagName): HeaderComponent
	{
		$this->tagName = $tagName;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * @param array $attributes
	 * @return HeaderComponent
	 */
	public function setAttributes(array $attributes): HeaderComponent
	{
		$this->attributes = $attributes;
		return $this;
	}

	public function addAttribute(string $key, ?string $value): HeaderComponent
	{
		$this->attributes[$key] = $value;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getContent(): ?string
	{
		return $this->content;
	}

	/**
	 * @param string $content
	 * @return HeaderComponent
	 */
	public function setContent(string $content): HeaderComponent
	{
		$this->content = $content;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isSelfClosingTag(): bool
	{
		return $this->selfClosingTag;
	}

	/**
	 * @param bool $selfClosingTag
	 * @return HeaderComponent
	 */
	public function setSelfClosingTag(bool $selfClosingTag): HeaderComponent
	{
		$this->selfClosingTag = $selfClosingTag;
		return $this;
	}


}