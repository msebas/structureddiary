<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\AlarmSoundController;
use OCA\StructuredDiary\Db\AlarmSound;
use OCA\StructuredDiary\Db\AlarmSoundMapper;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class AlarmSoundControllerTest extends TestCase {
	public function testIndexReturnsAlarmSounds(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(AlarmSoundMapper::class);
		$sound = new AlarmSound();

		$mapper->expects($this->once())->method('getAlarmSounds')->willReturn([$sound]);

		$controller = new AlarmSoundController(Application::APP_ID, $request, $mapper, 'alice');
		$response = $controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$sound], $response->getData());
	}

	public function testCreateNormalizesOsAffinityAndCallsUpsert(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(AlarmSoundMapper::class);
		$sound = new AlarmSound();

		$mapper->expects($this->once())
			->method('upsertAlarmSound')
			->with('Bell', '/system/bell.ogg', ['ios:17', 'android:15'], true)
			->willReturn($sound);

		$controller = new AlarmSoundController(Application::APP_ID, $request, $mapper, 'alice');
		$response = $controller->create('Bell', '/system/bell.ogg', [' ios:17 ', '', 'android:15'], true);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($sound, $response->getData());
	}

	public function testPatchPassesPartialPayloadToMapper(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(AlarmSoundMapper::class);
		$sound = new AlarmSound();

		$mapper->expects($this->once())
			->method('updateAlarmSound')
			->with(7, null, null, ['android:15'], null)
			->willReturn($sound);

		$controller = new AlarmSoundController(Application::APP_ID, $request, $mapper, 'alice');
		$response = $controller->patch(7, null, null, ['android:15'], null);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($sound, $response->getData());
	}

	public function testCreateRequiresAuthentication(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(AlarmSoundMapper::class);

		$mapper->expects($this->never())->method('upsertAlarmSound');

		$controller = new AlarmSoundController(Application::APP_ID, $request, $mapper, null);
		$response = $controller->create('Bell');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}
}
