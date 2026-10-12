<?php

namespace Laf\UI\Traits;

use Laf\UI\ComponentInterface;
use Laf\Util\Util;

/**
 * Attribute-backed accessors shared by form inputs and controls.
 *
 * Every value lives in the component's attribute bag (see ComponentTrait), so
 * addAttribute()/getAttribute() and these typed accessors always agree. Boolean
 * attributes (required, disabled, readonly, hidden, multiple, autofocus) are
 * stored as their own name, or '' when off, which the renderer skips.
 */
trait FormElementTrait
{
    /** Excluded from the form's processing and from the signed field list */
    protected bool $excluded = false;

    /** @var string[] validation messages shown next to the control in update mode */
    protected array $errors = [];

    public function setType(?string $value): ComponentInterface
    {
        $this->addAttribute('type', $value);
        return $this;
    }

    public function getType(): ?string
    {
        return $this->getAttribute('type');
    }

    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    public function setName(?string $value): ComponentInterface
    {
        $this->addAttribute('name', $value);
        return $this;
    }

    public function setId(?string $value): ComponentInterface
    {
        $this->addAttribute('id', $value);
        return $this;
    }

    public function getId(): ?string
    {
        return $this->getAttribute('id');
    }

    public function setMin(int|float|string|null $value): ComponentInterface
    {
        $this->addAttribute('min', $value === null ? null : (string)$value);
        return $this;
    }

    public function setMax(int|float|string|null $value): ComponentInterface
    {
        $this->addAttribute('max', $value === null ? null : (string)$value);
        return $this;
    }

    /**
     * @param int|float|string|null $value e.g. 1, 0.01, 'any'
     */
    public function setStep(int|float|string|null $value): ComponentInterface
    {
        $this->addAttribute('step', $value === null ? null : (string)$value);
        return $this;
    }

    /** @return int|float|string|null int or float when numeric, the string otherwise ('any', '18446744073709551615') */
    public function getMin(): int|float|string|null
    {
        return $this->numericAttribute('min');
    }

    public function getMax(): int|float|string|null
    {
        return $this->numericAttribute('max');
    }

    public function getStep(): int|float|string|null
    {
        return $this->numericAttribute('step');
    }

    public function setLabel(?string $value): ComponentInterface
    {
        $this->addWrapperAttribute('label', $value);
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->getWrapperAttribute('label');
    }

    public function setHint(?string $value): ComponentInterface
    {
        $this->addWrapperAttribute('hint', $value);
        return $this;
    }

    public function getHint(): ?string
    {
        return $this->getWrapperAttribute('hint');
    }

    public function setHeight(?int $value): ComponentInterface
    {
        $this->addAttribute('height', $value === null ? null : (string)$value);
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->intAttribute('height');
    }

    public function setWidth(?int $value): ComponentInterface
    {
        $this->addAttribute('width', $value === null ? null : (string)$value);
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->intAttribute('width');
    }

    public function isDisabled(): bool
    {
        return $this->getAttribute('disabled') === 'disabled';
    }

    public function setDisabled(bool $value): ComponentInterface
    {
        $this->addAttribute('disabled', $value ? 'disabled' : '');
        return $this;
    }

    public function isReadonly(): bool
    {
        return $this->getAttribute('readonly') === 'readonly';
    }

    public function setReadonly(bool $readonly): ComponentInterface
    {
        $this->addAttribute('readonly', $readonly ? 'readonly' : '');
        return $this;
    }

    public function setHidden(bool $value): ComponentInterface
    {
        $this->addAttribute('hidden', $value ? 'hidden' : '');
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->getAttribute('hidden') === 'hidden';
    }

    public function isRequired(): bool
    {
        return $this->getAttribute('required') === 'required';
    }

    public function setRequired(bool $value): ComponentInterface
    {
        $this->addAttribute('required', $value ? 'required' : '');
        return $this;
    }

    /**
     * The value, escaped for HTML output with line breaks kept ('' when there is none)
     */
    public function getValueForHtml(): string
    {
        return nl2br(Util::e($this->getAttribute('value') ?? ''));
    }

    public function isExcluded(): bool
    {
        return $this->excluded;
    }

    public function setExcluded(bool $value): ComponentInterface
    {
        $this->excluded = $value;
        return $this;
    }

    public function getSize(): ?int
    {
        return $this->intAttribute('size');
    }

    public function setSize(?int $value): ComponentInterface
    {
        $this->addAttribute('size', $value === null ? null : (string)$value);
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->getAttribute('multiple') === 'multiple';
    }

    public function setMultiple(bool $multiple): ComponentInterface
    {
        $this->addAttribute('multiple', $multiple ? 'multiple' : '');
        return $this;
    }

    public function getCols(): ?int
    {
        return $this->intAttribute('cols');
    }

    public function setCols(?int $value): ComponentInterface
    {
        $this->addAttribute('cols', $value === null ? null : (string)$value);
        return $this;
    }

    public function getRows(): ?int
    {
        return $this->intAttribute('rows');
    }

    public function setRows(?int $value): ComponentInterface
    {
        $this->addAttribute('rows', $value === null ? null : (string)$value);
        return $this;
    }

    public function isAutofocus(): bool
    {
        return $this->getAttribute('autofocus') === 'autofocus';
    }

    public function setAutofocus(bool $value): ComponentInterface
    {
        $this->addAttribute('autofocus', $value ? 'autofocus' : '');
        return $this;
    }

    /**
     * HTML autocomplete: true/false map to 'on'/'off'; a string is used as the token ('email', 'new-password', …).
     */
    public function setAutocomplete(bool|string|null $value): ComponentInterface
    {
        if (is_bool($value)) {
            $value = $value ? 'on' : 'off';
        }
        $this->addAttribute('autocomplete', $value);
        return $this;
    }

    public function getAutocomplete(): ?string
    {
        return $this->getAttribute('autocomplete');
    }

    /** False only when autocomplete is explicitly 'off' (the browser default is on). */
    public function isAutocomplete(): bool
    {
        return $this->getAttribute('autocomplete') !== 'off';
    }

    public function getPlaceholder(): ?string
    {
        return $this->getAttribute('placeholder');
    }

    public function setPlaceholder(?string $value): ComponentInterface
    {
        $this->addAttribute('placeholder', $value);
        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->getAttribute('pattern');
    }

    public function setPattern(?string $value): ComponentInterface
    {
        $this->addAttribute('pattern', $value);
        return $this;
    }

    /** HTML inputmode hint for virtual keyboards: 'numeric', 'decimal', 'email', 'tel', 'url', 'search' */
    public function setInputMode(?string $value): ComponentInterface
    {
        $this->addAttribute('inputmode', $value);
        return $this;
    }

    public function getInputMode(): ?string
    {
        return $this->getAttribute('inputmode');
    }

    /** Tooltip / native validation message text */
    public function setTitle(?string $value): ComponentInterface
    {
        $this->addAttribute('title', $value);
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->getAttribute('title');
    }

    /** Writes data-{name}; the name is used as given (e.g. 'bs-toggle' gives data-bs-toggle). */
    public function setDataAttribute(string $name, ?string $value): ComponentInterface
    {
        $this->addAttribute('data-' . $name, $value);
        return $this;
    }

    public function getDataAttribute(string $name): ?string
    {
        return $this->getAttribute('data-' . $name);
    }

    public function setAriaLabel(?string $value): ComponentInterface
    {
        $this->addAttribute('aria-label', $value);
        return $this;
    }

    public function setMaxLength(?int $value): ComponentInterface
    {
        $this->addAttribute('maxlength', $value === null ? null : (string)$value);
        return $this;
    }

    public function setMinLength(?int $value): ComponentInterface
    {
        $this->addAttribute('minlength', $value === null ? null : (string)$value);
        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->intAttribute('maxlength');
    }

    public function getMinLength(): ?int
    {
        return $this->intAttribute('minlength');
    }

    public function getValue(): ?string
    {
        return $this->getAttribute('value');
    }

    public function setValue(?string $value): ComponentInterface
    {
        $this->addAttribute('value', $value);
        return $this;
    }

    /* ───────────── validation messages ───────────── */

    /** @param string[] $errors */
    public function setErrors(array $errors): ComponentInterface
    {
        $this->errors = array_values(array_map('strval', $errors));
        return $this;
    }

    public function addError(string $message): ComponentInterface
    {
        $this->errors[] = $message;
        return $this;
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function clearErrors(): ComponentInterface
    {
        $this->errors = [];
        return $this;
    }

    /** Numeric attribute as int, null when unset or not numeric */
    private function intAttribute(string $key): ?int
    {
        $value = $this->getAttribute($key);
        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Attribute as int (whole number within PHP's range), float (other numbers) or the string itself
     */
    private function numericAttribute(string $key): int|float|string|null
    {
        $value = $this->getAttribute($key);
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^-?\d+$/', $value)) {
            // whole number: int when it fits, otherwise the digits themselves (no precision loss)
            return (string)(int)$value === $value ? (int)$value : $value;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        return $value;
    }
}
