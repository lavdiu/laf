<?php

namespace Laf\UI\Table;

class Cell
{
	private $data = null;
	private $colSpan = 0;
	private $rowSpan = 0;
	protected $tagName = 'td';
	private $classes = [];
	private $styles = [];
	private $params = [];

	private $columnIndex = 0;
	private $rowIndex = 0;

	/**
	 * Cell constructor.
	 * @param string $data
	 */
	public function __construct(string $data)
	{
		$this->setData($data);
	}

	/**
	 * @return null
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * @param null $data
	 * @return Cell
	 */
	public function setData($data): ?Cell
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getColSpan(): int
	{
		return $this->colSpan;
	}

	/**
	 * @param int $colSpan
	 * @return Cell
	 */
	public function setColSpan(int $colSpan): ?Cell
	{
		$this->colSpan = $colSpan;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getRowSpan(): int
	{
		return $this->rowSpan;
	}

	/**
	 * @param int $rowSpan
	 * @return Cell
	 */
	public function setRowSpan(int $rowSpan): ?Cell
	{
		$this->rowSpan = $rowSpan;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array
	{
		return $this->classes;
	}

	/**
	 * @param string $class
	 * @return Cell
	 */
	public function addClass(string $class): Cell
	{
		$this->classes[] = $class;
		return $this;
	}

	/**
	 * @param array $classes
	 * @return Cell
	 */
	public function setClasses(array $classes): Cell
	{
		$this->classes = $classes;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getStyles(): array
	{
		return $this->styles;
	}

	/**
	 * @param string $property
	 * @param string $value
	 * @return Cell
	 */
	public function addStyle(string $property, string $value): Cell
	{
		$this->styles[$property] = $value;
		return this;
	}

	/**
	 * @param array $styles
	 * @return Cell
	 */
	public function setStyles(array $styles): Cell
	{
		$this->styles = $styles;
		return $this;
	}

	public function addParam(string $property, string $value): Cell
	{
		$this->params[$property] = $value;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	/**
	 * @param array $params
	 * @return Cell
	 */
	public function setParams(array $params): Cell
	{
		$this->params = $params;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getColumnIndex(): int
	{
		return $this->columnIndex;
	}

	/**
	 * @param int $columnIndex
	 * @return Cell
	 */
	public function setColumnIndex(int $columnIndex): Cell
	{
		$this->columnIndex = $columnIndex;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getRowIndex(): int
	{
		return $this->rowIndex;
	}

	/**
	 * @param int $rowIndex
	 * @return Cell
	 */
	public function setRowIndex(int $rowIndex): Cell
	{
		$this->rowIndex = $rowIndex;
		return $this;
	}

	/**
	 * @return string
	 */
	public function draw(): string
	{
		$_style = [];
		foreach ($this->getCssStyles() as $k => $v) {
			$_style[] = $k . ':' . $v;
		}
		$_params = [];
		foreach ($this->getParams() as $k => $v) {
			$_params[] = "{$k}='{$v}'";
		}

		return "<"
			. $this->tagName
			. " id='cell{$this->getColumnIndex()}_{$this->getRowIndex()}'"
			. ($this->getColSpan() > 0 ? " colspan='{$this->getColSpan()}'" : '')
			. ($this->getRowSpan() > 0 ? " rowspan='{$this->getRowSpan()}'" : '')
			. (count($this->getClasses() > 0 ? " class='" . join(' ', $this->getClasses()) . "'" : ''))
			. (count($this->getStyles() > 0 ? " style='" . join(';', $_style) . "'" : ''))
			. (count($this->getParams() > 0 ? ' '.join(' ', $_params) : ''))
			. '>'
			. $this->getData()
			. '</'
			. $this->tagName
			. '>'
		;
	}
}