<?php

namespace Laf\UI\Form;

use Laf\Database\BaseObject;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBool;
use Laf\Exception\CsrfTokenException;
use Laf\Exception\FormValidationException;
use Laf\Exception\InvalidValueException;
use Laf\Filesystem\Document;
use Laf\UI\ComponentInterface;
use Laf\UI\Container\Fieldset;
use Laf\UI\Container\TabContent;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\Control\SubmitButton;
use Laf\UI\Form\Input\File;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * A form bound to a BaseObject: draws its field elements and writes a
 * submission back into the object.
 *
 * Submission contract (unchanged for the apps): hidden "form_submit" carries the
 * table name (isSubmitted()), a hidden input named after the table marks the
 * request (processForm()), "{name}_draw_mode" says whether it was an insert or
 * an update, every field is posted under its rot13 name, and
 * "_csrf_token_{name}" carries the CSRF token (POST only).
 *
 * processForm() = detectSubmission() + CSRF check + applySubmittedValues() +
 * validate() + insert()/update(). Invalid input raises FormValidationException
 * with every message; the elements then render their messages when the form is
 * drawn again.
 */
class Form implements ComponentInterface
{
    use ComponentTrait;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    const AUTOCOMPLETE_ON = 'on';
    const AUTOCOMPLETE_OFF = 'off';

    /** @var array<string, string[]> field groups for layout (label => [fieldNames]) */
    protected array $fieldGroups = [];

    /** @var array<string, callable> field visibility conditions (fieldName => callable) */
    protected array $fieldVisibilityConditions = [];

    protected ?Button $submitButton = null;

    protected ?Button $resetButton = null;

    protected ?BaseObject $object = null;

    protected ?string $action = null;

    protected bool $hasFiles = false;

    protected string $method = self::METHOD_POST;

    protected ?string $id = null;

    protected ?string $name = null;

    protected string $autocomplete = self::AUTOCOMPLETE_OFF;

    /** Browser-side validation (false renders novalidate) */
    protected bool $validate = true;

    /** @var TabContent[] @deprecated tabs are not drawn; kept for compatibility */
    protected array $tabs = [];

    protected bool $showSubmitButton = true;

    protected bool $showNavButtons = true;

    /** @var string */
    protected $formRowDisplayMode = FormRowDisplayMode::ROW;

    /** @var array<string, string|null> values set by code that override the request */
    protected array $submittedFieldValues = [];

    protected bool $csrfProtection = true;

    /** Run Field::validate() on the submitted fields before writing */
    protected bool $validateOnSubmit = true;

    /** @var array<string, string[]> field name => messages, from the last applySubmittedValues()/validate() */
    protected array $errors = [];

    /**
     * Form constructor.
     * @param BaseObject $tableObject
     * @param string|null $action
     */
    public function __construct(BaseObject $tableObject, ?string $action = null)
    {
        $this->setObject($tableObject);
        $this->setAction($action);

        $sb = new SubmitButton();
        $sb->setValue('Submit')
            ->addCssClass('btn')
            ->addCssClass('btn-md')
            ->addCssClass('btn-success');
        $this->setSubmitButton($sb);
    }

    /**
     * @param bool $hasFiles
     * @return Form
     */
    public function setHasFiles(bool $hasFiles): Form
    {
        $this->hasFiles = $hasFiles;
        return $this;
    }

    /* ───────────── processing ───────────── */

    /**
     * Process the form and store it in the db.
     *
     * @param string|null $documentHandlerClass class with a static upload(string $fieldName) for document fields (default Laf\Filesystem\Document)
     * @return int|string|array|null the record id, null when the form was not submitted
     * @throws CsrfTokenException when the token is missing or wrong (POST)
     * @throws FormValidationException when a submitted value is invalid or breaks a field constraint
     * @throws \Exception from the insert/update
     */
    public function processForm(?string $documentHandlerClass = null)
    {
        if (!$this->detectSubmission()) {
            return null;
        }

        if ($this->csrfProtection && $this->getMethod() === self::METHOD_POST && !$this->validateCsrfToken()) {
            throw new CsrfTokenException('CSRF token validation failed. Please reload the page and try again.');
        }

        $this->applySubmittedValues($documentHandlerClass);
        if ($this->validateOnSubmit) {
            $this->validate();
        } else {
            $this->pushErrorsToElements();
        }
        if ($this->hasErrors()) {
            throw new FormValidationException($this->errors);
        }

        $object = $this->getObject();
        $pk = $object->getTable()->getPrimaryKey();
        $pkField = $pk->getFirstField();

        if ($pk->hasValue()) {
            if ($pkField !== null && !$pkField->isAutoIncrement() && $this->getDrawMode() == DrawMode::INSERT) {
                $object->insert();
            } else {
                $object->update();
            }
        } else {
            $object->insert();
        }

        $object->reload();
        return $object->getRecordId();
    }

    /**
     * Reads the request marker: sets the method and the draw mode the form was drawn in.
     * @return bool false when this form was not submitted
     */
    public function detectSubmission(): bool
    {
        $marker = $this->getObject()->getTable()->getNameRot13();
        if (!empty($_GET[$marker])) {
            $this->setMethod(self::METHOD_GET);
        } elseif (!empty($_POST[$marker])) {
            $this->setMethod(self::METHOD_POST);
        } else {
            return false;
        }
        $mode = $this->getRequestData()[$this->getName() . '_draw_mode'] ?? '';
        $this->setDrawMode(is_scalar($mode) ? (string)$mode : '');
        return true;
    }

    /**
     * Copies the submitted values into the object's fields.
     *
     * Per field, in order: a value set with setSubmittedFieldValue() wins; a document
     * field is replaced only when a file was uploaded; a posted field is converted
     * with its type's fromInput(); a boolean that was rendered but not posted is
     * false. Conversion failures are collected as errors (getErrors()), not thrown.
     *
     * @param string|null $documentHandlerClass see processForm()
     */
    public function applySubmittedValues(?string $documentHandlerClass = null): void
    {
        $this->errors = [];
        $request = $this->getRequestData();
        $rendered = $this->getRenderedFieldNames();

        foreach ($this->getObject()->getTable()->getFields() as $field) {
            $name = $field->getName();
            $key = $field->getNameRot13();
            try {
                if (array_key_exists($name, $this->submittedFieldValues)) {
                    $field->setValue($this->convertInput($field, $this->submittedFieldValues[$name]));
                    continue;
                }

                if ($field->isDocumentField()) {
                    $this->applyUploadedDocument($field, $documentHandlerClass);
                    continue;
                }

                if (array_key_exists($key, $request)) {
                    $raw = $request[$key];
                    if (is_array($raw)) {
                        $raw = join(',', array_filter(array_map('strval', $raw), fn($v) => $v !== ''));
                    }
                    $field->setValue($this->convertInput($field, is_scalar($raw) ? (string)$raw : null));
                } elseif ($field->hasType() && $field->getType() instanceof TypeBool && in_array($name, $rendered, true)) {
                    /**
                     * a checkbox that is not ticked sends nothing: rendered but absent means false
                     */
                    $field->setValue(false);
                }
            } catch (InvalidValueException $ex) {
                $this->addError($name, sprintf('%s: invalid value', $field->getLabel() ?: $name));
            }
        }
    }

    /**
     * Runs Field::validate() on every rendered or explicitly submitted field and
     * hands the messages to the elements.
     * @return array<string, string[]> field name => messages (empty when valid)
     */
    public function validate(): array
    {
        $rendered = $this->getRenderedFieldNames();
        foreach ($this->getObject()->getTable()->getFields() as $field) {
            $name = $field->getName();
            if (!in_array($name, $rendered, true) && !array_key_exists($name, $this->submittedFieldValues)) {
                continue;
            }
            foreach ($field->validate() as $message) {
                $this->addError($name, $message);
            }
        }
        $this->pushErrorsToElements();
        return $this->errors;
    }

    /**
     * @return array<string, string[]> field name => messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string[] every message, in field order
     */
    public function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Records a message for a field (shown by its element in update mode)
     */
    public function addError(string $fieldName, string $message): Form
    {
        $this->errors[$fieldName][] = $message;
        return $this;
    }

    /**
     * @param array<string, string[]> $errors field name => messages
     */
    public function setErrors(array $errors): Form
    {
        $this->errors = [];
        foreach ($errors as $fieldName => $messages) {
            foreach ((array)$messages as $message) {
                $this->addError((string)$fieldName, (string)$message);
            }
        }
        $this->pushErrorsToElements();
        return $this;
    }

    public function clearErrors(): Form
    {
        $this->errors = [];
        $this->pushErrorsToElements();
        return $this;
    }

    /**
     * Validate the submitted fields with Field::validate() before writing (default true)
     */
    public function setValidateOnSubmit(bool $validateOnSubmit): Form
    {
        $this->validateOnSubmit = $validateOnSubmit;
        return $this;
    }

    public function isValidateOnSubmit(): bool
    {
        return $this->validateOnSubmit;
    }

    /**
     * @return BaseObject
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param BaseObject $object
     * @return Form
     */
    public function setObject(BaseObject $object): Form
    {
        $this->object = $object;
        $this->setId($object->getTable()->getNameRot13());
        $this->setName($object->getTable()->getNameRot13());
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): Form
    {
        $this->method = strtoupper($method) === self::METHOD_GET ? self::METHOD_GET : self::METHOD_POST;
        return $this;
    }

    /**
     * Checks if a field is submitted on the form
     * @param string $fieldName
     * @return bool
     */
    public function fieldIsSubmitted($fieldName): bool
    {
        return array_key_exists(Util::scrambleFieldOrTableName((string)$fieldName), $this->getRequestData());
    }

    /**
     * Checks if a form has been submitted
     * @return bool
     */
    public function isSubmitted(): bool
    {
        $submitted = $_POST['form_submit'] ?? ($_GET['form_submit'] ?? null);
        return is_scalar($submitted) && (string)$submitted !== ''
            && $this->getObject()->getTable()->getNameRot13() == (string)$submitted;
    }

    /**
     * @deprecated tabs added here are not drawn; they only decide whether Previous/Next buttons appear
     * @param TabContent $tab
     * @return $this
     */
    public function addTab(TabContent $tab): Form
    {
        $this->tabs[] = $tab;
        return $this;
    }

    public function showSubmitButton(): Form
    {
        $this->showSubmitButton = true;
        return $this;
    }

    public function hideSubmitButton(): Form
    {
        $this->showSubmitButton = false;
        return $this;
    }

    public function showNavButtons(): Form
    {
        $this->showNavButtons = true;
        return $this;
    }

    public function hideNavButtons(): Form
    {
        $this->showNavButtons = false;
        return $this;
    }

    public function getResetButton(): ?Button
    {
        return $this->resetButton;
    }

    /**
     * A reset button drawn before the submit button
     */
    public function setResetButton(?Button $resetButton): Form
    {
        $this->resetButton = $resetButton;
        return $this;
    }

    /* ───────────── drawing ───────────── */

    /**
     * @return string
     * @throws \Exception
     */
    public function draw(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        switch ($this->getDrawMode()) {
            case DrawMode::UPDATE:
            case DrawMode::INSERT:
                return $this->drawUpdateMode();
            default:
                return $this->drawViewMode();
        }
    }

    /**
     * Draws the form in view mode
     * @return string
     * @throws \Exception
     */
    public function drawViewMode(): string
    {
        $html = "<div class='form formview py-2' id='" . Util::e($this->getId()) . "_external_container'>";
        $html .= $this->drawComponents();
        $html .= "
        </div>";
        return $html;
    }

    /**
     * Draws the form in Update/insert Mode
     * @return string
     * @throws \Exception
     */
    public function drawUpdateMode(): string
    {
        $id = Util::e($this->getId());
        $name = Util::e($this->getName());
        $tableName = Util::e($this->getObject()->getTable()->getNameRot13());

        $html = "<div class='form formupdate py-2' id='{$id}_external_container'>
    <form
        action='" . Util::e($this->getAction()) . "'
        method='" . Util::e($this->getMethod()) . "'
        id='{$id}_id'
		class='" . Util::e($this->getCssClassesForHtml()) . "'
		style='" . Util::e($this->getCssStyleForHtml()) . "'
        name='{$name}_name'
        autocomplete='" . Util::e($this->getAutocomplete()) . "'"
            . (!$this->getValidate() ? "\n\t\tnovalidate='novalidate'" : "")
            . (($this->hasFiles() || $this->containsFileInput()) ? "\n\t\tenctype='multipart/form-data'" : "") . ">
             ";

        $html .= $this->drawComponents();

        $html .= "<input type='hidden' name='{$name}_draw_mode' id='{$id}_draw_mode' value='" . Util::e($this->getDrawMode()) . "' />";

        if ($this->csrfProtection) {
            $html .= "<input type='hidden' name='" . Util::e($this->getCsrfSessionKey()) . "' value='" . Util::e($this->generateCsrfToken()) . "' />";
        }

        $prevNextBtn = "";
        if ($this->hasTabs() && $this->getTabCount() > 1 && $this->isShowNavButtons()) {
            $prevNextBtn = "
            <a href='javascript:;' class='btn btn-outline-success' onclick=\"" . Util::attr($this->tabNavigationScript('previous')) . "\"><i class='fa fa-arrow-alt-circle-left'> </i> Previous</a>
            <a href='javascript:;' class='btn btn-outline-success' onclick=\"" . Util::attr($this->tabNavigationScript('next')) . "\"><i class='fa fa-arrow-alt-circle-right'> </i> Next</a>
            ";
        }

        $buttons = '';
        if ($this->getResetButton() !== null) {
            $buttons .= $this->getResetButton()->draw();
        }
        if ($this->isShowSubmitButton() && $this->getSubmitButton() !== null) {
            $this->getSubmitButton()->setName($this->getName() . '_submit');
            $buttons .= $this->getSubmitButton()->draw();
        }

        $html .= "
            <div class='row " . Util::e((string)$this->getFormRowDisplayMode()) . "'> <!-- footer -->
                <div class='col text-end'>
                    {$prevNextBtn}
                    {$buttons}
                    <input type='hidden' name='form_submit' id='form_submit' value='{$tableName}'/>
                    <input type='hidden' name='{$tableName}' id='{$tableName}' value='1' />
                </div>
            </div> <!-- footer -->
            ";
        $html .= "</form>
        </div>";
        return $html;
    }

    /**
     * Draws the components; the form's draw mode is applied only to components without one of their own
     * (same rule as the containers), so an element set to DrawMode::VIEW stays read-only in an update form.
     */
    protected function drawComponents(): string
    {
        $html = '';
        foreach ($this->getComponents() as $component) {
            if ((string)$component->getDrawMode() === '') {
                $component->setDrawMode($this->getDrawMode());
            }
            $component->setFormRowDisplayMode((string)$this->getFormRowDisplayMode());
            $html .= $component->draw();
        }
        return $html;
    }

    /**
     * Vanilla-JS click handler of the Previous / Next tab buttons
     * @param string $direction 'previous' or 'next'
     */
    protected function tabNavigationScript(string $direction): string
    {
        $sibling = $direction === 'next' ? 'nextElementSibling' : 'previousElementSibling';
        $id = $this->getId();
        return "(function(){var a=document.querySelector('#{$id}_tab_links > .nav-item > .active');"
            . "var li=a?a.closest('li'):null;var s=li?li.{$sibling}:null;var t=s?s.querySelector('a'):null;"
            . "if(t){t.click();window.scroll({top:0,left:0,behavior:'smooth'});}})();";
    }

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function setId(string $id): Form
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @deprecated see addTab()
     */
    public function hasTabs(): bool
    {
        return count($this->tabs) > 0;
    }

    /**
     * @deprecated see addTab()
     * @return TabContent[]
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    /**
     * @deprecated see addTab()
     * @param TabContent[] $tabs
     */
    public function setTabs(array $tabs): Form
    {
        $this->tabs = $tabs;
        return $this;
    }

    public function getName(): string
    {
        return (string)$this->name;
    }

    public function setName(string $name): Form
    {
        $this->name = $name;
        return $this;
    }

    public function getFormRowDisplayMode(): string
    {
        return (string)$this->formRowDisplayMode;
    }

    /**
     * Sets if the Input label and input should be displayed in the same row, or in new line.
     * Acceptable values are FormRowDisplayMode::INLINE ("") and FormRowDisplayMode::ROW ("row")
     * @param string $formRowDisplayMode
     * @return Form
     */
    public function setFormRowDisplayMode(string $formRowDisplayMode): Form
    {
        $this->formRowDisplayMode = $formRowDisplayMode;
        return $this;
    }

    /**
     * @deprecated see addTab()
     */
    public function getTabCount(): int
    {
        return count($this->getTabs());
    }

    public function isShowNavButtons(): bool
    {
        return $this->showNavButtons;
    }

    public function getAction(): string
    {
        return (string)$this->action;
    }

    /**
     * The action URL; empty falls back to the current request URI
     * @param string|null $action
     * @return Form
     */
    public function setAction(?string $action): Form
    {
        if (mb_strlen($action ?? '') > 0) {
            $this->action = $action;
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $this->action = (string)$_SERVER['REQUEST_URI'];
        } else {
            $this->action = null;
        }
        return $this;
    }

    public function getAutocomplete(): string
    {
        return $this->autocomplete;
    }

    public function setAutocomplete(string $autocomplete): Form
    {
        $this->autocomplete = $autocomplete;
        return $this;
    }

    public function getValidate(): bool
    {
        return $this->validate;
    }

    /**
     * Prevent automatic form validation by the browser
     * @param bool $validate
     * @return Form
     */
    public function setValidate(bool $validate): Form
    {
        $this->validate = $validate;
        return $this;
    }

    public function hasFiles(): bool
    {
        return $this->hasFiles;
    }

    public function isShowSubmitButton(): bool
    {
        return $this->showSubmitButton;
    }

    public function getSubmitButton(): ?Button
    {
        return $this->submitButton;
    }

    public function setSubmitButton(Button $submitButton): Form
    {
        $this->submitButton = $submitButton;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /* ───────────── submitted values set by code ───────────── */

    /**
     * Get the value of a field that was set with setSubmittedFieldValue()
     *
     * @param string $fieldName
     * @return string|null
     */
    public function getSubmittedFieldValue(string $fieldName): ?string
    {
        return $this->submittedFieldValues[$fieldName] ?? null;
    }

    /**
     * Set a value of a field before the form is processed; it overrides whatever the request carries.
     * @param string $fieldName
     * @param mixed $value string, int, float, bool or null
     * @return Form
     */
    public function setSubmittedFieldValue(string $fieldName, $value): Form
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf('Submitted value for %s must be scalar or null', $fieldName));
        }
        $this->submittedFieldValues[$fieldName] = $value === null ? null : (string)$value;
        return $this;
    }

    /* ───────────── field elements ───────────── */

    /**
     * The element of a field among the form's components (searched recursively), null when it is not on the form
     */
    public function getFieldElement(string $fieldName): ?FormElementInterface
    {
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            $field = $element->getField();
            if ($field !== null && $field->getName() === $fieldName) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Names of the fields whose elements are on the form (searched recursively through containers)
     * @return string[]
     */
    public function getRenderedFieldNames(): array
    {
        $names = [];
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            $field = $element->getField();
            if ($field !== null) {
                $names[] = $field->getName();
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Add fields from the object's table, supporting grouping and visibility.
     *
     * @param array|null $fields Field objects; every field of the table when null
     * @return $this
     */
    public function addFieldsFromObject(?array $fields = null): self
    {
        $fields = $fields ?? $this->object->getTable()->getFields();

        if (!empty($this->fieldGroups)) {
            foreach ($this->fieldGroups as $groupLabel => $fieldNames) {
                $fieldset = new Fieldset((string)$groupLabel);
                foreach ($fieldNames as $fieldName) {
                    if (!$this->object->getTable()->hasField($fieldName) || !$this->isFieldVisible($fieldName)) {
                        continue;
                    }
                    $fieldset->addComponent(FormElementFactory::create($this->object->getField($fieldName)));
                }
                $this->addComponent($fieldset);
            }
            return $this;
        }

        foreach ($fields as $field) {
            if ($field instanceof Field && $this->isFieldVisible($field->getName())) {
                $this->addComponent(FormElementFactory::create($field));
            }
        }
        return $this;
    }

    /**
     * Define a group of fields for layout purposes.
     *
     * @param string $groupLabel
     * @param string[] $fieldNames
     * @return $this
     */
    public function groupFields(string $groupLabel, array $fieldNames): self
    {
        $this->fieldGroups[$groupLabel] = $fieldNames;
        return $this;
    }

    /**
     * Set a visibility condition for a field.
     *
     * @param string $fieldName
     * @param callable $condition (Form $form): bool
     * @return $this
     */
    public function setFieldVisibilityCondition(string $fieldName, callable $condition): self
    {
        $this->fieldVisibilityConditions[$fieldName] = $condition;
        return $this;
    }

    /**
     * Check if a field is visible based on its condition.
     */
    public function isFieldVisible(string $fieldName): bool
    {
        if (!isset($this->fieldVisibilityConditions[$fieldName])) {
            return true;
        }
        return (bool)call_user_func($this->fieldVisibilityConditions[$fieldName], $this);
    }

    /* ───────────── CSRF ───────────── */

    /**
     * Enable or disable CSRF protection
     */
    public function setCsrfProtection(bool $enabled): Form
    {
        $this->csrfProtection = $enabled;
        return $this;
    }

    public function isCsrfProtection(): bool
    {
        return $this->csrfProtection;
    }

    /**
     * Session key and hidden input name of this form's token
     */
    public function getCsrfSessionKey(): string
    {
        return '_csrf_token_' . $this->getName();
    }

    /**
     * The token for this form: one per session and form name, created on first use and
     * reused by every draw, so several open tabs, the back button and a second draw on the
     * same page all stay valid.
     */
    public function getCsrfToken(): string
    {
        return $this->generateCsrfToken();
    }

    /**
     * Replace the token (e.g. after login/logout)
     */
    public function regenerateCsrfToken(): string
    {
        $this->ensureSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION[$this->getCsrfSessionKey()] = $token;
        return $token;
    }

    /**
     * Returns the session token, creating it when there is none
     */
    protected function generateCsrfToken(): string
    {
        $this->ensureSession();
        $key = $this->getCsrfSessionKey();
        $token = $_SESSION[$key] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[$key] = $token;
        }
        return $token;
    }

    /**
     * Validate the submitted CSRF token against the session token
     */
    protected function validateCsrfToken(): bool
    {
        $this->ensureSession();
        $key = $this->getCsrfSessionKey();
        $sessionToken = $_SESSION[$key] ?? null;
        $submittedToken = $this->getRequestData()[$key] ?? null;

        if (!is_string($sessionToken) || $sessionToken === '' || !is_string($submittedToken)) {
            return false;
        }
        return hash_equals($sessionToken, $submittedToken);
    }

    /* ───────────── internals ───────────── */

    /**
     * The request array the form reads from, by method
     */
    protected function getRequestData(): array
    {
        return $this->getMethod() === self::METHOD_GET ? $_GET : $_POST;
    }

    /**
     * User input to the field's canonical value
     * @throws InvalidValueException
     */
    protected function convertInput(Field $field, ?string $value): mixed
    {
        if (!$field->hasType()) {
            return $value;
        }
        return $field->getType()->fromInput($value);
    }

    /**
     * Stores the uploaded file of a document field and sets its id; keeps the current id when no file was sent.
     */
    protected function applyUploadedDocument(Field $field, ?string $documentHandlerClass): void
    {
        $key = $field->getNameRot13();
        $upload = $_FILES[$key] ?? [];
        if (!is_array($upload) || $upload === [] || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ((int)$upload['error'] !== UPLOAD_ERR_OK || (int)($upload['size'] ?? 0) <= 0) {
            $this->addError($field->getName(), sprintf('%s: the file could not be uploaded', $field->getLabel() ?: $field->getName()));
            return;
        }
        $handler = $documentHandlerClass ?? Document::class;
        $documentId = $handler::upload($key);
        if ($documentId === null || $documentId === '') {
            $this->addError($field->getName(), sprintf('%s: the file was not accepted', $field->getLabel() ?: $field->getName()));
            return;
        }
        $field->setValue($this->convertInput($field, (string)$documentId));
    }

    /**
     * Whether a File input is among the components (then the form needs multipart encoding)
     */
    protected function containsFileInput(): bool
    {
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            if ($element instanceof File) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hands the collected messages to the elements on the form
     */
    protected function pushErrorsToElements(): void
    {
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            $field = $element->getField();
            if ($field === null) {
                continue;
            }
            $element->setErrors($this->errors[$field->getName()] ?? []);
        }
    }

    /**
     * Every field-bound element among the components, recursively
     * @param ComponentInterface[] $components
     * @return FormElementInterface[]
     */
    protected function collectFieldElements(array $components): array
    {
        $elements = [];
        foreach ($components as $component) {
            if ($component instanceof FormElementInterface) {
                try {
                    $field = $component->getField();
                } catch (\Throwable $ex) {
                    $field = null;
                }
                if ($field !== null) {
                    $elements[] = $component;
                }
            }
            foreach ($this->collectFieldElements($component->getComponents()) as $element) {
                $elements[] = $element;
            }
        }
        return $elements;
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }
}
