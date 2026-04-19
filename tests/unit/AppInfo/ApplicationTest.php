<?php

declare(strict_types=1);

namespace AppInfo;

use OCA\StructuredDiary\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase {
	public function testApplicationCanBeConstructed(): void {
		$app = new Application();

		$this->assertInstanceOf(Application::class, $app);
		$this->assertSame('structureddiary', Application::APP_ID);
	}

	public function testRegisterAndBootAreNoOps(): void {
		$app = new Application();
		$app->register($this->createMock(IRegistrationContext::class));
		$app->boot($this->createMock(IBootContext::class));

		$this->addToAssertionCount(1);
	}
}
