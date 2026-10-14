<?php

declare(strict_types=1);

use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Input\Autocomplete;
use Laf\Util\Settings;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * Autocomplete markup, search over static options and a lookup callback, the lookup request a
 * form answers, and the threshold switch, without a database.
 */
class AutocompleteTest extends TestCase
{
    protected function setUp(): void
    {
        Autocomplete::resetRequestState();
        Autocomplete::setInlineScript(true);
    }

    protected function tearDown(): void
    {
        Autocomplete::resetRequestState();
        Autocomplete::setInlineScript(true);
        Settings::remove('form.select_autocomplete_threshold');
    }

    /** DummyTable with parent_id drawn as an Autocomplete over static options (no database lookup) */
    private function form(): array
    {
        $row = new DummyTable();
        $field = $row->getParentIdFld();
        $field->setFormElementClass('Autocomplete');
        /** @var Autocomplete $element */
        $element = $field->getFormElement();
        $element->setOptions(['1' => 'Alpha', '2' => 'Beta <b>', '3' => 'Alphabet', '10' => 'Gamma']);
        return [$row, $row->getForm(DrawMode::UPDATE), $element];
    }

    /** @test */
    public function the_column_comment_or_layout_name_resolves_to_the_class()
    {
        [, , $element] = $this->form();
        $this->assertInstanceOf(Autocomplete::class, $element);
        $this->assertInstanceOf(\Laf\UI\Form\Input\Select::class, $element, 'accepted for a foreign key');
        $this->assertSame('dummy_table.parent_id', $element->getLookupKey());
    }

    /** @test */
    public function it_posts_the_key_in_a_hidden_input_and_shows_the_label()
    {
        [$row, , $element] = $this->form();
        $row->getParentIdFld()->setValueRaw('2');
        $element->setDrawMode(DrawMode::UPDATE);
        $html = (string)$element->draw();

        $id = (string)$element->getId();
        $name = (string)$element->getName();
        $this->assertStringContainsString('type="hidden" class="laf-autocomplete-value" id="' . $id . '_value" name="' . $name . '" value="2"', $html);
        $this->assertStringContainsString('value="Beta &lt;b&gt;"', $html, 'the label is escaped');
        $this->assertSame(1, substr_count($html, 'name="' . $name . '"'), 'only the hidden input posts');
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-controls="' . $id . '_listbox"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('data-laf-autocomplete="dummy_table.parent_id"', $html);
        $this->assertStringContainsString('data-min-chars="1"', $html);
        $this->assertStringContainsString('<script>', $html);
        $this->assertStringNotContainsString('<select', $html);

        $again = (string)$element->draw();
        $this->assertStringNotContainsString('<script>', $again, 'the script is printed once per request');
    }

    /** @test */
    public function the_script_can_be_left_out_and_served_by_the_page()
    {
        Autocomplete::setInlineScript(false);
        [, , $element] = $this->form();
        $element->setDrawMode(DrawMode::UPDATE);
        $this->assertStringNotContainsString('<script>', (string)$element->draw());
        $this->assertFileExists(Autocomplete::getScriptPath());
        $this->assertStringContainsString('window.LafAutocomplete', Autocomplete::getScript());
    }

    /** @test */
    public function view_mode_shows_the_label()
    {
        [$row, , $element] = $this->form();
        $row->getParentIdFld()->setValueRaw('10');
        $this->assertSame('Gamma', $element->getDisplayValue());
    }

    /** @test */
    public function static_options_are_searched_case_insensitively_with_a_limit()
    {
        [, , $element] = $this->form();
        $this->assertSame([
            'results' => [['value' => '1', 'label' => 'Alpha'], ['value' => '3', 'label' => 'Alphabet']],
            'more' => false,
        ], $element->search('alp'));
        $one = $element->search('alp', 1);
        $this->assertCount(1, $one['results']);
        $this->assertTrue($one['more']);
        $this->assertCount(4, $element->search('')['results']);
    }

    /** @test */
    public function a_lookup_callback_replaces_the_table()
    {
        $calls = [];
        $element = (new Autocomplete())->setLimit(2)->setLookupCallback(function (string $term, int $limit) use (&$calls) {
            $calls[] = [$term, $limit];
            return ['a' => "$term 1", 'b' => "$term 2", 'c' => "$term 3"];
        });
        $result = $element->search('  x  ');
        $this->assertSame([['x', 3]], $calls, 'trimmed term, one row more than the limit to detect "more"');
        $this->assertSame([['value' => 'a', 'label' => 'x 1'], ['value' => 'b', 'label' => 'x 2']], $result['results']);
        $this->assertTrue($result['more']);

        $list = (new Autocomplete())->setLookupCallback(fn() => [['value' => 7, 'label' => 'Seven'], ['value' => 8]]);
        $this->assertSame([['value' => '7', 'label' => 'Seven'], ['value' => '8', 'label' => '8']], $list->search('s')['results']);

        $labelled = (new Autocomplete())->setLabelCallback(fn(string $v) => "Label $v");
        $this->assertSame('Label 5', (fn() => $this->labelFor('5'))->call($labelled));
    }

    /** @test */
    public function a_form_answers_the_lookup_of_its_own_autocomplete_fields_only()
    {
        [, $form] = $this->form();
        $this->assertFalse($form->handleLookupRequest([], false), 'not a lookup');

        ob_start();
        $this->assertTrue($form->handleLookupRequest(['laf_lookup' => 'dummy_table.parent_id', 'laf_q' => 'gam'], false));
        $json = json_decode((string)ob_get_clean(), true);
        $this->assertSame(['results' => [['value' => '10', 'label' => 'Gamma']], 'more' => false], $json);

        ob_start();
        $this->assertTrue($form->handleLookupRequest(['laf_lookup' => 'user.password', 'laf_q' => 'a'], false));
        $json = json_decode((string)ob_get_clean(), true);
        $this->assertSame([], $json['results']);
        $this->assertSame('Unknown lookup', $json['error']);

        ob_start();
        $form->handleLookupRequest(['laf_lookup' => 'dummy_table.parent_id', 'laf_q' => ['array']], false);
        $this->assertCount(4, json_decode((string)ob_get_clean(), true)['results'], 'a non-string term is an empty term');
    }

    /** @test */
    public function a_long_term_is_cut()
    {
        $seen = null;
        $element = (new Autocomplete())->setLookupCallback(function (string $term) use (&$seen) {
            $seen = $term;
            return [];
        });
        $element->search(str_repeat('x', 500));
        $this->assertSame(Autocomplete::MAX_TERM_LENGTH, mb_strlen((string)$seen));
    }

    /** @test */
    public function the_threshold_is_off_by_default()
    {
        $row = new DummyTable();
        $this->assertFalse(Autocomplete::suitsField($row->getParentIdFld()));
        $this->assertNotInstanceOf(Autocomplete::class, $row->getParentIdFld()->getFormElement());
        Settings::set('form.select_autocomplete_threshold', 100);
        $this->assertFalse(Autocomplete::suitsField($row->getVarcharField45Fld()), 'only foreign keys');
    }

    /** @test */
    public function settings_are_clamped()
    {
        $element = (new Autocomplete())->setLimit(1000)->setMinChars(-3);
        $this->assertSame(100, $element->getLimit());
        $this->assertSame(0, $element->getMinChars());
        $this->assertSame(['code', 'name'], $element->setSearchColumns(['code', 'name'])->getSearchColumns());
    }
}
