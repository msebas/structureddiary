import {createApp} from 'vue'
import {createPinia} from 'pinia'
import {router} from '@/router'
import VueEasymde from 'vue3-easymde'
import 'codemirror/lib/codemirror.css'
import "easymde/dist/easymde.min.css"

import App from './App.vue'

const app = createApp(App)

app.use(VueEasymde)
app.use(createPinia())
app.use(router)
app.mount('#structureddiary')
