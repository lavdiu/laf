<?php

declare(strict_types=1);

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeDecimal;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormElementFactory;
use Laf\UI\Form\FormLayout;
use Laf\UI\Form\Input\Checkbox;
use Laf\UI\Form\Input\CheckboxCollection;
use Laf\UI\Form\Input\File;
use Laf\UI\Form\Input\Hidden;
use Laf\UI\Form\Input\Number;
use Laf\UI\Form\Input\Password;
use Laf\UI\Form\Input\Radio;
use Laf\UI\Form\Input\Range;
use Laf\UI\Form\Input\Select;
use Laf\UI\Form\Input\Tel;
use Laf\UI\Form\Input\Text;
use Laf\UI\Form\Input\TextArea;
use Laf\Util\Settings;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * Third review of the form elements (2026-10-12): required never on checkboxes, multi selects
 * that can be cleared, unique hidden-input ids, aria feedback, label-less elements, input-group
 * addons, suggestions, switches, Number/Tel/Range, File URLs, extra form buttons, FormLayout html
 * sections and Settings::getBool().
 */
class FormElementsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_URI'] = '/test';
        Settings::set('form.native_date_inputs', false);
        File::setDocumentUrlBuilder(null);
    }

    protected function tearDown(): void
    {
        File::setDocumentUrlBuilder(null);
    }

    private function field(string $name, $type, array $options = []): Field
    {
        $table = new Table('test_table');
        $pk = new PrimaryKey();
        $id = (new Field('id'))->setType(new TypeInteger())->setAutoIncrement(true)->setRequired(false)->setUnique(false);
        $pk->addField($id);
        $table->setPrimaryKey($pk)->addField($id);

        $field = (new Field($name))
            ->setType($type)
            ->setRequired($options['required'] ?? false)
            ->setUnique(false)
            ->setAutoIncrement(false)
            ->setMaxLength($options['maxLength'] ?? 0)
            ->setMinLength(0)
            ->setLabel($options['label'] ?? ucfirst($name));
        if (isset($options['hint'])) {
            $field->setHint($options['hint']);
        }
        $table->addField($field);
        return $field;
    }

    private function draw($element, string $mode): string
    {
        return (string)$element->setDrawMode($mode)->draw();
    }

    // ── defects ──

    /** @test */
    public function a_required_boolean_renders_a_checkbox_without_the_required_attribute(): void
    {
        $box = new Checkbox();
        $box->setField($this->field('active', new \Laf\Database\Field\TypeBool(), ['required' => true, 'label' => 'Active']));
        $html = $this->draw($box, DrawMode::UPDATE);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringNotContainsString('required', $html);
        $this->assertStringContainsString('>Active* :</label>', $html, 'the label still marks the NOT NULL column');
        $this->assertStringContainsString("<input type='hidden' name='npgvir' value='0' />", $html);
    }

    /** @test */
    public function a_checkbox_collection_drops_required_while_radios_keep_it(): void
    {
        $field = $this->field('tags', new TypeVarchar(), ['required' => true]);
        $boxes = FormElementFactory::createOfType(CheckboxCollection::class, $field)->setOptions(['a' => 'A', 'b' => 'B']);
        $this->assertStringNotContainsString('required', $this->draw($boxes, DrawMode::UPDATE));

        $field = $this->field('size', new TypeVarchar(), ['required' => true]);
        $radio = FormElementFactory::createOfType(Radio::class, $field)->setOptions(['s' => 'S', 'm' => 'M']);
        $this->assertSame(2, substr_count($this->draw($radio, DrawMode::UPDATE), 'required="required"'));
    }

    /** @test */
    public function a_multiple_select_posts_an_empty_value_so_it_can_be_cleared(): void
    {
        $select = (new Select())->setOptions(['a' => 'A', 'b' => 'B'])->setId('gntf')->setName('gntf')->setMultiple(true);
        $html = $this->draw($select, DrawMode::UPDATE);
        $this->assertStringContainsString("<input type='hidden' name='gntf[]' value='' />", $html);
        $this->assertStringContainsString('name="gntf[]"', $html);

        $select->setDisabled(true);
        $this->assertStringNotContainsString("type='hidden'", $this->draw($select, DrawMode::UPDATE));

        $single = (new Select())->setOptions(['a' => 'A'])->setId('x')->setName('x');
        $this->assertStringNotContainsString("type='hidden'", $this->draw($single, DrawMode::UPDATE));
    }

    /** @test */
    public function the_hidden_marker_inputs_have_ids_unique_to_the_form(): void
    {
        $first = (new Form(new DummyTable()))->setDrawMode(DrawMode::UPDATE);
        $second = (new Form(new DummyTable()))->setDrawMode(DrawMode::UPDATE)->setId('second');
        $html = $first->draw() . $second->draw();

        $this->assertStringNotContainsString("id='form_submit'", $html);
        $this->assertStringContainsString("name='form_submit' id='dummy_table_form_submit'", $html);
        $this->assertStringContainsString("name='form_submit' id='second_form_submit'", $html);
        $this->assertStringContainsString("name='dummy_table' id='dummy_table_marker'", $html);
        $this->assertStringContainsString("name='dummy_table' id='second_marker'", $html);
    }

    /** @test */
    public function errors_are_announced_through_aria_describedby_and_aria_invalid(): void
    {
        $text = new Text();
        $text->setField($this->field('name', new TypeVarchar(), ['hint' => 'h']));
        $this->assertStringContainsString("aria-describedby='anzr_hint'", $this->draw($text, DrawMode::UPDATE));
        $this->assertStringNotContainsString('aria-invalid', $this->draw($text, DrawMode::UPDATE));

        $text->setErrors(['Too short']);
        $html = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString("aria-describedby='anzr_hint anzr_feedback' aria-invalid='true'", $html);
        $this->assertStringContainsString("<div id='anzr_feedback' class='invalid-feedback d-block'>Too short</div>", $html);
        $this->assertStringNotContainsString('aria-invalid', $this->draw($text, DrawMode::VIEW));
    }

    /** @test */
    public function an_element_without_a_label_draws_an_empty_label(): void
    {
        $text = (new Text())->setId('x')->setName('x')->setRequired(true);
        $html = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString("<label id='x_label' for='x' class='col-sm-2 col-form-label'></label>", $html);
        $this->assertStringNotContainsString('*', $html);
    }

    /** @test */
    public function a_button_uses_the_configured_marker_an_icon_and_no_for_without_an_id(): void
    {
        $button = (new Button())->setValue('Save')->setLabel('Action')->setRequired(true)->setIcon('fa fa-save');
        $html = (string)$button->draw();
        $this->assertStringContainsString("<label id='_label' class='col-sm-2 col-form-label'>Action*</label>", $html);
        $this->assertStringContainsString("><i class='fa fa-save'></i> Save</button>", $html);

        Text::setRequiredMarker(' (req)');
        try {
            $this->assertStringContainsString('>Action (req)</label>', (string)$button->draw());
        } finally {
            Text::setRequiredMarker('*');
        }

        $button->setId('go');
        $this->assertStringContainsString("<label id='go_label' for='go' class='col-sm-2 col-form-label'>Action*</label>", (string)$button->draw());
    }

    /** @test */
    public function input_only_attributes_stay_off_the_other_controls(): void
    {
        $area = new TextArea();
        $area->setField($this->field('note', new \Laf\Database\Field\TypeText(), ['maxLength' => 20]));
        $area->setPlaceholder('p')->setStep(2);
        $html = $this->draw($area, DrawMode::UPDATE);
        $this->assertStringContainsString('maxlength="20"', $html);
        $this->assertStringContainsString('placeholder="p"', $html);
        $this->assertStringNotContainsString('type=', $html);
        $this->assertStringNotContainsString('step=', $html);

        $hidden = new Hidden();
        $hidden->setField($this->field('pk', new TypeInteger(), ['required' => true]));
        $this->assertStringNotContainsString('required', $this->draw($hidden, DrawMode::UPDATE));

        $select = (new Select())->setOptions(['a' => 'A'])->setId('s')->setName('s')->setPlaceholder('p')->setSize(4);
        $html = $this->draw($select, DrawMode::UPDATE);
        $this->assertStringNotContainsString('placeholder', $html);
        $this->assertStringContainsString('size="4"', $html);
    }

    /** @test */
    public function settings_get_bool_reads_flags_forgivingly(): void
    {
        $this->assertFalse(Settings::has('form.some_flag'));
        $this->assertFalse(Settings::getBool('form.some_flag'));
        $this->assertTrue(Settings::getBool('form.some_flag', true));

        foreach (['1', 'yes', 'on', true, 1] as $yes) {
            Settings::set('form.some_flag', $yes);
            $this->assertTrue(Settings::getBool('form.some_flag'), var_export($yes, true));
        }
        foreach (['0', 'no', 'off', false, 0, ''] as $no) {
            Settings::set('form.some_flag', $no);
            $this->assertFalse(Settings::getBool('form.some_flag', true), var_export($no, true));
        }
        Settings::set('form.some_flag', 'maybe');
        $this->assertTrue(Settings::getBool('form.some_flag', true), 'unrecognised value keeps the default');
        Settings::set('form.some_flag', null);
        $this->assertTrue(Settings::has('form.some_flag'));
        $this->assertFalse(Settings::getBool('form.some_flag'));
    }

    // ── features ──

    /** @test */
    public function prefix_and_suffix_addons_wrap_the_control_in_an_input_group_in_edit_modes_only(): void
    {
        $text = (new Text())->setId('cevpr')->setName('cevpr')->setValue('9')->setPrefix('<€>')->setSuffixHtml("<button type='button'>go</button>");
        $html = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString("<div class='input-group'><span class='input-group-text'>&lt;€&gt;</span><input ", $html);
        $this->assertStringContainsString("<span class='input-group-text'><button type='button'>go</button></span></div>", $html);
        $this->assertSame('&lt;€&gt;', $text->getPrefix());

        $this->assertStringNotContainsString('input-group', $this->draw($text, DrawMode::VIEW));

        $text->setPrefix(null)->setSuffix(null);
        $this->assertFalse($text->hasAddons());
        $this->assertStringNotContainsString('input-group', $this->draw($text, DrawMode::UPDATE));
    }

    /** @test */
    public function suggestions_render_a_datalist_bound_to_the_input(): void
    {
        $text = (new Text())->setId('pvgl')->setName('pvgl')->setSuggestions(['Rome', 'Oslo', '', 'Rome', 42]);
        $this->assertSame(['Rome', 'Oslo', '42'], $text->getSuggestions());
        $html = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString('list="pvgl_list"', $html);
        $this->assertStringContainsString("<datalist id='pvgl_list'><option value='Rome'></option><option value='Oslo'></option><option value='42'></option></datalist>", $html);
        $this->assertStringNotContainsString('datalist', $this->draw($text, DrawMode::VIEW));

        $password = (new Password())->setId('cj')->setName('cj')->setSuggestions(['x']);
        $this->assertStringNotContainsString('datalist', $this->draw($password, DrawMode::UPDATE));
    }

    /** @test */
    public function a_checkbox_can_be_drawn_as_a_switch(): void
    {
        $box = (new Checkbox())->setId('b')->setName('b')->setValue('1')->setSwitch(true);
        $html = $this->draw($box, DrawMode::UPDATE);
        $this->assertStringContainsString("<div class='form-check form-switch'>", $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('checked="checked"', $html);
        $this->assertStringNotContainsString('form-switch', $this->draw((new Checkbox())->setId('c')->setName('c'), DrawMode::UPDATE));
    }

    /** @test */
    public function number_tel_and_range_inputs(): void
    {
        $number = new Number();
        $number->setField($this->field('price', new TypeDecimal(8, 2))->loadValueFromDb('12.50'));
        $html = $this->draw($number, DrawMode::UPDATE);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('step="0.01"', $html, 'the step of the decimal type is kept');
        $this->assertStringContainsString('value="12.50"', $html);
        $this->assertStringContainsString('step="any"', $this->draw((new Number())->setId('n')->setName('n'), DrawMode::UPDATE));

        $tel = (new Tel())->setId('t')->setName('t')->setValue('+383 44');
        $html = $this->draw($tel, DrawMode::UPDATE);
        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('inputmode="tel"', $html);

        $range = (new Range())->setId('r')->setName('r')->setMin(0)->setMax(10)->setValue('7');
        $html = $this->draw($range, DrawMode::UPDATE);
        $this->assertStringContainsString('type="range"', $html);
        $this->assertStringContainsString("class='Laf-UI-Form-Input-Range form-range'", $html);
        $this->assertStringNotContainsString('form-control', $html);
        $this->assertStringContainsString('>7</div>', $this->draw($range, DrawMode::VIEW));

        $this->assertSame(Number::class, FormLayout::resolveElementClass('Number'));
    }

    /** @test */
    public function file_document_urls_come_from_the_builder(): void
    {
        $this->assertSame('?module=document&submodule=thumbnail&id=5', File::documentUrl(File::ACTION_THUMBNAIL, 5));

        File::setDocumentUrlBuilder(fn(string $action, int $id): string => "/doc/{$action}/{$id}");
        $this->assertSame('/doc/download/7', File::documentUrl(File::ACTION_DOWNLOAD, 7));

        File::setDocumentUrlBuilder(null);
        $this->assertSame('?module=document&submodule=view&id=7', File::documentUrl(File::ACTION_VIEW, 7));

        $file = new File();
        $file->setField($this->field('doc', new TypeInteger())->loadValueFromDb(3));
        $this->assertTrue($file->isShowCurrentFile());
        $file->setShowCurrentFile(false);
        $html = $this->draw($file, DrawMode::UPDATE);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringNotContainsString('_current', $html);
        $this->assertStringContainsString('(no file attached)', $this->draw((new File())->setId('f')->setName('f'), DrawMode::VIEW));
    }

    /** @test */
    public function extra_buttons_and_a_cancel_link_are_drawn_before_the_submit_button(): void
    {
        $form = (new Form(new DummyTable()))->setDrawMode(DrawMode::UPDATE);
        $form->setSubmitButtonLabel('Save it')
            ->addButton((new Button())->setValue('Save and new')->setName('again'))
            ->setCancelLink('/back', 'Back');
        $this->assertCount(2, $form->getButtons());
        $this->assertFalse($form->getButtons()[0]->getWrapInDiv(), 'a Button in the footer row is drawn bare');

        $html = $form->draw();
        $this->assertStringContainsString('>Save and new</button>', $html);
        $this->assertStringNotContainsString("again_container", $html);
        $cancel = strpos($html, 'href="/back"');
        $submit = strpos($html, '>Save it</button>');
        $this->assertNotFalse($cancel);
        $this->assertNotFalse($submit);
        $this->assertLessThan($submit, $cancel);
        $this->assertStringContainsString('class="btn btn-outline-secondary', $html);
        $this->assertStringContainsString('>Back</a>', $html);

        $form->setButtons([]);
        $this->assertStringNotContainsString('Back', $form->draw());
    }

    /** @test */
    public function form_layout_html_sections_are_drawn_in_their_modes_and_survive_serialisation(): void
    {
        $layout = FormLayout::create()
            ->fields(['varchar_field45'])
            ->html('<p id="note">Net prices</p>', [DrawMode::VIEW])
            ->fields(['integer_field'])
            ->setAppendUnlisted(false);

        $object = new DummyTable();
        $view = (new Form($object))->applyLayout(DrawMode::VIEW, $layout)->draw();
        $this->assertStringContainsString('<p id="note">Net prices</p>', $view);
        $this->assertLessThan(strpos($view, '<p id="note">'), strpos($view, 'inepune_svryq45_container'));
        $this->assertGreaterThan(strpos($view, '<p id="note">'), strpos($view, 'vagrtre_svryq_container'));

        $update = (new Form($object))->applyLayout(DrawMode::UPDATE, $layout)->draw();
        $this->assertStringNotContainsString('Net prices', $update);

        $copy = FormLayout::fromArray($layout->toArray());
        $this->assertSame($layout->toArray(), $copy->toArray());
        $this->assertSame(FormLayout::KIND_HTML, $copy->getSections()[1]['kind']);
    }

    /** @test */
    public function draw_mode_lists_and_validation(): void
    {
        $this->assertSame(['view', 'insert', 'update'], DrawMode::ALL);
        $this->assertSame(['insert', 'update'], DrawMode::EDIT);
        $this->assertSame(DrawMode::ALL, FormLayout::ALL_MODES);
        $this->assertTrue(DrawMode::isValid('update'));
        $this->assertFalse(DrawMode::isValid('edit'));
        $this->assertFalse(DrawMode::isValid(null));
    }
}
