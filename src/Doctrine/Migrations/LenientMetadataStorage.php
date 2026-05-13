<?php

declare(strict_types=1);

namespace App\Doctrine\Migrations;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;

use function array_change_key_case;
use function is_numeric;
use function strtolower;
use function uasort;

use const CASE_LOWER;

/**
 * Workaround for MariaDB/DBAL schema-diff false positives on the
 * doctrine_migration_versions table that can make Doctrine\Migrations think the
 * metadata storage is "not up to date" even right after syncing.
 *
 * This implementation keeps the same storage format but skips the strict
 * schema up-to-date check that triggers MetadataStorageError::notUpToDate().
 */
final class LenientMetadataStorage implements MetadataStorage
{
    private const TABLE_NAME = 'doctrine_migration_versions';

    private const COL_VERSION = 'version';
    private const COL_EXECUTED_AT = 'executed_at';
    private const COL_EXECUTION_TIME = 'execution_time';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function ensureInitialized(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // Try to detect if table exists via direct query (more reliable across drivers)
        try {
            $this->connection->fetchOne(sprintf('SELECT 1 FROM %s LIMIT 1', self::TABLE_NAME));
            return; // Table exists and is accessible
        } catch (\Throwable) {
            // Table doesn't exist or can't be accessed, try to create it
        }

        try {
            $table = new Table(self::TABLE_NAME);
            $table->addColumn(self::COL_VERSION, 'string', ['notnull' => true, 'length' => 191]);
            $table->addColumn(self::COL_EXECUTED_AT, 'datetime', ['notnull' => false]);
            $table->addColumn(self::COL_EXECUTION_TIME, 'integer', ['notnull' => false]);
            if (class_exists(PrimaryKeyConstraint::class)) {
                $constraint = PrimaryKeyConstraint::editor()
                    ->setColumnNames(UnqualifiedName::unquoted(self::COL_VERSION))
                    ->create();
                $table->addPrimaryKeyConstraint($constraint);
            } else {
                $table->setPrimaryKey([self::COL_VERSION]);
            }

            $schemaManager->createTable($table);
        } catch (\Throwable) {
            // Table may already exist (race condition or schema issue), ignore
        }
    }

    public function getExecutedMigrations(): ExecutedMigrationsList
    {
        $this->ensureInitialized();

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s', self::TABLE_NAME),
        );

        $migrations = [];

        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);

            $version = new Version((string) ($row[self::COL_VERSION] ?? ''));
            if ((string) $version === '') {
                continue;
            }

            $executedAt = $row[self::COL_EXECUTED_AT] ?? null;
            $executedAt = is_string($executedAt) && $executedAt !== ''
                ? new DateTimeImmutable($executedAt)
                : null;

            $executionTimeMs = $row[self::COL_EXECUTION_TIME] ?? null;
            $executionTime = is_numeric($executionTimeMs) ? ((float) $executionTimeMs / 1000.0) : null;

            $migration = new ExecutedMigration($version, $executedAt, $executionTime);
            $migrations[(string) $version] = $migration;
        }

        // Keep ordering stable and compatible with the default comparator.
        uasort(
            $migrations,
            static fn (ExecutedMigration $a, ExecutedMigration $b): int => strcmp((string) $a->getVersion(), (string) $b->getVersion()),
        );

        return new ExecutedMigrationsList($migrations);
    }

    public function complete(ExecutionResult $result): void
    {
        $this->ensureInitialized();

        if ($result->getDirection() === Direction::DOWN) {
            $this->connection->delete(self::TABLE_NAME, [
                self::COL_VERSION => (string) $result->getVersion(),
            ]);

            return;
        }

        $executedAt = $result->getExecutedAt() ?? new DateTimeImmutable();
        $executionTimeMs = $result->getTime() === null ? null : (int) round($result->getTime() * 1000);

        // Idempotent write (re-run safe).
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (%s, %s, %s) VALUES (?, ?, ?) ' .
                'ON DUPLICATE KEY UPDATE %s = VALUES(%s), %s = VALUES(%s)',
                self::TABLE_NAME,
                self::COL_VERSION,
                self::COL_EXECUTED_AT,
                self::COL_EXECUTION_TIME,
                self::COL_EXECUTED_AT,
                self::COL_EXECUTED_AT,
                self::COL_EXECUTION_TIME,
                self::COL_EXECUTION_TIME,
            ),
            [
                (string) $result->getVersion(),
                $executedAt->format('Y-m-d H:i:s'),
                $executionTimeMs,
            ],
        );
    }

    public function reset(): void
    {
        $this->ensureInitialized();

        $this->connection->executeStatement(sprintf('DELETE FROM %s WHERE 1 = 1', self::TABLE_NAME));
    }

    /** @return iterable<Query> */
    public function getSql(ExecutionResult $result): iterable
    {
        yield new Query('-- Version ' . (string) $result->getVersion() . ' update table metadata');

        if ($result->getDirection() === Direction::DOWN) {
            yield new Query(sprintf(
                'DELETE FROM %s WHERE %s = %s',
                self::TABLE_NAME,
                self::COL_VERSION,
                $this->connection->quote((string) $result->getVersion()),
            ));

            return;
        }

        yield new Query(sprintf(
            'INSERT INTO %s (%s, %s, %s) VALUES (%s, %s, 0)',
            self::TABLE_NAME,
            self::COL_VERSION,
            self::COL_EXECUTED_AT,
            self::COL_EXECUTION_TIME,
            $this->connection->quote((string) $result->getVersion()),
            $this->connection->quote((new DateTimeImmutable())->format('Y-m-d H:i:s')),
        ));
    }
}

