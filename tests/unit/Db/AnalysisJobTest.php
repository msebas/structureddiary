<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\AnalysisJob;
use PHPUnit\Framework\TestCase;

final class AnalysisJobTest extends TestCase {
	public function testStatusConstantsContainLifecycleFromTicket(): void {
		$this->assertSame([
			'DRAFT',
			'SUBMITTED',
			'READY_QUEUE',
			'QUEUED',
			'LOAD_DATA',
			'RUNNING',
			'WORKER_UPLOAD',
			'RESTART',
			'CANCEL_REQUESTED',
			'JOB_CANCELED',
			'CANCELED',
			'JOB_FAILED',
			'FAILED',
			'JOB_COMPLETED',
			'COMPLETED',
		], AnalysisJob::statuses());
	}

	public function testJsonSerializeDoesNotExposeInternalTokenOrLlmHeader(): void {
		$job = new AnalysisJob();
		$job->setId(42);
		$job->setUuid('11111111-2222-4333-8444-555555555555');
		$job->setDiaryId(7);
		$job->setCreatedBy('alice');
		$job->setCreatedAt(100);
		$job->setUpdatedAt(101);
		$job->setDataFrom(10);
		$job->setDataUntil(20);
		$job->setStartedAt(null);
		$job->setFinishedAt(null);
		$job->setTitle('Report');
		$job->setLanguage('en-US');
		$job->setAnalysisType(AnalysisJob::TYPE_STANDARD);
		$job->setStatus(AnalysisJob::STATUS_DRAFT);
		$job->setProgress(0.0);
		$job->setOutputTypes('["JSON","HTML"]');
		$job->setParametersJson('{"includeTextAnalysis":true,"shifting_median_width":11,"plot_std_error":false,"show_single_data_points":true}');
		$job->setLlmUrl('http://llm');
		$job->setLlmHeader('{"Authorization":"secret"}');
		$job->setPythonJobId('py-1');
		$job->setToken('token');
		$job->setStoragePath('/StructuredDiary/Analyses/report-42');
		$job->setStorageUrl('https://cloud.example/apps/files/?dir=/StructuredDiary/Analyses/report-42');
		$job->setManifestPath(null);
		$job->setStatusMessage('');
		$job->setErrorMessage(null);
		$job->setCancelRequestedAt(null);
		$job->setArtifactsDownloaded(false);
		$job->setPythonDeleted(false);

		$data = $job->jsonSerialize();

		$this->assertSame(['JSON', 'HTML'], $data['output_types']);
		$this->assertSame([
			'includeTextAnalysis' => true,
			'shifting_median_width' => 11,
			'plot_std_error' => false,
			'show_single_data_points' => true,
		], $data['parameters']);
		$this->assertSame('https://cloud.example/apps/files/?dir=/StructuredDiary/Analyses/report-42', $data['storage_url']);
		$this->assertArrayNotHasKey('token', $data);
		$this->assertArrayNotHasKey('llm_header', $data);
		$this->assertArrayNotHasKey('python_job_id', $data);
		$this->assertArrayNotHasKey('storage_path', $data);
		$this->assertArrayNotHasKey('manifest_path', $data);
		$this->assertArrayNotHasKey('artifacts_downloaded', $data);
		$this->assertArrayNotHasKey('python_deleted', $data);
	}
}
