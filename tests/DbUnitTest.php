<?php

use Laf\Database\Db;
use Laf\Database\Identifier;
use Laf\Exception\DbConnectException;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Db behaviour that needs no server: construction, DSNs, settings, bindings,
 * instance registry, error state.
 */
class DbUnitTest extends TestCase
{
    public function tearDown(): void
    {
        Db::resetInstance('unit');
        Db::resetInstance('nope');
    }

    /** @test */
    public function constructor_normalises_port_and_engine()
    {
        $db = new Db('h', 'd', 'u', 'p', '3306', 'MariaDB');
        $this->assertSame(3306, $db->getPort());
        $this->assertSame(Identifier::MYSQL, $db->getEngine());
        $this->assertTrue($db->isMysql());
        $this->assertSame('h', $db->getHostName());
        $this->assertSame('d', $db->getDatabase());
        $this->assertSame('u', $db->getUserName());
        $this->assertSame('p', $db->getPassword());

        $this->assertSame(5432, (new Db('h', 'd', 'u', 'p', false, 'pgsql'))->getPort(), 'an unset getenv port falls back to the engine default');
        $this->assertSame(5432, (new Db('h', 'd', 'u', 'p', null, 'postgresql'))->getPort());
        $this->assertTrue((new Db('h', 'd', 'u', 'p', null, 'Postgres'))->isPostgres());
        $this->assertSame(3306, (new Db('h', 'd', 'u', 'p', 0))->getPort());
        $this->assertSame(3307, (new Db('h', 'd', 'u', 'p', 3307))->getPort());
    }

    /** @test */
    public function a_fresh_db_is_not_connected_and_has_no_error()
    {
        $db = new Db('h', 'd', 'u', 'p');
        $this->assertFalse($db->isConnected());
        $this->assertFalse($db->hasError());
        $this->assertFalse($db->getHasError());
        $this->assertNull($db->getErrorMessage());
        $this->assertNull($db->getErrorTraceString());
        $this->assertSame([], $db->getErrorTrace());
        $this->assertNull($db->getLogger());
        $this->assertNull($db->getSql());
        $this->assertSame(0, $db->getQueryCount());
        $this->assertFalse($db->inTransaction());
        $this->assertFalse($db->ping());
    }

    /** @test */
    public function dsn_for_each_engine()
    {
        $mysql = new Db('h', 'd', 'u', 'p', '3306', 'mysql');
        $this->assertSame('mysql:dbname=d;host=h;port=3306;charset=utf8mb4', $mysql->getDsn());
        $mysql->setCharset('utf8');
        $this->assertSame('mysql:dbname=d;host=h;port=3306;charset=utf8', $mysql->getDsn());
        $mysql->setSocket('/var/run/mysqld/mysqld.sock');
        $this->assertSame('mysql:dbname=d;unix_socket=/var/run/mysqld/mysqld.sock;charset=utf8', $mysql->getDsn());

        $pg = new Db('h', 'd', 'u', 'p', null, 'postgres');
        $this->assertSame('pgsql:dbname=d;host=h;port=5432', $pg->getDsn());
        $pg->setDsnExtra(';sslmode=require');
        $this->assertSame('pgsql:dbname=d;host=h;port=5432;sslmode=require', $pg->getDsn());
        $pg->setDsn('pgsql:host=/var/run/postgresql;dbname=d');
        $this->assertSame('pgsql:host=/var/run/postgresql;dbname=d', $pg->getDsn(), 'a full DSN overrides everything');
    }

    /** @test */
    public function unsupported_engine_is_refused_before_any_connection_attempt()
    {
        $db = new Db('h', 'd', 'u', 'p', null, 'oracle');
        $this->assertSame('oracle', $db->getEngine());
        try {
            $db->connect();
            $this->fail('expected DbConnectException');
        } catch (DbConnectException $ex) {
            $this->assertInstanceOf(\PDOException::class, $ex, 'catch (\PDOException) keeps matching');
            $this->assertInstanceOf(\Exception::class, $ex);
            $this->assertStringContainsString('oracle', $ex->getMessage());
        }
    }

    /** @test */
    public function statements_without_a_server_throw_instead_of_returning_null()
    {
        $this->expectException(DbConnectException::class);
        (new Db('h', 'd', 'u', 'p', null, 'oracle'))->query('SELECT 1');
    }

    /** @test */
    public function binding_conversions()
    {
        $this->assertSame([null, \PDO::PARAM_NULL], Db::binding(null));
        $this->assertSame([1, \PDO::PARAM_INT], Db::binding(true));
        $this->assertSame([0, \PDO::PARAM_INT], Db::binding(false));
        $this->assertSame([5, \PDO::PARAM_INT], Db::binding(5));
        $this->assertSame(['5', \PDO::PARAM_STR], Db::binding('5'));
        $this->assertSame(['1.5', \PDO::PARAM_STR], Db::binding(1.5));
        $this->assertSame([7, \PDO::PARAM_STR], Db::binding([7, \PDO::PARAM_STR]), 'an explicit pair is kept');
        $this->assertSame([1, \PDO::PARAM_INT], Db::binding([true, \PDO::PARAM_INT]));
        $this->assertSame(['2024-02-29 13:45:10', \PDO::PARAM_STR], Db::binding(new \DateTimeImmutable('2024-02-29 13:45:10')));
        $this->expectException(\InvalidArgumentException::class);
        Db::binding(['a', 'b', 'c']);
    }

    /** @test */
    public function engine_names_are_normalised()
    {
        foreach (['MySQL', 'mariadb', '', null] as $name) {
            $this->assertSame(Identifier::MYSQL, Db::normalizeEngine($name));
        }
        foreach (['Postgres', 'postgresql', 'PGSQL'] as $name) {
            $this->assertSame(Identifier::POSTGRES, Db::normalizeEngine($name));
        }
        $this->assertSame('oracle', Db::normalizeEngine('oracle'));
    }

    /** @test */
    public function from_settings_reads_the_optional_keys()
    {
        $settings = Settings::getInstance();
        foreach ([
            'hostname' => 'db.local', 'database_name' => 'erp', 'username' => 'app', 'port' => '5433', 'engine' => 'pgsql',
            'charset' => 'UTF8', 'persistent' => true, 'connect_timeout' => '3', 'options' => [\PDO::ATTR_CASE => \PDO::CASE_LOWER],
            'dsn_extra' => 'sslmode=require', 'timezone' => 'UTC', 'schema' => 'app', 'init_commands' => ['SET statement_timeout = 1000'],
            'empty_string_as_null' => false,
        ] as $key => $value) {
            $settings->setProperty('database.unit.' . $key, $value);
        }

        $db = Db::fromSettings('database.unit');
        $this->assertFalse($db->isConnected(), 'fromSettings() builds, it does not connect');
        $this->assertSame('db.local', $db->getHostName());
        $this->assertSame('erp', $db->getDatabase());
        $this->assertSame('app', $db->getUserName());
        $this->assertSame('', $db->getPassword(), 'password defaults to empty');
        $this->assertSame(5433, $db->getPort());
        $this->assertTrue($db->isPostgres());
        $this->assertSame('UTF8', $db->getCharset());
        $this->assertTrue($db->isPersistent());
        $this->assertSame(3, $db->getConnectTimeout());
        $this->assertSame([\PDO::ATTR_CASE => \PDO::CASE_LOWER], $db->getOptions());
        $this->assertSame('pgsql:dbname=erp;host=db.local;port=5433;sslmode=require', $db->getDsn());
        $this->assertSame('UTC', $db->getTimezone());
        $this->assertSame('app', $db->getSchema());
        $this->assertSame(['SET statement_timeout = 1000'], $db->getInitCommands());
        $this->assertFalse($db->isEmptyStringAsNull());
    }

    /** @test */
    public function from_settings_defaults_port_and_engine()
    {
        $settings = Settings::getInstance();
        $settings->setProperty('database.minimal.hostname', 'h');
        $settings->setProperty('database.minimal.database_name', 'd');
        $settings->setProperty('database.minimal.username', 'u');
        $db = Db::fromSettings('database.minimal.');
        $this->assertTrue($db->isMysql());
        $this->assertSame(3306, $db->getPort());
        $this->assertTrue($db->isEmptyStringAsNull());
    }

    /** @test */
    public function from_settings_requires_the_hostname()
    {
        $this->expectException(MissingConfigParamException::class);
        Db::fromSettings('database.nope');
    }

    /** @test */
    public function a_failed_get_instance_is_not_cached()
    {
        try {
            Db::getInstance('nope');
            $this->fail('expected MissingConfigParamException');
        } catch (MissingConfigParamException $ex) {
        }
        $this->assertFalse(Db::hasInstance('nope'));
        $this->expectException(MissingConfigParamException::class);
        Db::getInstance('nope');
    }

    /** @test */
    public function instances_can_be_registered_by_name()
    {
        $db = new Db('h', 'd', 'u', 'p');
        $this->assertFalse(Db::hasInstance('unit'));
        Db::setInstance($db, 'unit');
        $this->assertTrue(Db::hasInstance('unit'));
        $this->assertSame($db, Db::getInstance('unit'));
        Db::resetInstance('unit');
        $this->assertFalse(Db::hasInstance('unit'));
    }

    /** @test */
    public function error_state_can_be_set_and_cleared()
    {
        $db = new Db('h', 'd', 'u', 'p');
        $db->setErrorMessage('boom');
        $db->setHasError(true);
        $db->setErrorTrace([['file' => 'x']]);
        $db->setErrorTraceString('#0 x');
        $this->assertTrue($db->hasError());
        $this->assertSame('boom', $db->getErrorMessage());
        $db->clearError();
        $this->assertFalse($db->hasError());
        $this->assertNull($db->getErrorMessage());
        $this->assertSame([], $db->getErrorTrace());
    }

    /** @test */
    public function fluent_setters_return_the_instance()
    {
        $db = new Db();
        $this->assertSame($db, $db->setHostName('h')->setDatabase('d')->setUserName('u')->setPassword('p')->setPort('5432')->setEngine('postgres')
            ->setCharset('UTF8')->setSocket(null)->setPersistent(false)->setConnectTimeout(2)->setOptions([])->setDsn(null)->setDsnExtra(null)
            ->setTimezone('UTC')->setSchema('public')->setInitCommands([])->setEmptyStringAsNull(true)->enableQueryLog()->clearQueryLog()->setLogger(null));
        $this->assertSame('pgsql:dbname=d;host=h;port=5432', $db->getDsn());
        $this->assertSame([], $db->getQueryLog());
    }

    /** @test */
    public function name_helpers()
    {
        $this->assertSame('ServiceOrder', Db::convertTableNameToClassName('service_order'));
        $this->assertSame('service_order', Db::convertClassNameToTableName('ServiceOrder'));
        $this->assertSame('name', Db::getFieldNameFromHash(Db::encodeFieldNameToHash('name')));
    }
}
