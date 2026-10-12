<?php

namespace Laf\UI\Form;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;

/**
 * A component that can be bound to a Field (inputs) or stands on its own in a form (buttons, links).
 */
interface FormElementInterface extends ComponentInterface
{
    /**
     * Binds the element to a field and copies the field's metadata (name, value, label, constraints)
     * @param Field $field
     * @return ComponentInterface
     */
    public function setField(Field $field): ComponentInterface;

    /**
     * The bound field; null for elements that are not bound (buttons)
     * @return Field|null
     */
    public function getField(): ?Field;

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string;

    public function getHint(): ?string;

    public function setHint(?string $value): ComponentInterface;

    /**
     * Validation messages shown next to the control in update mode
     * @param string[] $errors
     */
    public function setErrors(array $errors): ComponentInterface;

    /**
     * @return string[]
     */
    public function getErrors(): array;

    public function hasErrors(): bool;
}
