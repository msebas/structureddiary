import {createAppConfig} from '@nextcloud/vite-config'
import vue from '@vitejs/plugin-vue'
import {nodePolyfills} from 'vite-plugin-node-polyfills'
import {resolve} from 'path';

export default createAppConfig({
		// entry points: {name: script}
		settings: 'src/settings.ts',
		main: 'src/main.ts',
	},
	{
		config: {
			resolve: {
				alias: {
					'@': resolve(__dirname, './src'),
				},
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
						sourcemapBaseUrl: 'http://nextcloud.local/apps-extra/structureddiary/js/'
					},
				},
			},
			server: {
				host: '0.0.0.0',          // listen in the container
				port: 5173,
				strictPort: true,
				// Docker/VM file watchers sometimes miss FS events:
				watch: {usePolling: true, interval: 300},
				// If a reverse proxy (e.g. nginx in nextcloud-docker-dev) talks to Vite
				// using a hostname like "vite" or "nextcloud", allow it:
				allowedHosts: ['localhost', '127.0.0.1', 'nextcloud', 'vite'],
				cors: {
					// the origin you will be accessing via browser
					origin: 'http://nextcloud.local',
				},
			},
		},
	}
)