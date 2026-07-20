<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getDiaryId()
 * @method void setDiaryId(int $diaryId)
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int getDataFrom()
 * @method void setDataFrom(int $dataFrom)
 * @method int getDataUntil()
 * @method void setDataUntil(int $dataUntil)
 * @method int|null getStartedAt()
 * @method void setStartedAt(?int $startedAt)
 * @method int|null getFinishedAt()
 * @method void setFinishedAt(?int $finishedAt)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getLanguage()
 * @method void setLanguage(string $language)
 * @method string getAnalysisType()
 * @method void setAnalysisType(string $analysisType)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method float getProgress()
 * @method void setProgress(float $progress)
 * @method string getOutputTypes()
 * @method void setOutputTypes(string $outputTypes)
 * @method string getParametersJson()
 * @method void setParametersJson(string $parametersJson)
 * @method string|null getLlmUrl()
 * @method void setLlmUrl(?string $llmUrl)
 * @method string|null getLlmHeader()
 * @method void setLlmHeader(?string $llmHeader)
 * @method string|null getPythonJobId()
 * @method void setPythonJobId(?string $pythonJobId)
 * @method string|null getToken()
 * @method void setToken(?string $token)
 * @method string getStoragePath()
 * @method void setStoragePath(string $storagePath)
 * @method string|null getManifestPath()
 * @method void setManifestPath(?string $manifestPath)
 * @method string getStatusMessage()
 * @method void setStatusMessage(string $statusMessage)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 * @method int|null getCancelRequestedAt()
 * @method void setCancelRequestedAt(?int $cancelRequestedAt)
 * @method bool getArtifactsDownloaded()
 * @method void setArtifactsDownloaded(bool $artifactsDownloaded)
 * @method bool getPythonDeleted()
 * @method void setPythonDeleted(bool $pythonDeleted)
 */
class AnalysisJob extends Entity implements JsonSerializable {
	public const TYPE_STANDARD = 'standard';

	public const STATUS_DRAFT = 'DRAFT';
	public const STATUS_SUBMITTED = 'SUBMITTED';
	public const STATUS_READY_QUEUE = 'READY_QUEUE';
	public const STATUS_QUEUED = 'QUEUED';
	public const STATUS_LOAD_DATA = 'LOAD_DATA';
	public const STATUS_RUNNING = 'RUNNING';
	public const STATUS_WORKER_UPLOAD = 'WORKER_UPLOAD';
	public const STATUS_RESTART = 'RESTART';
	public const STATUS_CANCEL_REQUESTED = 'CANCEL_REQUESTED';
	public const STATUS_JOB_CANCELED = 'JOB_CANCELED';
	public const STATUS_CANCELED = 'CANCELED';
	public const STATUS_JOB_FAILED = 'JOB_FAILED';
	public const STATUS_FAILED = 'FAILED';
	public const STATUS_JOB_COMPLETED = 'JOB_COMPLETED';
	public const STATUS_COMPLETED = 'COMPLETED';

	public const OUTPUT_JSON = 'JSON';
	public const OUTPUT_HTML = 'HTML';
	public const OUTPUT_PDF = 'PDF';
	public const OUTPUT_XLSX = 'XLSX';

	protected $diaryId;
	protected $uuid;
	protected $createdBy;
	protected $createdAt;
	protected $updatedAt;
	protected $dataFrom;
	protected $dataUntil;
	protected $startedAt;
	protected $finishedAt;
	protected $title;
	protected $language;
	protected $analysisType = self::TYPE_STANDARD;
	protected $status = self::STATUS_DRAFT;
	protected $progress = 0.0;
	protected $outputTypes;
	protected $parametersJson;
	protected $llmUrl;
	protected $llmHeader;
	protected $pythonJobId;
	protected $token;
	protected $storagePath;
	protected $manifestPath;
	protected $statusMessage = '';
	protected $errorMessage;
	protected $cancelRequestedAt;
	protected $artifactsDownloaded = false;
	protected $pythonDeleted = false;
	private ?string $storageUrl = null;

	public function __construct() {
		$this->addType('diaryId', 'integer');
		$this->addType('uuid', 'string');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('dataFrom', 'integer');
		$this->addType('dataUntil', 'integer');
		$this->addType('startedAt', 'integer');
		$this->addType('finishedAt', 'integer');
		$this->addType('title', 'string');
		$this->addType('language', 'string');
		$this->addType('analysisType', 'string');
		$this->addType('status', 'string');
		$this->addType('progress', 'float');
		$this->addType('outputTypes', 'string');
		$this->addType('parametersJson', 'string');
		$this->addType('llmUrl', 'string');
		$this->addType('llmHeader', 'string');
		$this->addType('pythonJobId', 'string');
		$this->addType('token', 'string');
		$this->addType('storagePath', 'string');
		$this->addType('manifestPath', 'string');
		$this->addType('statusMessage', 'string');
		$this->addType('errorMessage', 'string');
		$this->addType('cancelRequestedAt', 'integer');
		$this->addType('artifactsDownloaded', 'boolean');
		$this->addType('pythonDeleted', 'boolean');
	}

	/**
	 * @return list<string>
	 */
	public static function statuses(): array {
		return [
			self::STATUS_DRAFT,
			self::STATUS_SUBMITTED,
			self::STATUS_READY_QUEUE,
			self::STATUS_QUEUED,
			self::STATUS_LOAD_DATA,
			self::STATUS_RUNNING,
			self::STATUS_WORKER_UPLOAD,
			self::STATUS_RESTART,
			self::STATUS_CANCEL_REQUESTED,
			self::STATUS_JOB_CANCELED,
			self::STATUS_CANCELED,
			self::STATUS_JOB_FAILED,
			self::STATUS_FAILED,
			self::STATUS_JOB_COMPLETED,
			self::STATUS_COMPLETED,
		];
	}

	/**
	 * @return list<string>
	 */
	public static function outputTypes(): array {
		return [self::OUTPUT_JSON, self::OUTPUT_HTML, self::OUTPUT_PDF, self::OUTPUT_XLSX];
	}

	/**
	 * @return list<string>
	 */
	public static function terminalWebStatuses(): array {
		return [self::STATUS_CANCELED, self::STATUS_FAILED, self::STATUS_COMPLETED];
	}

	public function isTerminalWebStatus(): bool {
		return in_array($this->getStatus(), self::terminalWebStatuses(), true);
	}

	public function setStorageUrl(?string $storageUrl): void {
		$this->storageUrl = $storageUrl;
	}

	public function getStorageUrl(): ?string {
		return $this->storageUrl;
	}

	/**
	 * @return list<string>
	 */
	public function getOutputTypeList(): array {
		$decoded = json_decode($this->outputTypes, true, 512, JSON_THROW_ON_ERROR);
		return is_array($decoded) ? array_values(array_map(static fn (mixed $value): string => (string)$value, $decoded)) : [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getParameters(): array {
		$decoded = json_decode($this->parametersJson, true, 512, JSON_THROW_ON_ERROR);
		return is_array($decoded) ? $decoded : [];
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'diary_id' => (int)$this->diaryId,
			'created_by' => $this->createdBy,
			'created_at' => (int)$this->createdAt,
			'updated_at' => (int)$this->updatedAt,
			'data_from' => (int)$this->dataFrom,
			'data_until' => (int)$this->dataUntil,
			'started_at' => $this->startedAt === null ? null : (int)$this->startedAt,
			'finished_at' => $this->finishedAt === null ? null : (int)$this->finishedAt,
			'title' => $this->title,
			'language' => $this->language,
			'analysis_type' => $this->analysisType,
			'status' => $this->status,
			'progress' => (float)$this->progress,
			'output_types' => $this->getOutputTypeList(),
			'parameters' => $this->getParameters(),
			'storage_url' => $this->storageUrl,
			'status_message' => $this->statusMessage,
			'error_message' => $this->errorMessage,
			'cancel_requested_at' => $this->cancelRequestedAt === null ? null : (int)$this->cancelRequestedAt,
		];
	}
}
