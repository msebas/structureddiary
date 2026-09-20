<?php

declare(strict_types=1);

namespace Service;

use InvalidArgumentException;
use OCA\StructuredDiary\Service\ClientMutationId;
use PHPUnit\Framework\TestCase;

final class ClientMutationIdTest extends TestCase {
	public function testNormalizeAcceptsAndTrimsValidId(): void {
		$this->assertSame('mutation-1', ClientMutationId::normalize(' mutation-1 '));
		$this->assertNull(ClientMutationId::normalize(null));
	}

	/** @dataProvider invalidIds */
	public function testNormalizeRejectsBlankAndOversizedIds(string $value): void {
		$this->expectException(InvalidArgumentException::class);
		ClientMutationId::normalize($value);
	}

	/** @return array<string, array{string}> */
	public static function invalidIds(): array {
		return [
			'blank' => [''],
			'oversized' => [str_repeat('a', 65)],
		];
	}
}
