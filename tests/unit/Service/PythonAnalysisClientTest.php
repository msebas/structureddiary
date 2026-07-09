<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\PythonAnalysisClient;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;

final class PythonAnalysisClientTest extends TestCase {
	public function testEnqueueJobUsesAnalysisServiceCreateSchema(): void {
		$job = $this->job();
		$client = $this->createMock(IClient::class);
		$response = $this->response(201, '{"id":123,"uuid":"11111111-2222-4333-8444-555555555555","nextcloud_job_id":42}');
		$client->expects($this->once())
			->method('post')
			->with('http://analysis.test/v1/jobs', $this->callback(function (array $options): bool {
				$payload = json_decode((string)$options['body'], true, 512, JSON_THROW_ON_ERROR);
				$this->assertSame(42, $payload['id']);
				$this->assertSame('11111111-2222-4333-8444-555555555555', $payload['uuid']);
				$this->assertSame(7, $payload['diary_id']);
				$this->assertSame('alice', $payload['created_by']);
				$this->assertSame(1000, $payload['created_at']);
				$this->assertSame(10, $payload['data_from']);
				$this->assertSame(20, $payload['data_until']);
				$this->assertSame('Report', $payload['title']);
				$this->assertSame('en-US', $payload['language']);
				$this->assertSame('standard', $payload['analysis_type']);
				$this->assertSame(['JSON', 'HTML'], $payload['output_types']);
				$this->assertSame(['includeTextAnalysis' => true], $payload['parameters']);
				$this->assertSame(['Authorization' => 'Bearer token'], $payload['llm_header']);
				$this->assertSame('https://cloud.example.test', $payload['nextcloud_base_url']);
				$this->assertSame('job-token', $payload['token']);
				$this->assertArrayNotHasKey('nextcloudJobId', $payload);
				$this->assertArrayNotHasKey('nextcloud_job_id', $payload);
				$this->assertArrayNotHasKey('parameters_json', $payload);
				$this->assertArrayNotHasKey('status', $payload);
				$this->assertSame('secret', $options['headers']['X-StructuredDiary-Service-Secret']);

				return true;
			}))
			->willReturn($response);

		$this->assertSame('123', $this->client($client)->enqueueJob($job, 5));
	}

	public function testEnqueueJobRejectsMismatchingReturnedJobId(): void {
		$job = $this->job();
		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturn($this->response(201, '{"id":123,"uuid":"11111111-2222-4333-8444-555555555555","nextcloud_job_id":43}'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Python service returned a different Nextcloud job id.');

		$this->client($client)->enqueueJob($job, 5);
	}

	public function testEnqueueJobRejectsMismatchingReturnedUuid(): void {
		$job = $this->job();
		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturn($this->response(201, '{"id":123,"uuid":"22222222-2222-4333-8444-555555555555","nextcloud_job_id":42}'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Python service returned a different job uuid.');

		$this->client($client)->enqueueJob($job, 5);
	}

	public function testEnqueueJobRequiresReturnedPythonJobId(): void {
		$job = $this->job();
		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturn($this->response(201, '{"uuid":"11111111-2222-4333-8444-555555555555","nextcloud_job_id":42}'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Python service did not return a job id.');

		$this->client($client)->enqueueJob($job, 5);
	}

	public function testListArtifactsSendsDocumentedJobHeaders(): void {
		$job = $this->job();
		$job->setPythonJobId('123');
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('get')
			->with('http://analysis.test/v1/jobs/123/artifacts', $this->callback(function (array $options): bool {
				$this->assertSame('job-token', $options['headers']['X-StructuredDiary-Job-Token']);
				$this->assertSame('123', $options['headers']['X-StructuredDiary-Python-Job-Id']);
				$this->assertSame('application/json', $options['headers']['Accept']);
				$this->assertArrayNotHasKey('X-StructuredDiary-Job-Uuid', $options['headers']);
				return true;
			}))
			->willReturn($this->response(200, '[]'));

		$this->assertSame([], $this->client($client)->listArtifacts($job));
	}

	public function testJobEndpointsRequireNumericPythonJobId(): void {
		$job = $this->job();
		$job->setPythonJobId('py-123');
		$client = $this->createMock(IClient::class);
		$client->expects($this->never())->method('get');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Python job id must be numeric.');

		$this->client($client)->listArtifacts($job);
	}

	private function client(IClient $client): PythonAnalysisClient {
		$config = $this->createMock(AnalysisConfigService::class);
		$config->method('getServiceUrl')->willReturn('http://analysis.test');
		$config->method('getServiceSecret')->willReturn('secret');
		$config->method('getNextcloudBaseUrl')->willReturn('https://cloud.example.test');
		$config->method('assertServiceConfigured');
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		return new PythonAnalysisClient($config, $clientService);
	}

	private function response(int $statusCode, string $body): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($statusCode);
		$response->method('getBody')->willReturn($body);
		return $response;
	}

	private function job(): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId(42);
		$job->setUuid('11111111-2222-4333-8444-555555555555');
		$job->setDiaryId(7);
		$job->setCreatedBy('alice');
		$job->setCreatedAt(1000);
		$job->setUpdatedAt(1000);
		$job->setDataFrom(10);
		$job->setDataUntil(20);
		$job->setTitle('Report');
		$job->setLanguage('en-US');
		$job->setAnalysisType(AnalysisJob::TYPE_STANDARD);
		$job->setStatus(AnalysisJob::STATUS_READY_QUEUE);
		$job->setProgress(0.0);
		$job->setOutputTypes('["JSON","HTML"]');
		$job->setParametersJson('{"includeTextAnalysis":true}');
		$job->setLlmUrl(null);
		$job->setLlmHeader('{"Authorization":"Bearer token"}');
		$job->setToken('job-token');
		$job->setStoragePath('/StructuredDiary/Analyses/report-42');
		$job->setStatusMessage('');
		return $job;
	}
}
