<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getParentId()
 * @method void setParentId(?int $parentId)
 * @method int getJobId()
 * @method void setJobId(int $jobId)
 * @method string getArtifactType()
 * @method void setArtifactType(string $artifactType)
 * @method string getMimeType()
 * @method void setMimeType(string $mimeType)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 * @method string getFilePath()
 * @method void setFilePath(string $filePath)
 * @method int|null getFileId()
 * @method void setFileId(?int $fileId)
 * @method int|null getPythonParentId()
 * @method void setPythonParentId(?int $pythonParentId)
 * @method int|null getPythonFileId()
 * @method void setPythonFileId(?int $pythonFileId)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method string|null getChecksum()
 * @method void setChecksum(?string $checksum)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method bool getDownloaded()
 * @method void setDownloaded(bool $downloaded)
 * @method int getDownloadFails()
 * @method void setDownloadFails(int $downloadFails)
 * @method int|null getDownloadLastFailAt()
 * @method void setDownloadLastFailAt(?int $downloadLastFailAt)
 */
class AnalysisArtifact extends Entity implements JsonSerializable {
	public const TYPE_JSON = 'JSON';
	public const TYPE_HTML = 'HTML';
	public const TYPE_PDF = 'PDF';
	public const TYPE_XLSX = 'XLSX';
	public const TYPE_PLOT = 'PLOT';
	public const TYPE_MANIFEST = 'MANIFEST';
	public const TYPE_LOG = 'LOG';
	public const TYPE_MARKDOWN = 'MARKDOWN';
	public const TYPE_ERROR_LOG = 'ERROR_LOG';
	public const TYPE_ERROR_MARKDOWN = 'ERROR_MARKDOWN';

	protected $parentId;
	protected $jobId;
	protected $artifactType;
	protected $mimeType;
	protected $fileName;
	protected $filePath;
	protected $fileId;
	protected $pythonParentId;
	protected $pythonFileId;
	protected $size = 0;
	protected $checksum;
	protected $createdAt;
	protected $downloaded = false;
	protected $downloadFails = 0;
	protected $downloadLastFailAt;

	public function __construct() {
		$this->addType('parentId', 'integer');
		$this->addType('jobId', 'integer');
		$this->addType('artifactType', 'string');
		$this->addType('mimeType', 'string');
		$this->addType('fileName', 'string');
		$this->addType('filePath', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('pythonParentId', 'integer');
		$this->addType('pythonFileId', 'integer');
		$this->addType('size', 'integer');
		$this->addType('checksum', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('downloaded', 'boolean');
		$this->addType('downloadFails', 'integer');
		$this->addType('downloadLastFailAt', 'integer');
	}

	/**
	 * @return list<string>
	 */
	public static function types(): array {
		return [
			self::TYPE_JSON,
			self::TYPE_HTML,
			self::TYPE_PDF,
			self::TYPE_XLSX,
			self::TYPE_PLOT,
			self::TYPE_MANIFEST,
			self::TYPE_LOG,
			self::TYPE_MARKDOWN,
			self::TYPE_ERROR_LOG,
			self::TYPE_ERROR_MARKDOWN,
		];
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'parent_id' => $this->parentId === null ? null : (int)$this->parentId,
			'job_id' => (int)$this->jobId,
			'artifact_type' => $this->artifactType,
			'mime_type' => $this->mimeType,
			'file_name' => $this->fileName,
			'file_path' => $this->filePath,
			'file_id' => $this->fileId === null ? null : (int)$this->fileId,
			'size' => (int)$this->size,
			'checksum' => $this->checksum,
			'created_at' => (int)$this->createdAt,
		];
	}
}
