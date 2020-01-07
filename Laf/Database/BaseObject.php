<?php

namespace Laf\Database;

use Laf\Exception;
use Laf\Database\Field\Field;
use Laf\Exception\MissingFieldValueException;
use Laf\Exception\UniqueFieldDuplicateValueException;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Grid\SimpleTable;
use Laf\Util\Settings;
use Laf\Util\UrlParser;

class BaseObject
{
	/**
	 * @var Table $table
	 */
	private $table;

	/**
	 * @var int $recordId
	 */
	private $recordId;

	/**
	 * @var string $selectSql
	 */
	private $selectSql;

	/**
	 * @var string $insertSql
	 */
	private $insertSql;

	/**
	 * @var string $updateSql
	 */
	private $updateSql;

	/**
	 * @var string $deleteSql
	 */
	private $deleteSql;

	/**
	 * @var int $affectedRows
	 */
	private $affectedRows = null;

	/**
	 * @var \stdClass $logger
	 */
	private $logger = null;

	/**
	 * @var bool
	 */
	private $recordSelected = false;

	/**
	 * BaseObject constructor.
	 * @param int $id
	 */
	public function __construct($id = null)
	{
		$this->recordId = $id;
	}

	/**
	 * Find one row by using the first result
	 * @param array $keyValuePairs
	 * @return static
	 * @throws \Exception
	 */
	public static function bOfindOne(array $keyValuePairs): ?BaseObject
	{
		$object = new static();
		$params = [];

		foreach ($keyValuePairs as $fieldName => $fieldValue) {
			if (preg_match('/[^a-zA-Z_\-0-9]/', $fieldName)) {
				return null;
			}
			if (!$object->getTable()->hasField($fieldName)) {
				return null;
			}
			$params[$fieldName] = $fieldValue;
		}

		$filters = [];
		foreach ($params as $k => $v) {
			$filters[] = $k . ' = :' . $k;
		}
		$sql = "SELECT {$object->getTable()->getPrimaryKey()->getFirstField()->getName()} FROM {$object->getTable()->getName()} WHERE " . join(' AND ', $filters);
		$db = Db::getInstance();
		$stmt = $db->prepare($sql);
		foreach ($params as $fieldK => $fieldV) {
			$stmt->bindValue(':' . $fieldK, $fieldV);
		}
		$return = [];
		if ($stmt->execute()) {
			$res = $stmt->fetch(\PDO::FETCH_NUM);
			if (is_array($res) && count($res) > 0) {
				return new static($res[0]);
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * @param array $keyValuePairs
	 * @return static[]
	 * @throws \Exception
	 */
	public static function bOfind(array $keyValuePairs)
	{
		$object = new static();
		$params = [];

		foreach ($keyValuePairs as $fieldName => $fieldValue) {
			if (preg_match('/[^a-zA-Z_\-0-9]/', $fieldName)) {
				return null;
			}
			if (!$object->getTable()->hasField($fieldName)) {
				return null;
			}
			$params[$fieldName] = $fieldValue;
		}


		$filters = [];
		foreach ($params as $k => $v) {
			$filters[] = $k . ' = :' . $k;
		}
		$sql = "SELECT {$object->getTable()->getPrimaryKey()->getFirstField()->getName()} FROM {$object->getTable()->getName()} WHERE " . join(' AND ', $filters);
		$db = Db::getInstance();
		$stmt = $db->prepare($sql);
		foreach ($params as $fieldK => $fieldV) {
			$stmt->bindValue(':' . $fieldK, $fieldV);
		}
		$return = [];
		if ($stmt->execute()) {
			$res = $stmt->fetchAll(\PDO::FETCH_NUM);
			if (is_array($res) && count($res) > 0) {
				foreach ($res as $r) {
					$return[] = new static($r[0]);
				}

			} else {
				[];
			}
		} else {
			[];
		}
		return $return;
	}

	/**
	 * Returns Table Ref
	 * @return Table
	 */
	public function getTable(): Table
	{
		$this->addLoggerDebug(__METHOD__, [$this->table->getName()]);
		return $this->table;
	}

	/**
	 * Set Table object
	 * @param Table $table
	 */
	public function setTable(Table $table)
	{
		$this->addLoggerDebug(__METHOD__, [$table->getName()]);
		$this->table = $table;
	}

	/**
	 * Add Debug message
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerDebug($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->debug($message, $context);
		}
	}

	/**
	 * @param $fieldName
	 * @param null $value
	 * @return bool
	 */
	public function setFieldValueRaw($fieldName, $value = null)
	{
		$this->addLoggerDebug(__METHOD__, [$fieldName, $value]);
		if ($this->getTable()->hasField($fieldName)) {
			$field = $this->getTable()->getField($fieldName);
			try {
				$field->setValueRaw($value);
			} catch (\Exception $ex) {
				$this->addLoggerError("Error while setting Raw value", [$fieldName, $value]);
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerError($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->error($message, $context);
		}
	}

	/**
	 * @param $fieldName
	 * @param null $value
	 * @return bool
	 */
	public function setFieldValueHTML($fieldName, $value = null)
	{
		$this->addLoggerDebug(__METHOD__, [$fieldName, $value]);
		if ($this->getTable()->hasField($fieldName)) {
			$field = $this->getTable()->getField($fieldName);
			try {
				$field->setValueHTML($value);
			} catch (\Exception $ex) {
				$this->addLoggerError("Error while setting HTML value", [$fieldName, $value]);
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Reset the object and clear all loaded data
	 * @return BaseObject $this
	 */
	public function reset()
	{
		$this->addLoggerDebug("Resetting object data", [__METHOD__]);
		$this->setTable(new Table($this->getTable()->getName()));
		return $this;
	}

	/**
	 * Soft delete a record
	 * Set deleted property to 1
	 * @return bool
	 * @throws Exception\InvalidForeignKeyValue
	 */
	public function softDelete()
	{
		$this->addLoggerDebug(__METHOD__, [$this->getRecordId()]);
		if (!$this->getTable()->hasField('deleted')) {
			$this->addLoggerError("Soft delete method failed: No 'deleted' property found", []);
			return false;
		}

		$this->reload();
		$this->setFieldValue('deleted', 1);
		$this->store();
		return true;
	}

	/**
	 * Get Record Id
	 * @return int
	 */
	public function getRecordId()
	{
		$this->addLoggerDebug(__METHOD__, [$this->recordId]);
		return $this->recordId;
	}

	/**
	 * Set Db Record id
	 * @param mixed $recordId
	 * @return bool
	 */
	public function setRecordId($recordId): bool
	{
		$this->addLoggerDebug(__METHOD__, [$recordId]);
		if (filter_var($recordId, FILTER_VALIDATE_INT) && $this->getTable()->getPrimaryKey()->getFirstField()->isAutoIncrement()) {
			$this->recordId = $recordId;
			return true;
		}
		if ($recordId != '') {
			$this->recordId = $recordId;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Reloads the data from the database into the object
	 * @alias select()
	 * @return bool
	 */
	public function reload(): bool
	{
		$this->addLoggerDebug("Reloading object data", [__METHOD__, $this->getRecordId()]);
		return $this->select($this->getRecordId());
	}

	/**
	 * Select the record with the given id and retreive the data from the database
	 * @param int $id
	 * @return bool
	 */
	public function select($id)
	{
		$this->recordId = $id;
		$this->addLoggerDebug(__METHOD__, [$this->getRecordId()]);

		$idFieldName = $this->getTable()->getPrimaryKey()->getFirstField()->getName();

		$this->selectSql = "SELECT * FROM `{$this->getTable()->getName()}` WHERE `{$idFieldName}` = :recordId LIMIT 0,1;";
		$this->addLoggerDebug("SELECT SQL", [$this->selectSql]);
		$this->addLoggerDebug("SELECT SQL Params", [$this->getRecordId()]);

		$db = Db::getInstance();
		$stmt = $db->prepare($this->selectSql);
		$this->addLoggerDebug("Running query on DB", ['host' => $db->getHostName(), 'db' => $db->getDatabase()]);
		$ok = $stmt->execute(['recordId' => $this->getRecordId()]);
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$this->addLoggerDebug("SELECT SQL Result Count", [@count($results)]);

		if (@count($results) == 0) {
			$this->addLoggerDebug("SELECT Sql no results. Returning false");
			return false;
		}

		$this->setRecordSelected(true);

		if (isset($results[0][$idFieldName])) {
			foreach ($results[0] as $key => $value) {
				if ($this->getTable()->hasField($key)) {
					$this->getTable()->getField($key)->loadValueFromDb($value);
					$this->addLoggerDebug("Load field from Db", [$key, $value]);
				}
			}
		}
		return true;
	}

	/**
	 * Set Field value
	 * @param $fieldName
	 * @param $value
	 * @return bool
	 * @throws Exception\InvalidForeignKeyValue
	 */
	public function setFieldValue($fieldName, $value = null)
	{
		$this->addLoggerDebug(__METHOD__, [$fieldName, $value]);
		if ($this->getTable()->hasField($fieldName)) {
			$field = $this->getTable()->getField($fieldName);

			if ($field->isForeignKey()) {
				if (!$this->getTable()->getForeignKey($fieldName)->isValidValue($value)) {
					throw new Exception\InvalidForeignKeyValue("Foreign key not found " . $fieldName);
				}
			}

			$field->setValue($value);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Stores the data in the database
	 * if primary key field exists, it will update it, otherwise it will insert it
	 * @return bool
	 * @throws \Exception
	 */
	public function store()
	{
		if (!is_numeric($this->getTable()->getPrimaryKey()->getFirstField()->getValue())) {
			return $this->insert();
		}
		return $this->update();
	}

	/**
	 * Creates a new record in the database using the values in the object
	 * @return bool
	 * @throws \PDOException
	 * @throws \Exception
	 * @throws MissingFieldValueException
	 */
	public function insert()
	{
		$settings = Settings::getInstance();
		$personClass = '\\' . $settings->getProperty('project.package_name') . '\\Person';

		$this->addLoggerDebug(__METHOD__);
		$this->checkFieldsForMissingRequiredValues();
		$this->checkUniqueFieldsForDuplicateValues();

		if ($this->getTable()->hasField('created_on') && mb_strlen($this->getTable()->getField('created_on')->getValue()) < 1) {
			$this->setFieldValue('created_on', date('Y-m-d H:i:s'));
		}
		if ($this->getTable()->hasField('created_by') && mb_strlen($this->getTable()->getField('created_by')->getValue()) < 1) {
			$this->setFieldValue('created_by', $personClass::getLoggedUserId());
		}

		$this->insertSql = "INSERT INTO `{$this->getTable()->getName()}` (";
		$prepareColumns = $prepareValues = $executeValues = [];
		foreach ($this->getTable()->getFields() as $field) {
			if ($field->isPrimaryKey()) {
				if (!$field->isAutoIncrement()) {
					$prepareColumns[] = "`{$field->getName()}`";
					$prepareValues[] = ":{$field->getName()}";

					if (((string)$field->getValueForDbInsert()) != '') {
						$executeValues[':' . $field->getName()] = $field->getValueForDbInsert();
					} else {
						$executeValues[':' . $field->getName()] = \Laf\Util\Util::uuid();
					}
				}
			} else {
				$prepareColumns[] = "`{$field->getName()}`";
				$prepareValues[] = ":{$field->getName()}";
				$executeValues[':' . $field->getName()] = $field->getValueForDbInsert();
			}
		}

		if (count($prepareColumns) == 0) {
			$this->addLoggerDebug("No fields specified for the insert");
			return false;
		}

		$this->insertSql .= "\n\t  ";
		$this->insertSql .= join("\n\t, ", $prepareColumns) . "\n)";
		$this->insertSql .= "\nVALUES(\n\t  " . join("\n\t,", $prepareValues) . "\n); ";

		$this->addLoggerDebug("INSERT SQL", $this->insertSql);
		$this->addLoggerDebug("INSERT SQL Params", [json_encode($executeValues)]);

		try {
			$db = Db::getInstance();
			$stmt = $db->prepare($this->insertSql);
			$this->addLoggerDebug("Running query on DB", ['host' => $db->getHostName(), 'db' => $db->getDatabase()]);
			$count = $stmt->execute($executeValues);
		} catch (\PDOException $ex) {
			$this->addLoggerError("INSERT SQL failed", [$this->insertSql, json_encode($executeValues)]);
			$this->addLoggerError("Error Message", [$ex->getMessage()]);
			$this->addLoggerDebug("Exception", [$ex->getTraceAsString()]);
			throw new \Exception($ex->getMessage());
		} catch (\Exception $ex) {
			$this->addLoggerError("INSERT SQL failed with an unknown Exception", [$this->insertSql, json_encode($executeValues)]);
			$this->addLoggerError("Error Message", [$ex->getMessage()]);
			$this->addLoggerDebug("Exception", [$ex->getTraceAsString()]);
			throw new \Exception($ex->getMessage());
		}

		if ($count === false) {
			$this->addLoggerError("INSERT SQL failed with an unknown Exception", [$this->insertSql, json_encode($executeValues)]);
			throw new \Exception("INSERT SQL failed with an unknown Exception");
		}

		$this->setAffectedRows($stmt->rowCount());
		/**
		 * Non-Auto auto-increment fields getInsertId() returns 0
		 */
		if ($db->getInsertId() === '0') {
			$this->setRecordId($this->getTable()->getPrimaryKey()->getFirstField()->getValue());
		} else {
			$this->setRecordId($db->getInsertId());
		}
		$this->addLoggerDebug("INSERT Id", [$this->getRecordId()]);
		$this->addLoggerDebug("INSERT SQL Affected Records", [$this->getAffectedRows()]);

		static::getTable()->getPrimaryKey()->getFirstField()->setValue($this->getRecordId());
		return true;
	}

	/**
	 * @param int $affectedRows
	 */
	private function setAffectedRows($affectedRows): void
	{
		$this->addLoggerDebug(__METHOD__, [$affectedRows]);
		$this->affectedRows = $affectedRows;
	}

	/**
	 * Returns the number of rows affected by an update or delete
	 * @return int
	 */
	public function getAffectedRows(): int
	{
		$this->addLoggerDebug(__METHOD__, [$this->getRecordId()]);
		return $this->affectedRows;
	}

	/**
	 * Updates the record in the database, updating all it's field
	 * @return bool
	 * @throws \PDOException
	 * @throws \Exception
	 * @throws MissingFieldValueException
	 */
	public function update()
	{
		$this->addLoggerDebug(__METHOD__, [$this->getRecordId()]);
		$this->checkFieldsForMissingRequiredValues();
		$this->checkUniqueFieldsForDuplicateValues();

		$settings = Settings::getInstance();
		$personClass = '\\' . $settings->getProperty('project.package_name') . '\\Person';

		if (!$this->isrecordSelected()) {
			$this->addLoggerDebug('No prior record selected to update. Returning false');
			return false;
		}

		if ($this->getTable()->hasField('updated_on')) {
			$this->setFieldValue('updated_on', date('Y-m-d H:i:s'));
		}
		if ($this->getTable()->hasField('updated_by')) {
			$this->setFieldValue('updated_by', $personClass::getLoggedUserId());
		}

		$this->updateSql = "UPDATE `{$this->getTable()->getName()}` ";
		$this->updateSql .= "\nSET ";
		$prepareColumns = $executeValues = [];
		foreach ($this->getTable()->getFields() as $field) {
			if ($field->hasChanged()) {
				$prepareColumns[] = "`{$field->getName()}`=:{$field->getName()}";
			}
		}

		if (count($prepareColumns) == 0) {
			$this->addLoggerDebug("No Fields to update, returning");
			return true;
		}

		$this->updateSql .= "\n\t  ";
		$this->updateSql .= join("\n\t, ", $prepareColumns);
		$this->updateSql .= "\nWHERE ";
		$this->updateSql .= "\n\t{$this->getTable()->getPrimaryKey()->getFirstField()->getName()} = :primaryKeyField\n;";

		try {
			$db = Db::getInstance();
			$stmt = $db->prepare($this->updateSql);

			foreach ($this->getTable()->getFields() as $field) {
				if ($field->hasChanged()) {

					$type = $field->getType()->getPdoType();
					if (mb_strlen($field->getValue()) == 0)
						$type = \PDO::PARAM_NULL;
					$stmt->bindValue(':' . $field->getName(), $field->getValueForDbInsert(), $type);
					$this->addLoggerDebug("Store bindValue", [$field->getName(), $field->getValueForDbInsert()]);
				}
			}
			$stmt->bindValue(':primaryKeyField', $this->getRecordId(), \PDO::PARAM_INT);
			$this->addLoggerDebug("Store bindValue", [':primaryKeyField', $this->getRecordId()]);

			$this->addLoggerDebug("UPDATE SQL", [$this->updateSql]);
			$this->addLoggerDebug("UPDATE SQL Params", [json_encode($stmt)]);
			$this->addLoggerDebug("Running query on DB:", [$db->getHostName(), $db->getDatabase()]);
			$count = $stmt->execute();
		} catch (\PDOException $ex) {
			$this->addLoggerError("INSERT SQL failed", [$this->updateSql, json_encode($executeValues)]);
			$this->addLoggerError("Error Message", [$ex->getMessage()]);
			$this->addLoggerDebug("Exception", [$ex->getTraceAsString()]);
			throw new \Exception($ex->getMessage());
		} catch (\Exception $ex) {
			$this->addLoggerError("INSERT SQL failed with an unknown Exception", [$this->updateSql, json_encode($executeValues)]);
			$this->addLoggerError("Error Message", [$ex->getMessage()]);
			$this->addLoggerDebug("Exception", [$ex->getTraceAsString()]);
			throw new \Exception($ex->getMessage());
		}

		if ($count === false) {
			$this->addLoggerError("UPDATE SQL failed", [$this->updateSql, json_encode($executeValues)]);
			throw new \Exception("UPDATE SQL failed");
		}

		$this->setAffectedRows($stmt->rowCount());
		$this->addLoggerDebug("UPDATE SQL Affected Records", [$this->getAffectedRows()]);
		return true;
	}

	/**
	 * @return bool
	 */
	public function isRecordSelected(): bool
	{
		return $this->recordSelected;
	}

	/**
	 * @param bool $recordSelected
	 * @return BaseObject
	 */
	public function setRecordSelected(bool $recordSelected): BaseObject
	{
		$this->recordSelected = $recordSelected;
		return $this;
	}

	/**
	 * Returns the field object ref
	 * @param $fieldName
	 * @return Field
	 */
	public function getField($fieldName)
	{
		$this->addLoggerDebug(__METHOD__, [$fieldName]);
		if ($this->getTable()->hasField($fieldName)) {
			return $this->getTable()->getField($fieldName);
		} else {
			$this->addLoggerError("Field doesn't exist", [$fieldName]);
			return null;
		}
	}

	/**
	 * Deletes the selected record from the database
	 * This action cannot be undone
	 * @return bool
	 */
	public function hardDelete()
	{
		$this->addLoggerDebug(__METHOD__, [$this->getRecordId()]);

		if (!$this->isrecordSelected()) {
			$this->addLoggerDebug('No prior record selected to delete. Returning false');
			return false;
		}

		if (!filter_var($this->getRecordId(), FILTER_VALIDATE_INT)) {
			$this->addLoggerError("Delete method failed: Invalid record id provided", [$this->getRecordId()]);
			return false;
		}

		$this->deleteSql = "DELETE FROM `{$this->getTable()->getName()}` ";
		$this->deleteSql .= "\nWHERE `{$this->getTable()->getPrimaryKey()->getFirstField()->getName()}`=:primaryKeyField;";
		$executeValues = [':primaryKeyField' => (int)$this->getRecordId()];

		$this->addLoggerDebug("DELETE SQL", [$this->deleteSql]);
		$this->addLoggerDebug("DELETE SQL PARAMS", [json_encode($executeValues)]);

		try {
			$db = Db::getInstance();
			$stmt = $db->prepare($this->deleteSql);
			$this->addLoggerDebug("Running query on DB", [$db->getHostName(), $db->getDatabase()]);
			$count = $stmt->execute($executeValues);
		} catch (\PDOException $ex) {
			$this->addLoggerError("INSERT SQL failed", [$this->deleteSql, json_encode($executeValues)]);
			$this->addLoggerError("Error Message", [$ex->getMessage()]);
			$this->addLoggerDebug("Exception", [$ex->getTraceAsString()]);
			throw new \Exception($ex->getMessage());
		} catch (\Exception $ex) {
			$this->addLoggerError("INSERT SQL failed with an unknown Exception", [$this->deleteSql, json_encode($executeValues)]);
			$this->addLoggerError("Error Message", [$ex->getMessage()]);
			$this->addLoggerDebug("Exception", [$ex->getTraceAsString()]);
			throw new \Exception($ex->getMessage());
		}

		if ($count === false) {
			$this->addLoggerError("Delete method failed: Query:%s Params:%s", [$this->deleteSql, json_encode($executeValues)]);
			throw new \Exception("Delete method failed");
		}

		$this->setAffectedRows($stmt->rowCount());
		$this->addLoggerDebug("DELETE SQL Affected Records", [$this->getAffectedRows()]);

		return true;
	}

	/**
	 * Returns a reference to the logger object
	 * @return null
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * Set PSR3 compatible logger
	 * @param null $logger
	 */
	public function setLogger($logger)
	{
		$this->logger = $logger;
		$this->logger->debug("Loading Logger: " . get_class($logger));
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerInfo($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->info($message, $context);
		}
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerNotice($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->notice($message, $context);
		}
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerWarning($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->warning($message, $context);
		}
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerCritical($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->critical($message, $context);
		}
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerAlert($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->alert($message, $context);
		}
	}

	/**
	 * @param $message
	 * @param array $context
	 */
	public function addLoggerEmergency($message, $context = [])
	{
		if ($this->logger) {
			if (!is_array($context)) {
				$context = [$context];
			}
			$this->logger->emergency($message, $context);
		}
	}

	/**
	 * Reset record id
	 */
	public function __clone()
	{
		if ($this->getTable()->hasField('id')) {
			$this->setFieldValue('id', null);
		}
		$this->setRecordId(null);
	}

	/**
	 * Check if record exists and has an Int id
	 * @return bool
	 */
	public function recordExists()
	{
		if (!$this->getTable()->getPrimaryKey()->getFirstField())
			return false;

		if (is_numeric($this->getTable()->getPrimaryKey()->getFirstField()->getValue())) {
			return true;
		}

		if (trim($this->getTable()->getPrimaryKey()->getFirstField()->getValue()) != '') {
			return true;
		}
		return false;
	}

	/**
	 * Returns Field value
	 * @param $fieldName
	 * @return string
	 */
	public function getFieldValue($fieldName)
	{
		$this->addLoggerDebug(__METHOD__, [$fieldName]);
		if ($this->getTable()->hasField($fieldName)) {
			return $this->getTable()->getField($fieldName)->getValue();
		} else {
			$this->addLoggerError("Field doesn't exist", [$fieldName]);
			return null;
		}
	}

	/**
	 * @return SimpleTable
	 */
	public function getListAllSimpleTableObject()
	{
		$table = new SimpleTable();
		$parser = UrlParser::getInstance();
		$primaryKeyField = static::getTable()->getPrimaryKey()->getFirstField()->getName();
		$table->setSql(sprintf("
            SELECT * FROM `%s` 
        ", $this->returnLeafClass()->getTable()->getName()))
			->setRowsPerPage('10');
		$viewLink = new Link();

		$viewLinkURL = sprintf("?module=%s&submodule=%s&action=view&id={{$primaryKeyField}}", $parser->_getModule(), $parser->_getSubmodule());
		if ($parser->isUsePrettyUrl())
			$viewLinkURL = sprintf("/%s/%s/view/%s", $parser->_getModule(), $parser->_getSubmodule(), $primaryKeyField);

		$viewLink->setValue('')
			->setHref($viewLinkURL)
			->setIcon('fa fa-eye')
			->addCssClass('btn')
			->addCssClass('btn-outline-secondary')
			->addCssClass('btn-sm');

		$updateUrl = sprintf("?module=%s&submodule=%s&action=update&id={id}", $parser->_getModule(), $parser->_getSubmodule());
		if ($parser->isUsePrettyUrl()) {
			$updateUrl = sprintf("/%s/%s/update/{id}", $parser->_getModule(), $parser->_getSubmodule());
		}

		$updateLink = new Link();
		$updateLink->setValue('Update')
			->setHref($updateUrl)
			->setIcon('fa fa-edit');


		$deleteUrl = sprintf("?module=%s&submodule=%s&action=delete&id={id}", $parser->_getModule(), $parser->_getSubmodule());
		if ($parser->isUsePrettyUrl()) {
			$deleteUrl = sprintf("/%s/%s/delete/{id}", $parser->_getModule(), $parser->_getSubmodule());
		}
		$deleteLink = new Link();
		$deleteLink->setValue('Delete')
			->setHref($deleteUrl)
			->setIcon('fa fa-trash')
			->setConfirmationMessage('Are you sure you want to delete this item?\\nThis action cannot be undone!');


		$options = new Dropdown();
		$options->setIcon('fa fa-cogs')
			->addLink($updateLink)
			->addLink($deleteLink)
			->addCssClass('btn-outline-secondary')
			->addCssClass('btn-sm');


		$table->addActionButton($viewLink);
		$table->addActionButton($options);
		return $table;
	}

	/**
	 * Returns the lowest level class in the inheritance tree
	 * Used with late static binding to get the lowest level class
	 */
	protected function returnLeafClass()
	{
		return static::returnLeafClass();
	}

	/**
	 * @return Form
	 */
	public function getForm()
	{
		$form = new Form($this);
		$form->setComponents($this->getAllFieldsAsFormElements());
		foreach ($this->getTable()->getFields() as $field) {
			if ($field->isDocumentField()) {
				$form->setHasFiles(true);
			}
		}
		return $form;
	}

	/**
	 * Return all fields as form elements
	 * Should be calledfrom child objects
	 * @return FormElementInterface[]
	 *
	 */
	public function getAllFieldsAsFormElements(): array
	{
		return static::getAllFieldsAsFormElements();
	}

	/**
	 * Check if any field is required and has no value
	 * @throws MissingFieldValueException
	 */
	private function checkFieldsForMissingRequiredValues(): void
	{
		$msg = [];
		foreach ($this->getTable()->getFields() as $field) {
			if ($field->isRequired() && ((string)$field->getValueForDbInsert()) == '' && !$field->isPrimaryKey()) {
				$msg[] = sprintf("Field %s is required. Value provided is %s", $field->getName(), $field->getValue());
			}
		}
		if (count($msg) > 0) {
			throw new MissingFieldValueException(join('; ', $msg));
		}
	}

	/**
	 * * Check for any fields that are unique if the value already exists in the database
	 * @throws UniqueFieldDuplicateValueException
	 */
	private function checkUniqueFieldsForDuplicateValues(): void
	{
		#@TODO implement
		#throw new UniqueFieldDuplicateValueException();
	}

	/**
	 * @return bool
	 */
	public function canSoftDelete(): bool
	{
		return $this->getTable()->hasField('deleted');
	}

}
