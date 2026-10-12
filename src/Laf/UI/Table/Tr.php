<?php

namespace Laf\UI\Table;

use Laf\Util\Util;

/**
 * One <tr>. Cell ids are derived at draw time from the row's current index and
 * section (body/head/foot), so re-indexing a row after cells were added keeps
 * ids consistent.
 */
class Tr
{
    public const SECTION_BODY = 'body';
    public const SECTION_HEAD = 'head';
    public const SECTION_FOOT = 'foot';

    /** @var Cell[] */
    private array $cells = [];
    /** @var string[] */
    private array $classes = [];
    /** @var array<string, string> property => value */
    private array $styles = [];
    /** @var array<string, string> attribute => value */
    private array $params = [];
    private ?Table $table = null;
    private int $rowIndex = 0;
    private bool $prettyPrint = false;
    private string $section = self::SECTION_BODY;

    public function addCell(Cell $cell): static
    {
        $cell->setRowIndex($this->getRowIndex())
            ->setColumnIndex(count($this->cells));
        $this->cells[] = $cell;
        return $this;
    }

    /**
     * Add several plain-text cells at once (escaped). Use $header = true for <th>.
     * @param array<int|string, scalar|null> $values
     */
    public function addCells(array $values, bool $header = false): static
    {
        foreach ($values as $value) {
            $cell = $header ? new Th('') : new Td('');
            $cell->setText($value === null ? '' : (string)$value);
            $this->addCell($cell);
        }
        return $this;
    }

    public function getCell(int $index): ?Cell
    {
        if (array_key_exists($index, $this->cells)) {
            return $this->cells[$index];
        } else {
            return null;
        }
    }

    /** @return Cell[] */
    public function getCells(): array
    {
        return $this->cells;
    }

    /** @param Cell[] $cells */
    public function setCells(array $cells): static
    {
        $i = 0;
        foreach ($cells as $cell) {
            $cell->setRowIndex($this->getRowIndex())
                ->setColumnIndex($i++);
        }

        $this->cells = $cells;
        return $this;
    }

    /** @return string[] */
    public function getClasses(): array
    {
        return $this->classes;
    }

    public function addClass(string $class): static
    {
        $this->classes[] = $class;
        return $this;
    }

    /** @param string[] $classes */
    public function setClasses(array $classes): static
    {
        $this->classes = $classes;
        return $this;
    }

    /** @return array<string, string> */
    public function getStyles(): array
    {
        return $this->styles;
    }

    public function addStyle(string $property, string $value): static
    {
        $this->styles[$property] = $value;
        return $this;
    }

    public function setStyles(array $styles): static
    {
        $this->styles = $styles;
        return $this;
    }

    public function addParam(string $property, string $value): static
    {
        $this->params[$property] = $value;
        return $this;
    }

    /** @return array<string, string> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function setRowIndex(int $rowIndex): static
    {
        $this->rowIndex = $rowIndex;
        foreach ($this->cells as $cell) {
            $cell->setRowIndex($rowIndex);
        }
        return $this;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    /** body (default), head or foot; set by Table::draw() */
    public function setSection(string $section): static
    {
        $this->section = in_array($section, [self::SECTION_BODY, self::SECTION_HEAD, self::SECTION_FOOT], true) ? $section : self::SECTION_BODY;
        return $this;
    }

    /** "tbl_3" for body rows, "tbl_head_0" / "tbl_foot_0" for the other sections; null when drawn standalone. */
    public function getHtmlId(): ?string
    {
        if ($this->getTable() === null) {
            return null;
        }
        $prefix = $this->section === self::SECTION_BODY ? '' : $this->section . '_';
        return $this->getTable()->getId() . '_' . $prefix . $this->getRowIndex();
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
        $html = "";
        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t\t";
        }
        $id = $this->getHtmlId();
        $html
            .= "<tr"
            . ($id !== null ? " id='" . Util::e($id) . "'" : '')
            . (count($this->getClasses()) > 0 ? " class='" . Util::e(join(' ', $this->getClasses())) . "'" : '')
            . (count($this->getStyles()) > 0 ? " style='" . join(';', $_style) . "'" : '')
            . (count($this->getParams()) > 0 ? ' ' . join(' ', $_params) : '')
            . '>';

        $column = 0;
        foreach ($this->getCells() as $cell) {
            if ($this->getTable() !== null) {
                $cell->setTable($this->getTable());
            }
            $cell->setRowIndex($this->getRowIndex())
                ->setColumnIndex($column++)
                ->setRowHtmlId($id)
                ->setPrettyPrint($this->isPrettyPrint());
            $html .= $cell->draw();
        }
        $html .= '</tr>';
        if ($this->isPrettyPrint()) {
            $html .= "\n";
        }
        return $html;
    }

    public function hasCells(): bool
    {
        return $this->getCellCount() > 0;
    }

    public function getCellCount(): int
    {
        return count($this->cells);
    }

    public function setTable(Table $table): static
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

    public function setPrettyPrint(bool $prettyPrint): static
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }
}
