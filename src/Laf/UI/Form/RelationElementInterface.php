<?php

declare(strict_types=1);

namespace Laf\UI\Form;

use Laf\Database\ManyToMany;
use Laf\UI\ComponentInterface;

/**
 * A form element bound to a many-to-many relation (a linker table) instead of a column: it posts
 * several keys of the other table under the relation's name (name[]), and Form::processForm()
 * writes them with ManyToMany::sync() after the row itself. getField() is null for these elements.
 */
interface RelationElementInterface extends FormElementInterface
{
    /**
     * Bind the relation: name, label, hint, required and the current keys come from it
     * @return static
     */
    public function setRelation(ManyToMany $relation): ComponentInterface;

    public function getRelation(): ?ManyToMany;

    /** The relation's name (its linker table), also the posted name and the key of its errors */
    public function getRelationName(): string;

    /**
     * The chosen keys of the other table
     * @return array<int, string>
     */
    public function getSelectedIds(): array;

    /**
     * @param array<int, int|string> $ids
     * @return static
     */
    public function setSelectedIds(array $ids): ComponentInterface;

    public function getLabel(): ?string;

    /** At least one key must be chosen */
    public function isRequired(): bool;

    /** Most keys that may be chosen; null = no limit */
    public function getMaxItems(): ?int;

    /**
     * @return static
     */
    public function setMaxItems(?int $maxItems): ComponentInterface;
}
