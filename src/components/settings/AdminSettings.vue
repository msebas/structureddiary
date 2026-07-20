<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { mdiEyeOffOutline, mdiEyeOutline } from '@mdi/js'

declare global {
	interface Window {
		OC?: {
			requestToken?: string
		}
	}
}

interface Props {
	saveUrl: string
	testUrl: string
	initialServiceUrl: string
	serviceSecretConfigured: boolean
	initialOutputBaseFolder: string
	httpsWarning: boolean
}

const props = defineProps<Props>()

const serviceUrl = ref(props.initialServiceUrl)
const serviceSecret = ref('')
const outputBaseFolder = ref(props.initialOutputBaseFolder)
const busy = ref(false)
const statusMessage = ref('')
const statusType = ref<'success' | 'error' | 'info'>('info')
const showSecret = ref(false)

const secretPlaceholder = computed(() => props.serviceSecretConfigured
	? t('structureddiary', 'Configured; leave empty to keep')
	: '')
const secretInputType = computed(() => showSecret.value ? 'text' : 'password')

function ocsData(payload: unknown): unknown {
	if (payload !== null && typeof payload === 'object' && 'ocs' in payload) {
		const ocs = (payload as { ocs?: { data?: unknown } }).ocs
		return ocs?.data ?? payload
	}

	return payload
}

function messageFromPayload(payload: unknown, fallback: string): string {
	const data = ocsData(payload)
	if (data !== null && typeof data === 'object') {
		const record = data as Record<string, unknown>
		if (typeof record.error === 'string' && record.error !== '') {
			return record.error
		}
		if (typeof record.message === 'string' && record.message !== '') {
			return record.message
		}
	}
	if (payload !== null && typeof payload === 'object' && 'ocs' in payload) {
		const meta = (payload as { ocs?: { meta?: { message?: unknown } } }).ocs?.meta
		if (typeof meta?.message === 'string' && meta.message !== '') {
			return meta.message
		}
	}

	return fallback
}

function setStatus(message: string, type: 'success' | 'error' | 'info'): void {
	statusMessage.value = message
	statusType.value = type
}

async function submit(action: 'save' | 'test'): Promise<void> {
	const body = new FormData()
	body.set('serviceUrl', serviceUrl.value)
	body.set('serviceSecret', serviceSecret.value)
	body.set('outputBaseFolder', outputBaseFolder.value)

	busy.value = true
	setStatus('', 'info')
	try {
		const request = async (url: string): Promise<{ response: Response, payload: unknown, data: unknown }> => {
			const response = await fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'OCS-APIRequest': 'true',
				requesttoken: window.OC?.requestToken ?? '',
			},
			body,
		})
			const payload = await response.json().catch(() => null)
			return { response, payload, data: ocsData(payload) }
		}
		let { response, payload, data } = await request(action === 'test' ? props.testUrl : props.saveUrl)
		if (!response.ok || (data !== null && typeof data === 'object' && (data as Record<string, unknown>).ok === false)) {
			setStatus(messageFromPayload(payload, t('structureddiary', 'Analysis settings request failed.')), 'error')
			return
		}
		if (action === 'save') {
			;({ response, payload, data } = await request(props.testUrl))
			if (!response.ok || (data !== null && typeof data === 'object' && (data as Record<string, unknown>).ok === false)) {
				setStatus(messageFromPayload(payload, t('structureddiary', 'Analysis settings were saved, but the analysis service health check failed.')), 'error')
				return
			}
		}
		setStatus(action === 'test'
			? t('structureddiary', 'Analysis service connection works.')
			: t('structureddiary', 'Analysis settings saved.'), 'success')
	} catch (error) {
		setStatus(error instanceof Error ? error.message : t('structureddiary', 'Analysis settings request failed.'), 'error')
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<form :class="$style.form" @submit.prevent="submit('save')">

    <input
        id="password-secret"
        name="PasswordManagerIgnoreTokenField"
        :class="$style.fakePw"
        tabindex="-1"
        aria-hidden="true"
        type="password"/>

		<p :class="$style.field">
			<label for="structureddiary-analysis-service-url">{{ t('structureddiary', 'Analysis service URL') }}</label>
			<input
				id="structureddiary-analysis-service-url"
				v-model="serviceUrl"
				type="url"
				name="serviceUrl"
				placeholder="http://127.0.0.1:8790"
				:disabled="busy">
		</p>
		<p :class="$style.field">
			<label for="structureddiary-analysis-service-secret">{{ t('structureddiary', 'Analysis service secret') }}</label>
			<span :class="$style.secretInput">
				<input
					id="structureddiary-analysis-service-secret"
					v-model="serviceSecret"
					:type="secretInputType"
					name="analysisServiceApiToken"
					autocomplete="off"
					autocapitalize="off"
					spellcheck="false"
					:placeholder="secretPlaceholder"
					:disabled="busy">
				<label
					:class="$style.secretToggle"
					:aria-label="showSecret ? t('structureddiary', 'Hide analysis service secret') : t('structureddiary', 'Show analysis service secret')"
					:title="showSecret ? t('structureddiary', 'Hide analysis service secret') : t('structureddiary', 'Show analysis service secret')"
					@click="!busy? (showSecret = !showSecret): null">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path :d="showSecret ? mdiEyeOffOutline : mdiEyeOutline" />
					</svg>
				</label>
			</span>
		</p>
		<p :class="$style.field">
			<label for="structureddiary-analysis-output-base-folder">{{ t('structureddiary', 'Output base folder') }}</label>
			<input
				id="structureddiary-analysis-output-base-folder"
				v-model="outputBaseFolder"
				type="text"
				name="outputBaseFolder"
				:disabled="busy">
		</p>
		<p v-if="httpsWarning" class="warning">
			{{ t('structureddiary', 'Warning: the configured analysis service URL is not HTTPS and does not look local.') }}
		</p>
    <p
        v-if="statusMessage !== ''"
        role="status"
        aria-live="polite"
        :class="[statusType === 'error' ? 'warning' : $style.success]">
      {{ statusMessage }}
    </p>
		<p :class="$style.actions">
			<button type="submit" class="primary" :disabled="busy">
				{{ t('structureddiary', 'Save') }}
			</button>
			<button type="button" :disabled="busy" @click="submit('test')">
				{{ t('structureddiary', 'Test connection') }}
			</button>
		</p>
	</form>
</template>

<style module>
.form {
	display: grid;
	max-width: 720px;
  width: 100%;
}

.field {
  display: block;
	gap: 4px;
	margin: 0;
}

.field label {
  display: block;
}

.field input {
  display: block;
	width: 100%;
	min-height: 34px;
}

.secretInput {
	position: relative;
	display: block;
	width: 100%;
}

.secretInput input {
	padding-inline-end: 42px;
}

.secretToggle {
	position: absolute !important;
	inset-block: 3px;
	inset-inline-end: 6px;
	place-items: center;
	width: 24px;
  height: 100%;
	margin: 0 !important;
	padding: 0 !important;
	border: 0 !important;
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
}

.secretToggle:hover,
.secretToggle:focus-visible {
	color: var(--color-main-text);
}

.secretToggle svg {
	width: 20px;
	height: 20px;
	fill: currentColor;
  margin-top: 3px;
}

.actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 0;
}

.success {
	color: var(--color-success);
	font-weight: 600;
}

.fakePw {
  width: 0;
  height: 0;
  transform: translate(50vw,-3em);
  border: 0 !important;
  padding: 1px;
  margin: 1px;
}

.fakePw:hover {
  cursor: default;
}
</style>
