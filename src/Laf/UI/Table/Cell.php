<?php

namespace Laf\UI\Table;

use Laf\Util\Util;

/**
 * One <td>/<th>. Content given to the constructor or setData() is raw HTML
 * (cells often hold links and badges); use setText() for plain text, which is
 * escaped. Attribute values, class names and style values are always escaped.
 */
class Cell
{
    private $data = null;
    private $colSpan = 0;
    private $rowSpan = 0;
    protected $tagName = 'td';
    private $classes = [];
    private $styles = [];
    private $params = [];
    private $table = null;
    private $prettyPrint = false;

    private $columnIndex = 0;
    private $rowIndex = 0;

    /** @var string|null id of the owning row ("tbl_3", "tbl_head_0"); set by Tr::draw() */
    private $rowHtmlId = null;

    /**
     * @param string $data raw HTML content
     */
    public function __construct(string $data = '')
    {
        $this->setData($data);
    }

    /** @return string|null raw HTML content */
    public function getData()
    {
        return $this->data;
    }

    /** @param string|null $data raw HTML content */
    public function setData($data): Cell
    {
        $this->data = $data;
        return $this;
    }

    /** Plain-text content; escaped at this point so the stored data is safe HTML. */
    public function setText(?string $text): Cell
    {
        $this->data = Util::e($text);
        return $this;
    }

    public function getColSpan(): int
    {
        return $this->colSpan;
    }

    public function setColSpan(int $colSpan): Cell
    {
        $this->colSpan = $colSpan;
        return $this;
    }

    public function getRowSpan(): int
    {
        return $this->rowSpan;
    }

    public function setRowSpan(int $rowSpan): Cell
    {
        $this->rowSpan = $rowSpan;
        return $this;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function addClass(string $class): Cell
    {
        $this->classes[] = $class;
        return $this;
    }

    public function setClasses(array $classes): Cell
    {
        $this->classes = $classes;
        return $this;
    }

    /** Bootstrap text alignment helpers; numeric columns should be right-aligned. */
    public function alignRight(): Cell
    {
        return $this->addClass('text-end');
    }

    public function alignCenter(): Cell
    {
        return $this->addClass('text-center');
    }

    public function alignLeft(): Cell
    {
        return $this->addClass('text-start');
    }

    public function getStyles(): array
    {
        return $this->styles;
    }

    public function addStyle(string $property, string $value): Cell
    {
        $this->styles[$property] = $value;
        return $this;
    }

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

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): Cell
    {
        $this->params = $params;
        return $this;
    }

    public function getColumnIndex(): int
    {
        return $this->columnIndex;
    }

    public function setColumnIndex(int $columnIndex): Cell
    {
        $this->columnIndex = $columnIndex;
        return $this;
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function setRowIndex(int $rowIndex): Cell
    {
        $this->rowIndex = $rowIndex;
        return $this;
    }

    public function getRowHtmlId(): ?string
    {
        return $this->rowHtmlId;
    }

    public function setRowHtmlId(?string $rowHtmlId): Cell
    {
        $this->rowHtmlId = $rowHtmlId;
        return $this;
    }

    /** "tbl_3_2": row id plus column index; null when the cell is drawn standalone. */
    public function getHtmlId(): ?string
    {
        if ($this->rowHtmlId !== null) {
            return $this->rowHtmlId . '_' . $this->getColumnIndex();
        }
        if ($this->getTable() !== null) {
            return $this->getTable()->getId() . '_' . $this->getRowIndex() . '_' . $this->getColumnIndex();
        }
        return null;
    }

    public function draw(): string
    {
        $_style = [];
        foreach ($this->getStyles() as $k => $v) {
            $_style[] = Util::e($k) . ':' . Util::e($v);
        }
        $_params = [];
        foreach ($this->getParams() as $k => $v) {
            $_params[] = Util::e($k) . "='" . Util::e($v) . "'";
        }
        $html = '';

        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t\t\t";
        }

        $id = $this->getHtmlId();
        $html
            .= "<"
            . $this->tagName
            . ($id !== null ? " id='" . Util::e($id) . "'" : '')
            . ($this->getColSpan() > 0 ? " colspan='{$this->getColSpan()}'" : '')
            . ($this->getRowSpan() > 0 ? " rowspan='{$this->getRowSpan()}'" : '')
            . (count($this->getClasses()) > 0 ? " class='" . Util::e(join(' ', $this->getClasses())) . "'" : '')
            . (count($this->getStyles()) > 0 ? " style='" . join(';', $_style) . "'" : '')
            . (count($this->getParams()) > 0 ? ' ' . join(' ', $_params) : '')
            . '>'
            . $this->getData()
            . '</'
            . $this->tagName
            . '>';
        return $html;
    }

    public function setTable(Table $table): Cell
    {
        $this->table = $table;
        return $this;
    }

    public function getTable(): ?Table
    {
        return $this->table;
    }

    public function isPrettyPrint(): bool
    {
        return $this->prettyPrint;
    }

    public function setPrettyPrint(bool $prettyPrint): Cell
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }
}
