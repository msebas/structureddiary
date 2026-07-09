import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import type { AnalysisArtifact, AnalysisArtifactType, AnalysisJob, AnalysisJobStatus } from '@/types/types'

export const activeAnalysisStatuses: AnalysisJobStatus[] = [
	'READY_QUEUE',
	'QUEUED',
	'LOAD_DATA',
	'RUNNING',
	'RESTART',
	'CANCEL_REQUESTED',
	'JOB_CANCELED',
	'JOB_FAILED',
	'JOB_COMPLETED',
]

export const terminalAnalysisStatuses: AnalysisJobStatus[] = ['CANCELED', 'FAILED', 'COMPLETED']
export const mutableAnalysisStatuses: AnalysisJobStatus[] = activeAnalysisStatuses

const statusLabels: Record<AnalysisJobStatus, string> = {
	DRAFT: t('structureddiary', 'Draft'),
	READY_QUEUE: t('structureddiary', 'Waiting to start'),
	QUEUED: t('structureddiary', 'Queued'),
	LOAD_DATA: t('structureddiary', 'Loading data'),
	RUNNING: t('structureddiary', 'Running'),
	RESTART: t('structureddiary', 'Restarting'),
	CANCEL_REQUESTED: t('structureddiary', 'Cancelling'),
	JOB_CANCELED: t('structureddiary', 'Cleaning up'),
	CANCELED: t('structureddiary', 'Cancelled'),
	JOB_FAILED: t('structureddiary', 'Collecting failure details'),
	FAILED: t('structureddiary', 'Failed'),
	JOB_COMPLETED: t('structureddiary', 'Collecting results'),
	COMPLETED: t('structureddiary', 'Completed'),
}

export function analysisStatusLabel(status: AnalysisJobStatus): string {
	return statusLabels[status] ?? status
}

export function analysisStatusTone(status: AnalysisJobStatus): 'neutral' | 'info' | 'success' | 'warning' | 'error' {
	if (status === 'COMPLETED') return 'success'
	if (status === 'FAILED' || status === 'JOB_FAILED') return 'error'
	if (status === 'CANCELED' || status === 'CANCEL_REQUESTED' || status === 'JOB_CANCELED') return 'warning'
	if (status === 'DRAFT') return 'neutral'
	return 'info'
}

export function isAnalysisMutable(job: AnalysisJob | null | undefined): boolean {
	return job !== null && job !== undefined && mutableAnalysisStatuses.includes(job.status)
}

export function canStartAnalysis(job: AnalysisJob | null | undefined): boolean {
	return job?.status === 'DRAFT'
}

export function canCancelAnalysis(job: AnalysisJob | null | undefined): boolean {
	return job !== null
		&& job !== undefined
		&& ['READY_QUEUE', 'QUEUED', 'LOAD_DATA', 'RUNNING', 'RESTART', 'JOB_FAILED', 'JOB_COMPLETED'].includes(job.status)
}

export function canDeleteAnalysis(job: AnalysisJob | null | undefined): boolean {
	return job !== null
		&& job !== undefined
		&& ['CANCELED', 'FAILED', 'COMPLETED'].includes(job.status)
}

export function compareAnalysisJobs(a: AnalysisJob, b: AnalysisJob): number {
	const created = b.created_at - a.created_at
	if (created !== 0) return created
	const activeA = activeAnalysisStatuses.includes(a.status) ? 0 : 1
	const activeB = activeAnalysisStatuses.includes(b.status) ? 0 : 1
	if (activeA !== activeB) return activeA - activeB
	return b.id - a.id
}

export function artifactFileUrl(artifact: AnalysisArtifact, download = false): string | null {
	if (artifact.file_id === null) {
		return null
	}

	const base = generateUrl(`/f/${artifact.file_id}`)
	return download ? `${base}?download=1` : base
}

export function artifactDisplayType(artifact: AnalysisArtifact): AnalysisArtifactType {
	return artifact.artifact_type
}

export function formatBytes(bytes: number): string {
	if (!Number.isFinite(bytes) || bytes <= 0) {
		return '0 B'
	}

	const units = ['B', 'KB', 'MB', 'GB']
	let value = bytes
	let unit = 0
	while (value >= 1024 && unit < units.length - 1) {
		value /= 1024
		unit += 1
	}
	return `${value >= 10 || unit === 0 ? value.toFixed(0) : value.toFixed(1)} ${units[unit]}`
}
