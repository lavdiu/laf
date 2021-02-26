<?php


namespace Laf\UI\Table;


class Table
{
    private $thead = null;
    private $tbody = [];
    private $tfoot = null;
    private $caption = "";
    private $id = "";
    private $prettyPrint = false;


    private $classes = [];
    private $styles = [];
    private $params = [];

    public function __construct(string $id = null)
    {
        $this->thead = new Tr;
        $this->tfoot = new Tr;
        $this->setId($id);
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
     * @return Table
     */
    public function addClass(string $class): Table
    {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * @param array $classes
     * @return Table
     */
    public function setClasses(array $classes): Table
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
     * @return Table
     */
    public function addStyle(string $property, string $value): Table
    {
        $this->styles[$property] = $value;
        return $this;
    }

    /**
     * @param array $styles
     * @return Table
     */
    public function setStyles(array $styles): Table
    {
        $this->styles = $styles;
        return $this;
    }

    public function addParam(string $property, string $value): Table
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
     * @return Table
     */
    public function setParams(array $params): Table
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return Tr
     */
    public function getThead(): Tr
    {
        return $this->thead;
    }

    /**
     * @param Tr $thead
     * @return Table
     */
    public function setThead(Tr $thead): Table
    {
        $this->thead = $thead;
        return $this;
    }

    /**
     * @return Tr[]
     */
    public function getTbodyRows(): array
    {
        return $this->tbody;
    }

    /**
     * @param Tr[] $tbody
     * @return Table
     */
    public function setTbodyRows(array $tbody): Table
    {
        $i = 0;
        foreach ($tbody as $tr) {
            $tr->setRowIndex($i++);
        }
        $this->tbody = $tbody;
        return $this;
    }

    public function addTr(Tr $tr)
    {
        $tr->setRowIndex(count($this->tbody));
        $this->tbody[] = $tr;
    }

    /**
     * @return Tr
     */
    public function getTfoot(): Tr
    {
        return $this->tfoot;
    }

    /**
     * @param Tr $tfoot
     * @return Table
     */
    public function setTfoot(Tr $tfoot): Table
    {
        $this->tfoot = $tfoot;
        return $this;
    }

    /**
     * @return string
     */
    public function getCaption(): string
    {
        return $this->caption;
    }

    /**
     * @param string $caption
     * @return Table
     */
    public function setCaption(string $caption): Table
    {
        $this->caption = $caption;
        return $this;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return Table
     */
    public function setId(string $id): Table
    {
        $this->id = $id;
        return $this;
    }


    public function draw(): string
    {
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
            $html .= "\n\t";
        }
        $html
            .= "<table"
            . " id='{$this->getId()}'"
            . (count($this->getClasses()) > 0 ? " class='" . join(' ', $this->getClasses()) . "'" : '')
            . (count($this->getStyles()) > 0 ? " style='" . join(';', $_style) . "'" : '')
            . (count($this->getParams()) > 0 ? ' ' . join(' ', $_params) : '')
            . '>';

        if ($this->getThead()->hasCells()) {
            $this->getThead()->setTable($this)
                ->setPrettyPrint($this->isPrettyPrint());

            if ($this->isPrettyPrint()) {
                $html .= "\n\t\t";
            }

            $html .= '<thead>';
            $html .= $this->getThead()->draw();
            if ($this->isPrettyPrint()) {
                $html .= "\n\t\t";
            }
            $html .= '</thead>';
        }

        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t";
        }
        $html .= '<tbody>';
        foreach ($this->getTbodyRows() as $row) {
            $row->setTable($this)
                ->setPrettyPrint($this->isPrettyPrint());
            $html .= $row->draw();
        }
        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t";
        }
        $html .= '</tbody>';

        if ($this->getTfoot()->hasCells()) {
            $this->getTfoot()->setTable($this)
                ->setPrettyPrint($this->isPrettyPrint());
            if ($this->isPrettyPrint()) {
                $html .= "\n\t\t";
            }
            $html .= '<tfoot>';
            $html .= $this->getTfoot()->draw();
            if ($this->isPrettyPrint()) {
                $html .= "\n\t\t";
            }
            $html .= '</tfoot>';
        }

        if ($this->isPrettyPrint()) {
            $html .= "\n\t";
        }
        $html .= '</table>';

        if ($this->isPrettyPrint()) {
            $html .= "\n\t<!-- ENDOF Table {$this->getId()}-->\n\n";
        }

        return $html;
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
     * @return Table
     */
    public function setPrettyPrint(bool $prettyPrint): Table
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }


}