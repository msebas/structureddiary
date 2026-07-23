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

		$this->dropStructuredDiaryTables();

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

	private function dropStructuredDiaryTables(): void {
		$foreignKeySql = match (self::$connection->getDatabasePlatform()->getName()) {
			'mysql', 'mariadb' => ['SET FOREIGN_KEY_CHECKS = 0', 'SET FOREIGN_KEY_CHECKS = 1'],
			'sqlite' => ['PRAGMA foreign_keys = OFF', 'PRAGMA foreign_keys = ON'],
			default => [null, null],
		};
		if ($foreignKeySql[0] !== null) {
			self::$connection->executeStatement($foreignKeySql[0]);
		}

		try {
			foreach ([
				TableNames::ANALYSIS_ARTIFACTS,
				TableNames::ANALYSIS_JOBS,
				TableNames::ANSWERS,
				TableNames::QUESTIONS,
				TableNames::ENTRIES,
				TableNames::DIARY_SHARES,
				TableNames::DIARIES,
				TableNames::ALARM_SOUNDS,
			] as $tableName) {
				self::$connection->executeStatement('DROP TABLE IF EXISTS ' . self::$connection->quoteIdentifier('oc_' . $tableName));
			}
		} finally {
			if ($foreignKeySql[1] !== null) {
				self::$connection->executeStatement($foreignKeySql[1]);
			}
		}
	}

    protected function writeGeneratedFixture(string $relativePath, mixed $data): string {
        if (str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException('Generated fixture path must not contain parent directory segments.');
        }

        $target = dirname(__DIR__, 2) . '/generated-fixtures/' . ltrim($relativePath, '/');
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create generated fixture directory: ' . $directory);
        }

        $json = json_encode(
            $this->normalizeGeneratedFixtureData($data),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        file_put_contents($target, $json . "\n");

        return $target;
    }

    private function normalizeGeneratedFixtureData(mixed $data): mixed {
        if ($data instanceof \JsonSerializable) {
            return $this->normalizeGeneratedFixtureData($data->jsonSerialize());
        }

        if (is_array($data)) {
            return array_map(fn (mixed $value): mixed => $this->normalizeGeneratedFixtureData($value), $data);
        }

        return $data;
    }
}
