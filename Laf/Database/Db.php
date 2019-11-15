<?php

namespace Laf\Database;

use Laf\Util\Settings;

/**
 * Class Db
 */
class Db
{
	private $hostName;
	private $database;
	private $userName;
	private $password;
	private $connection;
	private $sql;
	private $time_start;
	private $time_end;
	private $error_message;
	private $error_trace_string;
	private $error_trace;
	private $has_error;
	private static $instance;

	/**
	 * Db constructor.
	 * @param $hostName
	 * @param $database
	 * @param $userName
	 * @param $password
	 */
	public function __construct($hostName = null, $database = null, $userName = null, $password = null)
	{
		$this->hostName = $hostName;
		$this->database = $database;
		$this->userName = $userName;
		$this->password = $password;
		$this->setHasError(false);
	}


	/**
	 * Get Instance
	 * @return Db
	 * @throws \Exception
	 */
	public static function getInstance(): Db
	{
		if (!is_a(self::$instance, 'Db')) {
			$settings = Settings::getInstance();
			self::$instance = new Db();
			self::$instance->hostName = $settings->getProperty('database.hostname');
			self::$instance->database = $settings->getProperty('database.database_name');
			self::$instance->userName = $settings->getProperty('database.username');
			self::$instance->password = $settings->getProperty('database.password');
			self::$instance->connect();
		}
		return self::$instance;
	}

	/**
	 * Connect
	 * @return \PDO
	 * @throws \Exception
	 */
	public function connect()
	{
		try {
			$this->startTimer();
			$this->connection = new \PDO("mysql:dbname={$this->getDatabase()};host={$this->getHostName()};charset=utf8mb4", $this->getUserName(), $this->getPassword());
			$this->getConnection()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this->getConnection()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
			$this->getConnection()->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
		} catch (\PDOException $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			$settings = Settings::getInstance();
			header('location:' . $settings->getProperty('homepage') . 'under_maintenance.html');
			return null;
		} catch (\Exception $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			$settings = Settings::getInstance();
			header('location:' . $settings->getProperty('homepage') . 'under_maintenance.html');
			die;
		} finally {
			$this->stopTimer();
		}
		return $this->getConnection();
	}

	/**
	 * Runs a query and returns the resultset
	 * @param $sql
	 * @return \PDOStatement
	 */
	public function query($sql): \PDOStatement
	{
		if (!is_a($this->getConnection(), 'PDO')) {
			return null;
		}

		$statement = null;
		$this->sql = $sql;
		try {
			$this->startTimer();
			$statement = $this->getConnection()->query($sql);
		} catch (\PDOException $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			return new \PDOStatement();
		} catch (\Exception $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			return new \PDOStatement();
		} finally {
			$this->stopTimer();
		}
		return $statement;
	}

	/**
	 * Execute a query and returns number of affected records
	 * @param $sql
	 * @return int number of rows affected
	 */
	public function execute($sql): int
	{
		if (!is_a($this->getConnection(), 'PDO')) {
			return false;
		}
		$count = null;
		$this->sql = $sql;
		try {
			$this->startTimer();
			$count = $this->getConnection()->exec($sql);
		} catch (\PDOException $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			return -1;
		} catch (\Exception $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			return -1;
		} finally {
			$this->stopTimer();
		}
		return $count;
	}

	/**
	 * @return \PDO
	 */
	public function getConnection()
	{
		return $this->connection;
	}

	/**
	 * @return mixed
	 */
	public function getHostName()
	{
		return $this->hostName;
	}

	/**
	 * @param mixed $hostName
	 */
	public function setHostName($hostName)
	{
		$this->hostName = $hostName;
	}

	/**
	 * @return mixed
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 * @param mixed $database
	 */
	public function setDatabase($database)
	{
		$this->database = $database;
	}

	/**
	 * @return mixed
	 */
	public function getUserName()
	{
		return $this->userName;
	}

	/**
	 * @param mixed $userName
	 */
	public function setUserName($userName)
	{
		$this->userName = $userName;
	}

	/**
	 * @return mixed
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * @param mixed $password
	 */
	public function setPassword($password)
	{
		$this->password = $password;
	}

	/**
	 * @return mixed
	 */
	public function getSql()
	{
		return $this->sql;
	}

	/**
	 * @param mixed $sql
	 */
	public function setSql($sql)
	{
		$this->sql = $sql;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return mixed
	 * @throws \Exception
	 */
	public static function getRowAssoc(string $sql, $params = [])
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		if ($row === false) {
			return [];
		} else {
			return $row;
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return mixed
	 * @throws \Exception
	 */
	public static function getAllAssoc(string $sql, $params = [])
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		if ($row === false) {
			return [];
		} else {
			return $row;
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return string[]
	 * @throws \Exception
	 */
	public static function getRowNum(string $sql, $params = []): array
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch(\PDO::FETCH_NUM);
		if ($row === false) {
			return [];
		} else {
			return $row;
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return string[]
	 * @throws \Exception
	 */
	public static function getAllNum(string $sql, $params = []): array
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetchAll(\PDO::FETCH_NUM);
		if ($row === false) {
			return [];
		} else {
			return $row;
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @param int $fetchType default is assoc
	 * @return string[]
	 * @throws \Exception
	 */
	public static function getRow(string $sql, $params = [], $fetchType = 2): array
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch($fetchType);
		if ($row === false) {
			return [];
		} else {
			return $row;
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @param string $classNamme
	 * @return \stdClass
	 * @throws \Exception
	 */
	public static function getRowObject(string $sql, $params = [], $classNamme = 'stdClass')
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$object = $stmt->fetchObject($classNamme);
		if ($object === false) {
			new \stdClass();
		} else {
			return $object;
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @param int $columnIndex default 0
	 * @return string
	 * @throws \Exception
	 */
	public static function getOne(string $sql, $params = [], $columnIndex = 0): string
	{
		$db = self::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$value = $stmt->fetchColumn($columnIndex);
		if ($value === false) {
			return null;
		} else {
			return $value;
		}
	}


	/**
	 * Convert Class name to table name
	 * Example: ServiceOrder -> service_order
	 * @param $className
	 * @return string
	 */
	public static function convertClassNameToTableName($className)
	{
		return strtolower(preg_replace('/([A-Z])/', '_$1', $className));
	}

	/**
	 * Convert table name to Class name
	 * Example: service_order -> ServiceOrder
	 * @param $tableName
	 * @return string
	 */
	public static function convertTableNameToClassName($tableName)
	{
		return str_replace('_', '', ucwords($tableName, '_'));
	}

	/**
	 * Generates a field-name-hash to be used publicly
	 * @param $fieldName
	 * @return string
	 */
	public static function encodeFieldNameToHash($fieldName)
	{
		return str_rot13($fieldName);
	}

	/**
	 * Returns Field Name from an encoded hash
	 * @param $hash
	 * @return mixed
	 */
	public static function getFieldNameFromHash($hash)
	{
		return str_rot13($hash);
	}


	/**
	 * @return mixed
	 */
	public function getTimeStart()
	{
		return $this->time_start;
	}

	/**
	 * @param mixed $time_start
	 */
	public function setTimeStart($time_start)
	{
		$this->time_start = $time_start;
	}

	/**
	 * @return mixed
	 */
	private function getTimeEnd()
	{
		return $this->time_end;
	}

	/**
	 * @param mixed $time_end
	 */
	private function setTimeEnd($time_end)
	{
		$this->time_end = $time_end;
	}

	/**
	 * Start query timer
	 */
	private function startTimer(): void
	{
		$this->setTimeStart(microtime(true));
	}

	/**
	 * Get Query Execution time
	 * @return float
	 */
	public function getExecTime(): float
	{
		if ($this->getTimeEnd() == 0) {
			$this->stopTimer();
		}
		return $this->getTimeEnd() - $this->getTimeStart();
	}

	/**
	 * End query timer
	 */
	private function stopTimer(): void
	{
		$this->setTimeEnd(microtime(true));
	}

	/**
	 * Quote sql param
	 * @param $string
	 * @return string
	 */
	public function quote($string): string
	{
		return $this->getConnection()->quote($string);
	}

	/**
	 * @return mixed
	 */
	public function getErrorMessage(): string
	{
		return $this->error_message;
	}

	/**
	 * @param mixed $error_message
	 */
	public function setErrorMessage($error_message): void
	{
		$this->error_message = $error_message;
	}

	/**
	 * @return mixed
	 */
	public function getErrorTraceString(): string
	{
		return $this->error_trace_string;
	}

	/**
	 * @param mixed $error_trace_string
	 */
	public function setErrorTraceString($error_trace_string): void
	{
		$this->error_trace_string = $error_trace_string;
	}

	/**
	 * @return mixed
	 */
	public function getErrorTrace(): array
	{
		return $this->error_trace;
	}

	/**
	 * @param mixed $error_trace
	 */
	public function setErrorTrace($error_trace): void
	{
		$this->error_trace = $error_trace;
	}

	/**
	 * @return mixed
	 */
	public function getHasError(): bool
	{
		return $this->has_error;
	}

	/**
	 * @param mixed $has_error
	 */
	public function setHasError($has_error)
	{
		$this->has_error = $has_error;
	}

	public function prepare($sql)
	{
		if (!is_a($this->getConnection(), 'PDO')) {
			return null;
		}

		$statement = null;
		$this->sql = $sql;
		try {
			$this->startTimer();
			$statement = $this->getConnection()->prepare($sql);
		} catch (\PDOException $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			return new \PDOStatement();
		} catch (\Exception $ex) {
			$this->setErrorMessage($ex->getMessage());
			$this->setErrorTrace($ex->getTrace());
			$this->setErrorTraceString($ex->getTrace());
			return new \PDOStatement();
		} finally {
			$this->stopTimer();
		}
		return $statement;
	}

	/**
	 * Get the ID of the new record after an insert
	 * @return string
	 */
	public function getInsertId()
	{
		return $this->getConnection()->lastInsertId();
	}

	/**
	 * @param $value
	 * @return false|string
	 * @throws \Exception
	 */
	public static function escape($value)
	{
		return self::getInstance()->getConnection()->quote($value);
	}

}