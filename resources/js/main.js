import { createApp } from 'vue'
import router from './router'
import store from './store'
import jQuery from 'jquery'

window.jQuery = jQuery
window.$ = jQuery

const app = createApp({})

app.use(store)
app.use(router)

app.mount('#app')
