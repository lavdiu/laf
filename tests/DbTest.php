<?php

use Laf\Database\Db;
use Laf\Database\Identifier;
use Laf\Database\SqlErrorLoggerInterface;
use Laf\Exception\DbConnectException;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Db connection wrapper: statements and bindings,
 * fetch helpers, error capture and SQL error logging, timing, reconnects,
 * named instances, session settings. Runs on MariaDB/MySQL or PostgreSQL
 * depending on LAF_DB_ENGINE (see tests/bootstrap.php).
 */
class DbTest extends TestCase
{
	private static bool $dbAvailable = false;
	private static string $engine = 'mysql';

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
		if (self::$engine === 'postgres') {
			Db::run("DROP TABLE IF EXISTS db_test; CREATE TABLE db_test (id SERIAL PRIMARY KEY, name VARCHAR(50), flag BOOLEAN, amount DECIMAL(8,2), note TEXT)");
		} else {
			Db::run("DROP TABLE IF EXISTS `db_test`; CREATE TABLE `db_test` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50), `flag` tinyint(1), `amount` decimal(8,2), `note` text, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
	}

	public function setUp(): void
	{
		if (!self::$dbAvailable) {
			$this->markTestSkipped('Database not available');
		}
		$db = Db::getInstance();
		$db->setSqlErrorLogger(null);
		$db->setLogger(null);
		$db->enableQueryLog(false)->clearQueryLog();
		$db->clearError();
		$db->execute("DELETE FROM db_test");
		$db->statement("INSERT INTO db_test (name, flag, amount, note) VALUES (?, ?, ?, ?)", ['a', true, '1.50', '']);
		$db->statement("INSERT INTO db_test (name, flag, amount, note) VALUES (?, ?, ?, ?)", ['b', false, '2.25', null]);
		$db->statement("INSERT INTO db_test (name, flag, amount, note) VALUES (?, ?, ?, ?)", ['c', null, '3.00', 'x']);
	}

	public function tearDown(): void
	{
		if (self::$dbAvailable) {
			Db::getInstance()->setSqlErrorLogger(null);
			Db::getInstance()->setLogger(null);
			Db::resetInstance('second');
			Db::resetInstance('broken');
		}
	}

	// ── statements and bindings ──

	/** @test */
	public function statement_binds_named_keys_with_and_without_colon_and_positional_values()
	{
		$this->assertSame(['a', 'c'], array_column(Db::getAllAssoc("SELECT name FROM db_test WHERE flag = :flag OR name = :name ORDER BY name", [':flag' => true, 'name' => 'c']), 'name'));
		$this->assertEquals(['b', 'c'], Db::getInstance()->statement("SELECT name FROM db_test WHERE amount > ? ORDER BY name", [2])->fetchAll(\PDO::FETCH_COLUMN));
		$this->assertEquals(['c'], Db::getColumn("SELECT name FROM db_test WHERE amount > :amount ORDER BY name", ['amount' => [2.5, \PDO::PARAM_STR]]));
	}

	/** @test */
	public function booleans_and_nulls_bind_on_both_engines()
	{
		$this->assertSame('1', Db::getOne("SELECT COUNT(*) FROM db_test WHERE flag = :f", ['f' => false]));
		$this->assertSame('1', Db::getOne("SELECT COUNT(*) FROM db_test WHERE flag = :f", ['f' => true]));
		$this->assertSame('1', Db::getOne("SELECT COUNT(*) FROM db_test WHERE flag IS NULL"));
		$this->assertSame('1', Db::getOne("SELECT flag FROM db_test WHERE name = :n", ['n' => 'a']));
		$this->assertSame('0', Db::getOne("SELECT flag FROM db_test WHERE name = :n", ['n' => 'b']), 'a false boolean is not mistaken for "no row"');
		$this->assertNull(Db::getOne("SELECT flag FROM db_test WHERE name = :n", ['n' => 'c']));
		$this->assertNull(Db::getOne("SELECT flag FROM db_test WHERE name = :n", ['n' => 'zzz']));
	}

	/** @test */
	public function fetch_helpers()
	{
		$this->assertSame([], Db::getRowAssoc("SELECT * FROM db_test WHERE name = 'zzz'"));
		$row = Db::getRowAssoc("SELECT * FROM db_test WHERE name = :n", ['n' => 'a']);
		$this->assertSame('a', $row['name']);
		$this->assertNull($row['note'], "'' is fetched as null by default (NULL_EMPTY_STRING)");
		$this->assertSame('x', Db::getRowAssoc("SELECT note FROM db_test WHERE name = 'c'")['note']);

		$this->assertCount(3, Db::getAllAssoc("SELECT * FROM db_test"));
		$this->assertSame('a', Db::getRowNum("SELECT name FROM db_test ORDER BY name")[0]);
		$this->assertSame([], Db::getRowNum("SELECT name FROM db_test WHERE 1 = 0"));
		$this->assertCount(3, Db::getAllNum("SELECT name FROM db_test"));
		$this->assertSame(['a'], Db::getRow("SELECT name FROM db_test ORDER BY name", [], \PDO::FETCH_NUM));
		$this->assertSame('a', Db::getRowObject("SELECT name FROM db_test ORDER BY name")->name);
		$this->assertNull(Db::getRowObject("SELECT name FROM db_test WHERE 1 = 0"));
		$this->assertSame(['a', 'b', 'c'], Db::getColumn("SELECT name FROM db_test ORDER BY name"));
		$pairs = Db::getPairs("SELECT name, amount FROM db_test ORDER BY name");
		$this->assertSame(['a', 'b', 'c'], array_keys($pairs));
		$this->assertEquals(2.25, $pairs['b']);
		$this->assertNull(Db::getOne("SELECT NULL"));
	}

	/** @test */
	public function execute_returns_affected_rows_and_run_returns_nothing()
	{
		$this->assertSame(3, Db::getInstance()->execute("UPDATE db_test SET note = 'u'"));
		Db::run("DELETE FROM db_test WHERE name = 'c'");
		$this->assertSame('2', Db::getOne("SELECT COUNT(*) FROM db_test"));
		$stmt = Db::getInstance()->prepare("SELECT COUNT(*) FROM db_test");
		$stmt->execute();
		$this->assertSame(2, (int)$stmt->fetchColumn());
		$this->assertSame("it's", Db::getOne("SELECT " . Db::escape("it's") . " AS v"), 'escape() quotes for the driver');
	}

	// ── errors ──

	/** @test */
	public function failures_are_recorded_and_the_driver_exception_is_rethrown()
	{
		$db = Db::getInstance();
		try {
			$db->statement("SELECT * FROM no_such_table_xyz");
			$this->fail('expected PDOException');
		} catch (\PDOException $ex) {
			$this->assertNotInstanceOf(DbConnectException::class, $ex);
		}
		$this->assertTrue($db->hasError());
		$this->assertStringContainsString('no_such_table_xyz', (string)$db->getErrorMessage());
		$this->assertIsString($db->getErrorTraceString());
		$this->assertNotEmpty($db->getErrorTrace());
		$this->assertSame("SELECT * FROM no_such_table_xyz", $db->getSql());
		$this->assertSame('3', Db::getOne("SELECT COUNT(*) FROM db_test"), 'the connection is still usable');
		$db->clearError();
		$this->assertFalse($db->hasError());
	}

	/** @test */
	public function sql_error_logger_receives_every_failed_statement()
	{
		$db = Db::getInstance();
		$log = new DbTestErrorLog('db_test');
		$db->setSqlErrorLogger($log);
		try {
			Db::getAllAssoc("SELECT nope FROM db_test");
		} catch (\PDOException $ex) {
		}
		$this->assertCount(1, $log->entries);
		$this->assertSame("SELECT nope FROM db_test", $log->entries[0]['sql']);
		$this->assertStringContainsString('nope', $log->entries[0]['message']);
		$this->assertMatchesRegularExpression('/^\d+$/', $log->entries[0]['line']);
		$this->assertNotEmpty($log->entries[0]['file']);
	}

	/** @test */
	public function sql_error_logger_with_a_missing_table_is_switched_off_without_hiding_the_error()
	{
		$db = Db::getInstance();
		$log = new DbTestErrorLog('no_such_log_table');
		$memory = new DbTestMemoryLogger();
		$db->setSqlErrorLogger($log);
		$db->setLogger($memory);
		for ($i = 0; $i < 2; $i++) {
			try {
				$db->execute("UPDATE no_such_table_xyz SET a = 1");
				$this->fail('expected PDOException');
			} catch (\PDOException $ex) {
				$this->assertStringContainsString('no_such_table_xyz', $ex->getMessage(), 'the original error is what the caller sees');
			}
		}
		$this->assertSame([], $log->entries);
		$warnings = array_filter($memory->records, fn(array $r) => $r[0] === 'warning');
		$this->assertCount(1, $warnings, 'reported once, then quiet');
		$this->assertStringContainsString('no_such_log_table', reset($warnings)[1]);
		$this->assertTrue($db->tableExists('db_test'));
		$this->assertFalse($db->tableExists('no_such_log_table'));
	}

	/** @test */
	public function a_failing_logger_never_breaks_the_caller()
	{
		$db = Db::getInstance();
		$log = new DbTestErrorLog('db_test');
		$log->throwOnStore = true;
		$db->setSqlErrorLogger($log);
		try {
			$db->query("SELECT * FROM no_such_table_xyz");
			$this->fail('expected PDOException');
		} catch (\PDOException $ex) {
			$this->assertStringContainsString('no_such_table_xyz', $ex->getMessage());
		}
	}

	/** @test */
	public function psr_logger_sees_statements_and_failures()
	{
		$db = Db::getInstance();
		$memory = new DbTestMemoryLogger();
		$db->setLogger($memory);
		Db::getOne("SELECT COUNT(*) FROM db_test");
		$debug = array_values(array_filter($memory->records, fn(array $r) => $r[0] === 'debug'));
		$this->assertCount(1, $debug);
		$this->assertSame("SELECT COUNT(*) FROM db_test", $debug[0][2]['sql']);
		$this->assertIsFloat($debug[0][2]['time']);
		try {
			$db->query("SELECT * FROM no_such_table_xyz");
		} catch (\PDOException $ex) {
		}
		$errors = array_values(array_filter($memory->records, fn(array $r) => $r[0] === 'error'));
		$this->assertCount(1, $errors);
		$this->assertSame("SELECT * FROM no_such_table_xyz", $errors[0][2]['sql']);
	}

	// ── engine, timing, connection ──

	/** @test */
	public function driver_and_engine_information()
	{
		$db = Db::getInstance();
		$expectedDriver = self::$engine === 'postgres' ? 'pgsql' : 'mysql';
		$this->assertSame($expectedDriver, $db->getDriverName());
		$this->assertSame($expectedDriver, $db->getAttribute(\PDO::ATTR_DRIVER_NAME));
		$this->assertSame(self::$engine === 'postgres', $db->isPostgres());
		$this->assertSame(self::$engine !== 'postgres', $db->isMysql());
		$this->assertSame(self::$engine === 'postgres' ? Identifier::POSTGRES : Identifier::MYSQL, $db->getEngine());
		$this->assertNotEmpty($db->getServerVersion());
		$this->assertStringStartsWith($expectedDriver . ':', $db->getDsn());
	}

	/** @test */
	public function query_count_time_and_log()
	{
		$db = Db::getInstance();
		$before = $db->getQueryCount();
		$totalBefore = $db->getTotalExecTime();
		$db->enableQueryLog();
		Db::getOne("SELECT COUNT(*) FROM db_test WHERE name = :n", ['n' => 'a']);
		$this->assertSame($before + 1, $db->getQueryCount());
		$this->assertGreaterThanOrEqual($totalBefore, $db->getTotalExecTime());
		$this->assertGreaterThanOrEqual(0.0, $db->getExecTime());
		$log = $db->getQueryLog();
		$this->assertCount(1, $log);
		$this->assertSame("SELECT COUNT(*) FROM db_test WHERE name = :n", $log[0]['sql']);
		$this->assertSame(['n' => 'a'], $log[0]['params']);
		$db->clearQueryLog();
		$this->assertSame([], $db->getQueryLog());
	}

	/** @test */
	public function ping_disconnect_and_reconnect()
	{
		$db = Db::getInstance();
		$this->assertTrue($db->ping());
		$db->disconnect();
		$this->assertFalse($db->isConnected());
		$this->assertFalse($db->ping());
		$this->assertSame('3', Db::getOne("SELECT COUNT(*) FROM db_test"), 'a statement reopens the connection');
		$this->assertTrue($db->isConnected());
		$this->assertInstanceOf(\PDO::class, $db->reconnect());
		$this->assertTrue($db->ping());
	}

	/** @test */
	public function transactions_on_the_connection()
	{
		$db = Db::getInstance();
		$db->transaction(function (Db $inner) use ($db) {
			$this->assertSame($db, $inner);
			$this->assertTrue($inner->inTransaction());
			$inner->statement("INSERT INTO db_test (name) VALUES (?)", ['d']);
		});
		$this->assertFalse($db->inTransaction());
		$this->assertSame('4', Db::getOne("SELECT COUNT(*) FROM db_test"));

		try {
			$db->transaction(function (Db $inner) {
				$inner->statement("INSERT INTO db_test (name) VALUES (?)", ['e']);
				throw new \RuntimeException('abort');
			});
		} catch (\RuntimeException $ex) {
		}
		$this->assertFalse($db->inTransaction());
		$this->assertSame('4', Db::getOne("SELECT COUNT(*) FROM db_test"));
	}

	/** @test */
	public function a_second_named_connection_comes_from_its_own_settings()
	{
		$settings = Settings::getInstance();
		foreach (['hostname', 'database_name', 'username', 'password', 'port', 'engine'] as $key) {
			$settings->setProperty('database.second.' . $key, $settings->getProperty('database.' . $key));
		}
		$second = Db::getInstance('second');
		$this->assertNotSame(Db::getInstance(), $second);
		$this->assertTrue($second->isConnected());
		$this->assertSame('3', $second->fetchOne("SELECT COUNT(*) FROM db_test"));
		$this->assertCount(3, $second->fetchAllAssoc("SELECT * FROM db_test"));
		$this->assertSame(['a', 'b', 'c'], $second->fetchColumn("SELECT name FROM db_test ORDER BY name"));
		Db::resetInstance('second');
		$this->assertFalse(Db::hasInstance('second'));
		$this->assertFalse($second->isConnected());
	}

	/** @test */
	public function a_failed_connection_is_a_db_connect_exception_and_is_not_cached()
	{
		$settings = Settings::getInstance();
		foreach (['hostname', 'database_name', 'port', 'engine'] as $key) {
			$settings->setProperty('database.broken.' . $key, $settings->getProperty('database.' . $key));
		}
		$settings->setProperty('database.broken.username', 'nobody_laf_xyz');
		$settings->setProperty('database.broken.password', 'wrong');
		try {
			Db::getInstance('broken');
			$this->fail('expected DbConnectException');
		} catch (DbConnectException $ex) {
			$this->assertStringContainsString('Database connection failed', $ex->getMessage());
			$this->assertInstanceOf(\PDOException::class, $ex->getPrevious());
		}
		$this->assertFalse(Db::hasInstance('broken'));

		$db = Db::fromSettings('database.broken');
		try {
			$db->connect();
			$this->fail('expected DbConnectException');
		} catch (DbConnectException $ex) {
		}
		$this->assertTrue($db->hasError());
		$this->assertFalse($db->isConnected());
	}

	/** @test */
	public function session_timezone_and_init_commands_run_after_connecting()
	{
		$db = Db::fromSettings();
		if (self::$engine === 'postgres') {
			$db->setTimezone('UTC')->setSchema('public')->setInitCommands(["SET application_name = 'laf_db_test'"]);
			$db->connect();
			$this->assertSame('UTC', $db->fetchOne("SHOW TIME ZONE"));
			$this->assertStringContainsString('public', (string)$db->fetchOne("SHOW search_path"));
			$this->assertSame('laf_db_test', $db->fetchOne("SHOW application_name"));
		} else {
			$db->setTimezone('+00:00')->setInitCommands(["SET @laf_db_test = 7"]);
			$db->connect();
			$this->assertSame('+00:00', $db->fetchOne("SELECT @@session.time_zone"));
			$this->assertSame('7', $db->fetchOne("SELECT @laf_db_test"));
		}
		$db->disconnect();
	}

	/** @test */
	public function empty_strings_as_null_can_be_switched_off_per_connection()
	{
		$this->assertNull(Db::getOne("SELECT '' AS v"), 'the default keeps the historic behaviour');
		$db = Db::fromSettings()->setEmptyStringAsNull(false);
		$db->connect();
		$this->assertSame('', $db->fetchOne("SELECT '' AS v"));
		$this->assertSame('', $db->fetchAssoc("SELECT note FROM db_test WHERE name = 'a'")['note']);
		$db->disconnect();
	}
}

/** In-memory SqlErrorLoggerInterface: what the apps' SqlError model does, without a table write. */
class DbTestErrorLog implements SqlErrorLoggerInterface
{
	public array $entries = [];
	public bool $throwOnStore = false;
	private string $tableName;
	private ?string $sql = null;
	private ?string $message = null;
	private ?string $file = null;
	private ?string $line = null;
	private ?string $trace = null;
	private ?string $custom = null;

	public function __construct(string $tableName)
	{
		$this->tableName = $tableName;
	}

	public function setSqlQuery(?string $value): void { $this->sql = $value; }
	public function setErrorMessage(?string $value): void { $this->message = $value; }
	public function setFile(?string $value): void { $this->file = $value; }
	public function setLineNumber(?string $value): void { $this->line = $value; }
	public function setTraceAsString(?string $value): void { $this->trace = $value; }
	public function setCustomMessage(?string $value): void { $this->custom = $value; }
	public function getSqlQuery(): ?string { return $this->sql; }
	public function getErrorMessage(): ?string { return $this->message; }
	public function getFile(): ?string { return $this->file; }
	public function getLineNumber(): ?string { return $this->line; }
	public function getTraceAsString(): ?string { return $this->trace; }
	public function getCustomMessage(): ?string { return $this->custom; }
	public function getTableName(): string { return $this->tableName; }

	public function storeLogEntry(): bool
	{
		if ($this->throwOnStore) {
			throw new \RuntimeException('log table is broken');
		}
		$this->entries[] = ['sql' => $this->sql, 'message' => $this->message, 'file' => $this->file, 'line' => $this->line, 'trace' => $this->trace];
		return true;
	}
}

/** PSR-3 logger that keeps its records. */
class DbTestMemoryLogger extends \Psr\Log\AbstractLogger
{
	public array $records = [];

	public function log($level, $message, array $context = []): void
	{
		$this->records[] = [(string)$level, (string)$message, $context];
	}
}
