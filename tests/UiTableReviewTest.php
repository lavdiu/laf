<?php

declare(strict_types=1);

use Laf\UI\Table\Cell;
use Laf\UI\Table\Table;
use Laf\UI\Table\Td;
use Laf\UI\Table\Th;
use Laf\UI\Table\Tr;
use PHPUnit\Framework\TestCase;

/**
 * UI/Table typing pass (2026-10-12): typed properties, static return types on the fluent setters,
 * nullable cell data, the tag name accessor. The markup itself is pinned by UiTableTest.
 */
class UiTableReviewTest extends TestCase
{
    /** @test */
    public function fluent_setters_return_the_subclass(): void
    {
        $th = (new Th('x'))->addClass('a')->setColSpan(2)->alignRight();
        $this->assertInstanceOf(Th::class, $th);
        $this->assertSame('th', $th->getTagName());
        $this->assertSame('td', (new Td())->getTagName());
        $this->assertSame("<th colspan='2' class='a text-end'>x</th>", $th->draw());
    }

    /** @test */
    public function cell_data_is_nullable_and_text_is_escaped(): void
    {
        $cell = new Cell();
        $this->assertSame('', $cell->getData());
        $cell->setData(null);
        $this->assertNull($cell->getData());
        $this->assertSame('<td></td>', $cell->draw());
        $cell->setText('<b>');
        $this->assertSame('&lt;b&gt;', $cell->getData());
        $cell->setText(null);
        $this->assertSame('', $cell->getData());
    }

    /** @test */
    public function rows_and_tables_keep_their_state_typed(): void
    {
        $tr = (new Tr())->addCells([1, null, 'c'])->setSection('nope');
        $this->assertSame(Tr::SECTION_BODY, $tr->getSection(), 'unknown section falls back to body');
        $this->assertSame(3, $tr->getCellCount());
        $this->assertNull($tr->getCell(3));
        $this->assertNull($tr->getTable());
        $this->assertNull($tr->getHtmlId(), 'no table, no id');

        $table = Table::fromArray([[1, 2]], ['a', 'b'], 'tbl');
        $this->assertSame('tbl', $table->getId());
        $this->assertSame('', $table->getCaption());
        $this->assertFalse($table->isResponsive());
        $this->assertCount(1, $table->getTheadRows());
        $this->assertCount(1, $table->getTbodyRows());
        $this->assertSame(
            "<table id='tbl'><thead><tr id='tbl_head_0'><th id='tbl_head_0_0'>a</th><th id='tbl_head_0_1'>b</th></tr></thead>"
            . "<tbody><tr id='tbl_0'><td id='tbl_0_0'>1</td><td id='tbl_0_1'>2</td></tr></tbody></table>",
            $table->draw()
        );
        $this->assertInstanceOf(Table::class, $table->setCaption('c')->setResponsive(true)->bootstrap());
        $this->assertStringStartsWith("<div class='table-responsive'><table id='tbl' class='table table-striped table-hover table-sm'><caption>c</caption>", $table->draw());
    }
}
