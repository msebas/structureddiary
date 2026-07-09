import { createApp } from 'vue'
import AdminSettings from '@/components/settings/AdminSettings.vue'

const mount = document.getElementById('structureddiary-analysis-settings-app')
if (mount) {
	createApp(AdminSettings, {
		saveUrl: mount.dataset.saveUrl ?? '',
		testUrl: mount.dataset.testUrl ?? '',
		initialServiceUrl: mount.dataset.serviceUrl ?? '',
		serviceSecretConfigured: mount.dataset.serviceSecretConfigured === 'true',
		initialOutputBaseFolder: mount.dataset.outputBaseFolder ?? '/StructuredDiary/Analyses',
		httpsWarning: mount.dataset.httpsWarning === 'true',
	}).mount(mount)
}
