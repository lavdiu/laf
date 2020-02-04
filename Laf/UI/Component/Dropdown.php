<?php

namespace Laf\UI\Component;


use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;

class Dropdown implements FormElementInterface, ComponentInterface
{
	use ComponentTrait;
	use FormElementTrait;

	protected $text = "";
	/**
	 * @var Link[]
	 */
	protected $links = [];
	protected $icon = "";
	protected $rightAlign = false;

	/**
	 * Dropdown constructor.
	 * @param string $text
	 * @param string $icon
	 */
	public function __construct(string $text = '', $class = '', string $icon = '', bool $rightAlign = false)
	{
		$this->text = $text;
		$this->icon = $icon;
		$this->rightAlign = $rightAlign;
		if ($class != '')
			$this->addCssClass($class);
	}


	/**
	 * @param Link $link
	 * @return $this
	 */
	public function addLink(Link $link)
	{
		$this->links[] = $link;
		return $this;
	}


	/**
	 * @return string
	 */
	public function draw(): ?string
	{
		$id = uniqid();
		$this->addCssClass(static::getComponentCssControlClass());
		$this->addAttribute('href', '#');
		$this->addCssClass('btn')
			->addCssClass('dropdown-toggle');

		$params = '';
		foreach ($this->getAttributes() as $key => $value)
			if (mb_strlen($value) > 0 && $key != 'value')
				$params .= "\n\t" . $key . '="' . $value . '" ';
		$text = $this->getText();
		if ($this->getIcon() != '') {
			$text = "<i class='{$this->getIcon()}'></i> " . $text;
		}
		$html = "
		<a {$params}  
			style='{$this->getCssStyleForHtml()}' 
			class='{$this->getCssClassesForHtml()}'
			role='button' data-toggle='dropdown' id='{$id}' aria-haspopup='true' aria-expanded='false'>
		    {$text}
		</a>
		<div class='dropdown-menu".($this->rightAlign?' dropdown-menu-right':'')."' aria-labelledby='{$id}'>";
		foreach ($this->getLinks() as $link) {
			$link->addCssClass('dropdown-item ');
			$link->removeCssClass('btn');
			$link->removeCssClass('btn-sm');
			$html .= $link->draw();
		}

		$html .= "
		</div>
        ";
		return $html;

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
	 * @return string
	 */
	public function getText(): string
	{
		return $this->text;
	}

	/**
	 * @param string $text
	 * @return Dropdown
	 */
	public function setText(string $text): Dropdown
	{
		$this->text = $text;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getIcon()
	{
		return $this->icon;
	}

	/**
	 * @param string $icon
	 * @return Dropdown
	 */
	public function setIcon(string $icon): Dropdown
	{
		$this->icon = $icon;
		return $this;
	}

	/**
	 * @return ComponentInterface[]
	 */
	public function getLinks(): array
	{
		return $this->links;
	}

	/**
	 * @param array $links
	 * @return Dropdown
	 */
	public function setLinks(array $links): Dropdown
	{
		$this->links = $links;
		return $this;
	}

	public function hasHrefParameter()
	{
		return false;
	}

	/**
	 * @param Field $field
	 * @return mixed
	 * @throws \Exception
	 */
	public function setField(Field $field): ComponentInterface
	{
		throw new \Exception('Invalid call');
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	public function getField(): ?Field
	{
		throw new \Exception('Invalid call');
	}

	public function getHint(): ?string
	{
		return "";
	}

	public function setHint(?string $value)
	{

	}

	/**
	 * @return Dropdown
	 */
	public function rightAlign(): Dropdown
	{
		$this->rightAlign = true;
		return $this;
	}


	/**
	 * @return Dropdown
	 */
	public function leftAlign(): Dropdown
	{
		$this->rightAlign = true;
		return $this;
	}


}

