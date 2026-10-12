<?php

namespace Laf\Util;

/**
 * Small static helpers: output escaping (e(), attr()), the name conversions the generator and
 * the models share (table_name → ClassName, field_name → Label / MethodName), JSON and number
 * parsing, UUIDs and a relative-time formatter.
 */
class Util
{
    /**
     * Escape a value for HTML text or attribute context.
     *
     * Storage keeps values verbatim; every place that writes a value into
     * markup goes through here. Existing entities are not re-encoded
     * (double_encode = false) so rows written by older LAF versions, which
     * stored "&amp;"-style text, still display as "&" instead of "&amp;".
     */
    public static function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    /**
     * Escape a value for a DOUBLE-quoted HTML attribute (key="...").
     * Single quotes are left alone so inline handlers such as
     * onclick="return confirm('Sure?')" render exactly as authored; the
     * double quote, &, < and > are what can break out of the attribute.
     */
    public static function attr(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return htmlspecialchars((string)$value, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    /**
     * Whether the value is a JSON object or array (a bare JSON scalar such as "1" or "\"x\"" is not).
     */
    public static function isJSON(mixed $string): bool
    {
        return is_string($string) && is_array(json_decode($string, true)) && json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Table name to class name: school_instructor → SchoolInstructor
     */
    public static function tableNameToClassName(?string $name): string
    {
        return str_replace('_', '', ucwords((string)$name, '_'));
    }

    /**
     * Column name to label: instructor_name → Instructor Name, department_id → Department
     */
    public static function tableFieldNameToLabel(?string $name): string
    {
        // only a trailing _id is a foreign key marker (user_identity stays "User Identity")
        $name = preg_replace('/_id$/', '', (string)$name);
        return str_replace('_', ' ', ucwords((string)$name, '_'));
    }

    /**
     * Column name to method name part: instructor_name → InstructorName
     */
    public static function tableFieldNameToMethodName(?string $name): string
    {
        return str_replace('_', '', ucwords((string)$name, '_'));
    }

    /**
     * A date typed in the locale display format (Locale::dateFormat(), 'F d, Y' by default) as
     * 'Y-m-d'; a date already in 'Y-m-d' is returned as is; null when it is neither.
     */
    public static function formatDateForDb(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }
        $parsed = Locale::parseDate($date, [Locale::dateFormat(), Locale::ISO_DATE]);
        return $parsed === null ? null : $parsed->format(Locale::ISO_DATE);
    }

    /**
     * A random version 4 UUID (xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx).
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant 10xx

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * The first argument that is not empty; 0 and "0" count as values, an array always does.
     * @param mixed ...$values
     * @return mixed null when every argument is empty
     */
    public static function coalesce(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if (is_array($value) || !empty($value) || $value === 0 || $value === '0') {
                return $value;
            }
        }
        return null;
    }

    /**
     * Returns a scrambled field name
     */
    public static function scrambleFieldOrTableName(string $name): string
    {
        return str_rot13($name);
    }

    /**
     * A number typed with either separator convention ("1,234.56", "1.234,56", "234,56") as a float;
     * the right-most separator is the decimal point, everything else is grouping.
     */
    public static function toFloat(string $number): float
    {
        $dotPos = strrpos($number, '.');
        $commaPos = strrpos($number, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
            ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

        if (!$sep) {
            return floatval(preg_replace("/[^0-9]/", "", $number));
        }

        return floatval(
            preg_replace("/[^0-9]/", "", substr($number, 0, $sep)) . '.' .
            preg_replace("/[^0-9]/", "", substr($number, $sep + 1, strlen($number)))
        );
    }

    /**
     * "5 seconds ago", "3 minutes ago", ... "2 years ago" for a past DateTime.
     */
    public static function timeAgo(\DateTimeInterface $dt): string
    {
        $timeDifference = time() - $dt->getTimestamp();

        if ($timeDifference < 60) {
            return $timeDifference . ' seconds ago';
        } elseif ($timeDifference < 3600) {
            return floor($timeDifference / 60) . ' minutes ago';
        } elseif ($timeDifference < 86400) {
            return floor($timeDifference / 3600) . ' hours ago';
        } elseif ($timeDifference < 2592000) {
            return floor($timeDifference / 86400) . ' days ago';
        } elseif ($timeDifference < 31536000) {
            return floor($timeDifference / 2592000) . ' months ago';
        }
        return floor($timeDifference / 31536000) . ' years ago';
    }
}
