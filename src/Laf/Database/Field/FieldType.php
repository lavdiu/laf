<?php

namespace Laf\Database\Field;

use Laf\Exception\InvalidValueException;

/**
 * Contract for a column type.
 *
 * A value crosses four boundaries and each has one method:
 *
 *   DB row ──fromDb()──► PHP value ──toDb()──► bound parameter
 *   form   ──fromInput()─► PHP value
 *   code   ──normalize()─► PHP value        (setXxxVal(), setFieldValue())
 *
 * The "PHP value" is canonical for the type (int, string decimal, bool,
 * 'Y-m-d' string, …) so comparisons, audit logs and JSON output are stable.
 *
 * The five legacy methods (isValid, getValueDbSanitized, formatForDb,
 * getPdoType, getFormElement) are kept so existing apps keep working; new code
 * should use the typed ones.
 */
interface FieldType
{
    // MySQL / MariaDB type names (information_schema.columns.DATA_TYPE)
    const TYPE_NUMERIC = 'decimal';
    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_REAL = 'real';
    const TYPE_INTEGER = 'int';
    const TYPE_BIG_INTEGER = 'bigint';
    const TYPE_SMALL_INTEGER = 'smallint';
    const TYPE_MEDIUM_INTEGER = 'mediumint';
    const TYPE_TINY_INTEGER = 'tinyint';
    const TYPE_YEAR = 'year';
    const TYPE_BIT = 'bit';
    const TYPE_VARCHAR = 'varchar';
    const TYPE_CHAR = 'char';
    const TYPE_TEXT = 'text';
    const TYPE_TINY_TEXT = 'tinytext';
    const TYPE_MEDIUM_TEXT = 'mediumtext';
    const TYPE_LONG_TEXT = 'longtext';
    const TYPE_JSON = 'json';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIME = 'time';
    const TYPE_BLOB = 'blob';
    const TYPE_TINY_BLOB = 'tinyblob';
    const TYPE_MEDIUM_BLOB = 'mediumblob';
    const TYPE_LONG_BLOB = 'longblob';
    const TYPE_BINARY = 'binary';
    const TYPE_VARBINARY = 'varbinary';
    const TYPE_BOOL = 'bool';
    const TYPE_ENUM = 'enum';
    const TYPE_SET = 'set';

    // PostgreSQL type names (information_schema.columns.data_type)
    const TYPE_NUMERIC2 = 'numeric';
    const TYPE_INTEGER2 = 'integer';
    const TYPE_VARCHAR2 = 'character varying';
    const TYPE_CHAR2 = 'character';
    const TYPE_DATETIME2 = 'timestamp with time zone';
    const TYPE_DATETIME3 = 'timestamp without time zone';
    const TYPE_TIME2 = 'time without time zone';
    const TYPE_TIME3 = 'time with time zone';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DOUBLE_PRECISION = 'double precision';
    const TYPE_JSONB = 'jsonb';
    const TYPE_BYTEA = 'bytea';
    const TYPE_UUID = 'uuid';
    const TYPE_USER_DEFINED = 'USER-DEFINED';

    /* ───────────── typed contract ───────────── */

    /** Short machine name: 'integer', 'decimal', 'date', … */
    public function getTypeName(): string;

    /**
     * Any PHP value → canonical PHP value for this type.
     * Empty input (null, '') becomes null (TypeBool maps '' to false).
     * @throws InvalidValueException when the value cannot be represented
     */
    public function normalize(mixed $value): mixed;

    /**
     * Raw value as returned by PDO → canonical PHP value.
     * Never throws: a value the type cannot parse is returned untouched so a
     * row can always be loaded.
     */
    public function fromDb(mixed $raw): mixed;

    /**
     * Text typed by a user (form field, query string, CLI) → canonical PHP value.
     * Accepts the locale display formats in addition to the canonical ones.
     * @throws InvalidValueException
     */
    public function fromInput(?string $input): mixed;

    /**
     * Canonical PHP value → [value to bind, PDO::PARAM_* type].
     * null always maps to [null, PDO::PARAM_NULL].
     * @return array{0: mixed, 1: int}
     */
    public function toDb(mixed $value): array;

    /** Type-aware equality used for change tracking ("2.10" equals "2.1", "1" equals true). */
    public function equals(mixed $a, mixed $b): bool;

    /**
     * Constraint checks beyond "is it the right shape": length, range, precision.
     * @return string[] human-readable messages, empty when valid
     */
    public function validate(mixed $value, ?Field $field = null): array;

    /** Extra HTML attributes for the generated input: step, min, max, pattern, inputmode… */
    public function getInputAttributes(?Field $field = null): array;

    /** Value to use for a non-auto-increment primary key that was left empty, or null if the type cannot generate one. */
    public function generatePrimaryKeyValue(): ?string;

    /* ───────────── legacy contract (kept for compatibility) ───────────── */

    /** @deprecated use normalize(); returns null instead of throwing on invalid input */
    public function getValueDbSanitized($value);

    /** @deprecated use normalize() in a try/catch */
    public function isValid($value);

    /** @deprecated use toDb() */
    public function getPdoType();

    /** Default input component for this type; FK/PK/document overrides are applied by Field::getFormElement(). */
    public function getFormElement(Field $field);

    /** @deprecated use fromInput(); returns null instead of throwing on invalid input */
    public function formatForDb(?string $value);
}
