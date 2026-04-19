import { createApp } from 'vue'

const settingsApp = createApp({
	template: '<div></div>',
})

const mount = document.getElementById('structureddiary-settings')
if (mount) {
	settingsApp.mount(mount)
}
