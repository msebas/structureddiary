import { defineConfig } from 'cypress'
import vue from '@vitejs/plugin-vue'
import { nodePolyfills } from 'vite-plugin-node-polyfills'
import { resolve } from 'path'
import { fileURLToPath } from 'url'

const configDir = fileURLToPath(new URL('.', import.meta.url))

const baseUrl = process.env.CYPRESS_BASE_URL ?? 'http://nextcloud.local/index.php/apps/structureddiary/'

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
			},
		},
	},
})
