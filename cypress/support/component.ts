import { mount } from 'cypress/vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import type { ComponentMountingOptions, MountingOptions } from 'cypress/vue'
import type { ComponentPublicInstance, DefineComponent } from 'vue'

import '../../src/settings'

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
	options.global.plugins.push(createPinia())

	return mount(component, options)
})

Cypress.on('uncaught:exception', (error) => {
	if (error.message.includes('ResizeObserver loop completed with undelivered notifications')) {
		return false
	}

	return true
})
