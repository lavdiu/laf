<?php

declare(strict_types=1);

namespace Laf\Generator;

use Laf\Database\Field\Field;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;

/**
 * The JSON column comment the generators read (MySQL COMMENT '...', PostgreSQL
 * COMMENT ON COLUMN):
 *
 *   {"fields": {"setLabel": "Customer", "setHint": "Billing contact", "setRequired": false,
 *               "setType": "uuid", "setDisplayField": "label_al"},
 *    "form":   {"hidden": true, "readonly": "update", "exclude": ["view"], "group": "Address",
 *               "tab": "Pricing", "order": 20, "element": "TextArea", "create": true, "multi": false}}
 *
 * "fields" names Field setters with their argument (a list = several arguments);
 * "setType" takes a type name and "setDisplayField" the display column of the
 * table (or anything truthy for the commented column). "form" holds the hints
 * FormLayout::auto() reads (exclude/readonly/hidden take true, one draw mode or
 * a list of modes) and the element class (short name under Laf\UI\Form\Input or
 * a fully qualified class). "create" lets an autocomplete add a missing row of the
 * referenced table. On a column of a linker table (person_group.group_id) the comment
 * configures the multi-value picker the generator adds to the other side's form (the
 * Person form's "Groups"): setLabel / setHint / setRequired and the form hints above;
 * "multi": false leaves the picker out.
 */
final class ColumnComment
{
    public const FORM_MODE_KEYS = ['exclude', 'readonly', 'hidden'];
    public const FORM_KEYS = ['exclude', 'readonly', 'hidden', 'group', 'tab', 'order', 'element', 'create', 'multi'];

    /** Form options that take true or false */
    public const FORM_BOOL_KEYS = ['create', 'multi'];

    /**
     * The decoded comment, null when it is not a JSON object
     * @return array<string, mixed>|null
     */
    public static function parse(?string $comment): ?array
    {
        $comment = trim((string)$comment);
        if ($comment === '' || $comment[0] !== '{') {
            return null;
        }
        $decoded = json_decode($comment, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Field setters from "fields": method => PHP source of the argument list.
     * Unknown methods and setType/setDisplayField (handled by the generator) are left out;
     * unknown methods are reported in $warnings.
     * @param string[] $warnings
     * @return array<string, string>
     */
    public static function fieldSetters(?string $comment, array &$warnings = []): array
    {
        $fields = self::parse($comment)['fields'] ?? null;
        if (!is_array($fields)) {
            return [];
        }
        $setters = [];
        foreach ($fields as $method => $value) {
            $method = (string)$method;
            if (in_array($method, ['setType', 'setDisplayField'], true)) {
                continue;
            }
            if (!method_exists(Field::class, $method)) {
                $warnings[] = sprintf('column comment names an unknown Field method "%s"', $method);
                continue;
            }
            $setters[$method] = self::exportArguments($method, $value);
        }
        return $setters;
    }

    /**
     * The type name from "fields":{"setType":...}, null when absent
     */
    public static function typeOverride(?string $comment): ?string
    {
        $type = self::parse($comment)['fields']['setType'] ?? null;
        return is_string($type) && trim($type) !== '' ? trim($type) : null;
    }

    /**
     * The display column named by "fields":{"setDisplayField":...} on this column:
     * the value when it names a column of the table, else the commented column itself
     * (any truthy value); null when the comment says nothing about it.
     * @param string[] $columnNames
     */
    public static function displayField(?string $comment, string $column, array $columnNames): ?string
    {
        $parsed = self::parse($comment);
        if ($parsed === null || !array_key_exists('setDisplayField', $parsed['fields'] ?? [])) {
            return null;
        }
        $value = $parsed['fields']['setDisplayField'];
        if (is_string($value) && in_array($value, $columnNames, true)) {
            return $value;
        }
        return $value ? $column : null;
    }

    /**
     * The normalised "form" hints: exclude/readonly/hidden as draw-mode lists, group/tab as
     * strings, order as int, element as a fully qualified class. Null when the comment has
     * no "form" object. Unknown keys, modes and element classes are dropped with a warning.
     * @param string[] $warnings
     * @return array<string, mixed>|null
     */
    public static function formOptions(?string $comment, array &$warnings = []): ?array
    {
        $form = self::parse($comment)['form'] ?? null;
        if (!is_array($form)) {
            return null;
        }
        $options = [];
        foreach ($form as $key => $value) {
            $key = (string)$key;
            if (in_array($key, self::FORM_MODE_KEYS, true)) {
                $modes = self::modes($value, $key === 'readonly' ? FormLayout::EDIT_MODES : FormLayout::ALL_MODES, $warnings, $key);
                if ($modes !== []) {
                    $options[$key] = $modes;
                }
            } elseif ($key === 'group' || $key === 'tab') {
                if (is_scalar($value) && trim((string)$value) !== '') {
                    $options[$key] = trim((string)$value);
                }
            } elseif ($key === 'order') {
                if (is_numeric($value)) {
                    $options[$key] = (int)$value;
                }
            } elseif (in_array($key, self::FORM_BOOL_KEYS, true)) {
                $options[$key] = is_string($value) ? !in_array(strtolower(trim($value)), ['', '0', 'false', 'no'], true) : (bool)$value;
            } elseif ($key === 'element') {
                try {
                    $options[$key] = FormLayout::resolveElementClass((string)$value);
                } catch (\InvalidArgumentException $ex) {
                    $warnings[] = 'column comment: ' . $ex->getMessage();
                }
            } else {
                $warnings[] = sprintf('column comment: ignored form option "%s"', $key);
            }
        }
        return $options;
    }

    /**
     * A mode specification (true, one mode, a list of modes) as a list of valid draw modes
     * @param mixed $value
     * @param string[] $default modes for true
     * @param string[] $warnings
     * @return string[]
     */
    private static function modes($value, array $default, array &$warnings, string $key): array
    {
        if ($value === true) {
            return $default;
        }
        if ($value === false || $value === null) {
            return [];
        }
        $modes = [];
        foreach ((array)$value as $mode) {
            $mode = strtolower(trim((string)$mode));
            if (in_array($mode, [DrawMode::VIEW, DrawMode::INSERT, DrawMode::UPDATE], true)) {
                $modes[] = $mode;
            } else {
                $warnings[] = sprintf('column comment: unknown draw mode "%s" for form option "%s"', $mode, $key);
            }
        }
        return array_values(array_unique($modes));
    }

    /**
     * PHP source of the argument list of a Field setter call: a list value spreads into
     * several arguments; scalars are cast to the parameter's declared type.
     * @param mixed $value
     */
    private static function exportArguments(string $method, $value): string
    {
        $reflection = new \ReflectionMethod(Field::class, $method);
        $parameters = $reflection->getParameters();
        $isList = is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
        $values = $isList ? $value : [$value];
        $arguments = [];
        foreach ($values as $position => $argument) {
            $type = isset($parameters[$position]) ? $parameters[$position]->getType() : null;
            $arguments[] = self::exportValue($argument, $type instanceof \ReflectionNamedType ? $type->getName() : null);
        }
        return implode(', ', $arguments);
    }

    /**
     * @param mixed $value
     */
    private static function exportValue($value, ?string $typeName): string
    {
        if ($value !== null && is_scalar($value)) {
            switch ($typeName) {
                case 'bool':
                    $value = is_string($value) ? !in_array(strtolower($value), ['', '0', 'false', 'no'], true) : (bool)$value;
                    break;
                case 'int':
                    $value = (int)$value;
                    break;
                case 'float':
                    $value = (float)$value;
                    break;
                case 'string':
                    $value = is_bool($value) ? ($value ? '1' : '') : (string)$value;
                    break;
            }
        }
        return var_export($value, true);
    }
}
