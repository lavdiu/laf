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
        $this->assertTrue($ref->getValue($form));
    }

    /** @test */
    public function csrf_protection_can_be_disabled(): void
    {
        $form = $this->makeForm();
        $form->setCsrfProtection(false);

        $ref = new \ReflectionProperty(Form::class, 'csrfProtection');
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
        $this->assertTrue($method->invoke($form));
        // The token stays valid for the session: a second tab or the back button must not fail
        $this->assertArrayHasKey($sessionKey, $_SESSION);
        $this->assertTrue($method->invoke($form));
    }

    /** @test */
    public function csrf_skipped_when_disabled_allows_processing(): void
    {
        // filter_input() can't be overridden in CLI, so processForm() returns null
        // when form is not truly submitted. Instead, verify the flag is respected.
        $form = $this->makeForm('/submit');
        $form->setCsrfProtection(false);

        $ref = new \ReflectionProperty(Form::class, 'csrfProtection');
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

    // ── Processing (the request is read from $_POST, so it can be simulated) ──

    /** Posts like a browser: marker, draw mode and rot13 field names */
    private function post(array $fields, string $mode = DrawMode::INSERT): void
    {
        $_POST = ['dummy_table' => '1', 'dummy_table_draw_mode' => $mode];
        foreach ($fields as $name => $value) {
            $_POST[str_rot13($name)] = $value;
        }
    }

    /** @test */
    public function detect_submission_reads_method_and_draw_mode(): void
    {
        $form = $this->makeForm('/submit');
        $this->assertFalse($form->detectSubmission());

        $this->post([], DrawMode::UPDATE);
        $this->assertTrue($form->detectSubmission());
        $this->assertSame(Form::METHOD_POST, $form->getMethod());
        $this->assertSame(DrawMode::UPDATE, $form->getDrawMode());
    }

    /** @test */
    public function apply_submitted_values_converts_posted_fields_with_their_types(): void
    {
        $form = $this->makeForm('/submit');
        $this->post(['varchar_field45' => '  hello  ', 'bool_field' => '1', 'text_field' => '']);
        $form->detectSubmission();
        $form->applySubmittedValues();

        $object = $form->getObject();
        $this->assertSame('hello', $object->getField('varchar_field45')->getValue());
        $this->assertTrue($object->getField('bool_field')->getValue());
        $this->assertNull($object->getField('text_field')->getValue());
        $this->assertFalse($form->hasErrors());
    }

    /** @test */
    public function unposted_boolean_is_false_only_when_its_checkbox_was_on_the_form(): void
    {
        $form = $this->makeForm('/submit');
        $object = $form->getObject();
        $object->getField('bool_field')->setValue(true);

        $form->setComponents([$object->getField('varchar_field45')->getFormElement()]);
        $this->post(['varchar_field45' => 'x']);
        $form->detectSubmission();
        $form->applySubmittedValues();
        $this->assertTrue($object->getField('bool_field')->getValue(), 'not on the form: untouched');

        $form->setComponents([$object->getField('bool_field')->getFormElement()]);
        $form->applySubmittedValues();
        $this->assertFalse($object->getField('bool_field')->getValue(), 'on the form but absent: unchecked');
    }

    /** @test */
    public function posted_arrays_are_joined_with_commas(): void
    {
        $form = $this->makeForm('/submit');
        $this->post(['varchar_field45' => ['a', '', 'b']]);
        $form->detectSubmission();
        $form->applySubmittedValues();
        $this->assertSame('a,b', $form->getObject()->getField('varchar_field45')->getValue());
    }

    /** @test */
    public function submitted_field_values_set_by_code_win_over_the_request(): void
    {
        $form = $this->makeForm('/submit');
        $form->setSubmittedFieldValue('varchar_field45', 42);
        $this->post(['varchar_field45' => 'from request']);
        $form->detectSubmission();
        $form->applySubmittedValues();
        $this->assertSame('42', $form->getObject()->getField('varchar_field45')->getValue());
        $this->assertSame('42', $form->getSubmittedFieldValue('varchar_field45'));
    }

    /** @test */
    public function document_field_keeps_its_value_when_no_file_is_uploaded(): void
    {
        $form = $this->makeForm('/submit');
        $object = $form->getObject();
        $field = $object->getField('text_field');
        $object->getTable()->addForeignKey((new \Laf\Database\ForeignKey())->setField($field)->setReferencingTable('document')->setReferencingField('id'));
        $this->assertTrue($field->isDocumentField());
        $field->setValue('17');

        $this->post(['text_field' => '1']);
        $_FILES = [];
        $form->detectSubmission();
        $form->applySubmittedValues();
        $this->assertSame('17', $field->getValue());
        $this->assertFalse($form->hasErrors());

        $_FILES[str_rot13('text_field')] = ['name' => 'big.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0];
        $form->applySubmittedValues();
        $this->assertSame('17', $field->getValue());
        $this->assertArrayHasKey('text_field', $form->getErrors());
        $_FILES = [];
    }

    /** @test */
    public function conversion_errors_are_collected_per_field_instead_of_thrown(): void
    {
        $form = $this->makeForm('/submit');
        $this->post(['bool_field' => 'maybe', 'varchar_field45' => 'fine']);
        $form->detectSubmission();
        $form->applySubmittedValues();

        $this->assertTrue($form->hasErrors());
        $this->assertArrayHasKey('bool_field', $form->getErrors());
        $this->assertSame('fine', $form->getObject()->getField('varchar_field45')->getValue());
    }

    /** @test */
    public function validate_reports_required_and_length_constraints_and_marks_the_elements(): void
    {
        $form = $this->makeForm('/submit');
        $object = $form->getObject();
        $object->getField('varchar_field45')->setRequired(true);
        $form->setComponents($object->getAllFieldsAsFormElements());

        $this->post(['varchar_field45' => '']);
        $form->detectSubmission();
        $form->applySubmittedValues();
        $errors = $form->validate();
        $this->assertSame(['Varchar Field is required'], $errors['varchar_field45']);

        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString("<div id='" . str_rot13('varchar_field45') . "_feedback' class='invalid-feedback d-block'>Varchar Field is required</div>", $html);

        $this->post(['varchar_field45' => str_repeat('x', 46)]);
        $form->applySubmittedValues();
        $errors = $form->validate();
        $this->assertSame(['Varchar Field must be at most 45 characters'], $errors['varchar_field45']);

        $form->clearErrors();
        $this->assertStringNotContainsString('is-invalid', $form->draw());
    }

    /** @test */
    public function process_form_throws_a_validation_exception_carrying_every_message(): void
    {
        $form = $this->makeForm('/submit');
        $form->setCsrfProtection(false);
        $form->setComponents($form->getObject()->getAllFieldsAsFormElements());
        $this->post(['varchar_field45' => str_repeat('x', 46), 'bool_field' => 'maybe']);

        try {
            $form->processForm();
            $this->fail('expected FormValidationException');
        } catch (\Laf\Exception\FormValidationException $ex) {
            $this->assertArrayHasKey('varchar_field45', $ex->getErrors());
            $this->assertArrayHasKey('bool_field', $ex->getErrors());
            $this->assertStringContainsString('at most 45', $ex->getMessage());
            $this->assertCount(2, $ex->getMessages());
        }
    }

    /** @test */
    public function process_form_rejects_a_post_without_csrf_token(): void
    {
        $form = $this->makeForm('/submit');
        $this->post(['varchar_field45' => 'x']);
        $this->expectException(\Laf\Exception\CsrfTokenException::class);
        $form->processForm();
    }

    /** @test */
    public function process_form_accepts_the_session_token(): void
    {
        $form = $this->makeForm('/submit');
        $form->setComponents($form->getObject()->getAllFieldsAsFormElements());
        $form->setDrawMode(DrawMode::INSERT);
        $form->draw();

        $this->post(['varchar_field45' => str_repeat('x', 46)]);
        $_POST[$form->getCsrfSessionKey()] = $form->getCsrfToken();
        // past the CSRF check: the too-long value is what stops it now
        $this->expectException(\Laf\Exception\FormValidationException::class);
        $form->processForm();
    }

    /** @test */
    public function action_is_escaped_in_the_form_tag(): void
    {
        $form = $this->makeForm("/page?x='><script>alert(1)</script>");
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString("action='/page?x=&apos;&gt;&lt;script&gt;", $html);
    }

    /** @test */
    public function an_element_with_its_own_view_mode_stays_read_only_in_an_update_form(): void
    {
        $form = $this->makeForm('/submit');
        $object = $form->getObject();
        $object->getField('varchar_field45')->setValue('keep');
        $readOnly = $object->getField('varchar_field45')->getFormElement()->setDrawMode(DrawMode::VIEW);
        $form->setComponents([$readOnly, $object->getField('text_field')->getFormElement()]);
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString('form-control-plaintext', $html);
        $this->assertStringContainsString('keep', $html);
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringNotContainsString('name="' . str_rot13('varchar_field45') . '"', $html);
    }

    /** @test */
    public function csrf_token_is_stable_across_draws(): void
    {
        $form = $this->makeForm('/submit');
        $form->setDrawMode(DrawMode::UPDATE);
        $first = $form->draw();
        $second = $form->draw();

        $token = $_SESSION[$form->getCsrfSessionKey()];
        $this->assertSame(64, strlen($token));
        $this->assertStringContainsString("value='{$token}'", $first);
        $this->assertStringContainsString("value='{$token}'", $second);
        $this->assertSame($token, $form->getCsrfToken());
        $this->assertNotSame($token, $form->regenerateCsrfToken());
    }

    /** @test */
    public function tab_navigation_buttons_use_plain_javascript(): void
    {
        $form = $this->makeForm('/submit');
        $tabs = new \ReflectionProperty(Form::class, 'tabs');
        $tabs->setValue($form, [1, 2]);
        $form->setDrawMode(DrawMode::UPDATE);
        $html = $form->draw();

        $this->assertStringContainsString('Previous', $html);
        $this->assertStringContainsString('previousElementSibling', $html);
        $this->assertStringNotContainsString("$('", $html);
    }

    /** @test */
    public function hidden_submit_button_is_not_drawn(): void
    {
        $form = $this->makeForm('/submit');
        $form->hideSubmitButton();
        $form->setDrawMode(DrawMode::UPDATE);
        $this->assertStringNotContainsString('<button', $form->draw());
    }

    /** @test */
    public function rendered_field_names_and_element_lookup_search_containers(): void
    {
        $form = $this->makeForm('/submit');
        $object = $form->getObject();
        $div = new \Laf\UI\Container\Div();
        $div->addComponent($object->getField('text_field')->getFormElement());
        $form->setComponents([$object->getField('varchar_field45')->getFormElement(), $div, $form->getSubmitButton()]);

        $this->assertSame(['varchar_field45', 'text_field'], $form->getRenderedFieldNames());
        $this->assertInstanceOf(\Laf\UI\Form\Input\TextArea::class, $form->getFieldElement('text_field'));
        $this->assertNull($form->getFieldElement('bool_field'));
    }

    /** @test */
    public function add_fields_from_object_builds_elements_and_fieldsets(): void
    {
        $form = $this->makeForm('/submit');
        $form->addFieldsFromObject();
        $this->assertCount(4, $form->getComponents());

        $grouped = $this->makeForm('/submit');
        $grouped->groupFields('Main', ['varchar_field45', 'text_field']);
        $grouped->addFieldsFromObject();
        $this->assertCount(1, $grouped->getComponents());
        $this->assertInstanceOf(\Laf\UI\Container\Fieldset::class, $grouped->getComponents()[0]);

        $grouped->setDrawMode(DrawMode::UPDATE);
        $html = $grouped->draw();
        $this->assertStringContainsString('<legend>Main</legend>', $html);
        $this->assertStringContainsString('<textarea', $html);
    }

    /** @test */
    public function file_input_on_the_form_switches_to_multipart(): void
    {
        $form = $this->makeForm('/submit');
        $file = new \Laf\UI\Form\Input\File();
        $file->setField($form->getObject()->getField('text_field'));
        $form->setComponents([$file]);
        $form->setDrawMode(DrawMode::UPDATE);
        $this->assertStringContainsString("enctype='multipart/form-data'", $form->draw());
    }
}
