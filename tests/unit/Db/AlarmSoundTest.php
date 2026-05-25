<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\AlarmSound;
use PHPUnit\Framework\TestCase;

final class AlarmSoundTest extends TestCase {
	public function testJsonSerializeReturnsExpectedPayload(): void {
		$sound = new AlarmSound();
		$sound->setId(7);
		$sound->setName('Bell');
		$sound->setPath('/system/bell.ogg');
		$sound->setCreatedAt(1713500000);
		$sound->setLastSeenAt(1713600000);
		$sound->setIsDefault(true);
		$sound->setOsAffinityList(['ios:17', 'android:15', 'ios:17']);

		$this->assertSame([
			'id' => 7,
			'path' => '/system/bell.ogg',
			'name' => 'Bell',
			'last_seen_at' => 1713600000,
			'created_at' => 1713500000,
			'is_default' => true,
			'os_affinity' => ['ios:17', 'android:15'],
		], $sound->jsonSerialize());
	}

	public function testInvalidOsAffinityJsonReturnsEmptyList(): void {
		$sound = new AlarmSound();
		$sound->setOsAffinity('invalid');

		$this->assertSame([], $sound->getOsAffinityList());
	}

	public function testSetOsAffinityListMarksEntityFieldAsUpdated(): void {
		$sound = new AlarmSound();

		$sound->setOsAffinityList(['ios:17']);

		$this->assertSame('["ios:17"]', $sound->getOsAffinity());
		$this->assertArrayHasKey('osAffinity', $sound->getUpdatedFields());
	}

	public function testOsAffinitiesOverlapForSharedAffinity(): void {
		$this->assertTrue(AlarmSound::osAffinitiesOverlap(['ios:17', 'android:15'], ['ios:17']));
		$this->assertFalse(AlarmSound::osAffinitiesOverlap(['ios:17'], ['android:15']));
	}

	public function testEmptyOsAffinityIsGenericAndOverlapsEverything(): void {
		$this->assertTrue(AlarmSound::osAffinitiesOverlap([], ['ios:17']));
		$this->assertTrue(AlarmSound::osAffinitiesOverlap(['android:15'], []));
	}
}
