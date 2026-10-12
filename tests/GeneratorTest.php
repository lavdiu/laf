<?php

use Laf\Database\Db;
use Laf\Database\Field\TypeDecimal;
use Laf\Database\Table;
use Laf\Generator\ClassGenerator;
use Laf\Generator\DatabaseGenerator;
use Laf\Generator\PageGenerator;
use Laf\Generator\TableInspectorFactory;
use Laf\UI\Container\Fieldset;
use Laf\UI\Form\DrawMode;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The generators against a real database (MariaDB/MySQL or PostgreSQL, chosen by the
 * LAF_DB_* environment): inspectors, generated classes loaded and used, generated page
 * SQL executed on the engine, DatabaseGenerator selection.
 */
class GeneratorTest extends TestCase
{
    private static bool $dbAvailable = false;

    private static string $engine = 'mysql';

    private static string $outDir = '';

    private static bool $classesLoaded = false;

    public static function setUpBeforeClass(): void
    {
        try {
            Db::getInstance();
            self::$dbAvailable = true;
        } catch (\Exception $e) {
            self::$dbAvailable = false;
            return;
        }
        self::$engine = Settings::get('database.engine') ?: 'mysql';
        self::$outDir = sys_get_temp_dir() . '/laf_gen_' . self::$engine . '_' . getmypid();
        self::dropTables();

        $comment = '{"fields":{"setLabel":"Item name","setHint":"Say hi"},"form":{"readonly":"update","group":"Main"}}';
        $display = '{"fields":{"setDisplayField":"label_al"}}';
        if (self::$engine === 'postgres') {
            Db::run("
                CREATE TABLE gen_customer (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    email VARCHAR(100),
                    region VARCHAR(10),
                    zone VARCHAR(10),
                    UNIQUE (email),
                    UNIQUE (region, zone)
                );
                CREATE TABLE gen_item (
                    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    \"order\" VARCHAR(20),
                    name VARCHAR(50) NOT NULL,
                    notes TEXT,
                    price NUMERIC(10,2),
                    customer_id INTEGER REFERENCES gen_customer(id) ON DELETE CASCADE,
                    label VARCHAR(80),
                    label_al VARCHAR(80),
                    created_on TIMESTAMP,
                    updated_on TIMESTAMP
                );
                COMMENT ON COLUMN gen_item.name IS '{$comment}';
                COMMENT ON COLUMN gen_item.label IS '{$display}';
                CREATE TABLE gen_line (
                    id SERIAL PRIMARY KEY,
                    item_id INTEGER NOT NULL REFERENCES gen_item(id),
                    alt_item_id INTEGER REFERENCES gen_item(id),
                    qty INTEGER
                );
                CREATE TABLE gen_tag (id SERIAL PRIMARY KEY, label VARCHAR(30));
                CREATE TABLE gen_item_tag (
                    id SERIAL PRIMARY KEY,
                    item_id INTEGER NOT NULL REFERENCES gen_item(id),
                    tag_id INTEGER NOT NULL REFERENCES gen_tag(id)
                );
                CREATE VIEW gen_item_view AS SELECT id, name FROM gen_item;
            ");
        } else {
            Db::run("
                CREATE TABLE `gen_customer` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(50) NOT NULL,
                    `email` VARCHAR(100) NULL,
                    `region` VARCHAR(10) NULL,
                    `zone` VARCHAR(10) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `gen_customer_email` (`email`),
                    UNIQUE KEY `gen_customer_region_zone` (`region`, `zone`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE `gen_item` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `order` VARCHAR(20) NULL,
                    `name` VARCHAR(50) NOT NULL COMMENT '{$comment}',
                    `notes` TEXT NULL,
                    `price` DECIMAL(10,2) NULL,
                    `customer_id` INT NULL,
                    `label` VARCHAR(80) NULL COMMENT '{$display}',
                    `label_al` VARCHAR(80) NULL,
                    `created_on` DATETIME NULL,
                    `updated_on` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `gen_item_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `gen_customer` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE `gen_line` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `item_id` INT NOT NULL,
                    `alt_item_id` INT NULL,
                    `qty` INT NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `gen_line_item_fk` FOREIGN KEY (`item_id`) REFERENCES `gen_item` (`id`),
                    CONSTRAINT `gen_line_alt_item_fk` FOREIGN KEY (`alt_item_id`) REFERENCES `gen_item` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE `gen_tag` (`id` INT NOT NULL AUTO_INCREMENT, `label` VARCHAR(30) NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE `gen_item_tag` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `item_id` INT NOT NULL,
                    `tag_id` INT NOT NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `gen_item_tag_item_fk` FOREIGN KEY (`item_id`) REFERENCES `gen_item` (`id`),
                    CONSTRAINT `gen_item_tag_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `gen_tag` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE VIEW `gen_item_view` AS SELECT `id`, `name` FROM `gen_item`;
            ");
        }
        $db = Db::getInstance();
        $db->insert('gen_customer', ['name' => 'Acme', 'email' => 'acme@example.com']);
        $db->insert('gen_item', ['order' => 'A1', 'name' => 'Widget', 'customer_id' => 1, 'label' => 'Widget label', 'label_al' => 'Etiketa']);
        $db->insert('gen_line', ['item_id' => 1, 'qty' => 3]);
        $db->insert('gen_tag', ['label' => 'blue']);
        $db->insert('gen_item_tag', ['item_id' => 1, 'tag_id' => 1]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$dbAvailable) {
            self::dropTables();
        }
        if (self::$outDir !== '' && is_dir(self::$outDir)) {
            self::removeDir(self::$outDir);
        }
    }

    private static function dropTables(): void
    {
        $views = self::$engine === 'postgres' ? 'DROP VIEW IF EXISTS gen_item_view;' : 'DROP VIEW IF EXISTS `gen_item_view`;';
        Db::run($views . ' DROP TABLE IF EXISTS gen_item_tag; DROP TABLE IF EXISTS gen_line; DROP TABLE IF EXISTS gen_tag; DROP TABLE IF EXISTS gen_item; DROP TABLE IF EXISTS gen_customer;');
    }

    private static function removeDir(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'namespace' => 'GenTest',
            'base_class_dir' => self::$outDir . '/GenTest/Base',
            'class_dir' => self::$outDir . '/GenTest',
            'page_dir' => self::$outDir . '/pages',
            'live_page_dir' => self::$outDir . '/pages_live',
            'rewrite_class' => true,
        ];
    }

    /**
     * Generate and require the GenTest classes once per process
     */
    private function loadGeneratedClasses(): void
    {
        if (self::$classesLoaded) {
            return;
        }
        foreach (['gen_customer', 'gen_item', 'gen_line', 'gen_tag', 'gen_item_tag'] as $table) {
            $generator = new ClassGenerator(new Table($table), $this->config());
            $generator->saveBaseClassToFile()->saveClassToFile();
            $this->assertSame([], $generator->getWarnings(), $table);
            require_once $generator->getBaseClassFilePath();
            require_once $generator->getClassFilePath();
        }
        self::$classesLoaded = true;
    }

    /** @test */
    public function inspector_reads_keys_comments_identity_and_referencing_columns(): void
    {
        $item = TableInspectorFactory::create('gen_item');
        $item->inspect();
        $columns = $item->getColumns();

        $this->assertSame(['id'], $item->getPrimaryColumnNames());
        $this->assertSame('PRI', $columns['id']['COLUMN_KEY']);
        $this->assertStringContainsString('auto_increment', (string)$columns['id']['EXTRA'], 'serial on MySQL, identity on PostgreSQL');
        $this->assertStringContainsString('Item name', (string)$columns['name']['COLUMN_COMMENT'], 'the comment is read on both engines');
        $this->assertSame('NO', $columns['name']['IS_NULLABLE']);
        $this->assertSame(20, (int)$columns['order']['CHARACTER_MAXIMUM_LENGTH']);
        $this->assertSame('gen_customer', $columns['customer_id']['FOREIGN_KEY']['referenced_table_name']);
        $this->assertSame('id', $columns['customer_id']['FOREIGN_KEY']['referenced_column_name']);
        $this->assertSame('CASCADE', $columns['customer_id']['FOREIGN_KEY']['delete_rule']);
        $this->assertArrayNotHasKey('FOREIGN_KEY', $columns['name']);
        $this->assertTrue($item->hasForeignKeys());
        $this->assertSame('label_al', $item->getDisplayColumnName());

        $references = array_map(static fn (array $r) => $r['table_name'] . '.' . $r['column_name'], $item->getReferencingTables());
        sort($references);
        $this->assertSame(['gen_item_tag.item_id', 'gen_line.alt_item_id', 'gen_line.item_id'], $references);
        $this->assertSame('gen_item_tag', $item->getReferencingTables()[0]['TABLE_NAME'], 'upper-case key kept for older callers');

        $customer = TableInspectorFactory::create('gen_customer');
        $customer->inspect();
        $this->assertSame('UNI', $customer->getColumns()['email']['COLUMN_KEY']);
        $this->assertNotSame('UNI', $customer->getColumns()['region']['COLUMN_KEY'], 'a column of a composite unique index is not unique by itself');
        $this->assertNotSame('UNI', $customer->getColumns()['zone']['COLUMN_KEY']);
        $this->assertSame('name', $customer->getDisplayColumnName());
        $this->assertFalse($customer->hasForeignKeys());
    }

    /** @test */
    public function generated_classes_load_and_describe_the_table(): void
    {
        $this->loadGeneratedClasses();

        $item = new \GenTest\GenItem();
        $table = $item->getTable();
        $this->assertSame('Item name', $table->getField('name')->getLabel());
        $this->assertSame('Say hi', $table->getField('name')->getHint());
        $this->assertTrue($table->getField('name')->isRequired());
        $this->assertSame(['readonly' => ['update'], 'group' => 'Main'], $table->getField('name')->getFormOptions());
        $this->assertTrue($table->getField('id')->isAutoIncrement());
        $this->assertSame('Order', $table->getField('order')->getLabel());
        $this->assertSame(20, $table->getField('order')->getMaxLength());
        $this->assertEmpty($table->getField('notes')->getMaxLength(), 'text columns carry no limit');
        $this->assertInstanceOf(TypeDecimal::class, $table->getField('price')->getType());
        $this->assertSame('label_al', $table->getDisplayField()->getName());
        $fk = $table->getForeignKey('customer_id');
        $this->assertSame('gen_customer', $fk->getReferencedTable());
        $this->assertSame('CASCADE', $fk->getOnDelete());
        $this->assertInstanceOf(\GenTest\GenCustomer::class, $item->getCustomerIdObj());

        $customer = new \GenTest\GenCustomer();
        $this->assertTrue($customer->getTable()->getField('email')->isUnique());
        $this->assertFalse($customer->getTable()->getField('region')->isUnique());

        // the model's form follows the comment: the name is read-only on update, in a "Main" fieldset
        $update = $item->getForm(DrawMode::UPDATE);
        $this->assertNotContains('name', $update->getEditableFieldNames());
        $this->assertContains('order', $update->getEditableFieldNames());
        $this->assertNotContains('created_on', $update->getRenderedFieldNames());
        $fieldsets = array_filter($update->getComponents(), static fn ($c) => $c instanceof Fieldset);
        $this->assertCount(1, $fieldsets);
        $this->assertContains('name', $item->getForm(DrawMode::INSERT)->getEditableFieldNames());

        // the generated classes read and write
        $loaded = new \GenTest\GenItem(1);
        $this->assertSame('Widget', $loaded->getNameVal());
        $this->assertSame('Acme', $loaded->getCustomerIdObj()->getNameVal());
        $created = new \GenTest\GenCustomer();
        $created->setNameVal('Beta');
        $created->store();
        $this->assertTrue($created->recordExists());
        $this->assertSame('Beta', \GenTest\GenCustomer::findOne(['name' => 'Beta'])->getNameVal());
        $this->assertCount(2, \GenTest\GenCustomer::find());
        $this->assertCount(2, $created->listAllArray());
    }

    /** @test */
    public function generated_page_sql_runs_on_the_engine(): void
    {
        $generator = new PageGenerator(new Table('gen_item'), $this->config(), ['delete-confirmation' => "A jeni të sigurtë? It's final"]);
        $generator->savePageToFile();
        $this->assertSame([], $generator->getWarnings());
        $file = $generator->getPageFilePath();
        $this->assertFileExists($file);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));

        $source = (string)file_get_contents($file);
        $this->assertStringContainsString("new TabItem('GenLine (Item)')", $source);
        $this->assertStringContainsString("new TabItem('GenLine (Alt Item)')", $source);
        $this->assertStringContainsString("new TabItem('GenTag')", $source);
        $this->assertStringContainsString("setConfirmationMessage('A jeni të sigurtë? It\\'s final')", $source);

        $this->assertSame(4, preg_match_all("/->setSqlQuery\\('(.*?)'\\);/s", $source, $matches), 'list, two gen_line tabs, the tag tab');
        $results = [];
        foreach ($matches[1] as $literal) {
            $sql = str_replace(["\\'", '\\\\'], ["'", '\\'], $literal);
            $sql = str_replace(["' . ((int)UrlParser::getId()) . '", "' . Db::getInstance()->quote((string)UrlParser::getId()) . '"], ['1', "'1'"], $sql);
            $results[] = Db::getAllAssoc($sql . ' LIMIT 5');
        }
        // the view page's related grids come first in the file (referencing tables sorted by
        // table and column: gen_item_tag.item_id, gen_line.alt_item_id, gen_line.item_id), the list grid last
        $this->assertSame('blue', $results[0][0]['gen_tag_label'], 'tags through the junction table');
        $this->assertCount(0, $results[1], 'gen_line rows through alt_item_id');
        $this->assertCount(1, $results[2], 'gen_line rows through item_id');
        $this->assertSame('A1', $results[3][0]['gen_item_order'], 'the reserved column name is quoted');
        $this->assertSame('Acme', $results[3][0]['gen_customer_name']);
        $this->assertSame('Etiketa', $results[3][0]['gen_item_label_al']);
    }

    /** @test */
    public function database_generator_selects_base_tables_and_writes_everything(): void
    {
        $generator = new DatabaseGenerator(self::$outDir . '/db');
        $generator->setTables(['gen_*'])->setExcludedTables(['gen_item_*']);
        $names = array_column($generator->getTables(), 'table_name');
        $this->assertSame(['gen_customer', 'gen_item', 'gen_line', 'gen_tag'], $names, 'no views, no excluded tables');

        $lines = [];
        $generator->setOutput(static function (string $line) use (&$lines): void {
            $lines[] = trim($line);
        });
        $generator->setTables(['gen_tag', 'gen_customer'])->generateEverything(['list' => 'Lista']);

        $files = $generator->getGeneratedFiles();
        $this->assertCount(7, $files, 'autoloader + 2 base + 2 model + 2 pages');
        foreach ($files as $file) {
            $this->assertFileExists($file);
        }
        $this->assertSame([], $generator->getWarnings());
        $this->assertContains('Processed table: gen_tag', $lines);
        $this->assertContains('Processed page: gen_customer', $lines);
        $this->assertStringContainsString("'Gen Tag Lista'", (string)file_get_contents(self::$outDir . '/db/generator_cache/pages/gen_tag.page'));
        $this->assertStringContainsString('namespace LafShell\\Base;', (string)file_get_contents(self::$outDir . '/db/LafShell/Base/BaseGenTag.php'));
    }
}
