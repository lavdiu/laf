<?php

use Laf\UI\Table\Cell;
use Laf\UI\Table\Td;
use Laf\UI\Table\Th;
use Laf\UI\Table\Tr;
use Laf\UI\Table\Table;
use PHPUnit\Framework\TestCase;

class TableComponentTest extends TestCase
{
    // ── Cell ──

    /** @test */
    public function cell_stores_data(): void
    {
        $cell = new Td('Hello');
        $this->assertEquals('Hello', $cell->getData());
    }

    /** @test */
    public function cell_set_data(): void
    {
        $cell = new Td('Initial');
        $cell->setData('Updated');
        $this->assertEquals('Updated', $cell->getData());
    }

    /** @test */
    public function cell_colspan(): void
    {
        $cell = new Td('Wide');
        $this->assertEquals(0, $cell->getColSpan());

        $cell->setColSpan(3);
        $this->assertEquals(3, $cell->getColSpan());
    }

    /** @test */
    public function cell_rowspan(): void
    {
        $cell = new Td('Tall');
        $this->assertEquals(0, $cell->getRowSpan());

        $cell->setRowSpan(2);
        $this->assertEquals(2, $cell->getRowSpan());
    }

    /** @test */
    public function cell_classes(): void
    {
        $cell = new Td('Styled');
        $this->assertEmpty($cell->getClasses());

        $cell->addClass('highlight');
        $cell->addClass('bold');
        $this->assertCount(2, $cell->getClasses());
        $this->assertContains('highlight', $cell->getClasses());
    }

    /** @test */
    public function cell_set_classes_replaces(): void
    {
        $cell = new Td('Data');
        $cell->addClass('old');
        $cell->setClasses(['new1', 'new2']);
        $this->assertCount(2, $cell->getClasses());
        $this->assertNotContains('old', $cell->getClasses());
    }

    /** @test */
    public function cell_styles(): void
    {
        $cell = new Td('Data');
        $this->assertEmpty($cell->getStyles());

        $cell->setStyles(['color' => 'red', 'font-weight' => 'bold']);
        $this->assertCount(2, $cell->getStyles());
        $this->assertEquals('red', $cell->getStyles()['color']);
    }

    /** @test */
    public function cell_params(): void
    {
        $cell = new Td('Data');
        $cell->addParam('data-id', '123');
        $this->assertEquals('123', $cell->getParams()['data-id']);

        $cell->setParams(['title' => 'Info']);
        $this->assertCount(1, $cell->getParams());
    }

    /** @test */
    public function cell_index_tracking(): void
    {
        $cell = new Td('Data');
        $cell->setColumnIndex(2);
        $cell->setRowIndex(5);

        $this->assertEquals(2, $cell->getColumnIndex());
        $this->assertEquals(5, $cell->getRowIndex());
    }

    /** @test */
    public function cell_draw_produces_td_tag(): void
    {
        $table = new Table('tbl');
        $cell = new Td('Content');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);

        $html = $cell->draw();

        $this->assertStringContainsString('<td', $html);
        $this->assertStringContainsString('</td>', $html);
        $this->assertStringContainsString('Content', $html);
    }

    /** @test */
    public function cell_draw_includes_colspan(): void
    {
        $table = new Table('tbl');
        $cell = new Td('Wide');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);
        $cell->setColSpan(3);

        $html = $cell->draw();
        $this->assertStringContainsString("colspan='3'", $html);
    }

    /** @test */
    public function cell_draw_includes_rowspan(): void
    {
        $table = new Table('tbl');
        $cell = new Td('Tall');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);
        $cell->setRowSpan(2);

        $html = $cell->draw();
        $this->assertStringContainsString("rowspan='2'", $html);
    }

    /** @test */
    public function cell_draw_includes_classes(): void
    {
        $table = new Table('tbl');
        $cell = new Td('Styled');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);
        $cell->addClass('highlight');

        $html = $cell->draw();
        $this->assertStringContainsString("class='highlight'", $html);
    }

    /** @test */
    public function cell_draw_includes_styles(): void
    {
        $table = new Table('tbl');
        $cell = new Td('Colored');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);
        $cell->setStyles(['color' => 'red']);

        $html = $cell->draw();
        $this->assertStringContainsString("style='color:red'", $html);
    }

    /** @test */
    public function cell_draw_includes_params(): void
    {
        $table = new Table('tbl');
        $cell = new Td('Data');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);
        $cell->addParam('data-value', '42');

        $html = $cell->draw();
        $this->assertStringContainsString("data-value='42'", $html);
    }

    /** @test */
    public function cell_pretty_print(): void
    {
        $cell = new Td('Data');
        $this->assertFalse($cell->isPrettyPrint());

        $cell->setPrettyPrint(true);
        $this->assertTrue($cell->isPrettyPrint());
    }

    // ── Th ──

    /** @test */
    public function th_renders_th_tag(): void
    {
        $table = new Table('tbl');
        $cell = new Th('Header');
        $cell->setTable($table);
        $cell->setRowIndex(0);
        $cell->setColumnIndex(0);

        $html = $cell->draw();
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('</th>', $html);
        $this->assertStringContainsString('Header', $html);
    }

    // ── Tr ──

    /** @test */
    public function tr_add_and_get_cells(): void
    {
        $tr = new Tr();
        $this->assertFalse($tr->hasCells());
        $this->assertEquals(0, $tr->getCellCount());

        $tr->addCell(new Td('Cell 1'));
        $tr->addCell(new Td('Cell 2'));

        $this->assertTrue($tr->hasCells());
        $this->assertEquals(2, $tr->getCellCount());
    }

    /** @test */
    public function tr_get_cell_by_index(): void
    {
        $tr = new Tr();
        $tr->addCell(new Td('First'));
        $tr->addCell(new Td('Second'));

        $this->assertEquals('First', $tr->getCell(0)->getData());
        $this->assertEquals('Second', $tr->getCell(1)->getData());
        $this->assertNull($tr->getCell(99));
    }

    /** @test */
    public function tr_set_cells_replaces(): void
    {
        $tr = new Tr();
        $tr->addCell(new Td('Old'));

        $tr->setCells([new Td('New1'), new Td('New2')]);
        $this->assertEquals(2, $tr->getCellCount());
        $this->assertEquals('New1', $tr->getCell(0)->getData());
    }

    /** @test */
    public function tr_assigns_column_indices_to_cells(): void
    {
        $tr = new Tr();
        $tr->addCell(new Td('A'));
        $tr->addCell(new Td('B'));
        $tr->addCell(new Td('C'));

        $this->assertEquals(0, $tr->getCell(0)->getColumnIndex());
        $this->assertEquals(1, $tr->getCell(1)->getColumnIndex());
        $this->assertEquals(2, $tr->getCell(2)->getColumnIndex());
    }

    /** @test */
    public function tr_classes(): void
    {
        $tr = new Tr();
        $tr->addClass('striped');
        $this->assertContains('striped', $tr->getClasses());

        $tr->setClasses(['new-class']);
        $this->assertCount(1, $tr->getClasses());
    }

    /** @test */
    public function tr_styles(): void
    {
        $tr = new Tr();
        $tr->addStyle('background', '#eee');
        $this->assertEquals('#eee', $tr->getStyles()['background']);

        $tr->setStyles(['color' => 'blue']);
        $this->assertCount(1, $tr->getStyles());
    }

    /** @test */
    public function tr_params(): void
    {
        $tr = new Tr();
        $tr->addParam('data-row', '1');
        $this->assertEquals('1', $tr->getParams()['data-row']);

        $tr->setParams([]);
        $this->assertEmpty($tr->getParams());
    }

    /** @test */
    public function tr_row_index(): void
    {
        $tr = new Tr();
        $tr->setRowIndex(3);
        $this->assertEquals(3, $tr->getRowIndex());
    }

    /** @test */
    public function tr_draw_produces_tr_tag(): void
    {
        $table = new Table('tbl');
        $tr = new Tr();
        $tr->setTable($table);
        $tr->addCell(new Td('Data'));

        $html = $tr->draw();
        $this->assertStringContainsString('<tr', $html);
        $this->assertStringContainsString('</tr>', $html);
        $this->assertStringContainsString('Data', $html);
    }

    /** @test */
    public function tr_draw_includes_classes(): void
    {
        $table = new Table('tbl');
        $tr = new Tr();
        $tr->setTable($table);
        $tr->addClass('highlight');
        $tr->addCell(new Td('Data'));

        $html = $tr->draw();
        $this->assertStringContainsString("class='highlight'", $html);
    }

    /** @test */
    public function tr_pretty_print(): void
    {
        $tr = new Tr();
        $this->assertFalse($tr->isPrettyPrint());

        $tr->setPrettyPrint(true);
        $this->assertTrue($tr->isPrettyPrint());
    }

    // ── Table ──

    /** @test */
    public function table_constructor_sets_id(): void
    {
        $table = new Table('my_table');
        $this->assertEquals('my_table', $table->getId());
    }

    /** @test */
    public function table_has_empty_thead_and_tfoot_by_default(): void
    {
        $table = new Table('tbl');
        $this->assertInstanceOf(Tr::class, $table->getThead());
        $this->assertInstanceOf(Tr::class, $table->getTfoot());
        $this->assertFalse($table->getThead()->hasCells());
        $this->assertFalse($table->getTfoot()->hasCells());
    }

    /** @test */
    public function table_add_rows(): void
    {
        $table = new Table('tbl');
        $this->assertEmpty($table->getTbodyRows());

        $tr1 = new Tr();
        $tr1->addCell(new Td('Row 1'));
        $table->addTr($tr1);

        $tr2 = new Tr();
        $tr2->addCell(new Td('Row 2'));
        $table->addTr($tr2);

        $this->assertCount(2, $table->getTbodyRows());
    }

    /** @test */
    public function table_add_tr_assigns_row_index(): void
    {
        $table = new Table('tbl');

        $tr1 = new Tr();
        $tr1->addCell(new Td('A'));
        $table->addTr($tr1);

        $tr2 = new Tr();
        $tr2->addCell(new Td('B'));
        $table->addTr($tr2);

        $rows = $table->getTbodyRows();
        $this->assertEquals(0, $rows[0]->getRowIndex());
        $this->assertEquals(1, $rows[1]->getRowIndex());
    }

    /** @test */
    public function table_set_tbody_rows(): void
    {
        $table = new Table('tbl');
        $tr1 = new Tr();
        $tr1->addCell(new Td('A'));
        $tr2 = new Tr();
        $tr2->addCell(new Td('B'));

        $table->setTbodyRows([$tr1, $tr2]);
        $this->assertCount(2, $table->getTbodyRows());
        $this->assertEquals(0, $tr1->getRowIndex());
        $this->assertEquals(1, $tr2->getRowIndex());
    }

    /** @test */
    public function table_classes(): void
    {
        $table = new Table('tbl');
        $table->addClass('table');
        $table->addClass('table-striped');

        $this->assertCount(2, $table->getClasses());
        $this->assertContains('table', $table->getClasses());
    }

    /** @test */
    public function table_styles(): void
    {
        $table = new Table('tbl');
        $table->addStyle('width', '100%');

        $this->assertEquals('100%', $table->getStyles()['width']);
    }

    /** @test */
    public function table_params(): void
    {
        $table = new Table('tbl');
        $table->addParam('data-page', '1');

        $this->assertEquals('1', $table->getParams()['data-page']);
    }

    /** @test */
    public function table_caption(): void
    {
        $table = new Table('tbl');
        $this->assertEquals('', $table->getCaption());

        $table->setCaption('My Table');
        $this->assertEquals('My Table', $table->getCaption());
    }

    /** @test */
    public function table_draw_produces_table_html(): void
    {
        $table = new Table('tbl');
        $tr = new Tr();
        $tr->addCell(new Td('Cell Data'));
        $table->addTr($tr);

        $html = $table->draw();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringContainsString('</tbody>', $html);
        $this->assertStringContainsString('Cell Data', $html);
        $this->assertStringContainsString("id='tbl'", $html);
    }

    /** @test */
    public function table_draw_with_thead(): void
    {
        $table = new Table('tbl');
        $thead = new Tr();
        $thead->addCell(new Th('Column 1'));
        $thead->addCell(new Th('Column 2'));
        $table->setThead($thead);

        $tr = new Tr();
        $tr->addCell(new Td('A'));
        $tr->addCell(new Td('B'));
        $table->addTr($tr);

        $html = $table->draw();

        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('</thead>', $html);
        $this->assertStringContainsString('Column 1', $html);
        $this->assertStringContainsString('Column 2', $html);
    }

    /** @test */
    public function table_draw_with_tfoot(): void
    {
        $table = new Table('tbl');
        $tfoot = new Tr();
        $tfoot->addCell(new Td('Total'));
        $table->setTfoot($tfoot);

        $tr = new Tr();
        $tr->addCell(new Td('Data'));
        $table->addTr($tr);

        $html = $table->draw();

        $this->assertStringContainsString('<tfoot>', $html);
        $this->assertStringContainsString('</tfoot>', $html);
        $this->assertStringContainsString('Total', $html);
    }

    /** @test */
    public function table_draw_without_thead_skips_thead(): void
    {
        $table = new Table('tbl');
        $tr = new Tr();
        $tr->addCell(new Td('Data'));
        $table->addTr($tr);

        $html = $table->draw();

        $this->assertStringNotContainsString('<thead>', $html);
    }

    /** @test */
    public function table_draw_includes_classes_and_styles(): void
    {
        $table = new Table('tbl');
        $table->addClass('table-bordered');
        $table->addStyle('width', '100%');

        $tr = new Tr();
        $tr->addCell(new Td('X'));
        $table->addTr($tr);

        $html = $table->draw();

        $this->assertStringContainsString('table-bordered', $html);
        $this->assertStringContainsString('width:100%', $html);
    }

    /** @test */
    public function table_pretty_print(): void
    {
        $table = new Table('tbl');
        $this->assertFalse($table->isPrettyPrint());

        $table->setPrettyPrint(true);
        $this->assertTrue($table->isPrettyPrint());

        $tr = new Tr();
        $tr->addCell(new Td('Data'));
        $table->addTr($tr);

        $html = $table->draw();
        $this->assertStringContainsString("<!-- ENDOF Table tbl-->", $html);
    }

    /** @test */
    public function table_full_render_with_all_sections(): void
    {
        $table = new Table('full_table');
        $table->addClass('table');

        $thead = new Tr();
        $thead->addCell(new Th('Name'));
        $thead->addCell(new Th('Age'));
        $table->setThead($thead);

        $tr1 = new Tr();
        $tr1->addCell(new Td('Alice'));
        $tr1->addCell(new Td('30'));
        $table->addTr($tr1);

        $tr2 = new Tr();
        $tr2->addCell(new Td('Bob'));
        $tr2->addCell(new Td('25'));
        $table->addTr($tr2);

        $tfoot = new Tr();
        $tfoot->addCell(new Td('Total'));
        $tfoot->addCell(new Td('2'));
        $table->setTfoot($tfoot);

        $html = $table->draw();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringContainsString('<tfoot>', $html);
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Bob', $html);
        $this->assertStringContainsString('Total', $html);
    }
}
