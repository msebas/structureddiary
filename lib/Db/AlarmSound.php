<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getPath()
 * @method void setPath(?string $path)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getLastSeenAt()
 * @method void setLastSeenAt(int $lastSeenAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method bool getIsDefault()
 * @method void setIsDefault(bool $isDefault)
 * @method string getOsAffinity()
 * @method void setOsAffinity(string $osAffinity)
 */
class AlarmSound extends Entity implements JsonSerializable {
	protected $path = null;
	protected $name;
	protected $lastSeenAt;
	protected $createdAt;
	protected $isDefault = false;
	protected $osAffinity = '[]';

	public function __construct() {
		$this->addType('path', 'string');
		$this->addType('name', 'string');
		$this->addType('lastSeenAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('isDefault', 'boolean');
		$this->addType('osAffinity', 'string');
	}

	/**
	 * @return list<string>
	 */
	public function getOsAffinityList(): array {
		$decoded = json_decode($this->osAffinity, true);
		if (!is_array($decoded)) {
			return [];
		}

		return array_values(array_filter(
			array_map(static fn (mixed $value): string => trim((string)$value), $decoded),
			static fn (string $value): bool => $value !== ''
		));
	}

	/**
	 * @param list<string> $osAffinity
	 */
	public function setOsAffinityList(array $osAffinity): void {
		$this->setOsAffinity(json_encode(self::normalizeOsAffinity($osAffinity), JSON_THROW_ON_ERROR));
	}

	/**
	 * @param list<string> $osAffinity
	 * @return list<string>
	 */
	public static function normalizeOsAffinity(array $osAffinity): array {
		$result = [];
		foreach ($osAffinity as $value) {
			$value = trim((string)$value);
			if ($value !== '' && !in_array($value, $result, true)) {
				$result[] = $value;
			}
		}

		return $result;
	}

	/**
	 * Empty affinity is treated as generic and therefore compatible with every OS affinity.
	 *
	 * @param list<string> $left
	 * @param list<string> $right
	 */
	public static function osAffinitiesOverlap(array $left, array $right): bool {
		$left = self::normalizeOsAffinity($left);
		$right = self::normalizeOsAffinity($right);
		if ($left === [] || $right === []) {
			return true;
		}

		return array_intersect($left, $right) !== [];
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'path' => $this->path,
			'name' => $this->name,
			'last_seen_at' => (int)$this->lastSeenAt,
			'created_at' => (int)$this->createdAt,
			'is_default' => (bool)$this->isDefault,
			'os_affinity' => $this->getOsAffinityList(),
		];
	}
}
