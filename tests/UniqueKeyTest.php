<?php

declare(strict_types=1);

use Laf\Database\Table;
use Laf\Exception\DatabaseException;
use LafShell\M2mGroup;
use LafShell\M2mPersonGroup;
use PHPUnit\Framework\TestCase;

/**
 * Unique keys over several columns (Table::addUniqueKey()) and the mapping of a database unique
 * violation back to its columns (BaseObject::uniqueViolationFieldNames()), without a database: the
 * messages are the drivers' real wording.
 */
class UniqueKeyTest extends TestCase
{
    /** @test */
    public function table_keeps_unique_keys_by_name(): void
    {
        $table = new Table('people');
        $this->assertSame([], $table->getUniqueKeys());

        $table->addUniqueKey('people_name_unique', ['first_name', 'last_name', 'first_name']);
        $this->assertTrue($table->hasUniqueKey('people_name_unique'));
        $this->assertSame(['people_name_unique' => ['first_name', 'last_name']], $table->getUniqueKeys(), 'columns kept once, in order');

        $table->removeUniqueKey('people_name_unique');
        $this->assertFalse($table->hasUniqueKey('people_name_unique'));

        $this->expectException(\InvalidArgumentException::class);
        $table->addUniqueKey('empty', []);
    }

    /** @test */
    public function a_key_with_an_empty_column_never_counts_as_a_duplicate(): void
    {
        $link = self::link();
        $link->getPersonIdFld()->setValue(1); // group_id empty: NULL never collides, and no query runs
        $this->assertSame([], $link->duplicateUniqueKeys());
    }

    /** @test */
    public function postgresql_key_detail_names_the_columns(): void
    {
        $ex = self::pdoError('23505', 7, "SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"m2m_person_group_unique\"\nDETAIL:  Key (person_id, group_id)=(1, 2) already exists.");
        $this->assertSame(['person_id', 'group_id'], self::link()->uniqueViolationFieldNames($ex));
    }

    /** @test */
    public function postgresql_expression_index_names_the_columns_it_uses(): void
    {
        $ex = self::pdoError('23505', 7, "SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"m2m_group_lower_name\"\nDETAIL:  Key (lower(name::text))=(admins) already exists.");
        $this->assertSame(['name'], (new M2mGroup())->uniqueViolationFieldNames($ex));
    }

    /** @test */
    public function mysql_key_name_is_looked_up_in_the_model(): void
    {
        $link = self::link();
        $mariadb = self::pdoError('23000', 1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2' for key 'm2m_person_group_unique'");
        $mysql8 = self::pdoError('23000', 1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2' for key 'm2m_person_group.m2m_person_group_unique'");
        $primary = self::pdoError('23000', 1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '5' for key 'PRIMARY'");

        $this->assertSame(['person_id', 'group_id'], $link->uniqueViolationFieldNames($mariadb));
        $this->assertSame(['person_id', 'group_id'], $link->uniqueViolationFieldNames($mysql8), 'MySQL 8 prefixes the table');
        $this->assertSame(['id'], $link->uniqueViolationFieldNames($primary));
    }

    /** @test */
    public function other_statement_failures_name_no_columns(): void
    {
        $foreignKey = self::pdoError('23000', 1452, 'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails');
        $this->assertSame([], self::link()->uniqueViolationFieldNames($foreignKey));
    }

    /** The linker model with its unique key, as the generator writes it */
    private static function link(): M2mPersonGroup
    {
        $link = new M2mPersonGroup();
        if (!$link->getTable()->hasUniqueKey('m2m_person_group_unique')) {
            $link->getTable()->addUniqueKey('m2m_person_group_unique', ['person_id', 'group_id']);
        }
        return $link;
    }

    private static function pdoError(string $state, int $code, string $message): DatabaseException
    {
        $pdo = new \PDOException($message);
        $pdo->errorInfo = [$state, $code, $message];
        return DatabaseException::fromPdo($pdo, 'INSERT ...');
    }
}
