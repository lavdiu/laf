<?php


namespace Laf\UI\Page;


use http\Header;

class HeaderComponent
{
	protected $tagName = "";
	protected $attributes = [];
	protected $content = "";

	/**
	 * HeaderComponent constructor.
	 * @param string $tagName
	 * @param array $attributes
	 * @param string $content
	 */
	public function __construct(string $tagName, array $attributes, ?string $content)
	{
		$this->tagName = $tagName;
		$this->attributes = $attributes;
		$this->content = $content;
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
	public function getContent(): string
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


}