<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

abstract class ApiOCSController extends OCSController {
	protected function respond(mixed $data, int $status = Http::STATUS_OK): DataResponse {
		return new DataResponse($data, $status);
	}

	protected function respondError(string $message, int $status = Http::STATUS_BAD_REQUEST): DataResponse {
		return new DataResponse(['error' => $message], $status);
	}

	protected function requireUser(?string $userId): string {
		if ($userId === null || $userId === '') {
			throw new \RuntimeException('Authentication required.');
		}

		return $userId;
	}

	/**
	 * @param list<string>|null $values
	 * @return list<string>|null
	 */
	protected function normalizeStringList(?array $values): ?array {
		if ($values === null) {
			return null;
		}

		$normalized = [];
		foreach ($values as $value) {
			$normalized[] = trim((string)$value);
		}

		return array_values(array_filter($normalized, static fn (string $value): bool => $value !== ''));
	}
}
