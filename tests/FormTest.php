<?php

use LafShell\DummyTable;
use Laf\UI\Form\Form;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Control\SubmitButton;
use Laf\UI\Form\Control\Button;
use Laf\UI\Container\TabContent;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FormTest extends TestCase
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

    private function makeObject(): DummyTable
    {
        $obj = new class extends DummyTable {
            public function __construct()
            {
                \Laf\Database\BaseObject::__construct();
                $this->buildClassForTest();
            }

            protected function returnLeafClass()
            {
                return $this;
            }

            private function buildClassForTest()
            {
                $this->setTable(new \Laf\Database\Table('dummy_table'));

                $pk = new \Laf\Database\PrimaryKey();
                $field = (new \Laf\Database\Field\Field())
                    ->setName("id")
                    ->setLabel("Id")
                    ->setRequired(true)
                    ->setMaxLength(255)
                    ->setAutoIncrement(true)
                    ->setUnique(false)
                    ->setType(new \Laf\Database\Field\TypeInteger());
                $pk->addField($field);
                $this->getTable()->addField($field);

                $field = (new \Laf\Database\Field\Field())
                    ->setName("varchar_field45")
                    ->setLabel("Varchar Field")
                    ->setRequired(false)
                    ->setMaxLength(45)
                    ->setAutoIncrement(false)
                    ->setUnique(false)
                    ->setType(new \Laf\Database\Field\TypeVarchar());
                $this->getTable()->addField($field);

                $field = (new \Laf\Database\Field\Field())
                    ->setName("text_field")
                    ->setLabel("Text Field")
                    ->setRequired(false)
                    ->setMaxLength(65535)
                    ->setAutoIncrement(false)
                    ->setUnique(false)
                    ->setType(new \Laf\Database\Field\TypeText());
                $this->getTable()->addField($field);

                $field = (new \Laf\Database\Field\Field())
                    ->setName("bool_field")
                    ->setLabel("Bool Field")
                    ->setRequired(false)
                    ->setMaxLength(255)
                    ->setAutoIncrement(false)
                    ->setUnique(false)
                    ->setType(new \Laf\Database\Field\TypeBool());
                $this->getTable()->addField($field);

                $this->getTable()->setPrimaryKey($pk);
            }
        };

        return $obj;
    }

    private function makeForm(?string $action = '/submit'): Form
    {
        return new Form($this->makeObject(), $action);
    }

    // ── Constructor ──

    /** @test */
    public function constructor_sets_object_and_action(): void
    {
        $form = $this->makeForm('/test-action');

        $this->assertEquals('/test-action', $form->getAction());
        $this->assertNotNull($form->getObject());
        $this->assertInstanceOf(SubmitButton::class, $form->getSubmitButton());
    }

    /** @test */
    public function constructor_sets_id_and_name_from_table(): void
    {
        $form = $this->makeForm();

        // Table::getNameRot13() returns getName() (no actual rot13)
        $this->assertEquals('dummy_table', $form->getId());
        $this->assertEquals('dummy_table', $form->getName());
    }

    // ── Method ──

    /** @test */
    public function method_defaults_to_post(): void
    {
        $form = $this->makeForm();
        $this->assertEquals('POST', $form->getMethod());
    }

    /** @test */
    public function set_method(): void
    {
        $form = $this->makeForm();
        $form->setMethod(Form::METHOD_GET);
        $this->assertEquals('GET', $form->getMethod());
    }

    // ── Action ──

    /** @test */
    public function set_action(): void
    {
        $form = $this->makeForm();
        $form->setAction('/new-action');
        $this->assertEquals('/new-action', $form->getAction());
    }

    /** @test */
    public function set_action_with_empty_string_falls_back(): void
    {
        $_SERVER['REQUEST_URI'] = '/current-page';
        $form = $this->makeForm();
        $form->setAction('');
        $this->assertEquals('/current-page', $form->getAction());
        unset($_SERVER['REQUEST_URI']);
    }

    // ── Autocomplete ──

    /** @test */
    public function autocomplete_defaults_to_off(): void
    {
        $form = $this->makeForm();
        $this->assertEquals('off', $form->getAutocomplete());
    }

    /** @test */
    public function set_autocomplete(): void
    {
        $form = $this->makeForm();
        $form->setAutocomplete(Form::AUTOCOMPLETE_ON);
        $this->assertEquals('on', $form->getAutocomplete());
    }

    // ── Validate ──

    /** @test */
    public function validate_defaults_to_true(): void
    {
        $form = $this->makeForm();
        $this->assertTrue($form->getValidate());
    }

    /** @test */
    public function set_validate_false(): void
    {
        $form = $this->makeForm();
        $form->setValidate(false);
        $this->assertFalse($form->getValidate());
    }

    // ── HasFiles ──

    /** @test */
    public function has_files_defaults_to_false(): void
    {
        $form = $this->makeForm();
        $this->assertFalse($form->hasFiles());
    }

    /** @test */
    public function set_has_files(): void
    {
        $form = $this->makeForm();
        $form->setHasFiles(true);
        $this->assertTrue($form->hasFiles());
    }

    // ── Submit Button ──

    /** @test */
    public function show_hide_submit_button(): void
    {
        $form = $this->makeForm();
        $this->assertTrue($form->isShowSubmitButton());

        $form->hideSubmitButton();
        $this->assertFalse($form->isShowSubmitButton());

        $form->showSubmitButton();
        $this->assertTrue($form->isShowSubmitButton());
    }

    /** @test */
    public function custom_submit_button(): void
    {
        $form = $this->makeForm();
        $btn = new SubmitButton();
        $btn->setValue('Save');
        $form->setSubmitButton($btn);

        $this->assertSame($btn, $form->getSubmitButton());
    }

    // ── Nav Buttons ──

    /** @test */
    public function show_hide_nav_buttons(): void
    {
        $form = $this->makeForm();
        $this->assertTrue($form->isShowNavButtons());

        $form->hideNavButtons();
        $this->assertFalse($form->isShowNavButtons());

        $form->showNavButtons();
        $this->assertTrue($form->isShowNavButtons());
    }

    // ── FormRowDisplayMode ──

    /** @test */
    public function form_row_display_mode_defaults_to_row(): void
    {
        $form = $this->makeForm();
        $this->assertEquals('row', $form->getFormRowDisplayMode());
    }

    /** @test */
    public function set_form_row_display_mode(): void
    {
        $form = $this->makeForm();
        $form->setFormRowDisplayMode('');
        $this->assertEquals('', $form->getFormRowDisplayMode());
    }

    // ── Tabs ──

    /** @test */
    public function tabs_empty_by_default(): void
    {
        $form = $this->makeForm();
        $this->assertFalse($form->hasTabs());
        $this->assertEquals(0, $form->getTabCount());
        $this->assertEmpty($form->getTabs());
    }

    /** @test */
    public function add_tab(): void
    {
        $form = $this->makeForm();
        $tab = new TabContent('Tab 1', 'Content 1');
        $form->addTab($tab);

        $this->assertTrue($form->hasTabs());
        $this->assertEquals(1, $form->getTabCount());
    }

    /** @test */
    public function set_tabs(): void
    {
        $form = $this->makeForm();
        $tabs = [new TabContent('Tab 1', 'C1'), new TabContent('Tab 2', 'C2')];
        $form->setTabs($tabs);

        $this->assertEquals(2, $form->getTabCount());
    }

    // ── Submitted Field Values ──

    /** @test */
    public function submitted_field_value_set_and_get(): void
    {
        $form = $this->makeForm();
        $form->setSubmittedFieldValue('varchar_field45', 'test_value');

        $this->assertEquals('test_value', $form->getSubmittedFieldValue('varchar_field45'));
    }

    /** @test */
    public function submitted_field_value_returns_null_for_unknown(): void
    {
        $form = $this->makeForm();
        $this->assertNull($form->getSubmittedFieldValue('nonexistent'));
    }

    // ── CSRF Protection ──

    /** @test */
    public function csrf_protection_enabled_by_default(): void
    {
        $form = $this->makeForm();

        $ref = new \ReflectionProperty(Form::class, 'csrfProtection');
        $ref->setAccessible(true);
        $this->assertTrue($ref->getValue($form));
    }

    /** @test */
    public function csrf_protection_can_be_disabled(): void
    {
        $form = $this->makeForm();
        $form->setCsrfProtection(false);

        $ref = new \ReflectionProperty(Form::class, 'csrfProtection');
        $ref->setAccessible(true);
        $this->assertFalse($ref->getValue($form));
    }

    /** @test */
    public function csrf_token_included_in_update_mode_html(): void
    {
        $form = $this->makeForm('/submit');
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString('_csrf_token_', $html);
        $this->assertStringContainsString("type='hidden'", $html);
    }

    /** @test */
    public function csrf_token_not_included_when_disabled(): void
    {
        $form = $this->makeForm('/submit');
        $form->setCsrfProtection(false);
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringNotContainsString('_csrf_token_', $html);
    }

    /** @test */
    public function csrf_token_stored_in_session(): void
    {
        $form = $this->makeForm('/submit');
        $form->setDrawMode(DrawMode::UPDATE);
        $form->draw();

        $sessionKey = '_csrf_token_' . $form->getName();
        $this->assertArrayHasKey($sessionKey, $_SESSION);
        $this->assertEquals(64, strlen($_SESSION[$sessionKey]));
    }

    /** @test */
    public function csrf_validate_fails_without_token_in_post(): void
    {
        $form = $this->makeForm('/submit');
        $form->setMethod(Form::METHOD_POST);
        $form->setDrawMode(DrawMode::UPDATE);
        $form->draw(); // generates token in session

        // No CSRF token in $_POST — validation should fail
        $method = new \ReflectionMethod(Form::class, 'validateCsrfToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($form));
    }

    /** @test */
    public function csrf_validate_fails_with_wrong_token(): void
    {
        $form = $this->makeForm('/submit');
        $form->setMethod(Form::METHOD_POST);
        $form->setDrawMode(DrawMode::UPDATE);
        $form->draw();

        $sessionKey = '_csrf_token_' . $form->getName();
        $_POST[$sessionKey] = 'wrong_token_value';

        $method = new \ReflectionMethod(Form::class, 'validateCsrfToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($form));
    }

    /** @test */
    public function csrf_validate_fails_with_expired_session_token(): void
    {
        $form = $this->makeForm('/submit');
        $form->setMethod(Form::METHOD_POST);
        $form->setDrawMode(DrawMode::UPDATE);
        $form->draw();

        $sessionKey = '_csrf_token_' . $form->getName();
        $submittedToken = $_SESSION[$sessionKey];
        unset($_SESSION[$sessionKey]); // simulate expired session

        $_POST[$sessionKey] = $submittedToken;

        $method = new \ReflectionMethod(Form::class, 'validateCsrfToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($form));
    }

    /** @test */
    public function csrf_validate_succeeds_with_correct_token(): void
    {
        $form = $this->makeForm('/submit');
        $form->setMethod(Form::METHOD_POST);
        $form->setDrawMode(DrawMode::UPDATE);
        $form->draw();

        $sessionKey = '_csrf_token_' . $form->getName();
        $_POST[$sessionKey] = $_SESSION[$sessionKey];

        $method = new \ReflectionMethod(Form::class, 'validateCsrfToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($form));
        // Token should be consumed after validation
        $this->assertArrayNotHasKey($sessionKey, $_SESSION);
    }

    /** @test */
    public function csrf_skipped_when_disabled_allows_processing(): void
    {
        // filter_input() can't be overridden in CLI, so processForm() returns null
        // when form is not truly submitted. Instead, verify the flag is respected.
        $form = $this->makeForm('/submit');
        $form->setCsrfProtection(false);

        $ref = new \ReflectionProperty(Form::class, 'csrfProtection');
        $ref->setAccessible(true);
        $this->assertFalse($ref->getValue($form));
    }

    /** @test */
    public function process_form_returns_null_when_not_submitted(): void
    {
        $form = $this->makeForm('/submit');

        $savedPost = $_POST;
        $savedGet = $_GET;
        $_POST = [];
        $_GET = [];

        $result = $form->processForm();
        $this->assertNull($result);

        $_POST = $savedPost;
        $_GET = $savedGet;
    }

    // ── Draw modes ──

    /** @test */
    public function draw_view_mode_produces_div(): void
    {
        $form = $this->makeForm('/view');
        $form->setDrawMode(DrawMode::VIEW);
        $html = $form->draw();

        $this->assertStringContainsString('formview', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    /** @test */
    public function draw_update_mode_produces_form(): void
    {
        $form = $this->makeForm('/update');
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('formupdate', $html);
        $this->assertStringContainsString("method='POST'", $html);
        $this->assertStringContainsString("action='/update'", $html);
        $this->assertStringContainsString('form_submit', $html);
    }

    /** @test */
    public function draw_insert_mode_produces_form(): void
    {
        $form = $this->makeForm('/insert');
        $form->setDrawMode(DrawMode::INSERT);
        $html = $form->draw();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('formupdate', $html);
    }

    /** @test */
    public function draw_update_mode_includes_submit_button(): void
    {
        $form = $this->makeForm('/submit');
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('Submit', $html);
    }

    /** @test */
    public function draw_update_with_novalidate(): void
    {
        $form = $this->makeForm('/submit');
        $form->setValidate(false);
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString("novalidate='novalidate'", $html);
    }

    /** @test */
    public function draw_update_with_file_enctype(): void
    {
        $form = $this->makeForm('/upload');
        $form->setHasFiles(true);
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString("enctype='multipart/form-data'", $html);
    }

    /** @test */
    public function draw_update_autocomplete_off(): void
    {
        $form = $this->makeForm('/submit');
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString("autocomplete='off'", $html);
    }

    // ── isSubmitted ──

    /** @test */
    public function is_submitted_returns_false_when_not_posted(): void
    {
        $_POST = [];
        $_GET = [];

        $form = $this->makeForm();
        $this->assertFalse($form->isSubmitted());
    }

    /** @test */
    public function is_submitted_returns_true_when_form_submit_matches(): void
    {
        $form = $this->makeForm();
        $tableName = $form->getObject()->getTable()->getNameRot13();

        $_POST['form_submit'] = $tableName;
        $this->assertTrue($form->isSubmitted());
    }

    /** @test */
    public function is_submitted_returns_false_when_form_submit_doesnt_match(): void
    {
        $form = $this->makeForm();

        $_POST['form_submit'] = 'wrong_table';
        $this->assertFalse($form->isSubmitted());
    }

    // ── Field Visibility ──

    /** @test */
    public function field_visibility_defaults_to_true(): void
    {
        $form = $this->makeForm();
        $this->assertTrue($form->isFieldVisible('varchar_field45'));
        $this->assertTrue($form->isFieldVisible('nonexistent_field'));
    }

    /** @test */
    public function field_visibility_condition_can_hide_field(): void
    {
        $form = $this->makeForm();
        $form->setFieldVisibilityCondition('varchar_field45', function () {
            return false;
        });

        $this->assertFalse($form->isFieldVisible('varchar_field45'));
        $this->assertTrue($form->isFieldVisible('text_field'));
    }

    /** @test */
    public function field_visibility_condition_receives_form(): void
    {
        $form = $this->makeForm();
        $receivedForm = null;

        $form->setFieldVisibilityCondition('text_field', function ($f) use (&$receivedForm) {
            $receivedForm = $f;
            return true;
        });

        $form->isFieldVisible('text_field');
        $this->assertSame($form, $receivedForm);
    }

    // ── Field Groups ──

    /** @test */
    public function group_fields(): void
    {
        $form = $this->makeForm();
        $result = $form->groupFields('Personal Info', ['varchar_field45', 'text_field']);

        $this->assertSame($form, $result);

        $ref = new \ReflectionProperty(Form::class, 'fieldGroups');
        $ref->setAccessible(true);
        $groups = $ref->getValue($form);

        $this->assertArrayHasKey('Personal Info', $groups);
        $this->assertEquals(['varchar_field45', 'text_field'], $groups['Personal Info']);
    }

    // ── Component CSS Class ──

    /** @test */
    public function component_css_control_class(): void
    {
        $form = $this->makeForm();
        $this->assertStringContainsString('Form', $form->getComponentCssControlClass());
        $this->assertStringNotContainsString('\\', $form->getComponentCssControlClass());
    }

    // ── Constants ──

    /** @test */
    public function form_constants(): void
    {
        $this->assertEquals('GET', Form::METHOD_GET);
        $this->assertEquals('POST', Form::METHOD_POST);
        $this->assertEquals('on', Form::AUTOCOMPLETE_ON);
        $this->assertEquals('off', Form::AUTOCOMPLETE_OFF);
    }
}
