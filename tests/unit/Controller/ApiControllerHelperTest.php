<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\Controller\ApiController;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class ApiControllerHelperTest extends TestCase {
	public function testRespondReturnsDataAndStatus(): void {
		$controller = $this->createController();
		$response = $controller->exposedRespond(['ok' => true], 201);

		$this->assertSame(201, $response->getStatus());
		$this->assertSame(['ok' => true], $response->getData());
	}

	public function testRespondErrorWrapsMessageWithDefaultStatus(): void {
		$controller = $this->createController();
		$response = $controller->exposedRespondError('broken');

		$this->assertSame(400, $response->getStatus());
		$this->assertSame(['error' => 'broken'], $response->getData());
	}

	public function testRespondErrorAllowsCustomStatus(): void {
		$controller = $this->createController();
		$response = $controller->exposedRespondError('missing', 404);

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'missing'], $response->getData());
	}

	public function testRequireUserRejectsNull(): void {
		$controller = $this->createController();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Authentication required.');

		$controller->exposedRequireUser(null);
	}

	public function testRequireUserRejectsEmptyString(): void {
		$controller = $this->createController();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Authentication required.');

		$controller->exposedRequireUser('');
	}

	public function testRequireUserReturnsUserId(): void {
		$controller = $this->createController();

		$this->assertSame('alice', $controller->exposedRequireUser('alice'));
	}

	public function testNormalizeStringListReturnsNullForNull(): void {
		$controller = $this->createController();

		$this->assertNull($controller->exposedNormalizeStringList(null));
	}

	public function testNormalizeStringListTrimsAndRemovesEmptyValues(): void {
		$controller = $this->createController();

		$this->assertSame(['yes', '0', 'no'], $controller->exposedNormalizeStringList([' yes ', '', '0', '  ', 'no ']));
	}

	public function testNormalizeStringListReturnsEmptyArrayWhenAllValuesAreEmpty(): void {
		$controller = $this->createController();

		$this->assertSame([], $controller->exposedNormalizeStringList([' ', '', "\t"]));
	}

	public function testNormalizeStringListPreservesDuplicates(): void {
		$controller = $this->createController();

		$this->assertSame(['yes', 'yes'], $controller->exposedNormalizeStringList([' yes ', 'yes']));
	}

	private function createController(): object {
		return new class('structureddiary', $this->createMock(IRequest::class)) extends ApiController {
			public function exposedRespond(mixed $data, int $status = 200): \OCP\AppFramework\Http\DataResponse {
				return $this->respond($data, $status);
			}

			public function exposedRespondError(string $message, int $status = 400): \OCP\AppFramework\Http\DataResponse {
				return $this->respondError($message, $status);
			}

			public function exposedRequireUser(?string $userId): string {
				return $this->requireUser($userId);
			}

			public function exposedNormalizeStringList(?array $values): ?array {
				return $this->normalizeStringList($values);
			}
		};
	}
}
