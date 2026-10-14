<?php

declare(strict_types=1);

namespace Laf\UI\Form;

use Laf\Database\Field\Field;
use Laf\Database\ManyToMany;
use Laf\Database\Table;
use Laf\UI\Container\Div;
use Laf\UI\Container\Fieldset;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabItem;

/**
 * Describes how a Form lays out the fields of its object in each draw mode and
 * builds the components: an ordered list of sections (a plain block, a
 * fieldset, a tab or a piece of raw HTML) with a column count and the draw modes they apply to,
 * plus per-field options (excluded / read-only / hidden in given modes, the
 * element class). Serialisable with toArray() / fromArray().
 *
 *   FormLayout::auto($table)                       // the generator's layout
 *       ->readOnly(['code'], [DrawMode::UPDATE])   // editable on insert only
 *       ->fieldset('Address', ['street', 'city'], 2)
 *       ->tab('Pricing', ['price', 'currency_id'])
 *       ->html('<p class="text-muted">Prices are net.</p>', [DrawMode::VIEW])
 *       ->hidden(['record_status_id'])
 *       ->exclude(['internal_note'], [DrawMode::VIEW]);
 *
 * A model returns one from getFormLayout(); $object->getForm(DrawMode::UPDATE)
 * and $form->applyLayout($mode) apply it.
 *
 * Rules: a field listed in several sections is drawn in the first section
 * active in the mode; fields in no section are appended (setAppendUnlisted);
 * when any active section is a tab, everything else becomes a leading
 * "General" tab and the form holds one TabContainer; columns are Bootstrap
 * col-lg inside a row (never Row/Column containers, which overwrite the
 * elements' draw modes). Read-only means the element is drawn in VIEW mode
 * inside an edit form: it posts nothing, is not in the signed field list and
 * is left untouched on processing. Hidden only hides the wrapper (d-none):
 * the value still posts and is applied; use exclude or readOnly to protect
 * a field. The layout owns the draw mode and hidden state of the elements it
 * places (apply() resets them), so tweak elements after getForm($mode).
 */
final class FormLayout
{
    public const KIND_FIELDS = 'fields';
    public const KIND_FIELDSET = 'fieldset';
    public const KIND_TAB = 'tab';
    public const KIND_HTML = 'html';

    public const ALL_MODES = DrawMode::ALL;
    public const EDIT_MODES = DrawMode::EDIT;

    /** Columns auto() shows in the "Details" tab of the view form, in this order */
    public const METADATA_FIELDS = ['created_by', 'created_on', 'record_status_id', 'updated_by', 'updated_on'];

    /** Columns auto() leaves out of insert and update forms (filled by BaseObject) */
    public const EDIT_EXCLUDED_FIELDS = ['created_on', 'created_by', 'updated_on', 'updated_by'];

    private const KINDS = [self::KIND_FIELDS, self::KIND_FIELDSET, self::KIND_TAB, self::KIND_HTML];
    private const MODE_OPTIONS = ['exclude', 'readonly', 'hidden'];

    /** @var array<int, array{kind: string, title: string, icon: string, fields: string[], columns: int, modes: string[]|null}> */
    private array $sections = [];

    /** @var array<string, array<string, mixed>> field name => exclude/readonly/hidden (mode lists), element (class) */
    private array $fieldOptions = [];

    private string $generalTabTitle = 'General';

    private ?string $tabContainerId = null;

    private bool $appendUnlisted = true;

    public static function create(): self
    {
        return new self();
    }

    /* ───────────── building ───────────── */

    /**
     * A plain block of fields
     * @param string[] $names
     * @param string[]|null $modes draw modes the block appears in; null = all
     */
    public function fields(array $names, int $columns = 1, ?array $modes = null): self
    {
        return $this->addSection(self::KIND_FIELDS, '', $names, $columns, $modes, '');
    }

    /**
     * A <fieldset> with a legend
     * @param string[] $names
     * @param string[]|null $modes
     */
    public function fieldset(string $legend, array $names, int $columns = 1, ?array $modes = null): self
    {
        return $this->addSection(self::KIND_FIELDSET, $legend, $names, $columns, $modes, '');
    }

    /**
     * A tab; the first tab turns the rest of the form into a leading "General" tab
     * @param string[] $names
     * @param string[]|null $modes
     */
    public function tab(string $title, array $names, int $columns = 1, ?array $modes = null, string $icon = ''): self
    {
        return $this->addSection(self::KIND_TAB, $title, $names, $columns, $modes, $icon);
    }

    /**
     * Raw HTML at this place among the sections (a note, an alert, a separator); not escaped.
     * Drawn at the top level, or in the "General" tab when the form has tabs.
     * @param string[]|null $modes draw modes it appears in; null = all
     */
    public function html(string $html, ?array $modes = null): self
    {
        return $this->addSection(self::KIND_HTML, $html, [], 1, $modes, '');
    }

    /**
     * Fields that are not on the form in the given modes (default: every mode)
     * @param string[] $names
     * @param string[]|null $modes
     */
    public function exclude(array $names, ?array $modes = null): self
    {
        return $this->addModeOption('exclude', $names, $modes ?? self::ALL_MODES);
    }

    /**
     * Fields drawn read-only (their element in VIEW mode) in the given modes (default: insert and update)
     * @param string[] $names
     * @param string[]|null $modes
     */
    public function readOnly(array $names, ?array $modes = null): self
    {
        return $this->addModeOption('readonly', $names, $modes ?? self::EDIT_MODES);
    }

    /**
     * Fields whose wrapper is hidden (d-none) in the given modes; the value still posts
     * @param string[] $names
     * @param string[]|null $modes
     */
    public function hidden(array $names, ?array $modes = null): self
    {
        return $this->addModeOption('hidden', $names, $modes ?? self::ALL_MODES);
    }

    /**
     * The input class of a field (short name under Laf\UI\Form\Input or a fully qualified class)
     */
    public function element(string $name, string $class): self
    {
        $this->fieldOptions[$name]['element'] = self::resolveElementClass($class);
        return $this;
    }

    /**
     * Forget every option of a field (exclusions, read-only, hidden, element)
     */
    public function clearFieldOptions(string $name): self
    {
        unset($this->fieldOptions[$name]);
        return $this;
    }

    public function setGeneralTabTitle(string $title): self
    {
        $this->generalTabTitle = $title;
        return $this;
    }

    public function getGeneralTabTitle(): string
    {
        return $this->generalTabTitle;
    }

    /**
     * Id of the TabContainer; null = "{table}_form_tabs"
     */
    public function setTabContainerId(?string $id): self
    {
        $this->tabContainerId = $id;
        return $this;
    }

    public function getTabContainerId(): ?string
    {
        return $this->tabContainerId;
    }

    /**
     * Whether fields that are in no section are appended after the sections (default true)
     */
    public function setAppendUnlisted(bool $append): self
    {
        $this->appendUnlisted = $append;
        return $this;
    }

    public function isAppendUnlisted(): bool
    {
        return $this->appendUnlisted;
    }

    /* ───────────── queries ───────────── */

    public function isExcluded(string $name, string $drawMode): bool
    {
        return in_array($drawMode, $this->fieldOptions[$name]['exclude'] ?? [], true);
    }

    public function isReadOnly(string $name, string $drawMode): bool
    {
        return in_array($drawMode, $this->fieldOptions[$name]['readonly'] ?? [], true);
    }

    public function isHidden(string $name, string $drawMode): bool
    {
        return in_array($drawMode, $this->fieldOptions[$name]['hidden'] ?? [], true);
    }

    public function getElementClass(string $name): ?string
    {
        return $this->fieldOptions[$name]['element'] ?? null;
    }

    /**
     * The sections, all of them or only those active in a draw mode
     * @return array<int, array{kind: string, title: string, icon: string, fields: string[], columns: int, modes: string[]|null}>
     */
    public function getSections(?string $drawMode = null): array
    {
        if ($drawMode === null) {
            return $this->sections;
        }
        return array_values(array_filter($this->sections, static function (array $section) use ($drawMode): bool {
            return $section['modes'] === null || in_array($drawMode, $section['modes'], true);
        }));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFieldOptions(): array
    {
        return $this->fieldOptions;
    }

    public function isEmpty(): bool
    {
        return $this->sections === [] && $this->fieldOptions === [];
    }

    /* ───────────── serialisation ───────────── */

    /**
     * @return array{sections: array, fields: array, generalTab: string, tabContainerId: string|null, appendUnlisted: bool}
     */
    public function toArray(): array
    {
        return [
            'sections' => $this->sections,
            'fields' => $this->fieldOptions,
            'generalTab' => $this->generalTabTitle,
            'tabContainerId' => $this->tabContainerId,
            'appendUnlisted' => $this->appendUnlisted,
        ];
    }

    /**
     * @param array<string, mixed> $data the shape of toArray(); a mode option may be true (every mode of its default)
     * @throws \InvalidArgumentException on an unknown section kind, draw mode, option or element class
     */
    public static function fromArray(array $data): self
    {
        $layout = new self();
        foreach ($data['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                throw new \InvalidArgumentException('FormLayout: every section must be an array');
            }
            $kind = (string)($section['kind'] ?? self::KIND_FIELDS);
            if (!in_array($kind, self::KINDS, true)) {
                throw new \InvalidArgumentException(sprintf('FormLayout: unknown section kind "%s"', $kind));
            }
            $layout->addSection(
                $kind,
                (string)($section['title'] ?? ''),
                (array)($section['fields'] ?? []),
                (int)($section['columns'] ?? 1),
                isset($section['modes']) ? (array)$section['modes'] : null,
                (string)($section['icon'] ?? '')
            );
        }
        foreach ($data['fields'] ?? [] as $name => $options) {
            if (!is_array($options)) {
                throw new \InvalidArgumentException(sprintf('FormLayout: the options of field "%s" must be an array', $name));
            }
            foreach ($options as $key => $value) {
                if (in_array($key, self::MODE_OPTIONS, true)) {
                    if ($value === true) {
                        $value = $key === 'readonly' ? self::EDIT_MODES : self::ALL_MODES;
                    }
                    $layout->addModeOption((string)$key, [(string)$name], (array)$value);
                } elseif ($key === 'element') {
                    $layout->element((string)$name, (string)$value);
                } else {
                    throw new \InvalidArgumentException(sprintf('FormLayout: unknown option "%s" for field "%s"', $key, $name));
                }
            }
        }
        if (isset($data['generalTab'])) {
            $layout->setGeneralTabTitle((string)$data['generalTab']);
        }
        if (array_key_exists('tabContainerId', $data)) {
            $layout->setTabContainerId($data['tabContainerId'] === null ? null : (string)$data['tabContainerId']);
        }
        if (array_key_exists('appendUnlisted', $data)) {
            $layout->setAppendUnlisted((bool)$data['appendUnlisted']);
        }
        return $layout;
    }

    /**
     * A fully qualified form element class from a short name (under Laf\UI\Form\Input, or a many-to-many
     * picker without its ManyToMany prefix: "DualList") or a class name
     * @throws \InvalidArgumentException
     */
    public static function resolveElementClass(string $name): string
    {
        $name = ltrim(trim($name), '\\');
        foreach ([$name, 'Laf\\UI\\Form\\Input\\' . $name, 'Laf\\UI\\Form\\Input\\ManyToMany' . $name] as $candidate) {
            if ($candidate !== '' && class_exists($candidate) && is_a($candidate, FormElementInterface::class, true)) {
                return $candidate;
            }
        }
        try {
            // the picker aliases of a many-to-many relation (chips, dual, TransferList, Checkboxes)
            return ManyToMany::resolveElementClass($name);
        } catch (\InvalidArgumentException $notAPicker) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a form element class', $name));
        }
    }

    /* ───────────── the generator's layout ───────────── */

    /**
     * The layout the generated pages use: columns by field count (fewer than 10 fields: 1,
     * up to 20: 2, more: 3), the metadata columns (METADATA_FIELDS) in a "Details" tab of the
     * view form and left out of insert/update forms (EDIT_EXCLUDED_FIELDS; record_status_id
     * stays editable), and the fields' form options (Field::getFormOptions(), from the JSON
     * column comments): order, group (fieldset), tab, exclude, readonly, hidden, element.
     *
     * @param array<string, mixed> $options columns (int), metadata (string[]), editExclude (string[]),
     *                                      detailsTab (string), generalTab (string)
     */
    public static function auto(Table $table, array $options = []): self
    {
        $metadataNames = (array)($options['metadata'] ?? self::METADATA_FIELDS);
        $editExcluded = (array)($options['editExclude'] ?? self::EDIT_EXCLUDED_FIELDS);
        $detailsTab = (string)($options['detailsTab'] ?? 'Details');
        $layout = (new self())->setGeneralTabTitle((string)($options['generalTab'] ?? 'General'));

        $all = [];
        $index = 0;
        $entries = [];
        foreach ($table->getFields() as $field) {
            $entries[(string)$field->getName()] = $field->getFormOptions();
        }
        // the many-to-many pickers (linker tables) follow the columns unless their comment orders them
        foreach ($table->getManyToManyDefinitions() as $junction => $definition) {
            if (!isset($entries[$junction])) {
                $entries[(string)$junction] = (array)($definition['form'] ?? []);
            }
        }
        foreach ($entries as $name => $formOptions) {
            $name = (string)$name;
            $all[$name] = [
                'index' => $index,
                'order' => is_numeric($formOptions['order'] ?? null) ? (int)$formOptions['order'] : ($index + 1) * 10,
                'group' => isset($formOptions['group']) && trim((string)$formOptions['group']) !== '' ? trim((string)$formOptions['group']) : null,
                'tab' => isset($formOptions['tab']) && trim((string)$formOptions['tab']) !== '' ? trim((string)$formOptions['tab']) : null,
            ];
            foreach (['exclude' => self::ALL_MODES, 'readonly' => self::EDIT_MODES, 'hidden' => self::ALL_MODES] as $key => $default) {
                if (!empty($formOptions[$key])) {
                    $layout->addModeOption($key, [$name], $formOptions[$key] === true ? $default : (array)$formOptions[$key]);
                }
            }
            if (!empty($formOptions['element'])) {
                $layout->element($name, (string)$formOptions['element']);
            }
            $index++;
        }
        uasort($all, static function (array $a, array $b): int {
            return [$a['order'], $a['index']] <=> [$b['order'], $b['index']];
        });

        $total = count($all);
        $columns = (int)($options['columns'] ?? ($total < 10 ? 1 : ($total <= 20 ? 2 : 3)));

        $meta = [];
        foreach ($metadataNames as $metadataName) {
            if (isset($all[$metadataName])) {
                $meta[] = (string)$metadataName;
            }
        }
        $main = [];
        $groups = [];
        $tabs = [];
        foreach ($all as $name => $info) {
            if (in_array($name, $meta, true)) {
                continue;
            }
            if ($info['tab'] !== null) {
                $tabs[$info['tab']][] = $name;
            } elseif ($info['group'] !== null) {
                $groups[$info['group']][] = $name;
            } else {
                $main[] = $name;
            }
        }
        $editMain = $main;
        foreach ($meta as $name) {
            if (!in_array($name, $editExcluded, true)) {
                $editMain[] = $name;
            }
        }
        if ($editMain !== []) {
            $layout->fields($editMain, $columns, self::EDIT_MODES);
        }
        if ($main !== []) {
            $layout->fields($main, $columns, [DrawMode::VIEW]);
        }
        foreach ($groups as $legend => $names) {
            $layout->fieldset((string)$legend, $names);
        }
        foreach ($tabs as $title => $names) {
            $layout->tab((string)$title, $names);
        }
        if ($meta !== []) {
            $layout->tab($detailsTab, $meta, 2, [DrawMode::VIEW]);
        }
        $excluded = array_values(array_intersect(array_map('strval', $editExcluded), array_keys($all)));
        if ($excluded !== []) {
            $layout->exclude($excluded, self::EDIT_MODES);
        }
        return $layout;
    }

    /* ───────────── applying ───────────── */

    /**
     * Replace the form's components with this layout for a draw mode, using the fields'
     * cached elements so values, errors and the signed field list keep working
     */
    public function apply(Form $form, string $drawMode): Form
    {
        if (!in_array($drawMode, self::ALL_MODES, true)) {
            $drawMode = DrawMode::VIEW;
        }
        $table = $form->getObject()->getTable();
        $form->setDrawMode($drawMode);

        $placed = [];
        $top = [];
        $tabs = [];
        $inline = false;

        foreach ($this->getSections($drawMode) as $section) {
            if ($section['kind'] === self::KIND_HTML) {
                if ($section['title'] !== '') {
                    $top[] = new HtmlContainer($section['title']);
                }
                continue;
            }
            $elements = [];
            foreach ($section['fields'] as $name) {
                if (isset($placed[$name]) || $this->isExcluded($name, $drawMode)) {
                    continue;
                }
                if ($table->hasField($name)) {
                    $element = $this->prepareElement($table->getField($name), $drawMode);
                } else {
                    $element = $this->prepareRelationElement($form, $name, $drawMode);
                }
                if ($element === null) {
                    continue;
                }
                $placed[$name] = true;
                $elements[] = $element;
            }
            if ($elements === []) {
                continue;
            }
            if ($section['columns'] > 1 && count($elements) > 1) {
                $inline = true;
            }
            $body = $this->columnise($elements, $section['columns']);
            if ($section['kind'] === self::KIND_FIELDSET) {
                $fieldset = new Fieldset($section['title']);
                foreach ($body as $component) {
                    $fieldset->addComponent($component);
                }
                $top[] = $fieldset;
            } elseif ($section['kind'] === self::KIND_TAB) {
                $tab = new TabItem($section['title'], $section['icon']);
                foreach ($body as $component) {
                    $tab->addComponent($component);
                }
                $tabs[] = $tab;
            } else {
                foreach ($body as $component) {
                    $top[] = $component;
                }
            }
        }

        if ($this->appendUnlisted) {
            foreach ($table->getFields() as $field) {
                $name = (string)$field->getName();
                if (!isset($placed[$name]) && !$this->isExcluded($name, $drawMode)) {
                    $placed[$name] = true;
                    $top[] = $this->prepareElement($field, $drawMode);
                }
            }
            foreach (array_keys($table->getManyToManyDefinitions()) as $name) {
                $name = (string)$name;
                if (isset($placed[$name]) || $this->isExcluded($name, $drawMode)) {
                    continue;
                }
                $element = $this->prepareRelationElement($form, $name, $drawMode);
                if ($element !== null) {
                    $placed[$name] = true;
                    $top[] = $element;
                }
            }
        }

        if ($tabs !== []) {
            $containerId = $this->tabContainerId ?? ($table->getName() . '_form_tabs');
            $container = new TabContainer($containerId);
            if ($top !== []) {
                $general = new TabItem($this->generalTabTitle);
                foreach ($top as $component) {
                    $general->addComponent($component);
                }
                array_unshift($tabs, $general);
            }
            foreach ($tabs as $tab) {
                $tab->setIdPrefix($containerId);
                $container->addComponent($tab);
            }
            $components = [$container];
        } else {
            $components = $top;
        }

        $form->setComponents($components);
        $form->setFormRowDisplayMode($inline ? FormRowDisplayMode::INLINE : FormRowDisplayMode::ROW);
        return $form;
    }

    /**
     * The field's cached element with the layout-owned state (element class, draw mode, hidden) applied
     */
    private function prepareElement(Field $field, string $drawMode): FormElementInterface
    {
        $name = (string)$field->getName();
        $class = $this->getElementClass($name);
        if ($class !== null && $field->getFormElementClass() !== $class) {
            $field->setFormElementClass($class);
        }
        $element = $field->getFormElement();
        $element->setDrawMode($this->isReadOnly($name, $drawMode) ? DrawMode::VIEW : null);
        if (method_exists($element, 'setHidden')) {
            $element->setHidden($this->isHidden($name, $drawMode));
        }
        return $element;
    }

    /**
     * The cached picker of a many-to-many relation of the form's object (a linker table the
     * generator found) with the layout-owned state applied; null when $name is no usable relation
     */
    private function prepareRelationElement(Form $form, string $name, string $drawMode): ?FormElementInterface
    {
        $object = $form->getObject();
        if (!$object->getTable()->hasManyToMany($name)) {
            return null;
        }
        try {
            $relation = $object->manyToMany($name);
            if (!$relation->isAvailable()) {
                return null;
            }
            $element = $relation->getFormElement($this->getElementClass($name));
        } catch (\Throwable $ex) {
            return null;
        }
        $element->setDrawMode($this->isReadOnly($name, $drawMode) ? DrawMode::VIEW : null);
        if (method_exists($element, 'setHidden')) {
            $element->setHidden($this->isHidden($name, $drawMode));
        }
        return $element;
    }

    /**
     * The elements as they are, or one row of col-lg columns
     * @param FormElementInterface[] $elements
     * @return \Laf\UI\ComponentInterface[]
     */
    private function columnise(array $elements, int $columns): array
    {
        if ($columns <= 1 || count($elements) < 2) {
            return $elements;
        }
        $row = new Div(['row']);
        foreach (array_chunk($elements, (int)ceil(count($elements) / $columns)) as $chunk) {
            $column = new Div(['col-lg']);
            foreach ($chunk as $element) {
                $column->addComponent($element);
            }
            $row->addComponent($column);
        }
        return [$row];
    }

    /* ───────────── internals ───────────── */

    /**
     * @param string[] $names
     * @param string[]|null $modes
     */
    private function addSection(string $kind, string $title, array $names, int $columns, ?array $modes, string $icon): self
    {
        $this->sections[] = [
            'kind' => $kind,
            'title' => $title,
            'icon' => $icon,
            'fields' => self::names($names),
            'columns' => max(1, $columns),
            'modes' => $modes === null ? null : self::modes($modes),
        ];
        return $this;
    }

    /**
     * Merge draw modes into a per-field option
     * @param string[] $names
     * @param string[] $modes
     */
    private function addModeOption(string $key, array $names, array $modes): self
    {
        $modes = self::modes($modes);
        foreach (self::names($names) as $name) {
            $current = $this->fieldOptions[$name][$key] ?? [];
            $this->fieldOptions[$name][$key] = array_values(array_unique(array_merge($current, $modes)));
        }
        return $this;
    }

    /**
     * @param array<int|string, mixed> $names
     * @return string[]
     */
    private static function names(array $names): array
    {
        $clean = [];
        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name !== '' && !in_array($name, $clean, true)) {
                $clean[] = $name;
            }
        }
        return $clean;
    }

    /**
     * @param array<int|string, mixed> $modes
     * @return string[]
     * @throws \InvalidArgumentException
     */
    private static function modes(array $modes): array
    {
        $clean = [];
        foreach ($modes as $mode) {
            $mode = strtolower(trim((string)$mode));
            if (!in_array($mode, self::ALL_MODES, true)) {
                throw new \InvalidArgumentException(sprintf('FormLayout: unknown draw mode "%s"', $mode));
            }
            if (!in_array($mode, $clean, true)) {
                $clean[] = $mode;
            }
        }
        return $clean;
    }
}
