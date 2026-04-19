<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\DiaryController;
use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShare;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class DiaryControllerTest extends TestCase {
	public function testShowUsesReadPermission(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($diary);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->show(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($diary, $response->getData());
	}

	public function testShowReturnsErrorWhenReadPermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willThrowException(new DoesNotExistException('Diary not accessible'));

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->show(42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not accessible'], $response->getData());
	}

	public function testStatsUsesAnalyzePermission(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$stats = ['entry_count' => 3];

		$diaryMapper->expects($this->once())->method('getDiaryStats')->with(42, 'alice')->willReturn($stats);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->stats(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($stats, $response->getData());
	}

	public function testStatsReturnsErrorWhenAnalyzePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryStats')
			->with(42, 'alice')
			->willThrowException(new DoesNotExistException('Diary not analyzable'));

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->stats(42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not analyzable'], $response->getData());
	}

	public function testUpdateUsesManagePermissionAndPassesOwnerUserId(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('updateDiary')
			->with(42, 'alice', 'Updated', 'Desc', 'bob', true, 36000, 4, 1800, 'bell', 'vibrate', 86400)
			->willReturn($diary);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->update(42, 'Updated', 'Desc', 'bob', true, 36000, 4, 1800, 'bell', 'vibrate', 86400);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($diary, $response->getData());
	}

	public function testSharesUsesManagePermission(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$shareMapper->expects($this->once())
			->method('getSharesForDiary')
			->with(42)
			->willReturn([]);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->shares(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testSharesReturnsErrorWhenManagePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willThrowException(new DoesNotExistException('Diary not manageable'));
		$shareMapper->expects($this->never())->method('getSharesForDiary');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->shares(42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not manageable'], $response->getData());
	}

	public function testCreateShareRejectsSharingWithOwner(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$shareMapper->expects($this->never())->method('upsertShare');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->createShare(42, 'alice', DiaryPermissions::READ);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'The owner cannot be shared explicitly.'], $response->getData());
	}

	public function testUpdateShareRejectsShareFromDifferentDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();
		$share = new DiaryShare();
		$share->setId(7);
		$share->setDiaryId(99);
		$share->setSharedWith('bob');

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$shareMapper->expects($this->once())->method('getShare')->with(7)->willReturn($share);
		$shareMapper->expects($this->never())->method('upsertShare');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->updateShare(42, 7, DiaryPermissions::READ);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Share does not belong to this diary.'], $response->getData());
	}

	public function testCreateRejectsEmptyTitle(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);

		$diaryMapper->expects($this->never())->method('createDiary');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->create('   ');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary title is required.'], $response->getData());
	}

	public function testUpdateRejectsEmptyTitle(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);

		$diaryMapper->expects($this->never())->method('updateDiary');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->update(42, '   ');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary title cannot be empty.'], $response->getData());
	}

	public function testCreateShareRejectsMissingSharedWith(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$shareMapper->expects($this->never())->method('upsertShare');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->createShare(42, '   ', DiaryPermissions::READ);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'sharedWith is required.'], $response->getData());
	}

	public function testUpdateShareRejectsUnsupportedPermissionValue(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$shareMapper->expects($this->never())->method('getShare');
		$shareMapper->expects($this->never())->method('upsertShare');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->updateShare(42, 7, 999);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Unsupported permission value.'], $response->getData());
	}

	public function testDeleteShareRejectsShareFromDifferentDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();
		$share = new DiaryShare();
		$share->setId(7);
		$share->setDiaryId(99);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$shareMapper->expects($this->once())->method('getShare')->with(7)->willReturn($share);
		$shareMapper->expects($this->never())->method('deleteShare');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->deleteShare(42, 7);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Share does not belong to this diary.'], $response->getData());
	}

	public function testIndexRejectsMissingAuthenticatedUser(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);

		$diaryMapper->expects($this->never())->method('getAccessibleDiaries');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, null);
		$response = $controller->index();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}

	public function testIndexReturnsAccessibleDiaries(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diaries = [new Diary(), new Diary()];

		$diaryMapper->expects($this->once())->method('getAccessibleDiaries')->with('alice')->willReturn($diaries);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($diaries, $response->getData());
	}

	public function testCreateTrimsTitleAndReturnsCreatedDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('createDiary')
			->with('alice', 'Journal', 'Desc', true, 36000, 4, 1800, 'bell', 'vibrate', 86400)
			->willReturn($diary);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->create('  Journal  ', 'Desc', true, 36000, 4, 1800, 'bell', 'vibrate', 86400);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($diary, $response->getData());
	}

	public function testDeleteReturnsDeletedDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())->method('deleteDiary')->with(42, 'alice')->willReturn($diary);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->delete(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($diary, $response->getData());
	}

	public function testCreateShareReturnsCreatedShare(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();
		$share = new DiaryShare();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$shareMapper->expects($this->once())
			->method('upsertShare')
			->with(42, 'bob', DiaryPermissions::READ)
			->willReturn($share);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->createShare(42, ' bob ', DiaryPermissions::READ);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($share, $response->getData());
	}

	public function testCreateShareRejectsUnsupportedPermissionValue(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$shareMapper->expects($this->never())->method('upsertShare');

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->createShare(42, 'bob', 999);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Unsupported permission value.'], $response->getData());
	}

	public function testUpdateShareReturnsUpdatedShare(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();
		$share = new DiaryShare();
		$share->setId(7);
		$share->setDiaryId(42);
		$share->setSharedWith('bob');
		$updated = new DiaryShare();

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$shareMapper->expects($this->once())->method('getShare')->with(7)->willReturn($share);
		$shareMapper->expects($this->once())->method('upsertShare')->with(42, 'bob', DiaryPermissions::READ | DiaryPermissions::WRITE)->willReturn($updated);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->updateShare(42, 7, DiaryPermissions::READ | DiaryPermissions::WRITE);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($updated, $response->getData());
	}

	public function testDeleteShareReturnsDeletedShare(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$shareMapper = $this->createMock(DiaryShareMapper::class);
		$diary = new Diary();
		$share = new DiaryShare();
		$share->setId(7);
		$share->setDiaryId(42);

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$shareMapper->expects($this->once())->method('getShare')->with(7)->willReturn($share);
		$shareMapper->expects($this->once())->method('deleteShare')->with(7)->willReturn($share);

		$controller = new DiaryController(Application::APP_ID, $request, $diaryMapper, $shareMapper, 'alice');
		$response = $controller->deleteShare(42, 7);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($share, $response->getData());
	}
}
