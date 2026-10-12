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

    /** @test */
    public function set_engine_re_defaults_a_port_that_was_not_given()
    {
        $db = new Db('h', 'd', 'u', 'p');
        $this->assertSame(3306, $db->getPort());
        $db->setEngine('postgres');
        $this->assertSame(5432, $db->getPort(), 'the default follows the engine');
        $this->assertSame('pgsql:dbname=d;host=h;port=5432', $db->getDsn());
        $db->setPort('5433')->setEngine('mysql');
        $this->assertSame(5433, $db->getPort(), 'an explicit port is kept across engines');
        $db->setPort(null);
        $this->assertSame(3306, $db->getPort());
    }

    /** @test */
    public function lost_connection_detection()
    {
        $gone = new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $this->assertTrue(Db::isLostConnection($gone));

        $lost = new \PDOException('SQLSTATE[HY000]: General error: 2013 Lost connection to MySQL server during query');
        $lost->errorInfo = ['HY000', 2013, 'Lost connection to MySQL server during query'];
        $this->assertTrue(Db::isLostConnection($lost));

        $terminated = new \PDOException('SQLSTATE[57P01]: Admin shutdown: 7 FATAL: terminating connection due to administrator command');
        $terminated->errorInfo = ['57P01', 7, 'FATAL:  terminating connection due to administrator command'];
        $this->assertTrue(Db::isLostConnection($terminated));

        $closed = new \PDOException('SQLSTATE[HY000]: General error: 7 server closed the connection unexpectedly');
        $this->assertTrue(Db::isLostConnection($closed), 'recognised by message when the driver gives no SQLSTATE');

        $missingTable = new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'x.y' doesn't exist");
        $missingTable->errorInfo = ['42S02', 1146, "Table 'x.y' doesn't exist"];
        $this->assertFalse(Db::isLostConnection($missingTable));
        $this->assertFalse(Db::isLostConnection(new \PDOException('SQLSTATE[23000]: Integrity constraint violation')));
    }

    /** @test */
    public function session_commands_quote_schema_lists_and_values()
    {
        $pg = new Db('h', 'd', 'u', 'p', null, 'postgres');
        $this->assertSame([], $pg->getSessionCommands());
        $pg->setSchema('app, public')->setTimezone('UTC')->setCharset('UTF8')->setInitCommands(['SET statement_timeout = 1000']);
        $this->assertSame(
            ["SET NAMES 'UTF8'", 'SET search_path TO "app", "public"', "SET TIME ZONE 'UTC'", 'SET statement_timeout = 1000'],
            $pg->getSessionCommands()
        );
        $pg->setSchema('"public", DROP SCHEMA x');
        $this->assertSame('SET search_path TO "public", "DROPSCHEMAx"', $pg->getSessionCommands()[1], 'names are whitelisted, never injected');

        $mysql = new Db('h', 'd', 'u', 'p');
        $mysql->setSchema('ignored')->setTimezone("+00:00")->setInitCommands(['SET sql_mode = STRICT_ALL_TABLES']);
        $this->assertSame(["SET time_zone = '+00:00'", 'SET sql_mode = STRICT_ALL_TABLES'], $mysql->getSessionCommands());
    }

    /** @test */
    public function identifier_engine_follows_the_registered_default_instance()
    {
        $hadInstance = Db::hasInstance();
        $previous = $hadInstance ? Db::getInstance() : null;
        $savedEngine = Settings::getInstance()->propertyExists('database.engine') ? Settings::get('database.engine') : null;
        try {
            Db::resetInstance(Db::DEFAULT_INSTANCE);
            Settings::set('database.engine', 'mysql');
            $this->assertSame(Identifier::MYSQL, Identifier::engine(), 'no instance: Settings decide');

            Db::setInstance(new Db('h', 'd', 'u', 'p', null, 'postgres'));
            $this->assertSame(Identifier::POSTGRES, Identifier::engine(), 'the registered connection wins over Settings');
            $this->assertSame('"name"', Identifier::quote('name'));

            Db::setInstance(new Db('h', 'd', 'u', 'p', null, 'oracle'));
            $this->assertSame(Identifier::MYSQL, Identifier::engine(), 'an unsupported engine quotes like MySQL, as before');
        } finally {
            Db::resetInstance(Db::DEFAULT_INSTANCE);
            if ($previous !== null) {
                Db::setInstance($previous);
            }
            Settings::set('database.engine', $savedEngine ?? 'mysql');
        }
    }

    /** @test */
    public function write_helpers_refuse_empty_where_and_unsafe_identifiers()
    {
        $db = new Db('h', 'd', 'u', 'p', null, 'postgres');
        $this->assertSame('"order_line"', $db->identifier('order_line'));
        try {
            $db->identifier('order line; DROP');
            $this->fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('not a plain SQL identifier', $ex->getMessage());
        }
        try {
            $db->update('t', ['a' => 1], []);
            $this->fail('an empty where must be refused before any connection attempt');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('non-empty where', $ex->getMessage());
        }
        try {
            $db->delete('t', []);
            $this->fail('an empty where must be refused');
        } catch (\InvalidArgumentException $ex) {
        }
        try {
            $db->insert('t', []);
            $this->fail('an empty row must be refused');
        } catch (\InvalidArgumentException $ex) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $db->update('t', ['a' => 1], ['b c' => 2]);
    }

    /** @test */
    public function connect_exception_keeps_the_driver_code_and_error_info()
    {
        $driver = new \PDOException('SQLSTATE[08006] [7] could not connect to server');
        $driver->errorInfo = ['08006', 7, 'could not connect to server'];
        $ex = DbConnectException::fromPdo($driver, 'pgsql:dbname=d;host=h;port=5432');
        $this->assertSame($driver, $ex->getPrevious());
        $this->assertSame($driver->getCode(), $ex->getCode());
        $this->assertSame(['08006', 7, 'could not connect to server'], $ex->errorInfo);
        $this->assertStringContainsString('pgsql:dbname=d;host=h;port=5432', $ex->getMessage());
        $this->assertStringNotContainsString('password', $ex->getMessage());
    }

    /** @test */
    public function from_settings_reads_reconnect_on_loss()
    {
        $settings = Settings::getInstance();
        foreach (['hostname' => 'h', 'database_name' => 'd', 'username' => 'u', 'reconnect_on_loss' => false] as $key => $value) {
            $settings->setProperty('database.noretry.' . $key, $value);
        }
        $this->assertFalse(Db::fromSettings('database.noretry')->isReconnectOnLoss());
        foreach (['hostname' => 'h', 'database_name' => 'd', 'username' => 'u'] as $key => $value) {
            $settings->setProperty('database.retry.' . $key, $value);
        }
        $this->assertTrue(Db::fromSettings('database.retry')->isReconnectOnLoss(), 'on by default');
        $this->assertTrue((new Db())->isReconnectOnLoss());
    }
}
