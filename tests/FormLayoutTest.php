<?php

use Laf\Database\BaseObject;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBool;
use Laf\Database\Field\TypeDateTime;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeText;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\Exception\FormValidationException;
use Laf\UI\Container\Div;
use Laf\UI\Container\Fieldset;
use Laf\UI\Container\TabContainer;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\FormLayout;
use Laf\UI\Form\FormRowDisplayMode;
use Laf\UI\Form\Input\Select;
use Laf\UI\Form\Input\Text;
use Laf\UI\Form\Input\TextArea;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * Builds the dummy_table of these tests without a database: five plain fields and,
 * optionally, the five row-metadata columns FormLayout::auto() treats specially.
 */
trait FormLayoutFixtureTrait
{
    private function buildFixture(bool $withMetadata): void
    {
        $this->setTable(new Table('dummy_table'));
        $pk = new PrimaryKey();

        $specs = [
            'id' => [new TypeInteger(), true, true],
            'code' => [new TypeVarchar(), false, false],
            'varchar_field45' => [new TypeVarchar(), false, false],
            'text_field' => [new TypeText(), false, false],
            'bool_field' => [new TypeBool(), false, false],
        ];
        if ($withMetadata) {
            $specs += [
                'record_status_id' => [new TypeInteger(), false, false],
                'created_by' => [new TypeInteger(), false, false],
                'created_on' => [new TypeDateTime(), false, false],
                'updated_by' => [new TypeInteger(), false, false],
                'updated_on' => [new TypeDateTime(), false, false],
            ];
        }
        foreach ($specs as $name => [$type, $required, $autoIncrement]) {
            $field = (new Field())
                ->setName($name)
                ->setLabel(ucwords(str_replace('_', ' ', $name)))
                ->setRequired($required)
                ->setAutoIncrement($autoIncrement)
                ->setUnique(false)
                ->setType($type);
            if ($name === 'code') {
                $field->setMaxLength(20);
            }
            if ($autoIncrement) {
                $pk->addField($field);
            }
            $this->getTable()->addField($field);
        }
        $this->getTable()->setPrimaryKey($pk);
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FormLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
    }

    private function makeObject(bool $withMetadata = true): DummyTable
    {
        return new class($withMetadata) extends DummyTable {
            use FormLayoutFixtureTrait;

            public function __construct(bool $withMetadata)
            {
                BaseObject::__construct();
                $this->buildFixture($withMetadata);
            }
        };
    }

    /**
     * The request of a submitted update/insert form: marker, draw mode, rot13 field names and the signed list
     * @param array<string, string> $fields
     */
    private function request(array $fields, string $mode, ?string $signedList): array
    {
        $request = ['dummy_table' => '1', 'dummy_table_draw_mode' => $mode];
        foreach ($fields as $name => $value) {
            $request[str_rot13($name)] = $value;
        }
        if ($signedList !== null) {
            $request['dummy_table_fields'] = $signedList;
        }
        return $request;
    }

    private function signedListOf(string $html): string
    {
        $this->assertSame(1, preg_match("/name='dummy_table_fields' value='([^']*)'/", $html, $m), 'the drawn form carries its field list');
        return $m[1];
    }

    /** @test */
    public function auto_layout_for_update_excludes_created_and_updated_columns_and_uses_two_columns(): void
    {
        $form = $this->makeObject()->getForm(DrawMode::UPDATE);

        $this->assertSame(DrawMode::UPDATE, $form->getDrawMode());
        $this->assertSame(['id', 'code', 'varchar_field45', 'text_field', 'bool_field', 'record_status_id'], $form->getRenderedFieldNames());
        $components = $form->getComponents();
        $this->assertCount(1, $components);
        $this->assertInstanceOf(Div::class, $components[0]);
        $this->assertCount(2, $components[0]->getComponents(), 'ten fields: two col-lg columns');
        $this->assertSame(FormRowDisplayMode::INLINE, $form->getFormRowDisplayMode());
    }

    /** @test */
    public function auto_layout_for_view_puts_metadata_in_a_details_tab(): void
    {
        $form = $this->makeObject()->getForm(DrawMode::VIEW);

        $components = $form->getComponents();
        $this->assertCount(1, $components);
        $this->assertInstanceOf(TabContainer::class, $components[0]);
        $tabs = $components[0]->getComponents();
        $this->assertCount(2, $tabs);
        $this->assertSame('General', $tabs[0]->getTitle());
        $this->assertSame('Details', $tabs[1]->getTitle());
        $this->assertSame(
            ['id', 'code', 'varchar_field45', 'text_field', 'bool_field', 'created_by', 'created_on', 'record_status_id', 'updated_by', 'updated_on'],
            $form->getRenderedFieldNames()
        );

        $html = $form->draw();
        $this->assertStringContainsString("id='dummy_table_form_tabs_Details-tab'", $html);
        $this->assertStringContainsString("id='dummy_table_form_tabs_General-content'", $html);
    }

    /** @test */
    public function auto_layout_without_metadata_columns_draws_no_tabs_and_no_columns(): void
    {
        $object = $this->makeObject(false);

        $update = $object->getForm(DrawMode::UPDATE);
        $this->assertCount(5, $update->getComponents());
        foreach ($update->getComponents() as $component) {
            $this->assertInstanceOf(FormElementInterface::class, $component);
        }
        $this->assertSame(FormRowDisplayMode::ROW, $update->getFormRowDisplayMode());

        $view = $object->getForm(DrawMode::VIEW);
        $this->assertNotInstanceOf(TabContainer::class, $view->getComponents()[0]);
        $this->assertSame(['id', 'code', 'varchar_field45', 'text_field', 'bool_field'], $view->getRenderedFieldNames());
    }

    /** @test */
    public function get_form_without_arguments_is_unchanged(): void
    {
        $object = $this->makeObject();
        $form = $object->getForm();

        $this->assertCount(10, $form->getComponents());
        $this->assertSame('', (string)$form->getDrawMode());

        // the apps' read-only idiom: an element set to VIEW before the draw stays VIEW
        $element = $object->getField('code')->getFormElement();
        $element->setDrawMode(DrawMode::VIEW);
        $form->setDrawMode(DrawMode::UPDATE);
        $form->draw();
        $this->assertSame(DrawMode::VIEW, $element->getDrawMode());
        $this->assertNotContains('code', $form->getEditableFieldNames());
    }

    /** @test */
    public function to_array_from_array_round_trip(): void
    {
        $layout = FormLayout::create()
            ->fields(['id', 'code'], 2)
            ->fieldset('Text', ['text_field'])
            ->tab('Flags', ['bool_field'], 1, [DrawMode::VIEW], 'fa fa-flag')
            ->exclude(['updated_on'])
            ->readOnly(['code'], [DrawMode::UPDATE])
            ->hidden(['record_status_id'])
            ->element('varchar_field45', 'TextArea')
            ->setGeneralTabTitle('Main')
            ->setTabContainerId('x_tabs')
            ->setAppendUnlisted(false);

        $array = $layout->toArray();
        $this->assertSame($array, FormLayout::fromArray($array)->toArray());
        $this->assertSame(['update'], $array['fields']['code']['readonly']);
        $this->assertSame(['view', 'insert', 'update'], $array['fields']['updated_on']['exclude']);
        $this->assertSame(TextArea::class, $array['fields']['varchar_field45']['element']);
        $this->assertSame('tab', $array['sections'][2]['kind']);
        $this->assertSame(['view'], $array['sections'][2]['modes']);
        $this->assertFalse($array['appendUnlisted']);

        // true means "every mode of the option's default"
        $fromTrue = FormLayout::fromArray(['fields' => ['code' => ['readonly' => true, 'hidden' => true]]]);
        $this->assertSame(FormLayout::EDIT_MODES, $fromTrue->toArray()['fields']['code']['readonly']);
        $this->assertSame(FormLayout::ALL_MODES, $fromTrue->toArray()['fields']['code']['hidden']);
    }

    /** @test */
    public function from_array_refuses_unknown_kinds_modes_and_options(): void
    {
        try {
            FormLayout::fromArray(['sections' => [['kind' => 'bogus']]]);
            $this->fail('unknown kind accepted');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('bogus', $ex->getMessage());
        }
        try {
            FormLayout::create()->readOnly(['code'], ['nope']);
            $this->fail('unknown mode accepted');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('nope', $ex->getMessage());
        }
        try {
            FormLayout::fromArray(['fields' => ['code' => ['width' => 6]]]);
            $this->fail('unknown option accepted');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('width', $ex->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        FormLayout::create()->element('code', 'NoSuchInput');
    }

    /** @test */
    public function signed_field_list_of_a_laid_out_form_equals_its_editable_fields(): void
    {
        $object = $this->makeObject();
        $layout = FormLayout::auto($object->getTable())
            ->readOnly(['code'], [DrawMode::UPDATE])
            ->hidden(['record_status_id']);
        $form = $object->getForm(DrawMode::UPDATE, $layout);

        $html = $form->draw();
        [$list] = explode('|', $this->signedListOf($html), 2);
        $editable = $form->getEditableFieldNames();

        $this->assertSame(array_map('str_rot13', $editable), explode(',', $list));
        $this->assertContains('id', $editable, 'the hidden primary key input posts');
        $this->assertContains('record_status_id', $editable, 'hidden only hides the wrapper');
        $this->assertNotContains('code', $editable, 'read-only elements post nothing');
        $this->assertTrue($object->getField('record_status_id')->getFormElement()->isHidden());
        $this->assertStringContainsString('d-none', $html);
    }

    /** @test */
    public function a_read_only_on_update_field_is_untouched_when_absent_from_the_request(): void
    {
        $object = $this->makeObject();
        $object->getField('code')->setValue('ABC');
        $object->getField('bool_field')->setValue(true);
        $layout = FormLayout::auto($object->getTable())->readOnly(['code'], [DrawMode::UPDATE]);
        $form = $object->getForm(DrawMode::UPDATE, $layout);
        $form->setCsrfProtection(false);
        $list = $this->signedListOf($form->draw());

        $form->setRequest($this->request(['varchar_field45' => 'x'], DrawMode::UPDATE, $list));
        $this->assertTrue($form->detectSubmission());
        $form->applySubmittedValues();
        $this->assertSame('ABC', $object->getField('code')->getValue(), 'read-only: untouched');
        $this->assertSame('x', $object->getField('varchar_field45')->getValue());
        $this->assertFalse($object->getField('bool_field')->getValue(), 'drawn editable and absent: unchecked');

        // without the list the laid-out components decide the same
        $object->getField('bool_field')->setValue(true);
        $form->setRequest($this->request(['varchar_field45' => 'y'], DrawMode::UPDATE, null));
        $form->applySubmittedValues();
        $this->assertSame('ABC', $object->getField('code')->getValue());
        $this->assertFalse($object->getField('bool_field')->getValue());

        // a read-only boolean is left alone too
        $object->getField('bool_field')->setValue(true);
        $readOnlyBool = $object->getForm(DrawMode::UPDATE, FormLayout::auto($object->getTable())->readOnly(['bool_field']));
        $readOnlyBool->setRequest($this->request(['varchar_field45' => 'z'], DrawMode::UPDATE, null));
        $readOnlyBool->applySubmittedValues();
        $this->assertTrue($object->getField('bool_field')->getValue());
    }

    /** @test */
    public function the_same_field_is_editable_on_insert(): void
    {
        $object = $this->makeObject();
        $layout = FormLayout::auto($object->getTable())->readOnly(['code'], [DrawMode::UPDATE]);
        $form = $object->getForm(DrawMode::INSERT, $layout);

        $this->assertContains('code', $form->getEditableFieldNames());
        $this->assertStringContainsString('name="' . str_rot13('code') . '"', $form->draw());
    }

    /** @test */
    public function a_hidden_field_still_posts_and_is_applied(): void
    {
        $object = $this->makeObject();
        $layout = FormLayout::auto($object->getTable())->hidden(['record_status_id']);
        $form = $object->getForm(DrawMode::UPDATE, $layout);
        $form->setCsrfProtection(false);
        $list = $this->signedListOf($form->draw());

        $form->setRequest($this->request(['record_status_id' => '2'], DrawMode::UPDATE, $list));
        $form->detectSubmission();
        $form->applySubmittedValues();
        $this->assertSame(2, $object->getField('record_status_id')->getValue());
    }

    /** @test */
    public function an_excluded_field_is_not_on_the_form_in_that_mode_only(): void
    {
        $object = $this->makeObject();
        $layout = FormLayout::auto($object->getTable())->exclude(['text_field'], [DrawMode::VIEW]);

        $this->assertNotContains('text_field', $object->getForm(DrawMode::VIEW, $layout)->getRenderedFieldNames());
        $this->assertContains('text_field', $object->getForm(DrawMode::UPDATE, $layout)->getRenderedFieldNames());
    }

    /** @test */
    public function applying_a_layout_twice_in_different_modes_resets_element_state(): void
    {
        $object = $this->makeObject();
        $layout = FormLayout::auto($object->getTable())
            ->readOnly(['code'], [DrawMode::UPDATE])
            ->hidden(['record_status_id'], [DrawMode::UPDATE]);

        $object->getForm(DrawMode::UPDATE, $layout);
        $this->assertSame(DrawMode::VIEW, $object->getField('code')->getFormElement()->getDrawMode());
        $this->assertTrue($object->getField('record_status_id')->getFormElement()->isHidden());

        $insert = $object->getForm(DrawMode::INSERT, $layout);
        $this->assertSame('', (string)$object->getField('code')->getFormElement()->getDrawMode());
        $this->assertFalse($object->getField('record_status_id')->getFormElement()->isHidden());
        $this->assertContains('code', $insert->getEditableFieldNames());
    }

    /** @test */
    public function unlisted_fields_are_appended_unless_disabled(): void
    {
        $object = $this->makeObject();

        $appended = $object->getForm(DrawMode::UPDATE, FormLayout::create()->fields(['varchar_field45']));
        $names = $appended->getRenderedFieldNames();
        $this->assertCount(10, $names);
        $this->assertSame('varchar_field45', $names[0]);
        $this->assertSame('id', $names[1]);

        $only = $object->getForm(DrawMode::UPDATE, FormLayout::create()->fields(['varchar_field45'])->setAppendUnlisted(false));
        $this->assertSame(['varchar_field45'], $only->getRenderedFieldNames());
    }

    /** @test */
    public function element_option_changes_the_element_class_and_survives_a_validation_redraw(): void
    {
        $object = $this->makeObject();
        $layout = FormLayout::auto($object->getTable())->element('varchar_field45', 'TextArea');
        $form = $object->getForm(DrawMode::UPDATE, $layout);
        $form->setCsrfProtection(false);

        $this->assertInstanceOf(TextArea::class, $object->getField('varchar_field45')->getFormElement());
        $this->assertSame(TextArea::class, $object->getField('varchar_field45')->getFormElementClass());
        $list = $this->signedListOf($form->draw());

        $form->setRequest($this->request(['bool_field' => 'maybe', 'varchar_field45' => 'typed'], DrawMode::UPDATE, $list));
        try {
            $form->processForm();
            $this->fail('an invalid boolean must fail validation');
        } catch (FormValidationException $ex) {
            $this->assertArrayHasKey('bool_field', $ex->getErrors());
        }
        $html = $form->draw();
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('>typed</textarea>', $html);
        $this->assertStringContainsString('is-invalid', $html);
    }

    /** @test */
    public function field_form_element_class_is_validated_and_replaces_a_cached_element(): void
    {
        $object = $this->makeObject();
        $field = $object->getField('code');
        $this->assertInstanceOf(Text::class, $field->getFormElement());

        $field->setFormElementClass(TextArea::class);
        $this->assertInstanceOf(TextArea::class, $field->getFormElement(), 'the cached Text was dropped');
        $this->assertSame(TextArea::class, $field->getFormOptions()['element']);

        $field->setFormElementClass(null);
        $field->clearFormElementCache();
        $this->assertInstanceOf(Text::class, $field->getFormElement());
        $this->assertArrayNotHasKey('element', $field->getFormOptions());

        $field->setFormOptions(['readonly' => ['update'], 'element' => 'Laf\\UI\\Form\\Input\\TextArea']);
        $this->assertSame(TextArea::class, $field->getFormElementClass());
        $this->assertSame(['update'], $field->getFormOption('readonly'));
        $this->assertNull($field->getFormOption('missing'));

        $this->expectException(\InvalidArgumentException::class);
        $field->setFormElementClass(\stdClass::class);
    }

    /** @test */
    public function a_foreign_key_field_only_accepts_a_select_element_class(): void
    {
        $object = new DummyTable();
        $parent = $object->getField('parent_id');
        $this->assertTrue($parent->isForeignKey());

        $parent->setFormElementClass('Radio');
        $this->assertInstanceOf(Select::class, $parent->getFormElement());

        $this->expectException(\InvalidArgumentException::class);
        $parent->setFormElementClass(TextArea::class);
    }

    /** @test */
    public function auto_layout_reads_the_fields_form_options(): void
    {
        $object = $this->makeObject();
        $object->getField('text_field')->setFormOptions(['group' => 'Text']);
        $object->getField('bool_field')->setFormOptions(['tab' => 'Flags']);
        $object->getField('code')->setFormOptions(['readonly' => true]);
        $object->getField('varchar_field45')->setFormOptions(['order' => 5, 'exclude' => ['view']]);

        $update = $object->getForm(DrawMode::UPDATE);
        $container = $update->getComponents()[0];
        $this->assertInstanceOf(TabContainer::class, $container);
        $this->assertSame(['General', 'Flags'], array_map(static fn ($tab) => $tab->getTitle(), $container->getComponents()));
        $general = $container->getComponents()[0]->getComponents();
        $this->assertInstanceOf(Div::class, $general[0], 'main block in columns');
        $this->assertInstanceOf(Fieldset::class, $general[1]);
        $this->assertSame('Text', $general[1]->getLegend());
        $names = $update->getRenderedFieldNames();
        $this->assertSame('varchar_field45', $names[0], 'order 5 comes before the id');
        $this->assertNotContains('code', $update->getEditableFieldNames());
        $this->assertContains('record_status_id', $names);

        $view = $object->getForm(DrawMode::VIEW);
        $viewTabs = $view->getComponents()[0]->getComponents();
        $this->assertSame(['General', 'Flags', 'Details'], array_map(static fn ($tab) => $tab->getTitle(), $viewTabs));
        $this->assertNotContains('varchar_field45', $view->getRenderedFieldNames());
    }

    /** @test */
    public function get_form_layout_override_and_configure_form_hook_are_used(): void
    {
        $object = new class extends DummyTable {
            use FormLayoutFixtureTrait;

            public bool $configured = false;

            public function __construct()
            {
                BaseObject::__construct();
                $this->buildFixture(true);
            }

            public function getFormLayout(): ?FormLayout
            {
                return FormLayout::auto($this->getTable())->readOnly(['code']);
            }

            protected function configureForm(Form $form): void
            {
                $this->configured = true;
                $form->hideSubmitButton();
            }
        };

        $form = $object->getForm(DrawMode::UPDATE);
        $this->assertTrue($object->configured);
        $this->assertFalse($form->isShowSubmitButton());
        $this->assertNotContains('code', $form->getEditableFieldNames());

        $object->configured = false;
        $flat = $object->getForm();
        $this->assertTrue($object->configured, 'the hook runs for the flat form too');
        $this->assertCount(10, $flat->getComponents());

        // applyLayout() on a flat form resolves the model's layout as well
        $flat->applyLayout(DrawMode::VIEW);
        $this->assertInstanceOf(TabContainer::class, $flat->getComponents()[0]);
        $this->assertSame($object->getForm(DrawMode::VIEW)->getRenderedFieldNames(), $flat->getRenderedFieldNames());
    }

    /** @test */
    public function csrf_token_link_helpers(): void
    {
        $form = $this->makeObject()->getForm();
        $token = $form->getCsrfToken();

        $this->assertSame('/x/delete/5?_csrf_token_dummy_table=' . $token, $form->withCsrfToken('/x/delete/5'));
        $this->assertSame('?module=x&id=5&_csrf_token_dummy_table=' . $token, $form->withCsrfToken('?module=x&id=5'));
        $this->assertTrue($form->isValidCsrfToken($token));
        $this->assertFalse($form->isValidCsrfToken(null));
        $this->assertFalse($form->isValidCsrfToken(strrev($token)));
    }
}
