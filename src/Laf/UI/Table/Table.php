<?php

namespace Laf\UI\Table;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * Server-rendered HTML table.
 *
 * It is a ComponentInterface, so it can be added to a Div, Card, TabItem or
 * AdminPage with addComponent(). The original addClass()/addStyle()/addParam()
 * API is kept and renders alongside the component-trait attributes
 * (addCssClass(), addCssStyleItem(), addAttribute()).
 */
class Table implements ComponentInterface
{
    use ComponentTrait;

    /** @var Tr first header row (legacy single-row API) */
    private $thead = null;
    /** @var Tr[] additional header rows */
    private $theadRows = [];
    /** @var Tr[] */
    private $tbody = [];
    /** @var Tr */
    private $tfoot = null;
    private $caption = "";
    private $id = "";
    private $prettyPrint = false;
    private $responsive = false;

    private $classes = [];
    private $styles = [];
    private $params = [];

    public function __construct(?string $id = null)
    {
        $this->thead = new Tr;
        $this->tfoot = new Tr;
        $this->setId($id);
    }

    /* ───────────── builders ───────────── */

    /**
     * Build a table from plain data: every value is escaped text.
     * @param array $rows list of rows, each a list (or map) of cell values
     * @param array $headers optional header labels
     */
    public static function fromArray(array $rows, array $headers = [], ?string $id = null): Table
    {
        $table = new Table($id);
        if (count($headers) > 0) {
            $table->getThead()->addCells(array_values($headers), true);
        }
        foreach ($rows as $row) {
            $table->addRow(is_array($row) ? array_values($row) : [$row]);
        }
        return $table;
    }

    /** Append a body row of escaped text cells. */
    public function addRow(array $values): Table
    {
        $tr = new Tr();
        $tr->addCells($values);
        return $this->addTr($tr);
    }

    /** Bootstrap 5 look: striped, hoverable, compact, optionally wrapped in .table-responsive. */
    public function bootstrap(bool $striped = true, bool $responsive = true): Table
    {
        $this->addClass('table');
        if ($striped) {
            $this->addClass('table-striped');
        }
        $this->addClass('table-hover')->addClass('table-sm');
        $this->setResponsive($responsive);
        return $this;
    }

    public function isResponsive(): bool
    {
        return $this->responsive;
    }

    /** Wrap the <table> in <div class='table-responsive'> so wide tables scroll on phones. */
    public function setResponsive(bool $responsive): Table
    {
        $this->responsive = $responsive;
        return $this;
    }

    /* ───────────── legacy attribute API (kept; rendered together with the trait's) ───────────── */

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function addClass(string $class): Table
    {
        $this->classes[] = $class;
        return $this;
    }

    public function setClasses(array $classes): Table
    {
        $this->classes = $classes;
        return $this;
    }

    public function getStyles(): array
    {
        return $this->styles;
    }

    public function addStyle(string $property, string $value): Table
    {
        $this->styles[$property] = $value;
        return $this;
    }

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

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): Table
    {
        $this->params = $params;
        return $this;
    }

    /* ───────────── sections ───────────── */

    public function getThead(): Tr
    {
        return $this->thead;
    }

    public function setThead(Tr $thead): Table
    {
        $this->thead = $thead;
        return $this;
    }

    /** All header rows: the legacy single row (when it has cells) followed by any added with addTheadRow(). */
    public function getTheadRows(): array
    {
        $rows = [];
        if ($this->thead->hasCells()) {
            $rows[] = $this->thead;
        }
        return array_merge($rows, $this->theadRows);
    }

    /** @param Tr[] $rows replaces the extra header rows (the legacy setThead() row is kept) */
    public function setTheadRows(array $rows): Table
    {
        $this->theadRows = array_values($rows);
        return $this;
    }

    public function addTheadRow(Tr $row): Table
    {
        $this->theadRows[] = $row;
        return $this;
    }

    /** @return Tr[] */
    public function getTbodyRows(): array
    {
        return $this->tbody;
    }

    /** @param Tr[] $tbody */
    public function setTbodyRows(array $tbody): Table
    {
        $i = 0;
        foreach ($tbody as $tr) {
            $tr->setRowIndex($i++);
        }
        $this->tbody = $tbody;
        return $this;
    }

    public function addTr(Tr $tr): Table
    {
        $tr->setRowIndex(count($this->tbody));
        $this->tbody[] = $tr;
        return $this;
    }

    public function getTfoot(): Tr
    {
        return $this->tfoot;
    }

    public function setTfoot(Tr $tfoot): Table
    {
        $this->tfoot = $tfoot;
        return $this;
    }

    public function getCaption(): string
    {
        return $this->caption;
    }

    public function setCaption(string $caption): Table
    {
        $this->caption = $caption;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** A null or empty id gets a generated one, so row and cell ids are always unique. */
    public function setId(?string $id): Table
    {
        if ($id === null || trim($id) === '') {
            $id = 'tbl_' . substr(md5(uniqid('', true)), 0, 8);
        }
        $this->id = $id;
        return $this;
    }

    /* ───────────── rendering ───────────── */

    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    private function drawSection(string $tag, string $section, array $rows): string
    {
        if (count($rows) === 0) {
            return '';
        }
        $html = '';
        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t";
        }
        $html .= '<' . $tag . '>';
        $i = 0;
        foreach ($rows as $row) {
            $row->setTable($this)
                ->setSection($section)
                ->setPrettyPrint($this->isPrettyPrint());
            if ($section !== Tr::SECTION_BODY) {
                $row->setRowIndex($i++);
            }
            $html .= $row->draw();
        }
        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t";
        }
        $html .= '</' . $tag . '>';
        return $html;
    }

    public function draw(): string
    {
        $classes = array_merge($this->getClasses(), $this->getCssClasses());
        $_style = [];
        foreach ($this->getStyles() as $k => $v) {
            $_style[] = Util::e($k) . ':' . Util::e($v);
        }
        $traitStyle = trim((string)$this->getCssStyleForHtml());
        if ($traitStyle !== '') {
            $_style[] = rtrim($traitStyle, ';');
        }
        $_params = [];
        foreach (array_merge($this->getAttributes(), $this->getParams()) as $k => $v) {
            if ($k === 'id' || $k === 'class' || $k === 'style') {
                continue;
            }
            $_params[] = Util::e($k) . "='" . Util::e($v) . "'";
        }

        $html = "";
        if ($this->isPrettyPrint()) {
            $html .= "\n\t";
        }
        if ($this->isResponsive()) {
            $html .= "<div class='table-responsive'>";
        }
        $html
            .= "<table"
            . " id='" . Util::e($this->getId()) . "'"
            . (count($classes) > 0 ? " class='" . Util::e(join(' ', array_unique($classes))) . "'" : '')
            . (count($_style) > 0 ? " style='" . join(';', $_style) . "'" : '')
            . (count($_params) > 0 ? ' ' . join(' ', $_params) : '')
            . '>';

        if ($this->getCaption() !== '') {
            $html .= '<caption>' . Util::e($this->getCaption()) . '</caption>';
        }

        $html .= $this->drawSection('thead', Tr::SECTION_HEAD, $this->getTheadRows());

        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t";
        }
        $html .= '<tbody>';
        foreach ($this->getTbodyRows() as $row) {
            $row->setTable($this)
                ->setSection(Tr::SECTION_BODY)
                ->setPrettyPrint($this->isPrettyPrint());
            $html .= $row->draw();
        }
        if ($this->isPrettyPrint()) {
            $html .= "\n\t\t";
        }
        $html .= '</tbody>';

        $html .= $this->drawSection('tfoot', Tr::SECTION_FOOT, $this->getTfoot()->hasCells() ? [$this->getTfoot()] : []);

        if ($this->isPrettyPrint()) {
            $html .= "\n\t";
        }
        $html .= '</table>';
        if ($this->isResponsive()) {
            $html .= '</div>';
        }

        if ($this->isPrettyPrint()) {
            $html .= "\n\t<!-- ENDOF Table {$this->getId()}-->\n\n";
        }

        return $html;
    }

    public function __toString(): string
    {
        return $this->draw();
    }

    public function isPrettyPrint(): bool
    {
        return $this->prettyPrint;
    }

    public function setPrettyPrint(bool $prettyPrint): Table
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }
}
