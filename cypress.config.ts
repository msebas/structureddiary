import { defineConfig } from 'cypress'
import vue from '@vitejs/plugin-vue'
import { nodePolyfills } from 'vite-plugin-node-polyfills'
import { resolve } from 'path'
import { fileURLToPath } from 'url'

const configDir = fileURLToPath(new URL('.', import.meta.url))

const baseUrl = process.env.CYPRESS_BASE_URL ?? 'http://nextcloud.dev.mcservice.eu/index.php/apps/structureddiary/'

export default defineConfig({
	video: false,
	screenshotOnRunFailure: true,
	fixturesFolder: 'cypress/fixtures',
	e2e: {
		baseUrl,
		specPattern: 'cypress/e2e/**/*.cy.ts',
		supportFile: 'cypress/support/e2e.ts',
	},
	component: {
		specPattern: 'cypress/component/**/*.cy.ts',
		supportFile: 'cypress/support/component.ts',
		devServer: {
			framework: 'vue',
			bundler: 'vite',
			viteConfig: {
				resolve: {
					alias: {
						'@': resolve(configDir, './src'),
					},
				},
				plugins: [
					vue(),
					nodePolyfills(),
				],
				optimizeDeps: {
					include: [
						'@nextcloud/vue/components/NcAppContent',
						'@nextcloud/vue/components/NcAppNavigation',
						'@nextcloud/vue/components/NcAppNavigationItem',
						'@nextcloud/vue/components/NcAppNavigationSearch',
						'@nextcloud/vue/components/NcButton',
						'@nextcloud/vue/components/NcCheckboxRadioSwitch',
						'@nextcloud/vue/components/NcContent',
						'@nextcloud/vue/components/NcDateTimePicker',
						'@nextcloud/vue/components/NcDialog',
						'@nextcloud/vue/components/NcIconSvgWrapper',
						'@nextcloud/vue/components/NcNoteCard',
						'@nextcloud/vue/components/NcRichText',
						'@nextcloud/vue/components/NcSelect',
						'@nextcloud/vue/components/NcSelectUsers',
						'@nextcloud/vue/components/NcTextArea',
						'@nextcloud/vue/components/NcTextField',
						'easymde',
						'primevue/floatlabel',
						'primevue/rating',
						'primevue/select',
						'vue3-easymde',
					],
				},
			},
		},
	},
})
