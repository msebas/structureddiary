<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use InvalidArgumentException;

final class ClientMutationId {
	public static function normalize(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		if ($value === '' || strlen($value) > 64) {
			throw new InvalidArgumentException('clientMutationId must contain between 1 and 64 characters.');
		}
		return $value;
	}
}
