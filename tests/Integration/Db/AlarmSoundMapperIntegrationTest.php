<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\AlarmSound;
use OCA\StructuredDiary\Db\AlarmSoundMapper;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;

/**
 * @runTestsInSeparateProcesses
 */
final class AlarmSoundMapperIntegrationTest extends IntegrationTestParentClass {
	private AlarmSoundMapper $alarmSoundMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->alarmSoundMapper = self::$container->get(AlarmSoundMapper::class);
	}

	public function testDefaultIsClearedOnlyForOverlappingOsAffinity(): void {
		$this->alarmSoundMapper->upsertAlarmSound('Bell', 'bell', ['ios:17'], true);
		$this->alarmSoundMapper->upsertAlarmSound('Chime', 'chime', ['android:15'], true);
		$this->alarmSoundMapper->upsertAlarmSound('Radar', 'radar', ['ios:17'], true);

		$sounds = $this->soundsByName();

		$this->assertFalse($sounds['Bell']->getIsDefault());
		$this->assertTrue($sounds['Chime']->getIsDefault());
		$this->assertTrue($sounds['Radar']->getIsDefault());
	}

	public function testDefaultWithMultipleAffinitiesClearsEachCompatibleDefault(): void {
		$this->alarmSoundMapper->upsertAlarmSound('Bell', 'bell', ['ios:17'], true);
		$this->alarmSoundMapper->upsertAlarmSound('Chime', 'chime', ['android:15'], true);
		$this->alarmSoundMapper->upsertAlarmSound('Default', 'default', ['ios:17', 'android:15'], true);

		$sounds = $this->soundsByName();

		$this->assertFalse($sounds['Bell']->getIsDefault());
		$this->assertFalse($sounds['Chime']->getIsDefault());
		$this->assertTrue($sounds['Default']->getIsDefault());
	}

	public function testAddingAffinityToExistingDefaultClearsNewCompatibleDefaults(): void {
		$bell = $this->alarmSoundMapper->upsertAlarmSound('Bell', 'bell', ['ios:17'], true);
		$this->alarmSoundMapper->upsertAlarmSound('Chime', 'chime', ['android:15'], true);

		$updatedBell = $this->alarmSoundMapper->upsertAlarmSound('Bell', 'bell', ['android:15']);
		$sounds = $this->soundsByName();

		$this->assertSame($bell->getId(), $updatedBell->getId());
		$this->assertSame(['ios:17', 'android:15'], $updatedBell->getOsAffinityList());
		$this->assertTrue($sounds['Bell']->getIsDefault());
		$this->assertFalse($sounds['Chime']->getIsDefault());
	}

	/**
	 * @return array<string, AlarmSound>
	 */
	private function soundsByName(): array {
		$result = [];
		foreach ($this->alarmSoundMapper->getAlarmSounds() as $sound) {
			$result[$sound->getName()] = $sound;
		}

		return $result;
	}
}
