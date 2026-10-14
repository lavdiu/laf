<?php

declare(strict_types=1);

namespace Laf\Database;

use Laf\Generator\JunctionDetector;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * Declares a many-to-many relation through a linker table, fluently. Start it from the model with
 * belongsToMany(); everything after the other table is optional:
 *
 *   // MyApp\Person
 *   $this->belongsToMany('group')            // or Group::class
 *        ->through('person_group')           // default: person_group or group_person, whichever has a model
 *        ->keys('person_id', 'group_id')     // default: the linker's foreign keys to person and to group
 *        ->label('Groups')->chips()->creatable();
 *
 * The definition is resolved when the table's relations are first read, so the calls may come in any
 * order. Declaring a relation that already exists (the generated Base class declares it, the model class
 * adds ->chips()) refines it: options given here win, the rest is kept. The relation's name on forms and
 * layouts is the linker table; $model->manyToMany('person_group') or manyToMany('group') returns it.
 */
final class ManyToManyBuilder
{
    private ?Table $table;

    private string $other;

    private ?string $through = null;

    private ?string $ownerKey = null;

    private ?string $otherKey = null;

    /** @var array<string, mixed> label, hint, required, max */
    private array $options = [];

    /** @var array<string, mixed> layout hints: element, create, group, tab, order, exclude, readonly, hidden */
    private array $form = [];

    /**
     * @param string $other the other table, or its model class (Group::class)
     * @param Table|null $table the table of the model whose forms get the picker (Table::addManyToManyBuilder() binds it)
     */
    public function __construct(string $other, ?Table $table = null)
    {
        $other = trim($other);
        if ($other === '') {
            throw new \InvalidArgumentException('belongsToMany() needs the other table');
        }
        $this->other = $other;
        $this->table = $table;
    }

    /** The table of the model that declares the relation */
    public function setTable(Table $table): static
    {
        $this->table = $table;
        return $this;
    }

    /** The linker table (person_group) */
    public function through(string $junction): static
    {
        $this->through = trim($junction) === '' ? null : trim($junction);
        return $this;
    }

    /**
     * The linker's two columns: the one that points at this model's table, then the one that points at the
     * other table. Needed only when the linker has several foreign keys to one of them (a table linked to itself).
     */
    public function keys(string $ownerColumn, string $otherColumn): static
    {
        $this->ownerKey = $ownerColumn;
        $this->otherKey = $otherColumn;
        return $this;
    }

    public function label(string $label): static
    {
        $this->options['label'] = $label;
        return $this;
    }

    public function hint(string $hint): static
    {
        $this->options['hint'] = $hint;
        return $this;
    }

    /** At least one row must be chosen */
    public function required(bool $required = true): static
    {
        $this->options['required'] = $required;
        return $this;
    }

    /** At most this many rows (null = no limit) */
    public function max(?int $maxItems): static
    {
        $this->options['max'] = $maxItems;
        return $this;
    }

    /** One checkbox per row of the other table (the default for small tables) */
    public function checkboxes(): static
    {
        return $this->picker('Checkboxes');
    }

    /** A search box with chips (the default above form.select_autocomplete_threshold rows) */
    public function chips(): static
    {
        return $this->picker('Autocomplete');
    }

    /** Two lists, available and selected, with Add / Remove buttons */
    public function dualList(): static
    {
        return $this->picker('DualList');
    }

    /** A picker by name (Checkboxes, Autocomplete, DualList) or a RelationElementInterface class */
    public function picker(string $element): static
    {
        $this->form['element'] = $element;
        return $this;
    }

    /** Chips and dual list may add rows to the other table ("create new") */
    public function creatable(bool $create = true): static
    {
        $this->form['create'] = $create;
        return $this;
    }

    /** Placed in the fieldset of this name by FormLayout::auto() */
    public function group(string $legend): static
    {
        $this->form['group'] = $legend;
        return $this;
    }

    /** Placed in the tab of this title by FormLayout::auto() */
    public function tab(string $title): static
    {
        $this->form['tab'] = $title;
        return $this;
    }

    /** Position among the fields in FormLayout::auto() */
    public function order(int $order): static
    {
        $this->form['order'] = $order;
        return $this;
    }

    /**
     * Any other layout hint, as in a column comment's "form" object (exclude, readonly, hidden: true or
     * draw modes)
     * @param array<string, mixed> $hints
     */
    public function form(array $hints): static
    {
        $this->form = array_merge($this->form, $hints);
        return $this;
    }

    /** The relation's name: the linker table */
    public function getName(): string
    {
        return $this->resolve()[0];
    }

    /**
     * The definition as Table::addManyToMany() takes it
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->resolve()[1];
    }

    /**
     * The linker table and the full definition, defaults filled in
     * @return array{0: string, 1: array<string, mixed>}
     * @throws \LogicException when the linker table or its columns cannot be told
     */
    public function resolve(?Table $table = null): array
    {
        $table ??= $this->table;
        if ($table === null) {
            throw new \LogicException('belongsToMany(): the relation is not bound to a table');
        }
        $ownerTable = (string)$table->getName();
        $other = $this->otherTable();

        $junction = $this->through;
        if ($junction === null) {
            foreach ($table->getManyToManyDefinitions(false) as $name => $definition) {
                if (($definition['table'] ?? null) === $other) {
                    $junction = (string)$name;
                    break;
                }
            }
        }
        if ($junction === null) {
            foreach (array_unique([$ownerTable . '_' . $other, $other . '_' . $ownerTable]) as $candidate) {
                if (class_exists(self::modelClass($candidate))) {
                    $junction = $candidate;
                    break;
                }
            }
        }
        if ($junction === null) {
            throw new \LogicException(sprintf(
                '%s ⇄ %s: no linker table found (looked for the models of %s_%s and %s_%s); name it with ->through(\'…\')',
                $ownerTable, $other, $ownerTable, $other, $other, $ownerTable
            ));
        }

        $base = $table->getManyToManyDefinitions(false)[$junction] ?? [];
        if (isset($base['table']) && $base['table'] !== $other) {
            throw new \LogicException(sprintf(
                '%s ⇄ %s through %s: the relation through %s already links %s',
                $ownerTable, $other, $junction, $junction, (string)$base['table']
            ));
        }
        $ownerKey = $this->ownerKey ?? ($base['owner'] ?? null);
        $otherKey = $this->otherKey ?? ($base['other'] ?? null);
        if ($ownerKey === null || $otherKey === null) {
            [$ownerKey, $otherKey] = $this->inferKeys($junction, $ownerTable, $other, $ownerKey, $otherKey);
        }

        $definition = array_merge($base, ['owner' => $ownerKey, 'other' => $otherKey, 'table' => $other], $this->options);
        if (!isset($definition['label']) || $definition['label'] === '') {
            $definition['label'] = Util::pluralLabel(Util::tableFieldNameToLabel($other));
        }
        $definition['form'] = array_merge(is_array($base['form'] ?? null) ? $base['form'] : [], $this->form);
        return [$junction, $definition];
    }

    /** The other table's name (a model class gives its table) */
    private function otherTable(): string
    {
        $class = ltrim($this->other, '\\');
        if (str_contains($class, '\\') && class_exists($class) && is_a($class, BaseObject::class, true)) {
            return (string)(new $class())->getTable()->getName();
        }
        return $this->other;
    }

    /**
     * The linker's foreign keys to the owner and to the other table
     * @return array{0: string, 1: string}
     */
    private function inferKeys(string $junction, string $ownerTable, string $other, ?string $ownerKey, ?string $otherKey): array
    {
        $class = self::modelClass($junction);
        if (!class_exists($class) || !is_a($class, BaseObject::class, true)) {
            throw new \LogicException(sprintf('%s ⇄ %s: the linker table %s has no model class %s (run the generator)', $ownerTable, $other, $junction, $class));
        }
        $toOwner = $toOther = [];
        foreach ((new $class())->getTable()->getForeignKeys() as $column => $foreignKey) {
            if (in_array((string)$column, JunctionDetector::METADATA_COLUMNS, true)) {
                continue; // created_by → person, record_status_id → record_status: row metadata, no linker key
            }
            $referenced = $foreignKey->getReferencedTable();
            if ($referenced === $ownerTable) {
                $toOwner[] = (string)$column;
            }
            if ($referenced === $other) {
                $toOther[] = (string)$column;
            }
        }
        $ownerKey ??= count($toOwner) === 1 ? $toOwner[0] : null;
        if ($otherKey === null) {
            $candidates = array_values(array_diff($toOther, [$ownerKey]));
            $otherKey = count($candidates) === 1 ? $candidates[0] : null;
        }
        if ($ownerKey === null || $otherKey === null) {
            throw new \LogicException(sprintf(
                '%s ⇄ %s through %s: cannot tell the linker columns apart (foreign keys to %s: %s; to %s: %s); name them with ->keys(\'…\', \'…\')',
                $ownerTable, $other, $junction, $ownerTable, implode(', ', $toOwner) ?: 'none', $other, implode(', ', $toOther) ?: 'none'
            ));
        }
        return [$ownerKey, $otherKey];
    }

    /** The generated model class of a table */
    private static function modelClass(string $table): string
    {
        return '\\' . trim(Settings::getString('project.package_name', ''), '\\') . '\\' . Util::tableNameToClassName($table);
    }
}
