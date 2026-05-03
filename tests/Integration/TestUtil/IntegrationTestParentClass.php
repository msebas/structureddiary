<?php

namespace OCA\Tests\StructuredDiary\Integration\TestUtil;

use PHPUnit\Framework\TestCase;
use OC\DB\MigrationService;
use OCA\StructuredDiary\Db\TableNames;
use OC\DB\Connection;
use OCP\AppFramework\App;
use OCP\IDBConnection;

class IntegrationTestParentClass extends TestCase {

    protected static IDBConnection $db;
    protected static Connection $connection;
    protected static \Psr\Container\ContainerInterface $container;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $app = new App('structureddiary');
        self::$container = $app->getContainer();
        self::$db = self::$container->get(IDBConnection::class);
        self::$connection = \OC::$server->get(Connection::class);
    }

    protected function setUp(): void {
        parent::setUp();

        $this->resetDatabase();
        $this->createSchema();

    }

    protected function tearDown(): void {
        $this->resetDatabase();
        parent::tearDown();
    }

    protected function resetDatabase(): void {
        $this->assertIntegrationDatabaseOptIn();

        $schemaManager = self::$connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        foreach ([
                     TableNames::ANSWERS,
                     TableNames::QUESTIONS,
                     TableNames::ENTRIES,
                     TableNames::DIARY_SHARES,
                     TableNames::DIARIES,
                 ] as $tableName) {
            if (in_array('oc_' . $tableName, $tables, true)) {
                $schemaManager->dropTable('oc_' . $tableName);
            }
        }

        $qb = self::$db->getQueryBuilder();
        $qb->delete('migrations')
            ->where(
                $qb->expr()->eq('app', $qb->createNamedParameter('structureddiary'))
            );
        $qb->executeStatement();
    }

    protected function createSchema(): void {
        $migrationService = new MigrationService('structureddiary', self::$connection);
        $migrationService->migrate('latest');
    }

    protected function assertIntegrationDatabaseOptIn(): void {
        if (getenv('INTEGRATION_TEST_DB') === '1') {
            return;
        }

        throw new \RuntimeException(
            'Refusing to reset Structured Diary tables without INTEGRATION_TEST_DB=1. ' .
            'Run integration tests only against a disposable integration-test database.'
        );
    }
}
