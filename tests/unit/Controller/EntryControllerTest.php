<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\EntryController;
use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\Entry;
use OCA\StructuredDiary\Db\EntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class EntryControllerTest extends TestCase {
	public function testIndexUsesReadPermission(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('getEntriesForDiary')
			->with(42, null, null)
			->willReturn([]);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->index(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testIndexPassesOptionalTimestampFilters(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entries = [new Entry()];

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('getEntriesForDiary')
			->with(42, 1000, 2000)
			->willReturn($entries);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->index(42, 1000, 2000);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entries, $response->getData());
	}

	public function testIndexReturnsErrorWhenReadPermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willThrowException(new DoesNotExistException('Diary not accessible'));
		$entryMapper->expects($this->never())->method('getEntriesForDiary');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->index(42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not accessible'], $response->getData());
	}

	public function testCreateUsesWritePermission(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('createEntry')
			->with(42, 1713254400, 'Note')
			->willReturn($entry);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->create(42, 1713254400, 'Note');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testCreateAllowsNullTitle(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('createEntry')
			->with(42, 1713254400, null)
			->willReturn($entry);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->create(42, 1713254400);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testCreateReturnsErrorWhenWritePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willThrowException(new DoesNotExistException('Diary not writable'));
		$entryMapper->expects($this->never())->method('createEntry');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->create(42, 1713254400, 'Note');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not writable'], $response->getData());
	}

	public function testShowUsesReadPermissionFromEntryDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->show(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testUpdateReturnsErrorWhenWritePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willThrowException(new DoesNotExistException('Diary not writable'));
		$entryMapper->expects($this->never())->method('updateEntry');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->update(5, 1713254401, 'Changed');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not writable'], $response->getData());
	}

	public function testShowReturnsErrorWhenReadPermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willThrowException(new DoesNotExistException('Diary not accessible'));

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->show(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not accessible'], $response->getData());
	}

	public function testUpdateUsesWritePermissionAndReturnsUpdatedEntry(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$updated = new Entry();
		$updated->setId(5);
		$updated->setDiaryId(42);
		$updated->setTimestamp(1713254401);
		$updated->setTitle('Changed');

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('updateEntry')
			->with($entry, 1713254401, 'Changed')
			->willReturn($updated);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->update(5, 1713254401, 'Changed');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($updated, $response->getData());
	}

	public function testUpdateCanChangeOnlyTitle(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$updated = new Entry();
		$updated->setId(5);
		$updated->setDiaryId(42);
		$updated->setTimestamp(1713254400);
		$updated->setTitle('Changed');

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('updateEntry')
			->with($entry, null, 'Changed')
			->willReturn($updated);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->update(5, null, 'Changed');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($updated, $response->getData());
	}

	public function testUpdateCanChangeOnlyTimestamp(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$updated = new Entry();
		$updated->setId(5);
		$updated->setDiaryId(42);
		$updated->setTimestamp(1713254500);
		$updated->setTitle('Original');

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())
			->method('updateEntry')
			->with($entry, 1713254500, null)
			->willReturn($updated);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->update(5, 1713254500);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($updated, $response->getData());
	}

	public function testDeleteUsesWritePermissionAndReturnsDeletedEntry(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$entryMapper->expects($this->once())->method('deleteEntry')->with($entry)->willReturn($entry);

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->delete(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testDeleteReturnsErrorWhenWritePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willThrowException(new DoesNotExistException('Diary not writable'));
		$entryMapper->expects($this->never())->method('deleteEntry');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, 'alice');
		$response = $controller->delete(5);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not writable'], $response->getData());
	}

	public function testIndexRejectsMissingAuthenticatedUser(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);

		$diaryMapper->expects($this->never())->method('getDiaryForUser');
		$entryMapper->expects($this->never())->method('getEntriesForDiary');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, null);
		$response = $controller->index(42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}

	public function testCreateRejectsMissingAuthenticatedUser(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);

		$diaryMapper->expects($this->never())->method('getDiaryForUser');
		$entryMapper->expects($this->never())->method('createEntry');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, null);
		$response = $controller->create(42, 1713254400, 'Note');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}

	public function testShowRejectsMissingAuthenticatedUser(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->never())->method('getDiaryForUser');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, null);
		$response = $controller->show(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}

	public function testUpdateRejectsMissingAuthenticatedUser(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->never())->method('getDiaryForUser');
		$entryMapper->expects($this->never())->method('updateEntry');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, null);
		$response = $controller->update(5, 1713254401, 'Changed');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}

	public function testDeleteRejectsMissingAuthenticatedUser(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->never())->method('getDiaryForUser');
		$entryMapper->expects($this->never())->method('deleteEntry');

		$controller = new EntryController(Application::APP_ID, $request, $diaryMapper, $entryMapper, null);
		$response = $controller->delete(5);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Authentication required.'], $response->getData());
	}
}
