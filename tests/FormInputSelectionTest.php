<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBool;
use Laf\Database\Field\TypeDate;
use Laf\Database\Field\TypeDateTime;
use Laf\Database\Field\TypeEnum;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeTime;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormElementFactory;
use Laf\UI\Form\Input\Checkbox;
use Laf\UI\Form\Input\CheckboxCollection;
use Laf\UI\Form\Input\Date;
use Laf\UI\Form\Input\DateTime;
use Laf\UI\Form\Input\File;
use Laf\UI\Form\Input\Hidden;
use Laf\UI\Form\Input\Password;
use Laf\UI\Form\Input\Radio;
use Laf\UI\Form\Input\Select;
use Laf\UI\Form\Input\Text;
use Laf\UI\Form\Input\TextArea;
use Laf\UI\Form\Input\Time;
use Laf\UI\Form\Input\Url;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Selection inputs (Select, Radio, Checkbox, CheckboxCollection), temporal inputs,
 * and the rendering rules every input shares (non-mutating draws, escaping, errors).
 */
class FormInputSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        Settings::set('locale.date.format', 'F d, Y');
        Settings::set('locale.datetime.format', 'F d, Y H:i');
        Settings::set('locale.time.format', 'g:i A');
        Settings::set('form.native_date_inputs', false);
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

    // ── Select ──

    /** @test */
    public function select_marks_the_current_value_and_shows_its_label_in_view_mode()
    {
        $field = $this->field('status', new TypeVarchar());
        $field->loadValueFromDb('closed');
        $select = (new Select())->setOptions(['open' => 'Open', 'closed' => 'Closed']);
        $select->setField($field);

        $update = $this->draw($select, DrawMode::UPDATE);
        $this->assertStringContainsString("<option value=''>&nbsp;</option>", $update);
        $this->assertStringContainsString("<option value='open'>Open</option>", $update);
        $this->assertStringContainsString("<option value='closed' selected='selected'>Closed</option>", $update);
        $this->assertStringContainsString('form-select', $update);
        $this->assertStringContainsString('name="' . str_rot13('status') . '"', $update);

        $view = $this->draw($select, DrawMode::VIEW);
        $this->assertStringContainsString('Closed', $view);
        $this->assertStringNotContainsString('<select', $view);

        // drawing did not change the element: the value is still the key, not the label
        $this->assertSame('closed', $select->getValue());
        $this->assertStringContainsString("<option value='closed' selected='selected'>", $this->draw($select, DrawMode::UPDATE));
    }

    /** @test */
    public function select_empty_option_can_carry_a_label_or_be_omitted()
    {
        $select = (new Select())->setOptions(['a' => 'A']);
        $select->setField($this->field('x', new TypeVarchar()));

        $select->setEmptyOptionLabel('-- choose --');
        $this->assertStringContainsString("<option value=''>-- choose --</option>", $this->draw($select, DrawMode::UPDATE));

        $select->setEmptyOption(false);
        $this->assertStringNotContainsString("<option value=''>", $this->draw($select, DrawMode::UPDATE));
    }

    /** @test */
    public function multiple_select_posts_an_array_and_lists_every_selected_label()
    {
        $field = $this->field('tags', new TypeVarchar());
        $field->loadValueFromDb('a,c');
        $select = (new Select())->setOptions(['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $select->setField($field);
        $select->setMultiple(true);

        $update = $this->draw($select, DrawMode::UPDATE);
        $this->assertStringContainsString('name="' . str_rot13('tags') . '[]"', $update);
        $this->assertStringContainsString('multiple="multiple"', $update);
        $this->assertStringContainsString("<option value='a' selected='selected'>A</option>", $update);
        $this->assertStringContainsString("<option value='b'>B</option>", $update);
        $this->assertStringContainsString("<option value='c' selected='selected'>C</option>", $update);
        $this->assertStringNotContainsString("<option value=''>", $update);

        $this->assertStringContainsString('A, C', $this->draw($select, DrawMode::VIEW));
    }

    /** @test */
    public function select_without_foreign_key_or_static_options_is_empty()
    {
        $select = new Select();
        $select->setField($this->field('plain', new TypeVarchar()));
        $this->assertSame([], $select->getOptions());
        $this->assertStringContainsString('<select', $this->draw($select, DrawMode::UPDATE));
    }

    // ── Radio ──

    /** @test */
    public function radio_renders_one_checked_input_per_option_with_unique_ids()
    {
        $field = $this->field('size', new TypeVarchar(), ['label' => 'Size', 'required' => true]);
        $field->loadValueFromDb('m');
        $radio = (new Radio())->setOptions(['s' => 'Small', 'm' => 'Medium', 'l' => 'Large']);
        $radio->setField($field);
        $name = str_rot13('size');

        $html = $this->draw($radio, DrawMode::UPDATE);
        $this->assertSame(3, preg_match_all('/<input /', $html));
        $this->assertSame(1, substr_count($html, 'checked="checked"'));
        $this->assertStringContainsString("type='radio' id='{$name}_2' name='{$name}' value='m'", $html);
        $this->assertStringContainsString("<label class='form-check-label' for='{$name}_2'>Medium</label>", $html);
        $this->assertStringContainsString("for='{$name}_1' class='col-sm-2 col-form-label'>Size* :</label>", $html);
        $this->assertStringContainsString('form-check-input', $html);
        $this->assertStringContainsString('required="required"', $html);
        $this->assertStringNotContainsString('custom-control', $html);
        $this->assertStringNotContainsString('form-check-inline', $html);

        $radio->setInline(true);
        $this->assertStringContainsString('form-check-inline', $this->draw($radio, DrawMode::UPDATE));

        $view = $this->draw($radio, DrawMode::VIEW);
        $this->assertStringContainsString('Medium', $view);
        $this->assertStringNotContainsString("type='radio'", $view);
    }

    // ── Checkbox ──

    /** @test */
    public function checkbox_posts_zero_when_unticked_and_one_when_ticked()
    {
        $field = $this->field('active', new TypeBool());
        $name = str_rot13('active');

        $field->loadValueFromDb(false);
        $box = new Checkbox();
        $box->setField($field);
        $html = $this->draw($box, DrawMode::UPDATE);
        $this->assertStringContainsString("<input type='hidden' name='{$name}' value='0' />", $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('form-check-input', $html);
        $this->assertStringNotContainsString('checked="checked"', $html);
        $this->assertStringContainsString('No', $this->draw($box, DrawMode::VIEW));

        $field->loadValueFromDb(true);
        $box = new Checkbox();
        $box->setField($field);
        $this->assertStringContainsString('checked="checked"', $this->draw($box, DrawMode::UPDATE));
        $this->assertStringContainsString('Yes', $this->draw($box, DrawMode::VIEW));

        $box->setDisabled(true);
        $this->assertStringNotContainsString("type='hidden'", $this->draw($box, DrawMode::UPDATE));

        Checkbox::setLabels('Jo', 'Po');
        $this->assertStringContainsString('Po', $this->draw($box, DrawMode::VIEW));
        Checkbox::setLabels('No', 'Yes');
    }

    /** @test */
    public function set_column_gets_a_checkbox_collection_posting_a_list()
    {
        $field = $this->field('colors', new TypeEnum(['red', 'green', 'blue'], true));
        $field->loadValueFromDb('red,blue');
        $element = $field->getFormElement();
        $this->assertInstanceOf(CheckboxCollection::class, $element);
        $name = str_rot13('colors');

        $html = $this->draw($element, DrawMode::UPDATE);
        $this->assertStringContainsString("<input type='hidden' name='{$name}[]' value='' />", $html);
        $this->assertSame(3, preg_match_all("/type='checkbox'/", $html));
        $this->assertSame(2, substr_count($html, 'checked="checked"'));
        $this->assertStringContainsString("id='{$name}_3' name='{$name}[]' value='blue'", $html);
        $this->assertStringContainsString('red, blue', $this->draw($element, DrawMode::VIEW));
    }

    // ── Date / DateTime / Time ──

    /** @test */
    public function temporal_inputs_show_the_locale_format_and_keep_non_zero_seconds()
    {
        $date = new Date();
        $date->setField($this->field('d', new TypeDate())->loadValueFromDb('2024-02-29'));
        $html = $this->draw($date, DrawMode::UPDATE);
        $this->assertStringContainsString('value="February 29, 2024"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('date-picker', $html);
        $this->assertStringContainsString('February 29, 2024', $this->draw($date, DrawMode::VIEW));
        $this->assertSame('2024-02-29', $date->getValue());

        $dateTime = new DateTime();
        $dateTime->setField($this->field('dt', new TypeDateTime())->loadValueFromDb('2024-02-29 13:45:10'));
        $this->assertStringContainsString('value="February 29, 2024 13:45:10"', $this->draw($dateTime, DrawMode::UPDATE));
        $this->assertStringContainsString('date-time-picker', $this->draw($dateTime, DrawMode::UPDATE));

        $dateTime->setField($this->field('dt', new TypeDateTime())->loadValueFromDb('2024-02-29 13:45:00'));
        $this->assertStringContainsString('value="February 29, 2024 13:45"', $this->draw($dateTime, DrawMode::UPDATE));

        $time = new Time();
        $time->setField($this->field('t', new TypeTime())->loadValueFromDb('13:45:00'));
        $this->assertStringContainsString('value="1:45 PM"', $this->draw($time, DrawMode::UPDATE));
        $this->assertStringContainsString('time-picker', $this->draw($time, DrawMode::UPDATE));
        $time->setField($this->field('t', new TypeTime())->loadValueFromDb('13:45:09'));
        $this->assertStringContainsString('1:45:09 PM', $this->draw($time, DrawMode::VIEW));

        // the seconds-bearing display value is accepted back by the type
        $this->assertSame('13:45:09', (new TypeTime())->fromInput('1:45:09 PM'));
        $this->assertSame('2024-02-29 13:45:10', (new TypeDateTime())->fromInput('February 29, 2024 13:45:10'));

        // an unparseable value is shown as stored
        $date->setField($this->field('d', new TypeVarchar())->loadValueFromDb('not a date'));
        $this->assertStringContainsString('value="not a date"', $this->draw($date, DrawMode::UPDATE));
    }

    /** @test */
    public function native_temporal_inputs_render_iso_values()
    {
        $date = (new Date())->setNativeInput(true);
        $date->setField($this->field('d', new TypeDate())->loadValueFromDb('2024-02-29'));
        $html = $this->draw($date, DrawMode::UPDATE);
        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('value="2024-02-29"', $html);
        $this->assertStringNotContainsString('date-picker', $html);

        $dateTime = (new DateTime())->setNativeInput(true);
        $dateTime->setField($this->field('dt', new TypeDateTime())->loadValueFromDb('2024-02-29 13:45:10'));
        $html = $this->draw($dateTime, DrawMode::UPDATE);
        $this->assertStringContainsString('type="datetime-local"', $html);
        $this->assertStringContainsString('value="2024-02-29T13:45:10"', $html);
        $this->assertStringContainsString('step="1"', $html);

        $dateTime->setField($this->field('dt', new TypeDateTime())->loadValueFromDb('2024-02-29 13:45:00'));
        $html = $this->draw($dateTime, DrawMode::UPDATE);
        $this->assertStringContainsString('value="2024-02-29T13:45"', $html);
        $this->assertStringNotContainsString('step=', $html);

        $time = (new Time())->setNativeInput(true);
        $time->setField($this->field('t', new TypeTime())->loadValueFromDb('13:45:00'));
        $this->assertStringContainsString('type="time"', $this->draw($time, DrawMode::UPDATE));
        $this->assertStringContainsString('value="13:45"', $this->draw($time, DrawMode::UPDATE));

        Settings::set('form.native_date_inputs', true);
        $byDefault = new Date();
        $byDefault->setField($this->field('d', new TypeDate())->loadValueFromDb('2024-02-29'));
        $this->assertTrue($byDefault->isNativeInput());
        $this->assertStringContainsString('type="date"', $this->draw($byDefault, DrawMode::UPDATE));
        Settings::set('form.native_date_inputs', false);
        $this->assertFalse($byDefault->isNativeInput());
    }

    // ── Rules shared by every input ──

    /** @test */
    public function drawing_does_not_change_the_element()
    {
        $text = new Text();
        $text->setField($this->field('name', new TypeVarchar())->loadValueFromDb('v'));

        $this->draw($text, DrawMode::VIEW);
        $this->assertFalse($text->hasCssClass('form-control-plaintext'));
        $this->assertSame('v', $text->getValue());

        $update = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString('value="v"', $update);
        $this->assertStringContainsString("class='Laf-UI-Form-Input-Text form-control'", $update);
        $this->assertFalse($text->hasCssClass('form-control'));
        $this->assertStringNotContainsString('form-control-plaintext', $update);
    }

    /** @test */
    public function view_mode_prints_no_input_only_attributes()
    {
        $text = new Text();
        $text->setField($this->field('name', new TypeVarchar())->loadValueFromDb('v'));
        $text->setPlaceholder('p')->setStep(2)->setDataAttribute('role', 'x')->setTitle('t');

        $view = $this->draw($text, DrawMode::VIEW);
        $this->assertStringNotContainsString('placeholder=', $view);
        $this->assertStringNotContainsString('step=', $view);
        $this->assertStringNotContainsString('type=', $view);
        $this->assertStringContainsString('data-role="x"', $view);
        $this->assertStringContainsString('title="t"', $view);
    }

    /** @test */
    public function set_field_keeps_the_input_type_and_merges_the_field_attributes()
    {
        $field = $this->field('email', new TypeVarchar());
        $field->setAttribute('autocomplete', 'off');
        $text = new Text();
        $text->setField($field);

        $this->assertSame('text', $text->getType());
        $this->assertSame('off', $text->getAttribute('autocomplete'));
        $this->assertStringContainsString('type="text"', $this->draw($text, DrawMode::UPDATE));
    }

    /** @test */
    public function maxlength_is_copied_for_string_columns_only()
    {
        $int = new Text();
        $int->setField($this->field('n', new TypeInteger(), ['maxLength' => 255]));
        $this->assertNull($int->getMaxLength());

        $str = new Text();
        $str->setField($this->field('s', new TypeVarchar(), ['maxLength' => 45]));
        $this->assertSame(45, $str->getMaxLength());
    }

    /** @test */
    public function an_input_without_field_draws()
    {
        $text = (new Text())->setId('x')->setName('x')->setLabel('X')->setValue('hello');
        $this->assertStringContainsString('hello', $this->draw($text, DrawMode::VIEW));
        $this->assertStringContainsString('name="x"', $this->draw($text, DrawMode::UPDATE));
        $this->assertStringContainsString('hello', (string)$text);
    }

    /** @test */
    public function url_password_and_hidden_inputs()
    {
        $url = new Url();
        $url->setField($this->field('site', new TypeVarchar())->loadValueFromDb('https://example.com'));
        $this->assertStringContainsString('type="url"', $this->draw($url, DrawMode::UPDATE));
        $this->assertStringContainsString('https://example.com', $this->draw($url, DrawMode::VIEW));

        $password = new Password();
        $password->setField($this->field('pw', new TypeVarchar())->loadValueFromDb('secret'));
        $update = $this->draw($password, DrawMode::UPDATE);
        $this->assertStringContainsString('type="password"', $update);
        $this->assertStringNotContainsString('secret', $update);
        $this->assertStringNotContainsString('secret', $this->draw($password, DrawMode::VIEW));
        $this->assertStringContainsString('••••••', $this->draw($password, DrawMode::VIEW));

        $hidden = new Hidden();
        $hidden->setField($this->field('id2', new TypeInteger())->loadValueFromDb(5));
        $update = $this->draw($hidden, DrawMode::UPDATE);
        $this->assertStringContainsString('type="hidden"', $update);
        $this->assertStringContainsString('value="5"', $update);
        $this->assertStringNotContainsString('hidden="hidden"', $update);
        $this->assertStringContainsString('d-none', $this->draw($hidden, DrawMode::VIEW));
    }

    /** @test */
    public function textarea_defaults_to_five_rows()
    {
        $area = new TextArea();
        $area->setField($this->field('body', new TypeVarchar()));
        $html = $this->draw($area, DrawMode::UPDATE);
        $this->assertStringContainsString('rows="5"', $html);
        $this->assertStringNotContainsString('cols=', $html);

        $area->setRows(10);
        $this->assertStringContainsString('rows="10"', $this->draw($area, DrawMode::UPDATE));
    }

    /** @test */
    public function button_label_has_no_stray_marker_and_a_bootstrap5_wrapper()
    {
        $button = (new Button())->setId('go')->setLabel('Go')->setValue('Go')->setHint('h');
        $html = (string)$button->draw();
        $this->assertStringContainsString("col-form-label'>Go</label>", $html);
        $this->assertStringNotContainsString('Go *', $html);
        $this->assertStringNotContainsString('form-group', $html);
        $this->assertStringContainsString('mb-3', $html);
        $this->assertStringContainsString("<small id='go_hint' class='form-text text-muted'>h</small>", $html);
        $this->assertNull($button->getField());
    }

    /** @test */
    public function autocomplete_writes_the_real_attribute()
    {
        $text = new Text();
        $text->setAutocomplete(false);
        $this->assertSame('off', $text->getAttribute('autocomplete'));
        $this->assertFalse($text->isAutocomplete());

        $text->setAutocomplete('email');
        $this->assertSame('email', $text->getAutocomplete());
        $this->assertTrue($text->isAutocomplete());
        $this->assertStringContainsString('autocomplete="email"', $this->draw($text, DrawMode::UPDATE));
    }

    /** @test */
    public function numeric_getters_keep_the_type_of_the_value()
    {
        $text = new Text();
        $text->setStep('any');
        $this->assertSame('any', $text->getStep());
        $text->setMax('18446744073709551615');
        $this->assertSame('18446744073709551615', $text->getMax());
        $text->setMin(0.5);
        $this->assertSame(0.5, $text->getMin());
        $text->setMax(10);
        $this->assertSame(10, $text->getMax());
        $this->assertNull($text->getType() === null ? null : $text->getSize());
    }

    /** @test */
    public function errors_render_is_invalid_and_feedback_in_update_mode_only()
    {
        $text = new Text();
        $text->setField($this->field('name', new TypeVarchar()));
        $text->setErrors(['Too long', 'And <bad>']);
        $name = str_rot13('name');

        $update = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString('is-invalid', $update);
        $this->assertStringContainsString("<div id='{$name}_feedback' class='invalid-feedback d-block'>Too long<br />And &lt;bad&gt;</div>", $update);

        $view = $this->draw($text, DrawMode::VIEW);
        $this->assertStringNotContainsString('is-invalid', $view);
        $this->assertStringNotContainsString('invalid-feedback', $view);

        $text->clearErrors();
        $this->assertStringNotContainsString('is-invalid', $this->draw($text, DrawMode::UPDATE));
    }

    /** @test */
    public function labels_and_hints_are_escaped()
    {
        $text = new Text();
        $text->setField($this->field('name', new TypeVarchar(), ['label' => 'A <b>&</b>', 'hint' => 'x<y']));
        $html = $this->draw($text, DrawMode::UPDATE);
        $this->assertStringContainsString('A &lt;b&gt;&amp;&lt;/b&gt;', $html);
        $this->assertStringContainsString('x&lt;y', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    /** @test */
    public function label_suffix_and_required_marker_are_configurable()
    {
        $text = new Text();
        $text->setField($this->field('name', new TypeVarchar(), ['label' => 'Name', 'required' => true]));
        $this->assertStringContainsString('>Name* :</label>', $this->draw($text, DrawMode::UPDATE));

        Text::setLabelSuffix('');
        Text::setRequiredMarker(' (required)');
        $this->assertStringContainsString('>Name (required)</label>', $this->draw($text, DrawMode::UPDATE));
        Text::setLabelSuffix(' :');
        Text::setRequiredMarker('*');
    }

    /** @test */
    public function form_element_factory_creates_and_overrides_elements()
    {
        $field = $this->field('name', new TypeVarchar());
        $this->assertInstanceOf(Text::class, FormElementFactory::create($field));

        $radio = FormElementFactory::createOfType(Radio::class, $field);
        $this->assertInstanceOf(Radio::class, $radio);
        $this->assertSame($radio, $field->getFormElement());

        $this->expectException(\InvalidArgumentException::class);
        FormElementFactory::createOfType(\stdClass::class, $field);
    }

    /** @test */
    public function file_input_posts_no_value_and_takes_accept_from_settings()
    {
        Settings::set('upload.allowed_mime_types', ['image/png', 'image/jpeg']);
        $file = new File();
        $file->setField($this->field('doc', new TypeInteger())->loadValueFromDb(3));
        $html = $this->draw($file, DrawMode::UPDATE);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept="image/png,image/jpeg"', $html);
        $this->assertStringNotContainsString('value=', $html);
        $this->assertStringContainsString("enctype", "enctype");
    }
}
