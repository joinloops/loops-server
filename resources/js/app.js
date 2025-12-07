/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap'
import { createApp } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue/client'
import axiosPlugin from './plugins/axios'
import { VueQueryPlugin } from '@tanstack/vue-query'
import AlertModalPlugin from '@/composables/useAlertModal.js'
import storePlugin from './plugins/stores'
import App from './App.vue'
import router from './routes/index'
import '@mdi/font/css/materialdesignicons.min.css'
import 'boxicons/css/boxicons.min.css'
import 'remixicon/fonts/remixicon.css'
import '../sass/next.css'
import i18n from './i18n/locales'

const app = createApp(App)
const head = createHead()
const pinia = createPinia()

Object.entries(import.meta.glob('./**/*.vue', { eager: true })).forEach(([path, definition]) => {
    app.component(
        path
            .split('/')
            .pop()
            .replace(/\.\w+$/, ''),
        definition.default
    )
})

app.config.globalProperties.$appConfig = window.appConfig
app.provide('appConfig', window.appConfig)
app.provide('appCaptcha', window.appCaptcha)

app.use(pinia)
    .use(head)
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
