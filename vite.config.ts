import {createAppConfig} from '@nextcloud/vite-config'
import vue from '@vitejs/plugin-vue'
import {nodePolyfills} from 'vite-plugin-node-polyfills'
import {resolve} from 'path';

const APP_NAME = 'structureddiary'
const APP_VERSION = '1.0.0'
const SRC_DIR = resolve(__dirname, './src')
//const VUE_ROUTER_ENTRY = resolve(__dirname, './node_modules/vue-router/dist/vue-router.mjs')
//const VUE_ENTRY = resolve(__dirname, './node_modules/vue/dist/vue.runtime.esm-bundler.js')

export default createAppConfig({
		// entry points: {name: script}
		settings: 'src/settings.ts',
		main: 'src/main.ts',
	},
	{
		config: {
			resolve: {
				//dedupe: ['vue', 'vue-router'],
				alias: {
					'@': SRC_DIR,
				//	'vue': VUE_ENTRY,
				//	'vue-router': VUE_ROUTER_ENTRY,
				},
			},
			define: {
				appName: JSON.stringify(APP_NAME),
				appVersion: JSON.stringify(APP_VERSION),
			},
			plugins: [
				//vue(),
				nodePolyfills(),
			],
			build: {
				manifest: true,
				sourcemap: true,
				cssCodeSplit: true,
				emptyOutDir: false,
				chunkSizeWarningLimit: 2048,
				rollupOptions: {
					input: 'src/main.ts',
					output: {
						format: 'es',
						inlineDynamicImports: false,
						manualChunks: {
							vendor: ['vue', 'vue-router'],
						},
						sourcemapBaseUrl: 'http://nextcloud.dev.mcservice.eu/apps-extra/structureddiary/js/'
					},
				},
			},
			server: {
				host: '0.0.0.0',          // listen in the container
				port: 5174,
				strictPort: true,
				// Docker/VM file watchers sometimes miss FS events:
				watch: {usePolling: true, interval: 300},
				// If a reverse proxy (e.g. nginx in nextcloud-docker-dev) talks to Vite
				// using a hostname like "vite" or "nextcloud", allow it:
				allowedHosts: ['localhost', '127.0.0.1', 'nextcloud', 'vite'],
				cors: {
					// the origin you will be accessing via browser
					origin: 'http://nextcloud.dev.mcservice.eu',
				},
			},
		},
	}
)
