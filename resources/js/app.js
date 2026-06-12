import './bootstrap'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue/client'
import axiosPlugin from './plugins/axios'
import { VueQueryPlugin } from '@tanstack/vue-query'
import AlertModalPlugin from '@/composables/useAlertModal.js'
import storePlugin from './plugins/stores'
import App from './App.vue'
import router from './routes/index'
import i18n from './i18n/locales'

import AdminLayout from '@/layouts/AdminLayout.vue'
import BlankLayout from '@/layouts/BlankLayout.vue'
import FeedLayout from '@/layouts/FeedLayout.vue'
import FullLayout from '@/layouts/FullLayout.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import StudioLayout from '@/layouts/StudioLayout.vue'

import '../css/fonts.css'
import '../sass/next.css'

const app = createApp(App)

import TikTokLayout from '@/layouts/TikTokLayout.vue'

app.component('AdminLayout', AdminLayout)
app.component('BlankLayout', BlankLayout)
app.component('FeedLayout', FeedLayout)
app.component('FullLayout', FullLayout)
app.component('MainLayout', MainLayout)
app.component('SettingsLayout', SettingsLayout)
app.component('StudioLayout', StudioLayout)
app.component('TikTokLayout', TikTokLayout)

app.config.globalProperties.$appConfig = window.appConfig
app.provide('appConfig', window.appConfig)
app.provide('appCaptcha', window.appCaptcha)

app.use(createPinia())
    .use(createHead())
    .use(axiosPlugin)
    .use(router)
    .use(storePlugin)
    .use(i18n)
    .use(AlertModalPlugin)
    .use(VueQueryPlugin, {
        queryClientConfig: {
            defaultOptions: {
                queries: {
                    staleTime: 1000 * 60 * 5,
                    refetchOnWindowFocus: false
                }
            }
        }
    })
    .mount('#app')
