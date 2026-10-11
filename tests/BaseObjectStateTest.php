<?php

use Laf\Database\BaseObject;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\Exception\DatabaseException;
use LafShell\Base\BaseDummyTable;
use LafShell\DummyTable;
use PHPUnit\Framework\TestCase;

/**
 * BaseObject state handling that needs no database: new/dirty/changed,
 * bulk setters, array/JSON export, reset, clone, hydrate, hooks, defaults.
 */
class BaseObjectStateTest extends TestCase
{
    /** @test */
    public function a_fresh_object_is_new_clean_and_not_loaded()
    {
        $row = new DummyTable();
        $this->assertTrue($row->isNew());
        $this->assertFalse($row->isDirty());
        $this->assertFalse($row->isLoaded());
        $this->assertFalse($row->recordExists());
        $this->assertNull($row->getRecordId());
        $this->assertNull($row->getPrimaryKeyValue());
        $this->assertSame(0, $row->getAffectedRows());
        $this->assertNull($row->getLastSql());
    }

    /** @test */
    public function hydrate_loads_a_row_without_a_query()
    {
        $row = DummyTable::hydrate(['id' => '12', 'varchar_field45' => 'v', 'integer_field' => '3', 'bool_field' => '1', 'no_such_column' => 'x']);
        $this->assertSame(12, $row->getRecordId(), 'record id comes from the key column, typed');
        $this->assertSame(12, $row->getPrimaryKeyValue());
        $this->assertTrue($row->isLoaded());
        $this->assertFalse($row->isNew());
        $this->assertFalse($row->isDirty(), 'loaded values are the persisted values');
        $this->assertSame(3, $row->getIntegerFieldVal());
        $this->assertTrue($row->getBoolFieldVal());
    }

    /** @test */
    public function changes_are_tracked_per_field()
    {
        $row = DummyTable::hydrate(['id' => 1, 'varchar_field45' => 'old', 'integer_field' => 1]);
        $row->setVarcharField45Val('new')->setIntegerFieldVal('1');
        $this->assertTrue($row->isDirty());
        $this->assertSame(['varchar_field45'], array_keys($row->getChangedFields()));
        $this->assertSame(['varchar_field45' => ['old' => 'old', 'new' => 'new']], $row->getChangedValues());
    }

    /** @test */
    public function set_field_values_takes_a_map_and_ignores_unknown_columns()
    {
        $row = new DummyTable();
        $same = $row->setFieldValues(['varchar_field45' => 'a', 'integer_field' => '7', 'nope' => 1]);
        $this->assertSame($row, $same);
        $this->assertSame('a', $row->getVarcharField45Val());
        $this->assertSame(7, $row->getIntegerFieldVal());
    }

    /** @test */
    public function to_array_and_json_export_the_columns()
    {
        $row = new DummyTable();
        $row->setVarcharField45Val('a')->setIntegerFieldVal(2);
        $all = $row->toArray();
        $this->assertSame('a', $all['varchar_field45']);
        $this->assertSame(2, $all['integer_field']);
        $this->assertArrayHasKey('id', $all);
        $this->assertSame(['integer_field' => 2], $row->toArray(['integer_field', 'nope']));
        $this->assertSame($all, $row->getAllFieldsAsKVArrays());
        $this->assertSame(json_encode($all), json_encode($row));
    }

    /** @test */
    public function reset_clears_values_but_keeps_the_metadata()
    {
        $row = DummyTable::hydrate(['id' => 5, 'varchar_field45' => 'a']);
        $row->reset();
        $this->assertNull($row->getRecordId());
        $this->assertFalse($row->isLoaded());
        $this->assertNull($row->getVarcharField45Val());
        $this->assertTrue($row->getTable()->hasField('varchar_field45'), 'fields survive a reset');
        $this->assertSame('dummy_table', $row->getTable()->getName());
        $this->assertTrue($row->isNew());
    }

    /** @test */
    public function a_clone_is_a_new_unsaved_row_with_its_own_fields()
    {
        $row = DummyTable::hydrate(['id' => 5, 'varchar_field45' => 'a']);
        $copy = clone $row;
        $this->assertNull($copy->getIdVal());
        $this->assertNull($copy->getRecordId());
        $this->assertTrue($copy->isNew());
        $this->assertSame('a', $copy->getVarcharField45Val());
        $copy->setVarcharField45Val('b');
        $this->assertSame('a', $row->getVarcharField45Val(), 'the original is untouched');
        $this->assertSame(5, $row->getIdVal());
    }

    /** @test */
    public function clone_of_an_object_without_a_table_does_not_fatal()
    {
        $bare = new BaseObject(3);
        $copy = clone $bare;
        $this->assertNull($copy->getRecordId());
        $this->assertFalse($copy->hasTable());
    }

    /** @test */
    public function get_table_without_a_table_is_a_logic_error_not_a_fatal()
    {
        $this->expectException(\LogicException::class);
        (new BaseObject())->getTable();
    }

    /** @test */
    public function a_base_class_without_a_leaf_returns_itself_from_setters()
    {
        $base = new BaseDummyTable();
        $this->assertSame($base, $base->setVarcharField45Val('x'), 'returnLeafClass() no longer recurses');
    }

    /** @test */
    public function record_ids_are_normalised()
    {
        $row = new DummyTable();
        $this->assertTrue($row->setRecordId('7'));
        $this->assertSame('7', $row->getRecordId());
        $this->assertTrue($row->setRecordId(0));
        $this->assertSame(0, $row->getRecordId());
        $this->assertFalse($row->setRecordId(''));
        $this->assertFalse($row->setRecordId(null));
        $this->assertSame(0, $row->getRecordId(), 'an empty id leaves the current one');
        $this->assertTrue($row->setRecordId(['left_id' => 1, 'code' => 'A']));
        $this->assertSame(['left_id' => 1, 'code' => 'A'], $row->getRecordId());
    }

    /** @test */
    public function validate_reports_required_and_type_rules_without_a_database()
    {
        $row = new DummyTable();
        $row->setVarcharField45Val(str_repeat('x', 46));
        $errors = $row->validate();
        $this->assertArrayHasKey('required_field', $errors);
        $this->assertArrayHasKey('varchar_field45', $errors);
    }

    /** @test */
    public function hooks_run_in_order_around_the_write_path()
    {
        $object = new class extends BaseObject {
            public array $calls = [];

            public function __construct()
            {
                parent::__construct();
                $table = new Table('hook_table');
                $id = (new Field())->setName('id')->setAutoIncrement(true)->setType(new TypeInteger());
                $name = (new Field())->setName('name')->setType(new TypeVarchar());
                $table->addField($id)->addField($name)->setPrimaryKey(new PrimaryKey([$id], $table));
                $this->setTable($table);
            }

            protected function beforeInsert(): void
            {
                $this->calls[] = 'beforeInsert';
                throw new \RuntimeException('stop here');
            }

            protected function afterLoad(): void
            {
                $this->calls[] = 'afterLoad';
            }

            protected function beforeDelete(string $mode): void
            {
                $this->calls[] = 'beforeDelete:' . $mode;
            }
        };

        $object->loadRow(['id' => 1, 'name' => 'n']);
        $this->assertSame(['afterLoad'], $object->calls);

        $fresh = clone $object;
        try {
            $fresh->insert();
            $this->fail('the hook aborts the insert');
        } catch (\RuntimeException $ex) {
            $this->assertSame('stop here', $ex->getMessage());
        }
        $this->assertSame(['afterLoad', 'beforeInsert'], $fresh->calls, 'the clone keeps the call list; beforeInsert ran before any SQL');
        $this->assertNull($fresh->getLastSql());
    }

    /** @test */
    public function update_and_delete_on_an_unloaded_object_do_nothing()
    {
        $row = new DummyTable();
        $row->setVarcharField45Val('x');
        $this->assertFalse($row->update());
        $this->assertFalse($row->hardDelete());
        $this->assertNull($row->getLastSql(), 'no statement was prepared');
    }

    /** @test */
    public function database_exception_carries_sql_bindings_and_sqlstate()
    {
        $pdo = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
        $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $ex = DatabaseException::fromPdo($pdo, 'INSERT INTO t (a) VALUES (:a)', [':a' => ['x', \PDO::PARAM_STR]]);
        $this->assertSame('INSERT INTO t (a) VALUES (:a)', $ex->getSql());
        $this->assertSame([':a' => 'x'], $ex->getBindings());
        $this->assertSame('23000', $ex->getSqlState());
        $this->assertSame(1062, $ex->getDriverCode());
        $this->assertTrue($ex->isUniqueViolation());
        $this->assertFalse($ex->isForeignKeyViolation());
        $this->assertSame($pdo, $ex->getPrevious());
        $this->assertInstanceOf(\Exception::class, $ex, 'catch (\Exception) in the apps still works');

        $pg = new \PDOException('SQLSTATE[23503]: Foreign key violation');
        $pg->errorInfo = ['23503', 7, 'violates foreign key constraint'];
        $this->assertTrue(DatabaseException::fromPdo($pg)->isForeignKeyViolation());
    }

    /** @test */
    public function app_style_untyped_overrides_stay_compatible()
    {
        // the production apps override these without parameter or return types (see AppStyleModel below);
        // loading the class is the check: an incompatible parent signature is a fatal error
        $model = new AppStyleModel();
        $this->assertSame($model, $model->setVarcharField45Val('x'));
        $this->assertFalse($model->update());
        $this->assertFalse($model->hardDelete());
        $this->assertSame('fa fa-x', AppStyleModel::getIcon());
        $this->assertInstanceOf(AppStyleModel::class, clone $model);
    }

    /** @test */
    public function active_user_resolution_prefers_the_explicit_user()
    {
        BaseObject::setActiveUserId(42);
        $this->assertSame(42, BaseObject::resolveActiveUserId());
        BaseObject::setActiveUserId(null);
        $this->assertNull(BaseObject::resolveActiveUserId(), 'LafShell has no Person class to fall back to');
    }
}

/**
 * The override shapes found in the asm and routerarena model classes:
 * untyped insert/update/hardDelete/returnLeafClass/__clone, typed find/findOne/getIcon.
 */
class AppStyleModel extends BaseDummyTable
{
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    protected function returnLeafClass()
    {
        return $this;
    }

    public static function findOne(array $keyValuePairs): ?AppStyleModel
    {
        return parent::bOfindOne($keyValuePairs);
    }

    public static function find(array $keyValuePairs): array
    {
        return parent::bOfind($keyValuePairs);
    }

    public function insert()
    {
        return parent::insert();
    }

    public function update()
    {
        return parent::update();
    }

    public function hardDelete()
    {
        return parent::hardDelete();
    }

    public function __clone()
    {
        $this->getTable()->getField('id')->setRequired(false);
    }

    public static function getIcon(): ?string
    {
        return 'fa fa-x';
    }
}
