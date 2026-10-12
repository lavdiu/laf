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

    /** first header row (legacy single-row API) */
    private Tr $thead;
    /** @var Tr[] additional header rows */
    private array $theadRows = [];
    /** @var Tr[] */
    private array $tbody = [];
    private Tr $tfoot;
    private string $caption = '';
    private string $id = '';
    private bool $prettyPrint = false;
    private bool $responsive = false;

    /** @var string[] */
    private array $classes = [];
    /** @var array<string, string> property => value */
    private array $styles = [];
    /** @var array<string, string> attribute => value */
    private array $params = [];

    public function __construct(?string $id = null)
    {
        $this->thead = new Tr();
        $this->tfoot = new Tr();
        $this->setId($id);
    }

    /* ───────────── builders ───────────── */

    /**
     * Build a table from plain data: every value is escaped text.
     * @param array<int, array<int|string, scalar|null>|scalar|null> $rows list of rows, each a list (or map) of cell values
     * @param array<int|string, scalar|null> $headers optional header labels
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

    /**
     * Append a body row of escaped text cells.
     * @param array<int|string, scalar|null> $values
     */
    public function addRow(array $values): static
    {
        $tr = new Tr();
        $tr->addCells($values);
        return $this->addTr($tr);
    }

    /** Bootstrap 5 look: striped, hoverable, compact, optionally wrapped in .table-responsive. */
    public function bootstrap(bool $striped = true, bool $responsive = true): static
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
    public function setResponsive(bool $responsive): static
    {
        $this->responsive = $responsive;
        return $this;
    }

    /* ───────────── legacy attribute API (kept; rendered together with the trait's) ───────────── */

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

    /* ───────────── sections ───────────── */

    public function getThead(): Tr
    {
        return $this->thead;
    }

    public function setThead(Tr $thead): static
    {
        $this->thead = $thead;
        return $this;
    }

    /**
     * All header rows: the legacy single row (when it has cells) followed by any added with addTheadRow().
     * @return Tr[]
     */
    public function getTheadRows(): array
    {
        $rows = [];
        if ($this->thead->hasCells()) {
            $rows[] = $this->thead;
        }
        return array_merge($rows, $this->theadRows);
    }

    /** @param Tr[] $rows replaces the extra header rows (the legacy setThead() row is kept) */
    public function setTheadRows(array $rows): static
    {
        $this->theadRows = array_values($rows);
        return $this;
    }

    public function addTheadRow(Tr $row): static
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
    public function setTbodyRows(array $tbody): static
    {
        $i = 0;
        foreach ($tbody as $tr) {
            $tr->setRowIndex($i++);
        }
        $this->tbody = $tbody;
        return $this;
    }

    public function addTr(Tr $tr): static
    {
        $tr->setRowIndex(count($this->tbody));
        $this->tbody[] = $tr;
        return $this;
    }

    public function getTfoot(): Tr
    {
        return $this->tfoot;
    }

    public function setTfoot(Tr $tfoot): static
    {
        $this->tfoot = $tfoot;
        return $this;
    }

    public function getCaption(): string
    {
        return $this->caption;
    }

    public function setCaption(string $caption): static
    {
        $this->caption = $caption;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** A null or empty id gets a generated one, so row and cell ids are always unique. */
    public function setId(?string $id): static
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

    /** @param Tr[] $rows */
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

    public function setPrettyPrint(bool $prettyPrint): static
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }
}
