<?php

use Laf\Database\Table;
use Laf\Generator\ClassGenerator;
use Laf\Generator\ColumnComment;
use Laf\Generator\DatabaseGenerator;
use Laf\Generator\PageGenerator;
use Laf\Generator\TableInspectorInterface;
use Laf\UI\Form\Input\TextArea;
use Laf\Util\Settings;
use Laf\Util\Util;
use PHPUnit\Framework\TestCase;

/**
 * Table metadata without a database, in the shape the engine inspectors produce
 */
final class FakeTableInspector implements TableInspectorInterface
{
    private string $table;

    /** @var array<string, array<string, mixed>> */
    private array $columns = [];

    /** @var array<int, array<string, string>> */
    private array $referencing;

    public int $inspections = 0;

    /** @var array<string, string[]> index name => columns (AbstractTableInspector::getUniqueKeys()) */
    public array $uniqueKeys = [];

    /** The table comment (AbstractTableInspector::getTableComment()); {"linker": true} marks a linker table */
    public ?string $tableComment = null;

    public function getTableComment(): ?string
    {
        return $this->tableComment;
    }

    /**
     * @return array<string, string[]>
     */
    public function getUniqueKeys(): array
    {
        return $this->uniqueKeys;
    }

    /**
     * @param array<int, array<string, mixed>> $columns rows with at least COLUMN_NAME and DATA_TYPE
     * @param array<int, array{0: string, 1: string}> $referencing [table, column] pairs
     */
    public function __construct(string $table, array $columns, array $referencing = [])
    {
        $this->table = $table;
        foreach ($columns as $column) {
            $column += ['IS_NULLABLE' => 'YES', 'CHARACTER_MAXIMUM_LENGTH' => null, 'COLUMN_KEY' => '', 'EXTRA' => null, 'COLUMN_COMMENT' => null];
            $this->columns[$column['COLUMN_NAME']] = $column;
        }
        $this->referencing = array_map(static function (array $pair): array {
            $row = ['table_name' => $pair[0], 'column_name' => $pair[1], 'constraint_name' => $pair[0] . '_' . $pair[1] . '_fk'];
            return $row + array_change_key_case($row, CASE_UPPER);
        }, $referencing);
    }

    public function inspect(): void
    {
        $this->inspections++;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function hasForeignKeys(): bool
    {
        foreach ($this->columns as $column) {
            if (!empty($column['FOREIGN_KEY'])) {
                return true;
            }
        }
        return false;
    }

    public function hasReferencingTables(): bool
    {
        return $this->referencing !== [];
    }

    public function getReferencingTables(): array
    {
        return $this->referencing;
    }

    public function getPrimaryColumnName(): ?string
    {
        return $this->getPrimaryColumnNames()[0] ?? null;
    }

    public function getPrimaryColumnNames(): array
    {
        $names = [];
        foreach ($this->columns as $name => $column) {
            if (($column['COLUMN_KEY'] ?? '') === 'PRI') {
                $names[] = $name;
            }
        }
        return $names;
    }

    public function getDisplayColumnName(): string
    {
        $names = array_keys($this->columns);
        foreach ($this->columns as $name => $column) {
            $fromComment = ColumnComment::displayField($column['COLUMN_COMMENT'] ?? null, $name, $names);
            if ($fromComment !== null) {
                return $fromComment;
            }
        }
        foreach (['label', 'name'] as $candidate) {
            if (isset($this->columns[$candidate])) {
                return $candidate;
            }
        }
        return (string)($names[1] ?? $names[0]);
    }
}

class GeneratorUnitTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            self::removeDir($this->tmpDir);
        }
    }

    private function tmpDir(): string
    {
        if ($this->tmpDir === null) {
            $this->tmpDir = sys_get_temp_dir() . '/laf_gen_unit_' . getmypid() . '_' . bin2hex(random_bytes(3));
            mkdir($this->tmpDir, 0755, true);
        }
        return $this->tmpDir;
    }

    private static function removeDir(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * php -l on a source string
     */
    private function assertParses(string $source, string $what): void
    {
        $file = $this->tmpDir() . '/' . preg_replace('/[^a-z0-9]/i', '_', $what) . '.php';
        file_put_contents($file, $source);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, $what . ' must parse: ' . implode("\n", $output));
    }

    private static function fk(string $column, string $table, string $referenced = 'id', ?string $delete = null): array
    {
        return [
            'column_name' => $column,
            'constraint_name' => "{$column}_fk",
            'referenced_table_name' => $table,
            'referenced_column_name' => $referenced,
            'delete_rule' => $delete,
            'update_rule' => null,
        ];
    }

    private static function itemInspector(): FakeTableInspector
    {
        return new FakeTableInspector('gen_item', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'name', 'DATA_TYPE' => 'varchar', 'IS_NULLABLE' => 'NO', 'CHARACTER_MAXIMUM_LENGTH' => 50,
                'COLUMN_COMMENT' => '{"fields":{"setLabel":"Say \"hi\"","setRequired":false,"setAttribute":["data-x","1"]},"form":{"readonly":"update","group":"Main"}}'],
            ['COLUMN_NAME' => 'customer_id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'MUL', 'FOREIGN_KEY' => self::fk('customer_id', 'gen_customer', 'id', 'CASCADE')],
            ['COLUMN_NAME' => 'notes', 'DATA_TYPE' => 'text', 'CHARACTER_MAXIMUM_LENGTH' => 65535],
            ['COLUMN_NAME' => 'order', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 20],
            ['COLUMN_NAME' => 'label', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 80, 'COLUMN_COMMENT' => '{"fields":{"setDisplayField":"label_al"}}'],
            ['COLUMN_NAME' => 'label_al', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 80],
            ['COLUMN_NAME' => 'created_on', 'DATA_TYPE' => 'datetime'],
        ], [['gen_line', 'item_id'], ['gen_line', 'alt_item_id'], ['gen_item_tag', 'item_id']]);
    }

    /** @test */
    public function column_comment_setters_are_exported_as_typed_literals(): void
    {
        $warnings = [];
        $setters = ColumnComment::fieldSetters(
            '{"fields":{"setLabel":"Say \"hi\"","setRequired":"false","setMaxLength":"12","setAttribute":["readonly","readonly"],"setNope":1,"setType":"uuid"}}',
            $warnings
        );
        $this->assertSame([
            'setLabel' => "'Say \"hi\"'",
            'setRequired' => 'false',
            'setMaxLength' => '12',
            'setAttribute' => "'readonly', 'readonly'",
        ], $setters);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('setNope', $warnings[0]);

        $this->assertSame('uuid', ColumnComment::typeOverride('{"fields":{"setType":"uuid"}}'));
        $this->assertNull(ColumnComment::typeOverride('plain text comment'));
        $this->assertSame([], ColumnComment::fieldSetters('plain text comment'));
        $this->assertSame([], ColumnComment::fieldSetters(null));

        $this->assertSame('label_al', ColumnComment::displayField('{"fields":{"setDisplayField":"label_al"}}', 'label', ['label', 'label_al']));
        $this->assertSame('label', ColumnComment::displayField('{"fields":{"setDisplayField":"label_al"}}', 'label', ['label']), 'unknown column: the commented column itself');
        $this->assertSame('label', ColumnComment::displayField('{"fields":{"setDisplayField":1}}', 'label', ['label']));
        $this->assertNull(ColumnComment::displayField('{"fields":{"setDisplayField":false}}', 'label', ['label']));
        $this->assertNull(ColumnComment::displayField('{"fields":{"setLabel":"x"}}', 'label', ['label']));
    }

    /** @test */
    public function form_options_from_comment_are_normalised(): void
    {
        $warnings = [];
        $options = ColumnComment::formOptions(
            '{"form":{"readonly":"update","hidden":true,"exclude":["VIEW","nope"],"element":"TextArea","order":"20","group":"Address","tab":" Pricing ","width":6}}',
            $warnings
        );
        $this->assertSame([
            'readonly' => ['update'],
            'hidden' => ['view', 'insert', 'update'],
            'exclude' => ['view'],
            'element' => TextArea::class,
            'order' => 20,
            'group' => 'Address',
            'tab' => 'Pricing',
        ], $options);
        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('nope', $warnings[0]);
        $this->assertStringContainsString('width', $warnings[1]);

        $this->assertNull(ColumnComment::formOptions('{"fields":{"setLabel":"x"}}'));
        $this->assertNull(ColumnComment::formOptions(null));
        $this->assertSame(['readonly' => ['insert', 'update']], ColumnComment::formOptions('{"form":{"readonly":true}}'));

        $warnings = [];
        $this->assertSame([], ColumnComment::formOptions('{"form":{"element":"NoSuchInput"}}', $warnings));
        $this->assertStringContainsString('NoSuchInput', $warnings[0]);
    }

    /** @test */
    public function base_class_source_includes_comment_setters_form_options_and_quoted_values(): void
    {
        $generator = (new ClassGenerator(new Table('gen_item'), ['namespace' => 'GenUnit'], self::itemInspector()))
            ->addTableInspector(new FakeTableInspector('gen_line', [
                ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
                ['COLUMN_NAME' => 'item_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('item_id', 'gen_item')],
                ['COLUMN_NAME' => 'alt_item_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('alt_item_id', 'gen_item')],
                ['COLUMN_NAME' => 'qty', 'DATA_TYPE' => 'int'],
            ]))
            ->addTableInspector(self::marked(new FakeTableInspector('gen_item_tag', [
                ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
                ['COLUMN_NAME' => 'item_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('item_id', 'gen_item')],
                ['COLUMN_NAME' => 'tag_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('tag_id', 'gen_tag')],
            ])));
        $source = (string)$generator->processBaseClass()->getBaseClassFile();

        $this->assertStringContainsString('$this->belongsToMany("gen_tag")->through("gen_item_tag")->keys("item_id", "tag_id")->label("Gen Tags");', $source);
        $this->assertStringNotContainsString('"gen_line"', $source, 'two keys to the same table: a child table');
        $this->assertStringContainsString('namespace GenUnit\\Base;', $source);
        $this->assertStringContainsString('class BaseGenItem extends Database\\BaseObject', $source);
        $this->assertStringContainsString('new Table("gen_item")', $source);
        $this->assertStringContainsString('->setName("name")', $source);
        $this->assertStringContainsString("\$field->setLabel('Say \"hi\"');", $source);
        $this->assertStringNotContainsString('->setLabel("Name")', $source, 'the comment label replaces the default');
        $this->assertStringContainsString('->setPlaceholder("Name")', $source);
        $this->assertStringContainsString("\$field->setRequired(false);", $source);
        $this->assertStringContainsString("\$field->setAttribute('data-x', '1');", $source);
        $this->assertStringContainsString("\$field->setFormOptions(['readonly' => ['update'], 'group' => 'Main']);", $source);
        $this->assertStringContainsString('->setMaxLength(50)', $source);
        $this->assertSame(4, substr_count($source, '->setMaxLength('), 'varchar columns only (name, order, label, label_al); text has no limit');
        $this->assertStringContainsString('->setAutoIncrement(true)', $source);
        $this->assertStringContainsString("->setOnDelete('CASCADE')", $source);
        $this->assertStringContainsString('->setReferencingTable("gen_customer")', $source);
        $this->assertStringContainsString('$this->getTable()->setDisplayField($this->getTable()->getField("label_al"));', $source);
        $this->assertStringContainsString('public function getCustomerIdObj(bool $fresh = false)', $source);
        $this->assertStringContainsString("relatedObject(\\GenUnit\\GenCustomer::class, \"customer_id\", \$fresh)", $source);
        $this->assertStringNotContainsString("if (\$value !== null && \$value !== '')", $source, 'the getter delegates to relatedObject()');
        $this->assertStringContainsString('public function getOrderFormElement(', $source);
        $this->assertStringContainsString('public function listAllObjects(): array', $source);
        $this->assertSame([], $generator->getWarnings());
        $this->assertParses($source, 'base class');

        $leaf = (string)$generator->processClass()->getClassFile();
        $this->assertStringContainsString('class GenItem extends Base\\BaseGenItem', $leaf);
        $this->assertStringContainsString('public static function findOne(array $keyValuePairs) : ?GenItem', $leaf);
        $this->assertStringContainsString('* public function getFormLayout(): ?FormLayout', $leaf);
        $this->assertStringNotContainsString('returnLeafClass', $leaf);
        $this->assertParses($leaf, 'model class');
    }

    /** @test */
    public function unique_indexes_over_several_columns_become_add_unique_key(): void
    {
        $columns = [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI'],
            ['COLUMN_NAME' => 'first_name', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 50],
            ['COLUMN_NAME' => 'last_name', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 50],
            ['COLUMN_NAME' => 'email', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 150, 'COLUMN_KEY' => 'UNI'],
        ];
        $plain = new FakeTableInspector('gen_people', $columns);
        $plainSource = (string)(new ClassGenerator(new Table('gen_people'), ['namespace' => 'GenUnit'], $plain))->processBaseClass()->getBaseClassFile();
        $this->assertStringNotContainsString('Unique keys over several columns', $plainSource, 'nothing emitted without such an index');

        $inspector = new FakeTableInspector('gen_people', $columns);
        $inspector->uniqueKeys = [
            'gen_people_name_unique' => ['first_name', 'last_name'],
            'gen_people_email_unique' => ['email'],
            'gen_people_ghost_unique' => ['first_name', 'no_such_column'],
        ];
        $source = (string)(new ClassGenerator(new Table('gen_people'), ['namespace' => 'GenUnit'], $inspector))->processBaseClass()->getBaseClassFile();

        $this->assertSame(1, substr_count($source, '->addUniqueKey('), 'single-column indexes stay on the field, unknown columns are skipped');
        $this->assertStringContainsString("\$this->getTable()->addUniqueKey(\"gen_people_name_unique\", ['first_name', 'last_name']);", $source);
        $this->assertSame(str_replace("\n\t\t/**\n\t\t * Unique keys over several columns\n\t\t */\n\t\t\$this->getTable()->addUniqueKey(\"gen_people_name_unique\", ['first_name', 'last_name']);\n", '', $source), $plainSource, 'the rest of the output is unchanged');
        $this->assertParses($source, 'base class');
    }

    /** @test */
    public function set_max_length_follows_the_column_type(): void
    {
        $inspector = new FakeTableInspector('gen_x', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI'],
            ['COLUMN_NAME' => 'body', 'DATA_TYPE' => 'text', 'CHARACTER_MAXIMUM_LENGTH' => 65535],
            ['COLUMN_NAME' => 'code', 'DATA_TYPE' => 'character varying', 'CHARACTER_MAXIMUM_LENGTH' => 10],
            ['COLUMN_NAME' => 'free', 'DATA_TYPE' => 'character varying'],
        ]);
        $source = (string)(new ClassGenerator(new Table('gen_x'), ['namespace' => 'GenUnit'], $inspector))->processBaseClass()->getBaseClassFile();

        $this->assertSame(1, substr_count($source, '->setMaxLength('));
        $this->assertStringContainsString('->setMaxLength(10)', $source);
        $this->assertSame(1, $inspector->inspections, 'inspected once');
    }

    /**
     * person ⇄ group through person_group (surrogate id, like asm), the person side
     * @param array<string, mixed> $groupColumn extra keys of person_group.group_id (a comment)
     * @param array<int, array<string, mixed>> $extraColumns more columns of person_group
     */
    private static function personGenerator(array $groupColumn = [], array $extraColumns = [], ?string $linkerComment = '{"linker": true}', array $config = []): ClassGenerator
    {
        $person = new FakeTableInspector('person', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment', 'IS_NULLABLE' => 'NO'],
            ['COLUMN_NAME' => 'name', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 50],
        ], [['person_group', 'person_id'], ['invoice', 'person_id']]);
        $personGroup = new FakeTableInspector('person_group', array_merge([
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment', 'IS_NULLABLE' => 'NO'],
            ['COLUMN_NAME' => 'person_id', 'DATA_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'FOREIGN_KEY' => self::fk('person_id', 'person')],
            ['COLUMN_NAME' => 'group_id', 'DATA_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'FOREIGN_KEY' => self::fk('group_id', 'group')] + $groupColumn,
        ], $extraColumns));
        $personGroup->tableComment = $linkerComment;
        $invoice = new FakeTableInspector('invoice', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'person_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('person_id', 'person')],
            ['COLUMN_NAME' => 'total', 'DATA_TYPE' => 'decimal'],
            ['COLUMN_NAME' => 'issued_on', 'DATA_TYPE' => 'date'],
            ['COLUMN_NAME' => 'note', 'DATA_TYPE' => 'text'],
        ]);
        return (new ClassGenerator(new Table('person'), ['namespace' => 'GenUnit'] + $config, $person))
            ->addTableInspector($personGroup)
            ->addTableInspector($invoice);
    }

    /** @test */
    public function a_linker_table_becomes_a_many_to_many_definition_in_the_base_class(): void
    {
        $generator = self::personGenerator();
        $relations = $generator->detectManyToMany();

        $this->assertSame(['person_group'], array_keys($relations), 'the invoice child table is no linker');
        $this->assertSame(['owner' => 'person_id', 'other' => 'group_id', 'table' => 'group', 'label' => 'Groups'], $relations['person_group']);

        $source = (string)$generator->processBaseClass()->getBaseClassFile();
        $this->assertStringContainsString('$this->belongsToMany("group")->through("person_group")->keys("person_id", "group_id")->label("Groups");', $source);
        $this->assertParses($source, 'base class');
    }

    /** @test */
    public function linker_tables_are_opt_in_by_their_table_comment(): void
    {
        $unmarked = self::personGenerator([], [], null);
        $this->assertSame([], $unmarked->detectManyToMany(), 'marked is the default mode');
        $source = (string)$unmarked->processBaseClass()->getBaseClassFile();
        $this->assertStringNotContainsString('belongsToMany', $source);
        $this->assertSame(['person_group looks like a linker table (group ⇄ person): mark it with the table comment {"linker": true} for many-to-many pickers'], $unmarked->getNotices());
        $this->assertSame([], $unmarked->getWarnings(), 'a notice, not a warning (--strict stays green)');

        $this->assertSame(['person_group'], array_keys(self::personGenerator([], [], null, ['many_to_many' => 'auto'])->detectManyToMany()), 'auto: every table the rule finds');
        $this->assertSame([], self::personGenerator([], [], '{"linker": false}', ['many_to_many' => 'auto'])->detectManyToMany(), 'auto honours {"linker": false}');
        $this->assertSame([], self::personGenerator([], [], '{"linker": true}', ['many_to_many' => 'off'])->detectManyToMany(), 'off: none at all');

        Settings::set('generator.many_to_many', 'auto');
        try {
            $this->assertSame('auto', self::personGenerator()->getManyToManyMode(), 'the setting is the default');
            $this->assertSame('marked', self::personGenerator([], [], null, ['many_to_many' => 'marked'])->getManyToManyMode(), 'the config wins');
        } finally {
            Settings::getInstance()->remove('generator.many_to_many');
        }
        $this->assertSame('marked', self::personGenerator([], [], null, ['many_to_many' => 'sometimes'])->getManyToManyMode(), 'an unknown value falls back');
    }

    /** @test */
    public function a_table_marked_as_linker_that_is_none_gets_a_warning(): void
    {
        $inspector = self::marked(new FakeTableInspector('invoice', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'person_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('person_id', 'person')],
            ['COLUMN_NAME' => 'total', 'DATA_TYPE' => 'decimal'],
        ]));
        $generator = new ClassGenerator(new Table('invoice'), ['namespace' => 'GenUnit'], $inspector);
        $generator->processBaseClass();
        $this->assertStringContainsString('table invoice is marked {"linker": true} but is no linker table', implode("\n", $generator->getWarnings()));
    }

    /** @test */
    public function the_generated_relation_reads_like_hand_written_code(): void
    {
        $this->assertSame(
            '$this->belongsToMany("group")->through("person_group")->keys("person_id", "group_id")->label("Member of")->hint("Pick")->required()->max(3)->dualList()->creatable()->group("Access")->tab("Rights")->order(5)->form([\'hidden\' => [\'view\']])',
            ClassGenerator::renderBelongsToMany('person_group', [
                'owner' => 'person_id', 'other' => 'group_id', 'table' => 'group', 'label' => 'Member of', 'hint' => 'Pick',
                'required' => true, 'max' => 3,
                'form' => ['element' => \Laf\UI\Form\Input\ManyToManyDualList::class, 'create' => true, 'group' => 'Access', 'tab' => 'Rights', 'order' => 5, 'hidden' => ['view']],
            ])
        );
        $this->assertStringEndsWith('->picker("Vendor\\\\Picker")', ClassGenerator::renderBelongsToMany('j', ['owner' => 'a', 'other' => 'b', 'table' => 't', 'form' => ['element' => 'Vendor\\Picker']]));
    }

    private static function marked(FakeTableInspector $inspector): FakeTableInspector
    {
        $inspector->tableComment = '{"linker": true}';
        return $inspector;
    }

    /** @test */
    public function the_linker_column_comment_configures_or_switches_off_the_picker(): void
    {
        $comment = json_encode([
            'fields' => ['setLabel' => 'Member of', 'setHint' => 'Pick the groups', 'setRequired' => true],
            'form' => ['element' => 'Autocomplete', 'create' => true, 'tab' => 'Access', 'order' => 5],
        ]);
        $relations = self::personGenerator(['COLUMN_COMMENT' => $comment])->detectManyToMany();
        $this->assertSame([
            'owner' => 'person_id',
            'other' => 'group_id',
            'table' => 'group',
            'label' => 'Member of',
            'hint' => 'Pick the groups',
            'required' => true,
            'form' => ['element' => \Laf\UI\Form\Input\Autocomplete::class, 'create' => true, 'tab' => 'Access', 'order' => 5],
        ], $relations['person_group']);

        $off = self::personGenerator(['COLUMN_COMMENT' => '{"form":{"multi":false}}']);
        $this->assertSame([], $off->detectManyToMany());
        $this->assertStringNotContainsString('belongsToMany', (string)$off->processBaseClass()->getBaseClassFile());
    }

    /** @test */
    public function a_linker_table_with_a_required_data_column_gets_no_picker(): void
    {
        $generator = self::personGenerator([], [['COLUMN_NAME' => 'role', 'DATA_TYPE' => 'varchar', 'IS_NULLABLE' => 'NO']]);
        $this->assertSame([], $generator->detectManyToMany());
        $this->assertStringContainsString('required column without a default', implode("\n", $generator->getWarnings()));

        $withDefault = self::personGenerator([], [['COLUMN_NAME' => 'role', 'DATA_TYPE' => 'varchar', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => 'member']]);
        $this->assertSame(['person_group'], array_keys($withDefault->detectManyToMany()));
    }

    /** @test */
    public function junction_detector_rules(): void
    {
        $fk = static fn(string $c, string $t) => ['COLUMN_NAME' => $c, 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk($c, $t)];
        $composite = new FakeTableInspector('link', [$fk('a_id', 'a') + ['COLUMN_KEY' => 'PRI'], $fk('b_id', 'b') + ['COLUMN_KEY' => 'PRI'], ['COLUMN_NAME' => 'note', 'DATA_TYPE' => 'text']]);
        $found = \Laf\Generator\JunctionDetector::detect($composite, 'a');
        $this->assertSame(['link', 'b', 'a_id', 'b_id', true], [$found['junction_table'], $found['other_table'], $found['fk_to_current'], $found['fk_to_other'], $found['insertable']]);
        $this->assertSame('a', \Laf\Generator\JunctionDetector::detect($composite, 'b')['other_table'], 'read from the other side');

        $threeKeys = new FakeTableInspector('x', [$fk('a_id', 'a'), $fk('b_id', 'b'), $fk('c_id', 'c')]);
        $this->assertNull(\Laf\Generator\JunctionDetector::detect($threeKeys, 'a'));

        $wide = new FakeTableInspector('x', [$fk('a_id', 'a'), $fk('b_id', 'b'), ['COLUMN_NAME' => 'p', 'DATA_TYPE' => 'int'], ['COLUMN_NAME' => 'q', 'DATA_TYPE' => 'int'], ['COLUMN_NAME' => 'r', 'DATA_TYPE' => 'int']]);
        $this->assertNull(\Laf\Generator\JunctionDetector::detect($wide, 'a'), 'a child table with its own data');

        $sameParent = new FakeTableInspector('x', [$fk('from_id', 'a'), $fk('to_id', 'a')]);
        $this->assertNull(\Laf\Generator\JunctionDetector::detect($sameParent, 'a'));

        $unrelated = new FakeTableInspector('x', [$fk('b_id', 'b'), $fk('c_id', 'c')]);
        $this->assertNull(\Laf\Generator\JunctionDetector::detect($unrelated, 'a'));

        // metadata columns that are foreign keys are neither linker keys nor data
        $statusChild = new FakeTableInspector('user_agent', [$fk('user_agent_os_id', 'user_agent_os'), $fk('record_status_id', 'record_status'), ['COLUMN_NAME' => 'name', 'DATA_TYPE' => 'varchar']]);
        $this->assertNull(\Laf\Generator\JunctionDetector::detect($statusChild, 'user_agent_os'), 'one real key plus a status key is a child table');
        $this->assertNull(\Laf\Generator\JunctionDetector::detect($statusChild, 'record_status'));

        $auditedLinker = new FakeTableInspector('person_group', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            $fk('person_id', 'person'), $fk('group_id', 'group'),
            $fk('created_by', 'person'), $fk('record_status_id', 'record_status'),
        ]);
        $found = \Laf\Generator\JunctionDetector::detect($auditedLinker, 'person');
        $this->assertSame(['group', 'person_id', 'group_id'], [$found['other_table'], $found['fk_to_current'], $found['fk_to_other']], 'still a linker with metadata foreign keys');
    }

    /** @test */
    public function save_methods_create_directories_and_keep_an_existing_model_class(): void
    {
        $dir = $this->tmpDir() . '/lib';
        $config = ['namespace' => 'GenUnit', 'base_class_dir' => $dir . '/GenUnit/Base', 'class_dir' => $dir . '/GenUnit'];
        $generator = new ClassGenerator(new Table('gen_item'), $config, self::itemInspector());

        $generator->saveBaseClassToFile()->saveClassToFile();
        $this->assertFileExists($dir . '/GenUnit/Base/BaseGenItem.php');
        $this->assertFileExists($dir . '/GenUnit/GenItem.php');

        file_put_contents($dir . '/GenUnit/GenItem.php', '<?php // edited by hand');
        $generator->saveClassToFile();
        $this->assertSame('<?php // edited by hand', file_get_contents($dir . '/GenUnit/GenItem.php'));

        $generator->setConfig($config + ['rewrite_class' => true]);
        $generator->saveClassToFile();
        $this->assertStringContainsString('class GenItem extends', (string)file_get_contents($dir . '/GenUnit/GenItem.php'));
    }

    /** @test */
    public function page_source_escapes_labels_quotes_identifiers_and_protects_delete(): void
    {
        $generator = $this->itemPageGenerator('mysql', ['delete-confirmation' => "It's \"final\"", 'cancel' => "Anulo'"]);
        $generator->generatePageFile();
        $source = (string)$generator->getPageFile();

        $this->assertStringContainsString("\$form = \$genitem->getForm(\$action === 'new' ? DrawMode::INSERT : (\$action === 'update' ? DrawMode::UPDATE : DrawMode::VIEW));", $source);
        $this->assertStringContainsString("new Link('Anulo\\'', UrlParser::getViewLink()", $source);
        $this->assertStringContainsString("\$deleteLink->setConfirmationMessage('It\\'s \"final\"');", $source);
        $this->assertStringContainsString('$form->withCsrfToken(UrlParser::getDeleteLink())', $source);
        $this->assertStringContainsString("if (!\$form->isValidCsrfToken(\$_GET[\$form->getCsrfSessionKey()] ?? null))", $source);
        $this->assertStringContainsString("'return confirm(\"It\\\\u0027s \\\\u0022final\\\\u0022\")'", $source);
        $this->assertStringContainsString('`gen_item`.`order` AS `gen_item_order`', $source);
        $this->assertStringContainsString('LEFT JOIN `gen_customer` `gen_customer` ON `gen_item`.`customer_id` = `gen_customer`.`id`', $source);
        $this->assertStringContainsString("new Column('gen_customer_name', 'Customer', true)", $source);
        $this->assertStringContainsString("new Column('gen_item_customer_id', 'Customer Id', false)", $source);
        $this->assertStringContainsString('use GenUnit\\Factory;', $source);
        $this->assertStringContainsString('$html = Factory::GeneralPage();', $source);
        $this->assertStringContainsString("\$form->setSubmittedFieldValue('record_status_id', 1)", $source);
        $this->assertStringNotContainsString('#[', $source);
        $this->assertParses($source, 'page');

        // one related tab per foreign key column, the junction table shows the table behind it
        $this->assertStringContainsString("new TabItem('GenLine (Item)')", $source);
        $this->assertStringContainsString("new TabItem('GenLine (Alt Item)')", $source);
        $this->assertStringContainsString("new TabItem('GenTag')", $source);
        $this->assertStringContainsString("new PhpGrid('gen_line_item_id_list')", $source);
        $this->assertStringContainsString("new PhpGrid('gen_line_alt_item_id_list')", $source);
        $this->assertStringContainsString("AND `gen_line`.`item_id` = \\' . ((int)UrlParser::getId()) . \\'", $source);
        $this->assertStringContainsString("AND `gen_line`.`alt_item_id` = \\' . ((int)UrlParser::getId()) . \\'", $source);
        $this->assertStringContainsString('INNER JOIN `gen_item_tag` jt ON l1.`gen_tag_id` = jt.`tag_id` WHERE jt.`item_id` = ', $source);
        $this->assertStringContainsString("->setIdPrefix('gen_item_related_tabs')", $source);
        $this->assertSame([], $generator->getWarnings());

        // the page's own list acts on the current module; a related tab's rows belong to the other table,
        // so its buttons open that table's page (no delete: that page owns its CSRF token)
        $this->assertStringContainsString("sprintf('?module=%s&action=delete&id={gen_item_id}', UrlParser::getModule())", $source);
        $this->assertStringContainsString("new ActionButton('View', '?module=gen_line&action=view&id={gen_line_id}', 'fa fa-eye')", $source);
        $this->assertStringContainsString("new ActionButton('Update', '?module=gen_line&action=update&id={gen_line_id}', 'fa fa-edit')", $source);
        $this->assertStringContainsString("new Column('gen_line_id', 'Id', true, true, '?module=gen_line&action=view&id={gen_line_id}')", $source);
        $this->assertStringContainsString("new ActionButton('View', '?module=gen_tag&action=view&id={gen_tag_id}', 'fa fa-eye')", $source);
        $this->assertStringNotContainsString("action=delete&id={gen_line_id}", $source);
        $this->assertStringNotContainsString("action=delete&id={gen_tag_id}", $source);
        $this->assertStringNotContainsString("sprintf('?module=%s&action=view&id={gen_line_id}'", $source);
    }

    /** @test */
    public function the_module_of_a_related_table_is_configurable(): void
    {
        $inspector = new FakeTableInspector('gen_item', [['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI']]);
        $generator = new PageGenerator(new Table('gen_item'), ['namespace' => 'GenUnit', 'module_for_table' => ['gen_line' => 'lines']], [], $inspector);
        $this->assertSame('lines', $generator->moduleForTable('gen_line'));
        $this->assertSame('gen_tag', $generator->moduleForTable('gen_tag'), 'default: the table name, as --install-pages names the page');
        $generator->setConfig(['namespace' => 'GenUnit', 'module_for_table' => fn (string $t): string => 'm_' . $t]);
        $this->assertSame('m_gen_tag', $generator->moduleForTable('gen_tag'));
    }

    /** @test */
    public function page_sql_is_quoted_for_postgres_and_the_factory_is_configurable(): void
    {
        $generator = $this->itemPageGenerator('postgres', [], ['page_factory' => 'App\\Ui\\Shell', 'page_factory_method' => 'adminPage']);
        $generator->generatePageFile();
        $source = (string)$generator->getPageFile();

        $this->assertStringContainsString('"gen_item"."order" AS "gen_item_order"', $source);
        $this->assertStringContainsString('FROM "gen_item"', $source);
        $this->assertStringContainsString('use App\\Ui\\Shell;', $source);
        $this->assertStringContainsString('$html = Shell::adminPage();', $source);
        $this->assertStringNotContainsString('`', $source);
        $this->assertParses($source, 'postgres page');
    }

    /** @test */
    public function a_string_primary_key_is_quoted_in_the_related_filter(): void
    {
        $code = new FakeTableInspector('gen_code', [
            ['COLUMN_NAME' => 'code', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 10, 'COLUMN_KEY' => 'PRI'],
            ['COLUMN_NAME' => 'name', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 50],
        ], [['gen_use', 'code']]);
        $use = new FakeTableInspector('gen_use', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI'],
            ['COLUMN_NAME' => 'code', 'DATA_TYPE' => 'varchar', 'FOREIGN_KEY' => self::fk('code', 'gen_code', 'code')],
            ['COLUMN_NAME' => 'qty', 'DATA_TYPE' => 'int'],
            ['COLUMN_NAME' => 'note', 'DATA_TYPE' => 'text'],
            ['COLUMN_NAME' => 'other', 'DATA_TYPE' => 'text'],
        ]);
        $generator = new PageGenerator(new Table('gen_code'), ['namespace' => 'GenUnit'], [], $code);
        $generator->addTableInspector($use)->setEngine('mysql');
        $generator->generatePageFile();
        $source = (string)$generator->getPageFile();

        $this->assertStringContainsString("use Laf\\Database\\Db;", $source);
        $this->assertStringContainsString("AND `gen_use`.`code` = \\' . Db::getInstance()->quote((string)UrlParser::getId()) . \\'", $source);
        $this->assertStringContainsString("sprintf('?module=%s&action=view&id={gen_code_code}', UrlParser::getModule())", $source);
        $this->assertParses($source, 'string key page');
    }

    /** @test */
    public function a_table_without_a_primary_key_is_reported(): void
    {
        $inspector = new FakeTableInspector('gen_log', [['COLUMN_NAME' => 'message', 'DATA_TYPE' => 'text']]);
        $generator = new PageGenerator(new Table('gen_log'), ['namespace' => 'GenUnit'], [], $inspector);
        $generator->setEngine('mysql')->generatePageFile();

        $this->assertCount(1, $generator->getWarnings());
        $this->assertStringContainsString('no primary key', $generator->getWarnings()[0]);
    }

    /** @test */
    public function the_autoloader_source_parses(): void
    {
        $generator = new DatabaseGenerator($this->tmpDir() . '/lib');
        $generator->setConfig(['namespace' => 'LafShell', 'class_dir' => $this->tmpDir()]);
        $lines = [];
        $generator->setOutput(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        $generator->generateAutoLoader();

        $file = $this->tmpDir() . '/autoload.php';
        $this->assertFileExists($file);
        $this->assertSame([$file], $generator->getGeneratedFiles());
        $this->assertStringContainsString("str_replace('\\\\', DIRECTORY_SEPARATOR, \$className)", (string)file_get_contents($file));
        $this->assertParses((string)file_get_contents($file), 'autoloader');
        $this->assertSame(["\nGenerating autoload.php"], $lines);
        $this->assertNull($generator->setLogger(null)->getLogger());
    }

    /** @test */
    public function database_generator_config_and_table_patterns(): void
    {
        $generator = new DatabaseGenerator('/srv/app/lib/');
        $config = $generator->getConfig();
        $this->assertSame('LafShell', $config['namespace']);
        $this->assertSame('/srv/app/lib/LafShell/Base', $config['base_class_dir']);
        $this->assertSame('/srv/app/lib/generator_cache/pages', $config['page_dir']);
        $this->assertFalse($config['rewrite_class']);

        $matches = new \ReflectionMethod(DatabaseGenerator::class, 'matchesAny');
        $this->assertTrue($matches->invoke(null, 'audit_log', ['audit_log']));
        $this->assertTrue($matches->invoke(null, 'phinx_migrations', ['phinx_*']));
        $this->assertTrue($matches->invoke(null, 'Person', ['person']));
        $this->assertFalse($matches->invoke(null, 'person_type', ['person']));
        $this->assertFalse($matches->invoke(null, 'person', []));

        $generator->setConfig(['class_dir' => '/x']);
        ob_start();
        $ok = $generator->checkConfig();
        ob_end_clean();
        $this->assertFalse($ok);
        $this->expectException(\Laf\Exception\MissingConfigParamException::class);
        ob_start();
        try {
            $generator->generateEverything();
        } finally {
            ob_end_clean();
        }
    }

    /** @test */
    public function table_field_name_to_label_strips_only_a_trailing_id(): void
    {
        $this->assertSame('Customer', Util::tableFieldNameToLabel('customer_id'));
        $this->assertSame('Record Status', Util::tableFieldNameToLabel('record_status_id'));
        $this->assertSame('User Identity', Util::tableFieldNameToLabel('user_identity'));
        $this->assertSame('Valid From', Util::tableFieldNameToLabel('valid_from'));
    }

    /**
     * @param array<string, string> $labels
     * @param array<string, mixed> $extraConfig
     */
    private function itemPageGenerator(string $engine, array $labels, array $extraConfig = []): PageGenerator
    {
        $item = self::itemInspector();
        $item = new FakeTableInspector('gen_item', array_values(array_merge($item->getColumns(), [
            ['COLUMN_NAME' => 'record_status_id', 'DATA_TYPE' => 'int'],
        ])), [['gen_line', 'item_id'], ['gen_line', 'alt_item_id'], ['gen_item_tag', 'item_id']]);
        $customer = new FakeTableInspector('gen_customer', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'name', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 50],
        ]);
        $line = new FakeTableInspector('gen_line', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'item_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('item_id', 'gen_item')],
            ['COLUMN_NAME' => 'alt_item_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('alt_item_id', 'gen_item')],
            ['COLUMN_NAME' => 'qty', 'DATA_TYPE' => 'int'],
            ['COLUMN_NAME' => 'note', 'DATA_TYPE' => 'text'],
            ['COLUMN_NAME' => 'other', 'DATA_TYPE' => 'text'],
        ]);
        $tag = new FakeTableInspector('gen_tag', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'label', 'DATA_TYPE' => 'varchar', 'CHARACTER_MAXIMUM_LENGTH' => 30],
        ]);
        $itemTag = new FakeTableInspector('gen_item_tag', [
            ['COLUMN_NAME' => 'id', 'DATA_TYPE' => 'int', 'COLUMN_KEY' => 'PRI', 'EXTRA' => 'auto_increment'],
            ['COLUMN_NAME' => 'item_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('item_id', 'gen_item')],
            ['COLUMN_NAME' => 'tag_id', 'DATA_TYPE' => 'int', 'FOREIGN_KEY' => self::fk('tag_id', 'gen_tag')],
        ]);
        $generator = new PageGenerator(new Table('gen_item'), ['namespace' => 'GenUnit'] + $extraConfig, $labels, $item);
        foreach ([$customer, $line, $tag, $itemTag] as $inspector) {
            $generator->addTableInspector($inspector);
        }
        $generator->setEngine($engine);
        return $generator;
    }
}
