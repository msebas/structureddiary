import AdminSettings from '@/components/settings/AdminSettings.vue'

describe('Analysis admin settings', () => {
	it('checks the analysis service health immediately after saving settings', () => {
		let saveRequests = 0
		cy.intercept('POST', '/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings', (request) => {
			saveRequests += 1
			request.reply({ ocs: { data: { service_url: 'https://analysis.example' } } })
		}).as('saveSettings')
		cy.intercept('POST', '/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings/test', (request) => {
			request.reply({ ocs: { data: { ok: true, health: { ok: true } } } })
		}).as('healthcheck')

		cy.mount(AdminSettings, {
			props: {
				saveUrl: '/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings',
				testUrl: '/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings/test',
				initialServiceUrl: '',
				serviceSecretConfigured: false,
				initialOutputBaseFolder: '/StructuredDiary/Analyses',
				httpsWarning: false,
			},
		})

		cy.get('#structureddiary-analysis-service-url').type('https://analysis.example')
		cy.get('#structureddiary-analysis-service-secret').type('service-secret')
		cy.contains('button', 'Save').click()
		cy.wait('@saveSettings')
		cy.wait('@healthcheck')
		cy.wrap(null).then(() => expect(saveRequests).to.eq(1))
		cy.contains('Analysis settings saved.').should('be.visible')
	})
})
