<?php

declare(strict_types=1);

namespace Laf\Database;

use Laf\UI\Form\FormLayout;
use Laf\UI\Form\Input\ManyToManyAutocomplete;
use Laf\UI\Form\Input\ManyToManyCheckboxes;
use Laf\UI\Form\Input\ManyToManyDualList;
use Laf\UI\Form\RelationElementInterface;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * A many-to-many relation of one row through a linker (junction) table: the groups of a person
 * through person_group(person_id, group_id). Reads the linked keys, labels and choices, and writes
 * the links (sync()) through the linker table's generated model, so the audit log and the model
 * hooks see every added and removed link.
 *
 *   $person = new Person(5);
 *   $groups = $person->manyToMany('person_group');   // from the generated definition
 *   $groups->ids();                                  // ['3', '7']
 *   $groups->sync([3, 9]);                           // adds 9, removes 7, in one transaction
 *
 * The linker table needs a generated model ({package}\{Class}); the other table is the one its
 * foreign key on the "other" column references, labelled by that table's display field.
 */
class ManyToMany
{
    private BaseObject $owner;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition;

    private ?Table $junctionMeta = null;

    private ?Table $otherMeta = null;

    private ?RelationElementInterface $formElement = null;

    /** @var array<int, string>|null cached ids() of the loaded owner */
    private ?array $ids = null;

    /** @var array<string, int> other table => row count, per request */
    private static array $rowCounts = [];

    /**
     * @param BaseObject $owner the row whose links are read and written
     * @param string $junction the linker table
     * @param array<string, mixed>|null $definition owner / other columns etc. (Table::addManyToMany()); null =
     *        the owner table's definition of $junction
     * @throws \InvalidArgumentException when the owner table has no such relation
     */
    public function __construct(BaseObject $owner, string $junction, ?array $definition = null)
    {
        $definition = $definition ?? $owner->getTable()->getManyToManyDefinition($junction);
        if ($definition === null) {
            throw new \InvalidArgumentException(sprintf('Table %s has no many-to-many relation through %s', $owner->getTable()->getName(), $junction));
        }
        foreach (['owner', 'other'] as $key) {
            if (empty($definition[$key]) || !is_string($definition[$key])) {
                throw new \InvalidArgumentException(sprintf('The many-to-many relation %s needs "%s"', $junction, $key));
            }
        }
        $definition['form'] = isset($definition['form']) && is_array($definition['form']) ? $definition['form'] : [];
        $this->owner = $owner;
        $this->name = $junction;
        $this->definition = $definition;
    }

    /* ───────────── metadata ───────────── */

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwner(): BaseObject
    {
        return $this->owner;
    }

    /** The linker column that points at the owner (person_id) */
    public function getOwnerColumn(): string
    {
        return (string)$this->definition['owner'];
    }

    /** The linker column that points at the other table (group_id) */
    public function getOtherColumn(): string
    {
        return (string)$this->definition['other'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function getLabel(): string
    {
        $label = $this->definition['label'] ?? null;
        if (is_string($label) && $label !== '') {
            return $label;
        }
        return Util::tableFieldNameToLabel((string)($this->definition['table'] ?? $this->name));
    }

    public function getHint(): ?string
    {
        $hint = $this->definition['hint'] ?? null;
        return is_string($hint) && $hint !== '' ? $hint : null;
    }

    public function isRequired(): bool
    {
        return !empty($this->definition['required']);
    }

    /**
     * One layout hint of the definition (order, group, tab, exclude, readonly, hidden, element, create)
     */
    public function getFormOption(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->definition['form']) ? $this->definition['form'][$key] : $default;
    }

    /** The generated model class of the linker table */
    public function getJunctionClass(): string
    {
        return '\\' . trim(Settings::get('project.package_name'), '\\') . '\\' . Util::tableNameToClassName($this->name);
    }

    /**
     * Whether the linker table has a model and both columns are foreign keys on it
     */
    public function isAvailable(): bool
    {
        try {
            $this->junctionMeta();
            $this->otherMeta();
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    /**
     * Metadata of the linker table (its generated model)
     * @throws \LogicException when the model is missing or the columns are no foreign keys on it
     */
    public function junctionMeta(): Table
    {
        if ($this->junctionMeta === null) {
            $class = $this->getJunctionClass();
            if (!class_exists($class) || !is_a($class, BaseObject::class, true)) {
                throw new \LogicException(sprintf('Many-to-many %s: the linker table has no model class %s (run the generator)', $this->name, $class));
            }
            $meta = (new $class())->getTable();
            foreach ([$this->getOwnerColumn(), $this->getOtherColumn()] as $column) {
                if ($meta->getForeignKey($column) === null) {
                    throw new \LogicException(sprintf('Many-to-many %s: %s is not a foreign key of the linker table', $this->name, $column));
                }
            }
            $this->junctionMeta = $meta;
        }
        return $this->junctionMeta;
    }

    /**
     * Metadata of the other table (the one whose rows are picked)
     * @throws \LogicException
     */
    public function otherMeta(): Table
    {
        if ($this->otherMeta === null) {
            $meta = $this->junctionMeta()->getForeignKey($this->getOtherColumn())->getReferencedTableMeta();
            if ($meta === null) {
                throw new \LogicException(sprintf('Many-to-many %s: the table %s has no model class', $this->name, (string)($this->definition['table'] ?? '?')));
            }
            $this->otherMeta = $meta;
        }
        return $this->otherMeta;
    }

    /** The other table's column the links store (its key, usually id) */
    public function getOtherKeyColumn(): string
    {
        return $this->junctionMeta()->getForeignKey($this->getOtherColumn())->resolveReferencedFieldName();
    }

    /** The other table's column that labels a row (its display field, else its key) */
    public function getOtherDisplayColumn(): string
    {
        $display = $this->otherMeta()->getDisplayField();
        return $display !== null ? (string)$display->getName() : $this->getOtherKeyColumn();
    }

    /** The other table's model class */
    public function getOtherClass(): string
    {
        return $this->junctionMeta()->getForeignKey($this->getOtherColumn())->getReferencedClassName();
    }

    /**
     * The owner's value the links carry (its key); null while the owner is not stored
     */
    public function getOwnerKey(): int|string|null
    {
        $column = $this->junctionMeta()->getForeignKey($this->getOwnerColumn())->resolveReferencedFieldName();
        $table = $this->owner->getTable();
        $value = $table->hasField($column) ? $this->owner->getFieldValue($column) : null;
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        return is_int($value) ? $value : (string)$value;
    }

    /* ───────────── reading ───────────── */

    /**
     * The keys of the other table linked to the owner, in the other table's display order
     * @return array<int, string>
     */
    public function ids(): array
    {
        $ownerKey = $this->getOwnerKey();
        if ($ownerKey === null) {
            return [];
        }
        if ($this->ids === null) {
            $query = new Query($this->junctionMeta());
            $ids = [];
            foreach ($query->where($this->getOwnerColumn(), $ownerKey)->orderBy($this->getOtherColumn())->column($this->getOtherColumn()) as $id) {
                $id = self::key($id);
                if ($id !== '' && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
            $this->ids = $ids;
        }
        return $this->ids;
    }

    /**
     * The linked rows as objects of the other table's model
     * @return BaseObject[]
     */
    public function objects(): array
    {
        $ids = $this->ids();
        if ($ids === []) {
            return [];
        }
        return (new Query($this->otherMeta(), ltrim($this->getOtherClass(), '\\')))
            ->whereIn($this->getOtherKeyColumn(), $ids)
            ->orderBy($this->getOtherDisplayColumn())
            ->get();
    }

    /**
     * The labels of some keys of the other table, key => label in the order given (unknown keys left out)
     * @param array<int, int|string> $ids
     * @return array<string, string>
     */
    public function labels(array $ids): array
    {
        $ids = self::cleanIds($ids);
        if ($ids === []) {
            return [];
        }
        $key = $this->getOtherKeyColumn();
        $display = $this->getOtherDisplayColumn();
        $found = [];
        foreach ((new Query($this->otherMeta()))->whereIn($key, $ids)->select([$key, $display])->arrays() as $row) {
            $found[self::key($row[$key] ?? null)] = (string)Util::coalesce($row[$display] ?? null, $row[$key] ?? '');
        }
        $labels = [];
        foreach ($ids as $id) {
            if (isset($found[$id])) {
                $labels[$id] = $found[$id];
            }
        }
        return $labels;
    }

    /**
     * Every row of the other table, key => label ordered by label (the choices of a small table)
     * @return array<string, string>
     */
    public function options(): array
    {
        $key = $this->getOtherKeyColumn();
        $display = $this->getOtherDisplayColumn();
        $options = [];
        foreach ((new Query($this->otherMeta()))->select([$key, $display])->orderBy($display)->arrays() as $row) {
            $options[self::key($row[$key] ?? null)] = (string)Util::coalesce($row[$display] ?? null, $row[$key] ?? '');
        }
        return $options;
    }

    /**
     * The given keys that exist in the other table, in the order given, without duplicates
     * @param array<int, int|string> $ids
     * @return array<int, string>
     */
    public function validIds(array $ids): array
    {
        return array_values(array_map('strval', array_keys($this->labels($ids))));
    }

    /** Rows of the other table (counted once per request) */
    public function countOptions(): int
    {
        $table = (string)$this->otherMeta()->getName();
        if (!isset(self::$rowCounts[$table])) {
            self::$rowCounts[$table] = (new Query($this->otherMeta()))->count();
        }
        return self::$rowCounts[$table];
    }

    /** Forget the per-request row counts (tests, long-running workers) */
    public static function resetRequestState(): void
    {
        self::$rowCounts = [];
    }

    /* ───────────── writing ───────────── */

    /**
     * Make the owner linked to exactly these keys of the other table: adds the missing links and
     * removes the others, inside one transaction (joins an outer one), through the linker model
     * (audit log, hooks). Keys are not checked against the other table here: the foreign key
     * refuses an unknown one (validIds() checks first).
     * @param array<int, int|string> $ids
     * @return array{added: array<int, string>, removed: array<int, string>}
     * @throws \LogicException when the owner is not stored yet
     * @throws \Exception from the database
     */
    public function sync(array $ids): array
    {
        $ownerKey = $this->getOwnerKey();
        if ($ownerKey === null) {
            throw new \LogicException(sprintf('Many-to-many %s: store the owner before linking rows to it', $this->name));
        }
        $this->ids = null;
        $diff = self::diff($this->ids(), $ids);
        if ($diff['added'] === [] && $diff['removed'] === []) {
            return $diff;
        }
        $class = $this->getJunctionClass();
        $ownerColumn = $this->getOwnerColumn();
        $otherColumn = $this->getOtherColumn();
        BaseObject::transaction(function () use ($class, $ownerKey, $ownerColumn, $otherColumn, $diff) {
            if ($diff['removed'] !== []) {
                $links = (new Query($this->junctionMeta(), ltrim($class, '\\')))
                    ->where($ownerColumn, $ownerKey)
                    ->whereIn($otherColumn, $diff['removed'])
                    ->get();
                foreach ($links as $link) {
                    $link->hardDelete();
                }
            }
            foreach ($diff['added'] as $id) {
                /** @var BaseObject $link */
                $link = new $class();
                $link->setFieldValue($ownerColumn, $ownerKey);
                $link->setFieldValue($otherColumn, $id);
                $link->insert();
            }
        });
        $this->ids = null;
        return $diff;
    }

    /**
     * What sync() changes: the keys to add and the keys to remove
     * @param array<int, int|string> $current
     * @param array<int, int|string> $wanted
     * @return array{added: array<int, string>, removed: array<int, string>}
     */
    public static function diff(array $current, array $wanted): array
    {
        $current = self::cleanIds($current);
        $wanted = self::cleanIds($wanted);
        return [
            'added' => array_values(array_diff($wanted, $current)),
            'removed' => array_values(array_diff($current, $wanted)),
        ];
    }

    /**
     * Keys as non-empty strings, without duplicates, in the order given
     * @param array<int|string, mixed> $ids
     * @return array<int, string>
     */
    public static function cleanIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $id = self::key($id);
            if ($id !== '' && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        return $clean;
    }

    private static function key(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /* ───────────── form ───────────── */

    /**
     * The picker of this relation, cached: the class given, else the definition's "element", else
     * checkboxes for a small other table and a chips autocomplete above
     * Settings form.select_autocomplete_threshold rows. Its value starts as ids().
     * @param string|null $class a RelationElementInterface class, or Autocomplete / CheckboxCollection
     */
    public function getFormElement(?string $class = null): RelationElementInterface
    {
        $class = $class !== null ? self::resolveElementClass($class) : null;
        if ($this->formElement !== null && ($class === null || $this->formElement instanceof $class)) {
            return $this->formElement;
        }
        if ($class === null) {
            $configured = $this->getFormOption('element');
            $class = is_string($configured) && $configured !== '' ? self::resolveElementClass($configured) : $this->defaultElementClass();
        }
        /** @var RelationElementInterface $element */
        $element = new $class();
        $element->setRelation($this);
        if ($element instanceof ManyToManyAutocomplete && $this->getFormOption('create') === true) {
            $element->setAllowCreate(true);
        }
        $this->formElement = $element;
        return $element;
    }

    public function hasFormElement(): bool
    {
        return $this->formElement !== null;
    }

    /**
     * A picker class from a name: a RelationElementInterface class, or the single-value name it
     * stands for (Autocomplete → ManyToManyAutocomplete, CheckboxCollection / Checkbox(es) → ManyToManyCheckboxes,
     * DualList / TransferList → ManyToManyDualList)
     * @throws \InvalidArgumentException
     */
    public static function resolveElementClass(string $name): string
    {
        $short = strtolower((string)preg_replace('/^.*\\\\/', '', trim($name)));
        $aliases = [
            'autocomplete' => ManyToManyAutocomplete::class,
            'manytomanyautocomplete' => ManyToManyAutocomplete::class,
            'checkboxcollection' => ManyToManyCheckboxes::class,
            'checkboxes' => ManyToManyCheckboxes::class,
            'checkbox' => ManyToManyCheckboxes::class,
            'manytomanycheckboxes' => ManyToManyCheckboxes::class,
            'duallist' => ManyToManyDualList::class,
            'transferlist' => ManyToManyDualList::class,
            'manytomanyduallist' => ManyToManyDualList::class,
        ];
        $candidate = ltrim(trim($name), '\\');
        if (class_exists($candidate) && is_a($candidate, RelationElementInterface::class, true)) {
            return $candidate;
        }
        if (isset($aliases[$short])) {
            return $aliases[$short];
        }
        throw new \InvalidArgumentException(sprintf('"%s" is not a many-to-many picker (use Autocomplete or CheckboxCollection)', $name));
    }

    private function defaultElementClass(): string
    {
        $threshold = Settings::getInt('form.select_autocomplete_threshold', 0);
        if ($threshold > 0) {
            try {
                if ($this->countOptions() > $threshold) {
                    return ManyToManyAutocomplete::class;
                }
            } catch (\Throwable $ex) {
                return ManyToManyCheckboxes::class;
            }
        }
        return ManyToManyCheckboxes::class;
    }

    /**
     * The relation names a layout may place, for FormLayout (kept here so the UI does not walk the table)
     * @return string[]
     */
    public static function namesOf(Table $table): array
    {
        return array_map('strval', array_keys($table->getManyToManyDefinitions()));
    }

    /** @internal FormLayout reads the mode hints from the definition */
    public function getLayoutModes(string $key): array
    {
        $value = $this->getFormOption($key);
        if ($value === true) {
            return $key === 'readonly' ? FormLayout::EDIT_MODES : FormLayout::ALL_MODES;
        }
        return is_array($value) ? array_values(array_map('strval', $value)) : (is_string($value) && $value !== '' ? [$value] : []);
    }
}
