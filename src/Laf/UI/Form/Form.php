<?php

namespace Laf\UI\Form;

use Laf\Database\BaseObject;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBool;
use Laf\Exception\CsrfTokenException;
use Laf\Exception\FormValidationException;
use Laf\Exception\InvalidValueException;
use Laf\Exception\MissingFieldValueException;
use Laf\Exception\UniqueFieldDuplicateValueException;
use Laf\Exception\ValidationException;
use Laf\Filesystem\Document;
use Laf\UI\Component\Link;
use Laf\UI\ComponentInterface;
use Laf\UI\Container\Fieldset;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabContent;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\Control\SubmitButton;
use Laf\UI\Form\Input\Autocomplete;
use Laf\UI\Form\Input\File;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * A form bound to a BaseObject: draws its field elements and writes a
 * submission back into the object.
 *
 * Submission contract (unchanged for the apps): hidden "form_submit" carries the
 * table name (isSubmitted()), a hidden input named after the table marks the
 * request (processForm()), "{name}_draw_mode" says whether it was an insert or
 * an update, every field is posted under its rot13 name, "_csrf_token_{name}"
 * carries the CSRF token (POST only) and "{name}_fields" the signed list of
 * the editable elements the form was drawn with. A form reads the request of
 * its own method only: a POST form (the default) ignores a GET submission.
 *
 * processForm() = detectSubmission() + CSRF check + applySubmittedValues() +
 * validate() + insert()/update(). Invalid input raises FormValidationException
 * with every message; the elements then render their messages when the form is
 * drawn again, and the form draws an error summary above them.
 *
 * The footer row holds, in order, the reset button, the extra controls added
 * with addButton() / setCancelLink(), and the submit button.
 */
class Form implements ComponentInterface
{
    use ComponentTrait;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    const AUTOCOMPLETE_ON = 'on';
    const AUTOCOMPLETE_OFF = 'off';

    /** Key of the messages in getErrors() that belong to the form as a whole, not to one field */
    const FORM_ERROR_KEY = '_form';

    /** Session key of the secret that signs the field list */
    const SIGNING_KEY_SESSION_KEY = '_laf_form_key';

    /** @var array<string, string[]> field groups for layout (label => [fieldNames]) */
    protected array $fieldGroups = [];

    /** @var array<string, callable> field visibility conditions (fieldName => callable) */
    protected array $fieldVisibilityConditions = [];

    protected ?Button $submitButton = null;

    protected ?Button $resetButton = null;

    /** @var ComponentInterface[] extra controls drawn between the reset and the submit button */
    protected array $buttons = [];

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

    /** Draw the alert with every message above the fields when there are errors */
    protected bool $showErrorSummary = true;

    /** @var array<string, string|null> values set by code that override the request */
    protected array $submittedFieldValues = [];

    protected bool $csrfProtection = true;

    /** Run Field::validate() on the submitted fields before writing */
    protected bool $validateOnSubmit = true;

    /** Apply only the fields the form was drawn with (signed field list); off keeps every posted field */
    protected bool $restrictToRenderedFields = false;

    /** @var array<string, string[]> field name => messages, from the last applySubmittedValues()/validate() */
    protected array $errors = [];

    /** @var array|null request data set with setRequest(); null reads the superglobals */
    private ?array $requestData = null;

    /** @var array|null uploaded files set with setRequest(); null reads $_FILES */
    private ?array $requestFiles = null;

    /**
     * Form constructor.
     * @param BaseObject $tableObject
     * @param string|null $action
     */
    public function __construct(BaseObject $tableObject, ?string $action = null)
    {
        $this->setObject($tableObject);
        $this->setAction($action);
        $this->restrictToRenderedFields = Settings::getBool('form.restrict_to_rendered_fields');

        $sb = new SubmitButton();
        $sb->setValue('Submit')
            ->addCssClass('btn')
            ->addCssClass('btn-md')
            ->addCssClass('btn-success');
        $this->setSubmitButton($sb);
    }

    public function __toString(): string
    {
        return $this->draw();
    }

    /**
     * @param bool $hasFiles
     * @return static
     */
    public function setHasFiles(bool $hasFiles): static
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
     * @throws FormValidationException when a submitted value is invalid, breaks a field constraint, is missing
     *         although required or duplicates a unique value
     * @throws \Exception from the insert/update
     */
    public function processForm(?string $documentHandlerClass = null): int|string|array|null
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

        // the form has just run the same rules: the write does not repeat them
        $validateOnStore = $object->getValidateOnStore();
        if ($this->validateOnSubmit) {
            $object->setValidateOnStore(false);
        }
        try {
            if ($pk->hasValue()) {
                if ($pkField !== null && !$pkField->isAutoIncrement() && $this->getDrawMode() == DrawMode::INSERT) {
                    $object->insert();
                } else {
                    $object->update();
                }
            } else {
                $object->insert();
            }
        } catch (UniqueFieldDuplicateValueException | MissingFieldValueException $ex) {
            throw $this->validationExceptionFromWrite($ex);
        } catch (ValidationException $ex) {
            $this->setErrors(self::formErrors($ex->getErrors()));
            throw new FormValidationException($this->errors, $ex->getMessage(), 0, $ex);
        } finally {
            $object->setValidateOnStore($validateOnStore);
        }

        $object->reload();
        return $object->getRecordId();
    }

    /**
     * Answers the search of an Autocomplete on this form (?laf_lookup=table.field&laf_q=text) with
     * JSON {"results": [{"value", "label"}], "more": bool} and exits; call it before any output, after
     * the form is built (generated pages do). Only autocomplete fields on this form are answered.
     * @param array<string, mixed>|null $query the request parameters (default $_GET)
     * @param bool $terminate false = print the answer and return instead of exiting (tests)
     * @return bool false when the request is not a lookup
     */
    public function handleLookupRequest(?array $query = null, bool $terminate = true): bool
    {
        $query = $query ?? $_GET;
        $key = $query[Autocomplete::REQUEST_KEY] ?? null;
        if (!is_string($key) || $key === '') {
            return false;
        }
        $term = $query[Autocomplete::REQUEST_TERM] ?? '';
        $term = is_string($term) ? $term : '';

        $element = null;
        foreach ($this->getFieldElements() as $candidate) {
            if ($candidate instanceof Autocomplete && $candidate->getLookupKey() === $key) {
                $element = $candidate;
                break;
            }
        }
        if ($element === null) {
            $status = 404;
            $body = ['results' => [], 'more' => false, 'error' => 'Unknown lookup'];
        } else {
            try {
                $status = 200;
                $body = $element->search($term);
            } catch (\Throwable $ex) {
                $status = 500;
                $body = ['results' => [], 'more' => false, 'error' => 'Lookup failed'];
            }
        }

        if ($terminate) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($terminate) {
            $this->terminateRequest();
        }
        return true;
    }

    /** End of a lookup request; overridable */
    protected function terminateRequest(): void
    {
        exit;
    }

    /**
     * Reads the request marker of the form's method and the draw mode the form was drawn in.
     * @return bool false when this form was not submitted (a POST form ignores a GET submission)
     */
    public function detectSubmission(): bool
    {
        $request = $this->getRequestData();
        $marker = $this->getObject()->getTable()->getNameRot13();
        if (empty($request[$marker])) {
            return false;
        }
        $mode = $request[$this->getName() . '_draw_mode'] ?? '';
        $this->setDrawMode(is_scalar($mode) ? (string)$mode : '');
        return true;
    }

    /**
     * Copies the submitted values into the object's fields.
     *
     * Per field, in order: a value set with setSubmittedFieldValue() wins; a document
     * field is replaced only when a file was uploaded; a posted field is converted
     * with its type's fromInput(); a boolean that was drawn editable but not posted
     * is false. Which fields were drawn editable comes from the signed field list
     * the drawn form posts back ("{name}_fields"), or from the form's components
     * when the request carries none. With setRestrictToRenderedFields(true) a
     * posted field outside that list is ignored and a missing list is an error.
     * Conversion failures are collected as errors (getErrors()), not thrown; the
     * element then shows what was typed.
     *
     * @param string|null $documentHandlerClass see processForm()
     */
    public function applySubmittedValues(?string $documentHandlerClass = null): void
    {
        $this->errors = [];
        $request = $this->getRequestData();
        $listed = $this->verifiedFieldList();
        if ($listed === null && $this->restrictToRenderedFields) {
            $this->addError(self::FORM_ERROR_KEY, 'The form is out of date or was changed; reload the page and try again');
            return;
        }
        $editable = $this->editableFieldNamesFor($listed);

        foreach ($this->getObject()->getTable()->getFields() as $field) {
            $name = $field->getName();
            $key = $field->getNameRot13();
            $raw = null;
            try {
                if (array_key_exists($name, $this->submittedFieldValues)) {
                    $field->setValue($this->convertInput($field, $this->submittedFieldValues[$name]));
                    continue;
                }

                $isEditable = in_array($name, $editable, true);
                if ($this->restrictToRenderedFields && !$isEditable) {
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
                    $raw = is_scalar($raw) ? (string)$raw : null;
                    $field->setValue($this->convertInput($field, $raw));
                } elseif ($isEditable && $field->hasType() && $field->getType() instanceof TypeBool) {
                    /**
                     * a checkbox that is not ticked sends nothing: drawn editable but absent means false
                     */
                    $field->setValue(false);
                }
            } catch (InvalidValueException $ex) {
                $this->addError($name, sprintf('%s: invalid value', $field->getLabel() ?: $name));
                $element = $this->elementOf($field);
                if ($element !== null && $raw !== null && method_exists($element, 'setValue')) {
                    $element->setValue($raw);
                }
            }
        }
    }

    /**
     * Runs Field::validate() on every field that was drawn editable (signed field
     * list, else the form's components) or explicitly submitted, and hands the
     * messages to the elements.
     * @return array<string, string[]> field name => messages (empty when valid)
     */
    public function validate(): array
    {
        $editable = $this->editableFieldNamesFor($this->verifiedFieldList());
        foreach ($this->getObject()->getTable()->getFields() as $field) {
            $name = $field->getName();
            if (!in_array($name, $editable, true) && !array_key_exists($name, $this->submittedFieldValues)) {
                continue;
            }
            foreach ($field->validate() as $message) {
                $this->addError($name, $message);
            }
        }
        // the model's own rules (BaseObject::validateRecord()), once the fields are valid
        if (!$this->hasErrors()) {
            foreach (self::formErrors($this->getObject()->recordErrors()) as $name => $messages) {
                foreach ($messages as $message) {
                    $this->addError($name, $message);
                }
            }
        }
        $this->pushErrorsToElements();
        return $this->errors;
    }

    /**
     * Model errors keyed for the form: record-level messages go to the form's own key
     * @param array<string, string[]> $errors
     * @return array<string, string[]>
     */
    private static function formErrors(array $errors): array
    {
        $result = [];
        foreach ($errors as $name => $messages) {
            $key = $name === ValidationException::RECORD_KEY ? self::FORM_ERROR_KEY : (string)$name;
            foreach ($messages as $message) {
                $result[$key][] = $message;
            }
        }
        return $result;
    }

    /**
     * @return array<string, string[]> field name => messages (FORM_ERROR_KEY for form-level messages)
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
     * Records a message for a field; its element (when on the form) shows it in update mode.
     * Use FORM_ERROR_KEY for a message that belongs to the form as a whole.
     */
    public function addError(string $fieldName, string $message): static
    {
        $this->errors[$fieldName][] = $message;
        $element = $this->getFieldElement($fieldName);
        if ($element !== null) {
            $element->setErrors($this->errors[$fieldName]);
        }
        return $this;
    }

    /**
     * @param array<string, string[]> $errors field name => messages
     */
    public function setErrors(array $errors): static
    {
        $this->errors = [];
        foreach ($errors as $fieldName => $messages) {
            foreach ((array)$messages as $message) {
                $this->errors[(string)$fieldName][] = (string)$message;
            }
        }
        $this->pushErrorsToElements();
        return $this;
    }

    public function clearErrors(): static
    {
        $this->errors = [];
        $this->pushErrorsToElements();
        return $this;
    }

    /**
     * Validate the submitted fields with Field::validate() before writing (default true)
     */
    public function setValidateOnSubmit(bool $validateOnSubmit): static
    {
        $this->validateOnSubmit = $validateOnSubmit;
        return $this;
    }

    public function isValidateOnSubmit(): bool
    {
        return $this->validateOnSubmit;
    }

    /**
     * Apply only the fields the form was drawn with. The drawn form posts a signed list of its
     * editable elements; with this on, a posted field outside the list is ignored and a submission
     * without a valid list fails validation (FORM_ERROR_KEY). Default off, or Settings
     * 'form.restrict_to_rendered_fields'. Values set with setSubmittedFieldValue() always apply.
     */
    public function setRestrictToRenderedFields(bool $restrict): static
    {
        $this->restrictToRenderedFields = $restrict;
        return $this;
    }

    public function isRestrictToRenderedFields(): bool
    {
        return $this->restrictToRenderedFields;
    }

    /**
     * Read the submission from the given arrays instead of $_POST/$_GET and $_FILES
     * (tests, imports, API handlers); null goes back to the superglobals.
     * @param array|null $data field values keyed like the request (rot13 names, markers)
     * @param array|null $files like $_FILES
     */
    public function setRequest(?array $data, ?array $files = null): static
    {
        $this->requestData = $data;
        $this->requestFiles = $data === null ? null : ($files ?? []);
        return $this;
    }

    public function getObject(): BaseObject
    {
        return $this->object;
    }

    /**
     * @param BaseObject $object
     * @return static
     */
    public function setObject(BaseObject $object): static
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

    /**
     * GET or POST (default); the form reads the request array of this method only
     */
    public function setMethod(string $method): static
    {
        $this->method = strtoupper($method) === self::METHOD_GET ? self::METHOD_GET : self::METHOD_POST;
        return $this;
    }

    /**
     * Checks if a field is submitted on the form
     * @param string $fieldName
     * @return bool
     */
    public function fieldIsSubmitted(string $fieldName): bool
    {
        return array_key_exists(Util::scrambleFieldOrTableName($fieldName), $this->getRequestData());
    }

    /**
     * Checks if a form has been submitted (hidden "form_submit" of the form's method carries the table name)
     * @return bool
     */
    public function isSubmitted(): bool
    {
        $submitted = $this->getRequestData()['form_submit'] ?? null;
        return is_scalar($submitted) && (string)$submitted !== ''
            && $this->getObject()->getTable()->getNameRot13() == (string)$submitted;
    }

    /**
     * @deprecated tabs added here are not drawn; they only decide whether Previous/Next buttons appear
     * @param TabContent $tab
     * @return $this
     */
    public function addTab(TabContent $tab): static
    {
        $this->tabs[] = $tab;
        return $this;
    }

    public function showSubmitButton(): static
    {
        $this->showSubmitButton = true;
        return $this;
    }

    public function hideSubmitButton(): static
    {
        $this->showSubmitButton = false;
        return $this;
    }

    public function showNavButtons(): static
    {
        $this->showNavButtons = true;
        return $this;
    }

    public function hideNavButtons(): static
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
    public function setResetButton(?Button $resetButton): static
    {
        $this->resetButton = $resetButton;
        return $this;
    }

    /**
     * An extra control in the footer row, drawn between the reset and the submit button: a
     * Link styled as a button, a second SubmitButton with its own name ("save and new"), ...
     * A Button is drawn bare (setWrapInDiv(false)), the footer row is not a form row.
     */
    public function addButton(ComponentInterface $button): static
    {
        if ($button instanceof Button) {
            $button->setWrapInDiv(false);
        }
        $this->buttons[] = $button;
        return $this;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    /**
     * @param ComponentInterface[] $buttons
     */
    public function setButtons(array $buttons): static
    {
        $this->buttons = [];
        foreach ($buttons as $button) {
            $this->addButton($button);
        }
        return $this;
    }

    /**
     * A "Cancel" link styled as a button in the footer row (added through addButton())
     * @param string[] $cssClasses
     */
    public function setCancelLink(string $href, string $label = 'Cancel', array $cssClasses = ['btn', 'btn-outline-secondary']): static
    {
        return $this->addButton(new Link($label, $href, '', [], $cssClasses));
    }

    /**
     * The caption of the submit button (same as getSubmitButton()->setValue())
     */
    public function setSubmitButtonLabel(string $label): static
    {
        if ($this->submitButton !== null) {
            $this->submitButton->setValue($label);
        }
        return $this;
    }

    /**
     * Draw an alert listing every message above the fields when the form has errors (default true)
     */
    public function setShowErrorSummary(bool $show): static
    {
        $this->showErrorSummary = $show;
        return $this;
    }

    public function isShowErrorSummary(): bool
    {
        return $this->showErrorSummary;
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

        if ($this->showErrorSummary) {
            $html .= $this->drawErrorSummary();
        }

        $html .= $this->drawComponents();

        $html .= "<input type='hidden' name='{$name}_draw_mode' id='{$id}_draw_mode' value='" . Util::e($this->getDrawMode()) . "' />";
        $html .= "<input type='hidden' name='" . Util::e($this->getFieldListInputName()) . "' value='" . Util::e($this->signedFieldList($this->editableFieldKeys())) . "' />";

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
        foreach ($this->buttons as $button) {
            $buttons .= (string)$button->draw();
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
                    <input type='hidden' name='form_submit' id='{$id}_form_submit' value='{$tableName}'/>
                    <input type='hidden' name='{$tableName}' id='{$id}_marker' value='1' />
                </div>
            </div> <!-- footer -->
            ";
        $html .= "</form>
        </div>";
        return $html;
    }

    /**
     * The alert with every current message (empty string without errors); drawn above the
     * fields by default, or wherever a page puts it with setShowErrorSummary(false).
     */
    public function drawErrorSummary(): string
    {
        $messages = $this->getErrorMessages();
        if ($messages === []) {
            return '';
        }
        $items = '';
        foreach ($messages as $message) {
            $items .= "\n\t\t\t\t<li>" . Util::e($message) . "</li>";
        }
        return "\n\t\t<div id='" . Util::e($this->getId()) . "_errors' class='alert alert-danger laf-form-errors' role='alert'>"
            . "\n\t\t\t<ul class='mb-0'>" . $items . "\n\t\t\t</ul>\n\t\t</div>\n";
    }

    /**
     * Draws the components; the form's draw mode is applied only to components without one of their own
     * (same rule as the containers), so an element set to DrawMode::VIEW stays read-only in an update form.
     * Messages recorded on the form are handed to the elements first.
     */
    protected function drawComponents(): string
    {
        if ($this->hasErrors()) {
            $this->pushErrorsToElements(false);
        }
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
     * Vanilla-JS click handler of the Previous / Next tab buttons; targets the first
     * TabContainer among the components, else "{form id}_tab_links".
     * @param string $direction 'previous' or 'next'
     */
    protected function tabNavigationScript(string $direction): string
    {
        $container = $this->findTabContainer($this->getComponents());
        return TabContainer::navigationScript($container !== null ? $container->getId() : $this->getId(), $direction);
    }

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function setId(string $id): static
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
    public function setTabs(array $tabs): static
    {
        $this->tabs = $tabs;
        return $this;
    }

    public function getName(): string
    {
        return (string)$this->name;
    }

    public function setName(string $name): static
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
     * @return static
     */
    public function setFormRowDisplayMode(string $formRowDisplayMode): static
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
     * @return static
     */
    public function setAction(?string $action): static
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

    public function setAutocomplete(string $autocomplete): static
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
     * @return static
     */
    public function setValidate(bool $validate): static
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

    public function setSubmitButton(Button $submitButton): static
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
     * @return static
     */
    public function setSubmittedFieldValue(string $fieldName, $value): static
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
     * Every field-bound element among the components, recursively through containers
     * @return FormElementInterface[]
     */
    public function getFieldElements(): array
    {
        return $this->collectFieldElements($this->getComponents());
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
     * Names of the fields whose elements are on the form and post a value: not disabled, not excluded
     * and not drawn in VIEW mode (their own mode, or the form's after a draw)
     * @return string[]
     */
    public function getEditableFieldNames(): array
    {
        $names = [];
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            $field = $element->getField();
            if ($field !== null && $this->isEditableElement($element)) {
                $names[] = $field->getName();
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Lay the form out for a draw mode: $layout, else the object's getFormLayout(), else
     * FormLayout::auto() from the table. The layout replaces the components with the fields'
     * cached elements (values, errors and the signed field list keep working) and owns their
     * draw mode and hidden state.
     */
    public function applyLayout(string $drawMode, ?FormLayout $layout = null): static
    {
        $layout = $layout ?? $this->getObject()->getFormLayout() ?? FormLayout::auto($this->getObject()->getTable());
        $layout->apply($this, $drawMode);
        return $this;
    }

    /**
     * Add fields from the object's table, supporting grouping and visibility.
     *
     * @param array|null $fields Field objects; every field of the table when null
     * @return $this
     * @see FormLayout for sections, tabs, columns and per-mode exclusions (prefer it for new code)
     */
    public function addFieldsFromObject(?array $fields = null): static
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
     * @see FormLayout::fieldset() for a layout the model can declare
     */
    public function groupFields(string $groupLabel, array $fieldNames): static
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
    public function setFieldVisibilityCondition(string $fieldName, callable $condition): static
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
    public function setCsrfProtection(bool $enabled): static
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
     * The url with this form's CSRF token appended as a query parameter (named like the
     * hidden input, "_csrf_token_{form}"), for links that trigger a write such as delete
     */
    public function withCsrfToken(string $url): string
    {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . rawurlencode($this->getCsrfSessionKey()) . '=' . rawurlencode($this->getCsrfToken());
    }

    /**
     * Whether a token (from a query parameter or a request body) is this form's session token
     */
    public function isValidCsrfToken(?string $token): bool
    {
        $this->ensureSession();
        $sessionToken = $_SESSION[$this->getCsrfSessionKey()] ?? null;
        return is_string($sessionToken) && $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
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

    /* ───────────── field list ───────────── */

    /**
     * Name of the hidden input that carries the signed list of the editable elements the form was drawn with
     */
    public function getFieldListInputName(): string
    {
        return $this->getName() . '_fields';
    }

    /**
     * A request array this form accepts as its own submission, for tests, imports and
     * APIs that reuse processForm(): the markers, the draw mode, the CSRF token of the
     * session, the signed list of the given fields and the values under their rot13 names.
     *   $id = $form->setRequest($form->buildRequest(['name' => 'ACME', 'city' => 'Boston']))->processForm();
     * Unknown field names are kept as given (posted as-is). The list only names the
     * fields passed, so a boolean left out is left alone, as for an undrawn element.
     * @param array<string, mixed> $values column name => submitted value
     * @param string|null $drawMode DrawMode::INSERT / UPDATE; null = the form's current mode
     * @return array<string, mixed>
     */
    public function buildRequest(array $values, ?string $drawMode = null, bool $withCsrfToken = true): array
    {
        $table = $this->getObject()->getTable();
        $request = [
            'form_submit' => (string)$table->getName(),
            $table->getNameRot13() => '1',
            $this->getName() . '_draw_mode' => $drawMode ?? (string)$this->getDrawMode(),
        ];
        $keys = [];
        foreach ($values as $name => $value) {
            $name = (string)$name;
            if ($table->hasField($name)) {
                $key = $table->getField($name)->getNameRot13();
                $keys[] = $key;
                $request[$key] = $value;
            } else {
                $request[$name] = $value;
            }
        }
        $request[$this->getFieldListInputName()] = $this->signedFieldList($keys);
        if ($withCsrfToken && $this->csrfProtection) {
            $request[$this->getCsrfSessionKey()] = $this->getCsrfToken();
        }
        return $request;
    }

    /**
     * The value of the field-list input for the given rot13 field names: "name,name|hmac",
     * signed with a per-session secret the browser never sees, so a list cannot be forged or
     * moved to another form.
     * @param string[] $fieldKeys rot13 names
     */
    public function signedFieldList(array $fieldKeys): string
    {
        $list = join(',', $fieldKeys);
        return $list . '|' . hash_hmac('sha256', $this->getName() . '|' . $list, $this->signingKey());
    }

    /**
     * The rot13 names from the request's field list when its signature is valid, null when the
     * request carries no list or a tampered one.
     * @return string[]|null
     */
    protected function verifiedFieldList(): ?array
    {
        $raw = $this->getRequestData()[$this->getFieldListInputName()] ?? null;
        if (!is_string($raw) || strpos($raw, '|') === false) {
            return null;
        }
        [$list, $mac] = explode('|', $raw, 2);
        if (!preg_match('/^[A-Za-z0-9_,]*$/', $list)) {
            return null;
        }
        $expected = hash_hmac('sha256', $this->getName() . '|' . $list, $this->signingKey());
        if (!hash_equals($expected, $mac)) {
            return null;
        }
        return $list === '' ? [] : explode(',', $list);
    }

    /**
     * Field names that count as drawn editable for this request: those of a verified field list,
     * else the editable elements among the components.
     * @param string[]|null $fieldKeys rot13 names from verifiedFieldList()
     * @return string[]
     */
    protected function editableFieldNamesFor(?array $fieldKeys): array
    {
        if ($fieldKeys === null) {
            return $this->getEditableFieldNames();
        }
        $names = [];
        foreach ($this->getObject()->getTable()->getFields() as $field) {
            if (in_array($field->getNameRot13(), $fieldKeys, true)) {
                $names[] = $field->getName();
            }
        }
        return $names;
    }

    /**
     * rot13 names of the editable elements, for the field list of a drawn form
     * @return string[]
     */
    protected function editableFieldKeys(): array
    {
        $keys = [];
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            $field = $element->getField();
            if ($field !== null && $this->isEditableElement($element)) {
                $keys[] = $field->getNameRot13();
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * An element posts a value unless it is disabled, excluded or drawn read-only
     */
    protected function isEditableElement(FormElementInterface $element): bool
    {
        if ((string)$element->getDrawMode() === DrawMode::VIEW) {
            return false;
        }
        if (method_exists($element, 'isDisabled') && $element->isDisabled()) {
            return false;
        }
        if (method_exists($element, 'isExcluded') && $element->isExcluded()) {
            return false;
        }
        return true;
    }

    /**
     * The per-session secret that signs field lists, created on first use
     */
    private function signingKey(): string
    {
        $this->ensureSession();
        $key = $_SESSION[self::SIGNING_KEY_SESSION_KEY] ?? null;
        if (!is_string($key) || strlen($key) !== 64) {
            $key = bin2hex(random_bytes(32));
            $_SESSION[self::SIGNING_KEY_SESSION_KEY] = $key;
        }
        return $key;
    }

    /* ───────────── internals ───────────── */

    /**
     * The request array the form reads from: setRequest() data, else the superglobal of the form's method
     */
    protected function getRequestData(): array
    {
        if ($this->requestData !== null) {
            return $this->requestData;
        }
        return $this->getMethod() === self::METHOD_GET ? $_GET : $_POST;
    }

    /**
     * The uploaded files: setRequest() files, else $_FILES
     */
    protected function getRequestFiles(): array
    {
        return $this->requestFiles ?? $_FILES;
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
        $upload = $this->getRequestFiles()[$key] ?? [];
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
     * A write refused by the object (missing required value, duplicate unique value) as a
     * validation exception carrying one message per field, so the form shows it like any other error.
     */
    protected function validationExceptionFromWrite(\Exception $ex): FormValidationException
    {
        $errors = [];
        $names = method_exists($ex, 'getFieldNames') ? $ex->getFieldNames() : [];
        $table = $this->getObject()->getTable();
        foreach ($names as $name) {
            $name = (string)$name;
            $label = $table->hasField($name) ? ($table->getField($name)->getLabel() ?: $name) : $name;
            $errors[$name][] = $ex instanceof UniqueFieldDuplicateValueException
                ? sprintf('%s: this value is already in use', $label)
                : sprintf('%s is required', $label);
        }
        if ($errors === []) {
            $errors[self::FORM_ERROR_KEY][] = $ex->getMessage();
        }
        $this->setErrors($errors);
        return new FormValidationException($errors, $ex->getMessage(), 0, $ex);
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
     * @param bool $clearOthers also empty the messages of elements whose field has none here
     */
    protected function pushErrorsToElements(bool $clearOthers = true): void
    {
        foreach ($this->collectFieldElements($this->getComponents()) as $element) {
            $field = $element->getField();
            if ($field === null) {
                continue;
            }
            $messages = $this->errors[$field->getName()] ?? [];
            if ($messages !== [] || $clearOthers) {
                $element->setErrors($messages);
            }
        }
    }

    /**
     * The element bound to a field: the field's cached element, else the one among the components
     */
    protected function elementOf(Field $field): ?FormElementInterface
    {
        if ($field->hasFormElement()) {
            return $field->getFormElement();
        }
        return $this->getFieldElement($field->getName());
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

    /**
     * The first TabContainer among the components, recursively
     * @param ComponentInterface[] $components
     */
    protected function findTabContainer(array $components): ?TabContainer
    {
        foreach ($components as $component) {
            if ($component instanceof TabContainer) {
                return $component;
            }
            $found = $this->findTabContainer($component->getComponents());
            if ($found !== null) {
                return $found;
            }
        }
        return null;
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
