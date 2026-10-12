<?php

namespace Laf\Util;

/**
 * Single source of truth for locale-dependent parsing and display formats.
 *
 * Every value is read from Settings on each call (so tests and multi-tenant
 * apps can change them at runtime) and falls back to the historical LAF default
 * when the key is not configured.
 *
 * Settings keys:
 *   locale.date.format        display/parse format for DATE columns       (default 'F d, Y')
 *   locale.datetime.format    display/parse format for DATETIME columns   (default 'F d, Y H:i')
 *   locale.time.format        display/parse format for TIME columns       (default 'g:i A')
 *   locale.decimal_separator  character users type as the decimal point   (default '.')
 *   locale.thousands_separator character users type as a grouping mark    (default ',')
 *   locale.language           the lang attribute of the HTML document   (default 'en')
 */
class Locale
{
    public const ISO_DATE = 'Y-m-d';
    public const ISO_DATETIME = 'Y-m-d H:i:s';
    public const ISO_TIME = 'H:i:s';

    public const DEFAULT_DATE_FORMAT = 'F d, Y';
    public const DEFAULT_DATETIME_FORMAT = 'F d, Y H:i';
    public const DEFAULT_TIME_FORMAT = 'g:i A';

    private static function setting(string $key, string $default): string
    {
        try {
            $value = Settings::get($key);
            return (is_string($value) && $value !== '') ? $value : $default;
        } catch (\Throwable $ex) {
            return $default;
        }
    }

    public static function dateFormat(): string
    {
        return self::setting('locale.date.format', self::DEFAULT_DATE_FORMAT);
    }

    public static function dateTimeFormat(): string
    {
        return self::setting('locale.datetime.format', self::DEFAULT_DATETIME_FORMAT);
    }

    public static function timeFormat(): string
    {
        return self::setting('locale.time.format', self::DEFAULT_TIME_FORMAT);
    }

    /**
     * The language of the HTML document (`<html lang>`), e.g. 'en', 'sq', 'de-CH'.
     */
    public static function language(): string
    {
        return self::setting('locale.language', 'en');
    }

    /**
     * The format with ":s" after the minutes when it has no seconds token ('H:i' gives 'H:i:s',
     * 'F d, Y H:i' gives 'F d, Y H:i:s'); unchanged when it already shows seconds or has no minutes.
     */
    public static function formatWithSeconds(string $format): string
    {
        if (preg_match('/(?<!\\\\)s/', $format)) {
            return $format;
        }
        $result = preg_replace('/(?<!\\\\)i/', 'i:s', $format, 1);
        return $result === null ? $format : $result;
    }

    public static function decimalSeparator(): string
    {
        return self::setting('locale.decimal_separator', '.');
    }

    public static function thousandsSeparator(): string
    {
        return self::setting('locale.thousands_separator', ',');
    }

    /**
     * A number for display with the locale's separators: "1234567.5" -> "1,234,567.50"
     * (or "1.234.567,50" with ',' / '.' configured). Accepts a canonical numeric string,
     * an int/float, or a user-typed string (parsed with parseNumber()); null, '' and
     * non-numbers give $empty. $decimals null keeps the digits the value has.
     * @param int|float|string|null $value
     */
    public static function formatNumber($value, ?int $decimals = 2, string $empty = ''): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }
        $canonical = is_int($value) || is_float($value) ? (string)$value : self::parseNumber((string)$value);
        if ($canonical === null || !is_numeric($canonical)) {
            return $empty;
        }
        if ($decimals === null) {
            $dot = strpos($canonical, '.');
            $decimals = $dot === false ? 0 : strlen(rtrim(substr($canonical, $dot + 1), '0'));
        }
        return number_format((float)$canonical, $decimals, self::decimalSeparator(), self::thousandsSeparator());
    }

    /**
     * A DATE value for display in locale.date.format: '2026-10-12' -> 'October 12, 2026'.
     * Accepts a DateTimeInterface, an ISO string, or a string already in the display
     * format; null, '' and unparsable values give $empty.
     * @param \DateTimeInterface|string|null $value
     */
    public static function formatDate($value, string $empty = ''): string
    {
        return self::formatTemporal($value, self::dateFormat(), [self::ISO_DATE, self::ISO_DATETIME], $empty);
    }

    /** A DATETIME value for display in locale.datetime.format (seconds kept when the value has them and the format shows them) */
    public static function formatDateTime($value, string $empty = ''): string
    {
        return self::formatTemporal($value, self::dateTimeFormat(), [self::ISO_DATETIME, self::ISO_DATE], $empty);
    }

    /** A TIME value for display in locale.time.format: '14:30:00' -> '2:30 PM' */
    public static function formatTime($value, string $empty = ''): string
    {
        return self::formatTemporal($value, self::timeFormat(), [self::ISO_TIME, 'H:i', self::ISO_DATETIME], $empty);
    }

    /**
     * @param \DateTimeInterface|string|null $value
     * @param string[] $isoFormats formats the stored value may have, tried before the display format itself
     */
    private static function formatTemporal($value, string $displayFormat, array $isoFormats, string $empty): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format($displayFormat);
        }
        $parsed = self::parseDate((string)$value, array_merge($isoFormats, [$displayFormat, self::formatWithSeconds($displayFormat)]));
        return $parsed === null ? $empty : $parsed->format($displayFormat);
    }

    /**
     * Parse a string against a list of formats, strictly.
     * Each format is tried with the '!' modifier so unspecified fields are reset to
     * the epoch instead of being filled from the current time, and any parser
     * warning (e.g. "2024-02-30" → "The parsed date was invalid") counts as a failure.
     *
     * @param string[] $formats
     */
    public static function parseDate(string $value, array $formats): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach ($formats as $format) {
            $format = ltrim($format, '!');
            $dt = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($dt === false) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }
            return $dt;
        }
        return null;
    }

    /**
     * Normalise a user-typed number ("1 234,56", "1,234.56", "-12.5", "1.5e3") to a
     * canonical numeric string using '.' as the decimal point and no grouping.
     * Returns null when the string is not a number.
     *
     * Rules, in order:
     *   1. whitespace (incl. NBSP) is removed;
     *   2. if both '.' and ',' are present, the right-most one is the decimal point;
     *   3. if only one of them is present: it is the decimal point when it is the
     *      configured decimal separator, OR when it is followed by a group of digits
     *      that is not exactly 3 long (e.g. "1,5"); otherwise it is a grouping mark;
     *   4. scientific notation is accepted when it uses '.' only.
     */
    public static function parseNumber(string $value): ?string
    {
        $v = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $value) ?? '';
        if ($v === '') {
            return null;
        }
        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/', $v)) {
            return self::canonical($v);
        }

        $decSep = self::decimalSeparator();
        $hasDot = str_contains($v, '.');
        $hasComma = str_contains($v, ',');

        if ($hasDot && $hasComma) {
            $decimalChar = strrpos($v, '.') > strrpos($v, ',') ? '.' : ',';
        } else if ($hasDot || $hasComma) {
            $char = $hasDot ? '.' : ',';
            $tail = substr($v, strrpos($v, $char) + 1);
            $occurrences = substr_count($v, $char);
            if ($char === $decSep && $occurrences === 1) {
                $decimalChar = $char;
            } else if ($occurrences === 1 && strlen($tail) !== 3) {
                $decimalChar = $char;
            } else {
                $decimalChar = null; // grouping only
            }
        } else {
            $decimalChar = null;
        }

        if ($decimalChar === null) {
            // grouping marks only: every group after the first must be exactly three digits ("1.234.567"), otherwise it is garbage ("1.2.3.4")
            if (!preg_match('/^[+-]?\d{1,3}([.,]\d{3})*$/', $v)) {
                return null;
            }
            $digits = preg_replace('/[.,]/', '', $v);
        } else {
            $groupChar = $decimalChar === '.' ? ',' : '.';
            $integerPart = substr($v, 0, strrpos($v, $decimalChar));
            $fraction = substr($v, strrpos($v, $decimalChar) + 1);
            if (str_contains($fraction, $groupChar) || str_contains($fraction, $decimalChar)) {
                return null;
            }
            if (str_contains($integerPart, $groupChar) && !preg_match('/^[+-]?\d{1,3}(' . preg_quote($groupChar, '/') . '\d{3})*$/', $integerPart)) {
                return null;
            }
            $digits = str_replace($groupChar, '', $v);
            $digits = str_replace($decimalChar, '.', $digits);
        }

        if (!preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $digits)) {
            return null;
        }
        return self::canonical($digits);
    }

    /**
     * "+1.50" → "1.50", ".5" → "0.5", "1e3" → "1000", "-0" → "0"
     */
    private static function canonical(string $v): string
    {
        if (preg_match('/[eE]/', $v)) {
            $v = rtrim(rtrim(sprintf('%.14F', (float)$v), '0'), '.');
        }
        $sign = '';
        if ($v[0] === '-' || $v[0] === '+') {
            $sign = $v[0] === '-' ? '-' : '';
            $v = substr($v, 1);
        }
        if ($v === '' || $v === '.') {
            return '0';
        }
        if ($v[0] === '.') {
            $v = '0' . $v;
        }
        if (str_ends_with($v, '.')) {
            $v = substr($v, 0, -1);
        }
        $v = ltrim($v, '0');
        if ($v === '' || $v[0] === '.') {
            $v = '0' . $v;
        }
        if ($sign === '-' && preg_match('/^0(\.0*)?$/', $v)) {
            $sign = '';
        }
        return $sign . $v;
    }
}
