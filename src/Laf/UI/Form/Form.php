<?php

namespace Laf\UI\Form;

use Laf\Database\Field\FieldType;
use Laf\Database\Field\TypeBool;
use Laf\Filesystem\Document;
use Laf\Database\BaseObject;
use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Container\GenericContainer;
use Laf\UI\Container\TabContent;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\Control\SubmitButton;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

class Form implements ComponentInterface
{
    /**
     * @var array Field groups for layout (label => [fieldNames])
     */
    protected $fieldGroups = [];

    /**
     * @var array Field visibility conditions (fieldName => callable)
     */
    protected $fieldVisibilityConditions = [];
    use ComponentTrait;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    const AUTOCOMPLETE_ON = 'on';
    const AUTOCOMPLETE_OFF = 'off';

    /**
     * @var GenericContainer[]
     */
    protected $components = [];

    /**
     * @var Button
     */
    protected $submitButton = null;

    /**
     * @var Button
     */
    protected $resetButton = null;

    /**
     * @var BaseObject $object
     */
    protected $object = null;

    /**
     * @var string $action
     */
    protected $action = null;

    /**
     * @var bool $hasFiles
     */
    protected $hasFiles = false;

    /**
     * @var int $mode
     */
    protected $mode = null;

    /**
     * @var string $method
     */
    protected $method = 'POST';

    /**
     * @var string $id
     */
    protected $id = null;

    /**
     * @var string $name ;
     */
    protected $name = null;

    /**
     * @var string $autocomplete
     */
    protected $autocomplete = 'off';

    /**
     * @var bool $validate
     */
    protected $validate = true;

    /**
     * @var TabContent[]
     */
    protected $tabs = [];

    /**
     * @var bool
     */
    protected $showSubmitButton = true;

    /**
     * @var bool
     */
    protected $showNavButtons = true;

    /**
     * @var string
     */
    protected $formRowDisplayMode = "row";

    /**
     * @var array
     */
    protected $submittedFieldValues = [];

    /**
     * Form constructor.
     * @param BaseObject $tableObject
     * @param string $action
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
     * @return \Laf\UI\Form\Form
     */
    public function setHasFiles(bool $hasFiles)
    {
        $this->hasFiles = $hasFiles;
        return $this;
    }


    /**
     * Process the form and store it in the db
     * @return int
     * @throws \Exception
     */
    public function processForm(?string $documentHandlerClass = null)
    {
        $object = $this->getObject();

        if (filter_input(INPUT_GET, $object->getTable()->getNameRot13())) {
            $this->setMethod(self::METHOD_GET);
            $this->setDrawMode($_GET[$this->getName() . '_draw_mode'] ?? '');
        } else if (filter_input(INPUT_POST, $object->getTable()->getNameRot13())) {
            $this->setMethod(self::METHOD_POST);
            $this->setDrawMode($_POST[$this->getName() . '_draw_mode'] ?? '');
        } else {
            return null;
        }

        foreach ($object->getTable()->getFields() as $field) {

            if ($this->getMethod() == self::METHOD_GET) {
                $value = $_GET[$field->getNameRot13()] ?? null;
            } else if ($this->getMethod() == self::METHOD_POST) {
                $value = $_POST[$field->getNameRot13()] ?? null;
            }
            $value = trim($value ?? '');

            if (array_key_exists($field->getName(), $this->submittedFieldValues)) {
                $value = trim($this->getSubmittedFieldValue($field->getName()) ?? '');
                $field->setValue($field->getType()->formatForDb($value));
            }else {
                #only upload and store files if the file is submitted,
                #if the form is updated and a replacement file is not uploaded, retain the old file id
                if (mb_strlen($value) > 0 || $this->fieldIsSubmitted($field->getName())) {
                    if ($field->isDocumentField()) {
                        if ($documentHandlerClass != null) {
                            $value = $documentHandlerClass::upload($field->getNameRot13());
                        } else {
                            $value = Document::upload($field->getNameRot13());
                        }
                    }
                    $field->setValue($field->getType()->formatForDb($value));
                }
            }
            /**
             * boolean fields show up as check boxes. WHen checkbox is not checked, it doesn't submit a var with empty result or 0.
             */
            if (get_class($field->getType()) == TypeBool::class) {
                $field->setValue($field->getType()->formatForDb($value));
            }
        }

        $pkField = $object->getField($object->getTable()->getPrimaryKey()->getFirstField()->getName());


        if ($pkField->getValue() != '') {
            if (!$pkField->isAutoIncrement() && $this->getDrawMode() == DrawMode::INSERT) {
                $object->insert();
            } else {
                $object->update();
            }
        } else {
            $object->insert();
        }
        /*
                if (
                    $pkField->getValue() != '' && $this->getDrawMode() == DrawMode::UPDATE #if the primary key field is set
                    && ($object->getTable()->getPrimaryKeyCount() != $object->getTable()->getFieldCount()) #but not all table fields are included in primary key (this is for 2 column linker tables)
                ) {
                    $object->update();
                } else {
                    $object->insert();
                }
        */

        $this->getObject()->reload();
        return $object->getRecordId();
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
     * @return \Laf\UI\Form\Form
     */
    public function setObject(BaseObject $object)
    {
        $this->object = $object;
        $this->setId($object->getTable()->getNameRot13());
        $this->setName($object->getTable()->getNameRot13());
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     * @return \Laf\UI\Form\Form
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Checks if a field is submitted on the form
     * @param $fieldName
     * @return bool
     */
    public function fieldIsSubmitted($fieldName)
    {
        $fieldNameRot13 = Util::scrambleFieldOrTableName($fieldName);

        if ($this->getMethod() == self::METHOD_GET) {
            return array_key_exists($fieldNameRot13, $_GET);
        } else if ($this->getMethod() == self::METHOD_POST) {
            return array_key_exists($fieldNameRot13, $_POST);
        } else {
            return false;
        }
    }

    /**
     * checks if a form has been submitted
     * @return bool
     */
    public function isSubmitted()
    {
        return (isset($_POST['form_submit']) || isset($_GET['form_submit']))
            && $this->getObject()->getTable()->getNameRot13() == (($_POST['form_submit'] ?? $_GET['form_submit']) ?? '');
    }

    /**
     * @param TabContent $tab
     * @return $this
     */
    public function addTab(TabContent $tab)
    {
        $this->tabs[] = $tab;
        return $this;
    }

    /**
     * @return Form
     */
    public function showSubmitButton(): Form
    {
        $this->showSubmitButton = true;
        return $this;
    }

    /**
     * @return Form
     */
    public function hideSubmitButton(): Form
    {
        $this->showSubmitButton = false;
        return $this;
    }

    /**
     * @return Form
     */
    public function showNavButtons(): Form
    {
        $this->showNavButtons = true;
        return $this;
    }

    /**
     * @return Form
     */
    public function hideNavButtons(): Form
    {
        $this->showNavButtons = false;
        return $this;
    }

    /**
     * @return Button
     */
    public function getResetButton(): Button
    {
        return $this->resetButton;
    }

    /**
     * @param Button $resetButton
     * @return Form
     */
    public function setResetButton(Button $resetButton): Form
    {
        $this->resetButton = $resetButton;
        return $this;
    }

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
                return static::drawUpdateMode();
                break;
            default:
                return static::drawViewMode();
                break;
        }
    }

    /**
     * Draws the form in view mode
     * @return string
     * @throws \Exception
     */
    public function drawViewMode()
    {
        $html =
            "<div class='form formview py-2' id='{$this->getId()}_external_container'>";

        foreach ($this->getComponents() as $component) {
            $component->setDrawMode($this->getDrawMode());
            $component->setFormRowDisplayMode($this->getFormRowDisplayMode());
            $html .= $component->draw();
        }

        $html .= "
        </div>";
        return $html;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return \Laf\UI\Form\Form
     */
    public function setId(string $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function hasTabs()
    {
        return count($this->tabs) > 0;
    }

    /**
     * @return TabContent[]
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    /**
     * @param TabContent[] $tabs
     * @return Form
     */
    public function setTabs(array $tabs): Form
    {
        $this->tabs = $tabs;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return \Laf\UI\Form\Form
     */
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getFormRowDisplayMode(): string
    {
        return $this->formRowDisplayMode;
    }

    /**
     * Sets if the Input label and input should be displayed in the same row, or in new line.
     * Acceptable values are "" and row
     * @param string $formRowDisplayMode
     * @return Form
     */
    public function setFormRowDisplayMode(string $formRowDisplayMode): Form
    {
        $this->formRowDisplayMode = $formRowDisplayMode;
        return $this;
    }

    /**
     * @return int
     */
    public function getTabCount()
    {
        return count($this->getTabs());
    }

    /**
     * @return bool
     */
    public function isShowNavButtons(): bool
    {
        return $this->showNavButtons;
    }

    /**
     * Draws the form in Update/insert Mode
     * @return string
     * @throws \Exception
     */
    public function drawUpdateMode()
    {
        $html =
            "<div class='form formupdate py-2' id='{$this->getId()}_external_container'>
    <form 
        action='{$this->getAction()}'
        method='{$this->getMethod()}'
        id='{$this->getId()}_id'
		class='{$this->getCssClassesForHtml()}' 
		style='{$this->getCssStyleForHtml()}'
        name='{$this->getName()}_name' 
        autocomplete='{$this->getAutocomplete()}'"
            . (!$this->getValidate() ? "\n\t\tnovalidate='novalidate'" : "")
            . ($this->hasFiles() ? "\n\t\tenctype='multipart/form-data'" : "") . ">
             ";

        foreach ($this->getComponents() as $component) {
            if ($component->getDrawMode() == '')
                $component->setDrawMode($this->getDrawMode());
            $component->setFormRowDisplayMode($this->getFormRowDisplayMode());
            $html .= $component->draw();
        }

        $html .= "<input type='hidden' name='{$this->getName()}_draw_mode' id='{$this->getId()}_draw_mode' value='{$this->getDrawMode()}' />";

        $prevNextBtn = "";
        if ($this->hasTabs() && $this->getTabCount() > 1 && $this->isShowNavButtons()) {
            $prevNextBtn = "
            <a href='javascript:;' class='btn btn-outline-success' onclick=\"$('#{$this->getId()}_tab_links > .nav-item > .active') . parent() . prev('li') . find('a') . trigger('click');window . scroll({top:0,left:0,behavior:'smooth'});\"><i class='fa fa-arrow-alt-circle-left'> </i> Previous</a>
            <a href='javascript:;' class='btn btn-outline-success' onclick=\"$('#{$this->getId()}_tab_links > .nav-item > .active') . parent() . next('li') . find('a') . trigger('click');window . scroll({top:0,left:0,behavior:'smooth'});\"><i class='fa fa-arrow-alt-circle-right'> </i> Next</a>
            ";
        }
        if ($this->isShowSubmitButton())
            $this->getSubmitButton()->setName($this->getName() . '_submit');
        $html .= "
            <div class='row {$this->getFormRowDisplayMode()} text-right'> <!-- footer -->
                <div class='col text-end'>
                    {$prevNextBtn}
                    {$this->getSubmitButton()->draw()}
                    <input type='hidden' name='form_submit' id='form_submit' value='{$this->getObject()->getTable()->getNameRot13()}'/>
                    <input type='hidden' name='{$this->getObject()->getTable()->getNameRot13()}' id='{$this->getObject()->getTable()->getNameRot13()}' value='" . rand(10000, 99999) . "' />
                </div>
            </div> <!-- footer -->
            ";
        $html .= "</form>
        </div>";
        return $html;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param string $action
     * @return \Laf\UI\Form\Form
     */
    public function setAction(?string $action)
    {

        if (mb_strlen($action ?? '') > 0)
            $this->action = $action;
        else if (isset($_SERVER['REQUEST_URI']))
            $this->action = $_SERVER['REQUEST_URI'];
        else
            $this->action = null;
        return $this;
    }

    /**
     * @return string
     */
    public function getAutocomplete(): string
    {
        return $this->autocomplete;
    }

    /**
     * @param string $autocomplete
     * @return \Laf\UI\Form\Form
     */
    public function setAutocomplete(string $autocomplete)
    {
        $this->autocomplete = $autocomplete;
        return $this;
    }

    /**
     * @return bool
     */
    public function getValidate(): bool
    {
        return $this->validate;
    }

    /**
     * Prevent automatic form validation by the browser
     * @param bool $validate
     * @return \Laf\UI\Form\Form
     */
    public function setValidate(bool $validate)
    {
        $this->validate = $validate;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasFiles(): bool
    {
        return $this->hasFiles;
    }

    /**
     * @return bool
     */
    public function isShowSubmitButton(): bool
    {
        return $this->showSubmitButton;
    }

    /**
     * @return Button
     */
    public function getSubmitButton(): Button
    {
        return $this->submitButton;
    }

    /**
     * @param FormElementInterface $submitButton
     * @return Form
     */
    public function setSubmitButton(FormElementInterface $submitButton): Form
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

    /**
     * Get the value of a field that is submitted in the form
     *
     * @param string $fieldName
     * @return string|null
     */
    public function getSubmittedFieldValue(string $fieldName): ?string
    {
        if (array_key_exists($fieldName, $this->submittedFieldValues)) {
            return $this->submittedFieldValues[$fieldName];
        } else {
            return null;
        }
    }

    /**
     * Set a value of a field before form is processed
     * @param string $fieldName
     * @param string $value
     * @return Form
     */
    public
    function setSubmittedFieldValue(string $fieldName, string $value): Form
    {
        $this->submittedFieldValues[$fieldName] = $value;
        return $this;
    }

    /**
     * Add fields from the object's table, supporting grouping and visibility.
     *
     * @param array|null $fields
     * @return $this
     */
    public function addFieldsFromObject(?array $fields = null): self {
        $fields = $fields ?? $this->object->getTable()->getFields();
        // If fieldGroups are defined, add grouped fields
        if (!empty($this->fieldGroups)) {
            foreach ($this->fieldGroups as $groupLabel => $fieldNames) {
                $groupHtml = "<fieldset><legend>{$groupLabel}</legend>";
                foreach ($fieldNames as $fieldName) {
                    $field = $this->object->getField($fieldName);
                    if ($field && $this->isFieldVisible($fieldName)) {
                        $input = FormElementFactory::create($field);
                        $groupHtml .= $input->draw();
                    }
                }
                $groupHtml .= "</fieldset>";
                $this->addComponent(new \Laf\UI\Container\HtmlContainer($groupHtml));
            }
        } else {
            // No groups: add all fields normally
            foreach ($fields as $field) {
                if ($this->isFieldVisible($field->getName())) {
                    $input = FormElementFactory::create($field);
                    $this->addComponent($input);
                }
            }
        }
        return $this;
    }

    /**
     * Define a group of fields for layout purposes.
     *
     * @param string $groupLabel
     * @param array $fieldNames
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
     *
     * @param string $fieldName
     * @return bool
     */
    public function isFieldVisible(string $fieldName): bool
    {
        if (!isset($this->fieldVisibilityConditions[$fieldName])) {
            return true;
        }
        return call_user_func($this->fieldVisibilityConditions[$fieldName], $this);
    }


}

