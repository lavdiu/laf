<?php


namespace Laf\UI\Table;


class Tr
{
    private $cells = [];
    private $classes = [];
    private $styles = [];
    private $params = [];
    private $table = null;
    private $rowIndex = 0;
    private $prettyPrint = false;

    /**
     * Cell constructor.
     */
    public function __construct()
    {

    }

    /**
     * @param Cell $cell
     * @return Tr
     */
    public function addCell(Cell $cell): Tr
    {
        $cell->setRowIndex($this->getRowIndex())
            ->setColumnIndex(count($this->cells));
        $this->cells[] = $cell;
        return $this;
    }

    /**
     * @param int $index
     * @return Cell|null
     */
    public function getCell(int $index): ?Cell
    {
        if (array_key_exists($index, $this->cells)) {
            return $this->cells[$index];
        } else {
            return null;
        }
    }

    /**
     * @return Cell[]
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * @param Cell[] $cells
     * @return Tr
     */
    public function setCells(array $cells): Tr
    {
        $i = 0;
        foreach ($cells as $cell) {
            $cell->setRowIndex($this->getRowIndex())
                ->setColumnIndex($i++);
        }

        $this->cells = $cells;
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
     * @return Tr
     */
    public function addClass(string $class): Tr
    {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * @param array $classes
     * @return Tr
     */
    public function setClasses(array $classes): Tr
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
     * @return Tr
     */
    public function addStyle(string $property, string $value): Tr
    {
        $this->styles[$property] = $value;
        return $this;
    }

    /**
     * @param array $styles
     * @return Tr
     */
    public function setStyles(array $styles): Tr
    {
        $this->styles = $styles;
        return $this;
    }

    public function addParam(string $property, string $value): Tr
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
     * @return Tr
     */
    public function setParams(array $params): Tr
    {
        $this->params = $params;
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
     * @return Tr
     */
    public function setRowIndex(int $rowIndex): Tr
    {
        $this->rowIndex = $rowIndex;
        return $this;
    }

    /**
     * @return string
     */
    public function draw(): string
    {
        /*if(!$this->hasCells()){
            return "";
        }*/


        $_style = [];
        foreach ($this->getStyles() as $k => $v) {
            $_style[] = $k . ':' . $v;
        }
        $_params = [];
        foreach ($this->getParams() as $k => $v) {
            $_params[] = "{$k}='{$v}'";
        }
        $html = "";
        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t\t";
        }
        $html
            .= "<tr"
            . " id='{$this->getTable()->getId()}_{$this->getRowIndex()}'"
            . (count($this->getClasses()) > 0 ? " class='" . join(' ', $this->getClasses()) . "'" : '')
            . (count($this->getStyles()) > 0 ? " style='" . join(';', $_style) . "'" : '')
            . (count($this->getParams()) > 0 ? ' ' . join(' ', $_params) : '')
            . '>';

        foreach ($this->getCells() as $cell) {
            $cell->setTable($this->getTable())
                ->setPrettyPrint($this->isPrettyPrint());
            $html .= $cell->draw();
        }
        $html .= '</tr>';
        if ($this->isPrettyPrint()) {
            $html .= "\n";
        }
        return $html;
    }


    /**
     * @return bool
     */
    public function hasCells(): bool
    {
        return $this->getCellCount() > 0;
    }

    /**
     * @return int
     */
    public function getCellCount(): int
    {
        return count($this->cells);
    }

    /**
     * @param Table $table
     * @return Tr
     */
    public function setTable(Table $table): Tr
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return Table
     */
    public function getTable(): ?Table
    {
        return $this->table;
    }

    /**
     * @return bool
     */
    public function isPrettyPrint(): bool
    {
        return $this->prettyPrint;
    }

    /**
     * @param bool $prettyPrint
     * @return Tr
     */
    public function setPrettyPrint(bool $prettyPrint): Tr
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }

}