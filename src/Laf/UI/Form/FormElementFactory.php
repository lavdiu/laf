<?php

declare(strict_types=1);

namespace Laf\UI\Form;

use Laf\Database\Field\Field;

/**
 * Creates the input element of a field.
 *
 * The field's type picks the natural input (Text, Integer, TextArea, Checkbox,
 * Date…) and the field's role overrides it: a foreign key is a Select (a File
 * upload when it points at the document table), an auto-increment primary key
 * is Hidden. The element is cached on the field until clearFormElementCache().
 */
final class FormElementFactory
{
    /**
     * The field's element, or the given override bound to the field.
     */
    public static function create(Field $field, ?FormElementInterface $override = null): FormElementInterface
    {
        /** @var FormElementInterface $element the type's getFormElement() always builds a form element */
        $element = $field->getFormElement($override);
        return $element;
    }

    /**
     * An element of the given class bound to the field (replaces the field's cached element).
     *
     * @param class-string $class e.g. Input\Radio::class
     */
    public static function createOfType(string $class, Field $field): FormElementInterface
    {
        if (!is_a($class, FormElementInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf('%s is not a form element class', $class));
        }
        /** @var FormElementInterface $element */
        $element = new $class();
        return self::create($field, $element);
    }
}
