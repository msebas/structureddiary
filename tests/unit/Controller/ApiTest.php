<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\PageController;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testIndex(): void {
		$request = $this->createMock(IRequest::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->with('dev_server', [])->willReturn([]);
		$controller = new PageController(Application::APP_ID, $request, $config);

		$this->assertEquals('structureddiary', $controller->index()->getApp());
	}

	public function testIndexUsesIndexTemplate(): void {
		$request = $this->createMock(IRequest::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->with('dev_server', [])->willReturn([]);
		$controller = new PageController(Application::APP_ID, $request, $config);

		$this->assertSame('index', $controller->index()->getTemplateName());
	}
}
