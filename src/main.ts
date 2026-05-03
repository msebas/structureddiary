import {createApp} from 'vue'
import {createPinia} from 'pinia'
import {router} from '@/router'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura';
import VueEasymde from 'vue3-easymde'
import 'codemirror/lib/codemirror.css'
import "easymde/dist/easymde.min.css"
import '@/css/workspace-card.css'

import App from './App.vue'

const app = createApp(App)

app.use(VueEasymde)
app.use(PrimeVue, {
    // Default theme configuration
    theme: {
        preset: Aura,
        options: {
            prefix: 'p',
            darkModeSelector: 'system',
            cssLayer: false
        }
    }
 })
app.use(createPinia())
app.use(router)
app.mount('#structureddiary')
