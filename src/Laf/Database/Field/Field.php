<?php

namespace Laf\Database\Field;

use Laf\Database\Db;
use Laf\Database\PrimaryKey;
use Laf\Exception;
use Laf\Database\Table;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\Input\Text;
use Laf\Util\Util;

/**
 * One column of a table: name, type, constraints, the current and the loaded value, the
 * labels of its input and the form element bound to it.
 *
 * Values live in the type's canonical PHP form (see FieldType): setValue() normalises and
 * validates through the type, loadValueFromDb() converts without validating and remembers
 * the loaded value for hasChanged(). Generated Base classes build fields fluently
 * ((new Field())->setName()->setType()...) and attach them with Table::addField().
 */
class Field
{
    private ?string $name;

    private ?Table $table;

    /** @var mixed the current value in the type's canonical PHP form (int, bool, decimal string, 'Y-m-d', ...) */
    private mixed $value;

    /** @var mixed the value as loaded from the database */
    private mixed $oldValue;

    /** @var int|null unix time the value was loaded from the database */
    private ?int $loadedOnTs;

    /** @var int|null unix time the value last changed */
    private ?int $updatedOnTs;

    private ?FieldType $type;

    private ?int $maxLength;

    private ?int $minLength;

    /** @var int|float|string|null lower bound of a numeric value (a numeric string keeps decimals exact) */
    private int|float|string|null $minValue = null;

    /** @var int|float|string|null upper bound of a numeric value */
    private int|float|string|null $maxValue = null;

    /** Regular expression a non-empty value must match (preg syntax, with delimiters) */
    private ?string $pattern = null;

    private ?string $patternMessage = null;

    /** @var array<int, callable> fn(mixed $value, Field $field): null|bool|string|string[] */
    private array $validators = [];

    private ?int $incrementStep;

    private bool $required;

    private bool $unique;

    private bool $autoIncrement;

    private ?string $label;

    private ?string $placeholder;

    private ?string $hint;

    /** @var array<string, mixed> HTML attributes copied onto the input */
    private array $attributes;

    /** The input bound to this field (getFormElement()) */
    protected ?FormElementInterface $formElement = null;

    /**
     * Form-layout hints read by FormLayout::auto(): exclude / readonly / hidden (draw-mode
     * lists), group, tab, order, element (see Laf\Generator\ColumnComment)
     * @var array<string, mixed>
     */
    private array $formOptions = [];

    /** Explicit input class, wins over the type's choice and the foreign-key / hidden-key rules */
    private ?string $formElementClass = null;

    private ?string $invalidValueErrorMessage;

    /** @var array<string, mixed> column => value, narrows the rows a Select offers for this field */
    public array $dbSelectionCriteria = [];

    /** When true the value is rendered as raw HTML instead of being escaped */
    public bool $allowHtml = false;

    /**
     * Every argument is optional: generated code builds a field with `(new Field())->setName(...)`.
     * @param array<string, mixed>|null $attributes
     */
    public function __construct(?string $name = null, ?Table $table = null, ?string $value = null, ?string $oldValue = null, ?int $loadedOnTs = null, ?int $updatedOnTs = null, ?FieldType $type = null, ?int $maxLength = null, ?int $minLength = null, ?int $incrementStep = null, ?bool $required = null, ?bool $unique = null, ?bool $autoIncrement = null, ?string $label = null, ?string $placeholder = null, ?string $hint = null, ?array $attributes = [], ?string $invalidValueErrorMessage = null)
    {
        $this->name = $name;
        $this->table = $table;
        $this->value = $value;
        $this->oldValue = $oldValue;
        $this->loadedOnTs = $loadedOnTs;
        $this->updatedOnTs = $updatedOnTs;
        $this->type = $type;
        $this->maxLength = $maxLength;
        $this->minLength = $minLength;
        $this->incrementStep = $incrementStep;
        $this->required = $required ?? false;
        $this->unique = $unique ?? false;
        $this->autoIncrement = $autoIncrement ?? false;
        $this->label = $label;
        $this->placeholder = $placeholder;
        $this->hint = $hint;
        $this->attributes = $attributes ?? [];
        $this->invalidValueErrorMessage = $invalidValueErrorMessage;
    }

    /**
     * Column name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * The column name rot13-scrambled: the input name and id the form uses for this field
     */
    public function getNameRot13(): string
    {
        return str_rot13((string)$this->getName());
    }

    /**
     * Set field name
     * @param string|null $name
     * @return static
     */
    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Whether the field has been attached to a Table (false for a bare `new Field()`)
     */
    public function hasTable(): bool
    {
        return $this->table !== null;
    }

    /**
     * The table the field belongs to (TypeError for a bare field: check hasTable() first)
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * Attach the field to a table
     * @param Table $table
     * @return static
     */
    public function setTable(Table $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get field value
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * The value as escaped HTML with line breaks (raw when allowHtml is set)
     */
    public function getValueNl2Br(): string
    {
        if ($this->allowHtml()) {
            return nl2br((string)$this);
        }
        return nl2br(Util::e((string)$this));
    }

    /**
     * Returns the value formatted
     * for db insert
     * @return mixed
     */
    public function getValueForDbInsert(): mixed
    {
        return $this->getType()->formatForDb($this->getValue());
    }

    /**
     * Set field value
     * @param mixed $value
     * @return static
     * @throws Exception\InvalidValueException
     */
    public function setValue(mixed $value): static
    {
        if ($this->type === null) {
            return $this->setValueRaw($value);
        }

        try {
            $tmpVal = $this->type->normalize($value);
        } catch (Exception\InvalidValueException $ex) {
            throw new Exception\InvalidValueException(sprintf(
                'Invalid Value of \'%s\' for type:%s and field: %s:%s (%s)',
                is_scalar($value) ? $value : gettype($value),
                get_class($this->getType()),
                $this->getTableName(),
                $this->getName(),
                $ex->getMessage()
            ), 0, $ex);
        }

        if ($this->value !== $tmpVal) {
            $this->value = $tmpVal;
            $this->setUpdatedOnTs();
        }
        $this->syncFormElementValue();
        return $this;
    }

    /**
     * Load value from DB, sets value and oldValue
     * Bypasses validation; the value is converted to the type's canonical PHP
     * form (int, bool, decimal string, 'Y-m-d', …) when the type recognises it,
     * and kept as-is otherwise so a row can always be loaded.
     * @param mixed $value
     * @return static
     */
    public function loadValueFromDb(mixed $value): static
    {
        $converted = $this->type === null ? $value : $this->type->fromDb($value);
        $this->value = $converted;
        $this->oldValue = $converted;
        $this->setLoadedOnTs(time());
        $this->syncFormElementValue();
        return $this;
    }

    /**
     * Constraint check for the current value: required, the type's rules (length, range,
     * precision), minimum length, pattern and the validators added with addValidator().
     * Minimum length, pattern and validators only look at a non-empty value (an empty one is
     * the required rule's business).
     * @return string[] empty when valid
     */
    public function validate(): array
    {
        $messages = [];
        $label = $this->getLabel() ?: $this->getName();
        $empty = $this->value === null || $this->value === '';
        if ($this->isRequired() && !$this->isPrimaryKey() && $empty) {
            $messages[] = sprintf('%s is required', $label);
        }
        if ($this->type !== null && $this->value !== null) {
            $messages = array_merge($messages, $this->type->validate($this->value, $this));
        }
        if ($empty) {
            return $messages;
        }
        if (is_scalar($this->value) && !is_bool($this->value)) {
            $text = (string)$this->value;
            if (is_int($this->minLength) && $this->minLength > 0 && mb_strlen($text) < $this->minLength) {
                $messages[] = sprintf('%s must be at least %d characters', $label, $this->minLength);
            }
            if ($this->pattern !== null && preg_match($this->pattern, $text) !== 1) {
                $messages[] = $this->patternMessage ?? sprintf('%s has an invalid format', $label);
            }
        }
        foreach ($this->validators as $validator) {
            $result = $validator($this->value, $this);
            if ($result === null || $result === true || $result === [] || $result === '') {
                continue;
            }
            if ($result === false) {
                $messages[] = $this->invalidValueErrorMessage ?? sprintf('%s is invalid', $label);
                continue;
            }
            foreach ((array)$result as $message) {
                $messages[] = (string)$message;
            }
        }
        return $messages;
    }

    /**
     * Adds a rule checked by validate() (forms, BaseObject::validate(), store() with validation on):
     *   $field->addValidator(fn ($value) => str_ends_with($value, '.al') ? null : 'Only .al addresses');
     * The callable gets the value and the field and returns null/true (valid), false (invalid, the
     * field's invalid-value message or "%s is invalid"), a message, or a list of messages.
     * Not called for an empty value.
     */
    public function addValidator(callable $validator): static
    {
        $this->validators[] = $validator;
        return $this;
    }

    /** Removes the rules added with addValidator() */
    public function clearValidators(): static
    {
        $this->validators = [];
        return $this;
    }

    /**
     * @return array<int, callable>
     */
    public function getValidators(): array
    {
        return $this->validators;
    }

    /**
     * A regular expression a non-empty value must match, e.g. setPattern('/^[A-Z]{2}\d{4}$/', 'Code is two letters and four digits')
     * @throws \InvalidArgumentException when the expression does not compile
     */
    public function setPattern(?string $pattern, ?string $message = null): static
    {
        if ($pattern !== null && @preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException(sprintf('Invalid pattern for field %s: %s', (string)$this->name, $pattern));
        }
        $this->pattern = $pattern;
        $this->patternMessage = $message;
        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function getPatternMessage(): ?string
    {
        return $this->patternMessage;
    }

    /**
     * Set a raw unfiltered value
     * @param mixed $value
     * @return static
     */
    public function setValueRaw(mixed $value): static
    {
        if ($this->value != $value) {
            $this->value = $value;
            $this->setUpdatedOnTs();
        }
        $this->syncFormElementValue();
        return $this;
    }

    /**
     * Set HTML Value
     * @param mixed $value
     * @return static
     */
    public function setValueHTML(mixed $value): static
    {
        if ($this->value != $value) {
            $this->value = $value;
            $this->setUpdatedOnTs();
        }
        $this->syncFormElementValue();
        return $this;
    }

    /**
     * Mirrors the current value into the cached form element, so a form drawn after
     * the value changed (a submission, a setter, a reload) shows the current value.
     */
    private function syncFormElementValue(): void
    {
        if ($this->formElement !== null && method_exists($this->formElement, 'setValue')) {
            $this->formElement->setValue(Text::valueToString($this->value));
        }
    }

    /**
     * Unix time the value last changed, null when it never did
     */
    public function getUpdatedOnTs(): ?int
    {
        return $this->updatedOnTs;
    }

    /**
     * Stamp the value as changed now
     * @return static
     */
    public function setUpdatedOnTs(): static
    {
        $this->updatedOnTs = time();
        return $this;
    }

    /**
     * @param int|null $timestamp unix time
     * @return static
     */
    public function setLoadedOnTs(?int $timestamp): static
    {
        $this->loadedOnTs = $timestamp;
        return $this;
    }

    /**
     * Unix time the value was loaded from the database, null for a value that never was
     */
    public function getLoadedOnTs(): ?int
    {
        return $this->loadedOnTs;
    }

    /**
     * The value as loaded from the database
     * @return mixed
     */
    public function getOldValue(): mixed
    {
        return $this->oldValue;
    }

    /**
     * Set the loaded value
     * @param mixed $oldValue
     * @return static
     */
    public function setOldValue(mixed $oldValue): static
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    /**
     * Checks if field value has changed
     * @return bool
     */
    public function hasChanged(): bool
    {
        if ($this->type !== null) {
            return !$this->type->equals($this->getOldValue(), $this->getValue());
        }
        return $this->getOldValue() != $this->getValue();
    }

    /**
     * Whether a FieldType has been assigned
     */
    public function hasType(): bool
    {
        return $this->type !== null;
    }

    /**
     * Get field type
     * @return FieldType
     */
    public function getType(): FieldType
    {
        return $this->type;
    }

    /**
     * Set field type
     * @param FieldType $type
     * @return static
     */
    public function setType(FieldType $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get field length
     * @return int $length
     */
    public function getLength(): int
    {
        return mb_strlen((string)$this);
    }

    /**
     * Is required?
     * @return bool
     */
    public function isRequired(): bool
    {
        return (bool)$this->required;
    }

    /**
     * Set required
     * @param bool $required
     * @return static
     */
    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUnique(): bool
    {
        return (bool)$this->unique;
    }

    /**
     * @param bool $unique
     * @return static
     */
    public function setUnique(bool $unique): static
    {
        $this->unique = $unique;
        return $this;
    }


    /**
     * Is autoincrement?
     * @return bool
     */
    public function isAutoIncrement(): bool
    {
        return (bool)$this->autoIncrement;
    }

    /**
     * Set autoincrement
     * @param bool $autoIncrement
     * @return static
     */
    public function setAutoIncrement(bool $autoIncrement): static
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    /**
     * The primary key of the field's table, null when the table has none
     */
    public function getPrimaryKey(): ?PrimaryKey
    {
        return $this->getTable()->getPrimaryKey();
    }

    /**
     * Whether the field is (part of) the primary key
     */
    public function isPrimaryKey(): bool
    {
        $primaryKey = $this->getTable()->getPrimaryKey();
        return $primaryKey !== null && $primaryKey->hasField((string)$this->getName());
    }

    /**
     * Is a foreign key
     * @return bool
     */
    public function isForeignKey(): bool
    {
        return $this->getTable()->isForeignKey((string)$this->getName());
    }

    /**
     * Label of the input
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Set Label
     * @param string|null $label
     * @return static
     */
    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Placeholder of the input
     */
    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Set placeholder
     * @param string|null $placeholder
     * @return static
     */
    public function setPlaceholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Hint shown under the input
     */
    public function getHint(): ?string
    {
        return $this->hint;
    }

    /**
     * Set hint
     * @param string|null $hint
     * @return static
     */
    public function setHint(?string $hint): static
    {
        $this->hint = $hint;
        return $this;
    }

    /**
     * Message shown when the value is refused (null = the type's own message)
     */
    public function getInvalidValueErrorMessage(): ?string
    {
        return $this->invalidValueErrorMessage;
    }

    /**
     * Set the message shown when the value is refused
     * @param string|null $invalidValueErrorMessage
     * @return static
     */
    public function setInvalidValueErrorMessage(?string $invalidValueErrorMessage): static
    {
        $this->invalidValueErrorMessage = $invalidValueErrorMessage;
        return $this;
    }

    public function __toString(): string
    {
        $value = $this->getValue();
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }

    /**
     * For a foreign key: the display-field value of the referenced row (the value itself when the
     * referenced model or row is missing); the value for any other field
     * @return mixed
     */
    public function getReferencedValue(): mixed
    {
        if ($this->isForeignKey()) {
            $foreignKey = $this->getTable()->getForeignKey((string)$this->getName());
            $referencedTableObject = $foreignKey->getReferencedObject($this->getValue());
            if ($referencedTableObject === null) {
                return $this->getValue();
            }
            $displayField = $referencedTableObject->getTable()->getDisplayField();
            return $displayField ? $displayField->getValue() : $this->getValue();
        } else {
            return $this->getValue();
        }
    }

    /**
     * Name of the field's table
     */
    public function getTableName(): ?string
    {
        return $this->getTable()->getName();
    }

    /**
     * Model class name that corresponds to the table name
     */
    public function getClassName(): string
    {
        return Db::convertTableNameToClassName((string)$this->getTableName());
    }

    /**
     * Whether the current value passes validate()
     */
    public function isValidValue(): bool
    {
        return count($this->validate()) === 0;
    }

    /**
     * Cleanup value
     * @param mixed $value
     * @return mixed
     */
    public function sanitize(mixed $value): mixed
    {
        return $this->getType()->getValueDbSanitized($value);
    }

    /**
     * Get all HTML tag attributes
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * One HTML attribute, null when not set
     * @return mixed the value as given to setAttribute() (a string as a rule)
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set HTML tag attribute
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * @param int $maxLength
     * @return static
     */
    public function setMaxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    /**
     * @return int
     */
    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    /**
     * @param int $minLength
     * @return static
     */
    public function setMinLength(int $minLength): static
    {
        $this->minLength = $minLength;
        return $this;
    }

    /**
     * @return int
     */
    public function getIncrementStep(): ?int
    {
        return $this->incrementStep;
    }

    /**
     * @param int $incrementStep
     * @return static
     */
    public function setIncrementStep(int $incrementStep): static
    {
        $this->incrementStep = $incrementStep;
        return $this;
    }

    /**
     * @return int|float|string|null as given to setMinValue()
     */
    public function getMinValue(): int|float|string|null
    {
        return $this->minValue;
    }

    /**
     * Lower bound of a numeric value; a numeric string ('0.01') keeps a decimal bound exact, null removes it
     * @throws \InvalidArgumentException for a non-numeric value
     */
    public function setMinValue(int|float|string|null $minValue): static
    {
        $this->minValue = $this->numericBound($minValue);
        return $this;
    }

    /**
     * @return int|float|string|null as given to setMaxValue()
     */
    public function getMaxValue(): int|float|string|null
    {
        return $this->maxValue;
    }

    /**
     * Upper bound of a numeric value; null removes it
     * @throws \InvalidArgumentException for a non-numeric value
     */
    public function setMaxValue(int|float|string|null $maxValue): static
    {
        $this->maxValue = $this->numericBound($maxValue);
        return $this;
    }

    private function numericBound(int|float|string|null $bound): int|float|string|null
    {
        if (is_string($bound)) {
            $bound = trim($bound);
            if (!is_numeric($bound)) {
                throw new \InvalidArgumentException(sprintf('Field %s: a minimum or maximum must be numeric, got "%s"', (string)$this->name, $bound));
            }
        }
        return $bound;
    }

    /**
     * Set this field as display field
     * @return static
     */
    public function setTableDisplayField(): static
    {
        $this->getTable()->setDisplayField($this);
        return $this;
    }

    /**
     * Returns the input component for this field (cached until clearFormElementCache()).
     * The type decides, taking the column's role into account: a foreign key is a
     * Select whatever its type, a foreign key to the document table is a File
     * upload, an auto-increment primary key is Hidden.
     *
     * @param FormElementInterface|null $formElementOverride binds this element instead
     * @throws \LogicException when the type or the configured class does not produce a form element
     */
    public function getFormElement(?FormElementInterface $formElementOverride = null): FormElementInterface
    {
        if ($formElementOverride !== null && $formElementOverride != $this->formElement) {
            $this->formElement = $formElementOverride;
            $this->formElement->setField($this);
        }

        if ($this->formElement !== null) {
            return $this->formElement;
        }

        $class = $this->getFormElementClass();
        $element = $class !== null ? new $class() : $this->getType()->getFormElement($this);
        if (!$element instanceof FormElementInterface) {
            throw new \LogicException(sprintf('Field %s: %s is not a form element', $this->getName(), is_object($element) ? get_class($element) : gettype($element)));
        }
        if ($element->getField() === null) {
            $element->setField($this);
        }
        if ($element instanceof \Laf\UI\Form\Input\Autocomplete && $this->getFormOption('create') === true) {
            $element->setAllowCreate(true);
        }
        $this->formElement = $element;
        return $element;
    }

    /**
     * Form-layout hints for FormLayout::auto() (exclude, readonly, hidden, group, tab, order, element)
     * @param array<string, mixed> $options
     */
    public function setFormOptions(array $options): static
    {
        $this->formOptions = $options;
        if (array_key_exists('element', $options)) {
            $this->setFormElementClass($options['element'] === null ? null : (string)$options['element']);
        }
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormOptions(): array
    {
        return $this->formOptions;
    }

    /**
     * One form-layout hint
     * @param mixed $default
     * @return mixed
     */
    public function getFormOption(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->formOptions) ? $this->formOptions[$key] : $default;
    }

    /**
     * The input class getFormElement() instantiates instead of the type's choice; null restores
     * the default. A foreign key keeps its options only with a Select (Select, Radio,
     * CheckboxCollection), so another class is refused for it. A cached element of another
     * class is dropped so the next getFormElement() returns the new class.
     * @param string|null $class a FormElementInterface implementation (short name under Laf\UI\Form\Input or fully qualified)
     * @throws \InvalidArgumentException
     */
    public function setFormElementClass(?string $class): static
    {
        if ($class !== null) {
            $class = \Laf\UI\Form\FormLayout::resolveElementClass($class);
            if ($this->hasTable() && $this->isForeignKey() && !is_a($class, \Laf\UI\Form\Input\Select::class, true)) {
                throw new \InvalidArgumentException(sprintf('Field %s is a foreign key: its element must be a Select, "%s" given', $this->getName(), $class));
            }
        }
        $this->formElementClass = $class;
        $this->formOptions['element'] = $class;
        if ($class === null) {
            unset($this->formOptions['element']);
        }
        if ($this->formElement !== null && $class !== null && !($this->formElement instanceof $class)) {
            $this->formElement = null;
        }
        return $this;
    }

    public function getFormElementClass(): ?string
    {
        return $this->formElementClass;
    }

    /**
     * Whether a form element is bound to this field (getFormElement() was called or an override set)
     */
    public function hasFormElement(): bool
    {
        return $this->formElement !== null;
    }

    /**
     * Form elements with it's value are cached
     * This allows clearing cache so it can be reloaded
     */
    public function clearFormElementCache(): void
    {
        $this->formElement = null;
    }

    /** A cloned field starts without a cached form element (it would still point at the original field). */
    public function __clone(): void
    {
        $this->formElement = null;
    }

    /**
     * Check if the field points to the documents table and should have a document
     * @return bool
     */
    public function isDocumentField(): bool
    {
        $foreignKey = $this->getTable()->getForeignKey((string)$this->getName());
        return $foreignKey !== null && $foreignKey->getReferencingTable() === 'document';
    }

    /**
     * Get the criteria to select rows from DB
     * Used primarly for Select outputs and FK fields
     * @return array
     */
    public function getDbSelectionCriteria(): array
    {
        return $this->dbSelectionCriteria;
    }

    /**
     * Add a criteria to select rows from DB
     * Used primarly for Select outputs and FK fields
     * @param array $dbSelectionCriteria
     * @return static
     */
    public function setDbSelectionCriteria(array $dbSelectionCriteria): static
    {
        $this->dbSelectionCriteria = $dbSelectionCriteria;
        return $this;
    }

    /**
     * Add a criteria to select rows from DB
     * Used primarly for Select outputs and FK fields
     * @param string $key column name
     * @param mixed $value
     * @return static
     */
    public function addDbSelectionCriteria(string $key, mixed $value): static
    {
        $this->dbSelectionCriteria[$key] = $value;
        return $this;
    }

    /**
     * Check if the field has selection criteria
     * @return bool
     */
    public function hasDbSelectionCriteria(): bool
    {
        return count($this->dbSelectionCriteria) > 0;
    }


    /**
     * @return bool
     */
    public function allowHtml(): bool
    {
        return $this->allowHtml;
    }

    /**
     * @param bool $allowHtml
     * @return static
     */
    public function setAllowHtml(bool $allowHtml): static
    {
        $this->allowHtml = $allowHtml;
        return $this;
    }

}