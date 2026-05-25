import { mount } from 'cypress/vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import type { ComponentMountingOptions, MountingOptions } from 'cypress/vue'
import type { ComponentPublicInstance, DefineComponent } from 'vue'

import '../../src/settings'

function isPiniaPlugin(plugin: unknown): boolean {
	if (Array.isArray(plugin)) {
		return isPiniaPlugin(plugin[0])
	}

	return typeof plugin === 'object'
		&& plugin !== null
		&& 'install' in plugin
		&& 'state' in plugin
}

declare global {
	namespace Cypress {
		interface Chainable {
			mount(component: DefineComponent<any, any, any> | object, options?: MountingOptions<any>): Chainable<ComponentPublicInstance>
		}
	}
}

Cypress.Commands.add('mount', (component, options: ComponentMountingOptions<any> = {}) => {
	options.global ??= {}
	options.global.plugins ??= []
	options.global.plugins.push([PrimeVue, {
		theme: {
			preset: Aura,
			options: {
				prefix: 'p',
				darkModeSelector: 'system',
				cssLayer: false,
			},
		},
	}])
	if (!options.global.plugins.some(isPiniaPlugin)) {
		options.global.plugins.push(createPinia())
	}

	return mount(component, options)
})

Cypress.on('uncaught:exception', (error) => {
	if (error.message.includes('ResizeObserver loop completed with undelivered notifications')) {
		return false
	}

	return true
})
