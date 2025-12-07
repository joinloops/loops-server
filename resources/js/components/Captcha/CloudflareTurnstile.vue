<template>
    <div ref="turnstileRef" class="cf-turnstile"></div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    sitekey: {
        type: String,
        required: true
    },
    theme: {
        type: String,
        default: 'auto', // 'light', 'dark', 'auto'
        validator: (value) => ['light', 'dark', 'auto'].includes(value)
    },
    size: {
        type: String,
        default: 'normal', // 'normal', 'compact'
        validator: (value) => ['normal', 'compact'].includes(value)
    }
})

const emit = defineEmits(['success', 'error', 'expired'])

const turnstileRef = ref(null)
let widgetId = null

const loadTurnstile = () => {
    if (window.turnstile) {
        renderTurnstile()
        return
    }

    const script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'
    script.async = true
    script.defer = true
    script.onload = renderTurnstile
    document.head.appendChild(script)
}

const renderTurnstile = () => {
    if (window.turnstile && turnstileRef.value && widgetId === null) {
        try {
            widgetId = window.turnstile.render(turnstileRef.value, {
                sitekey: props.sitekey,
                theme: props.theme,
                size: props.size,
                callback: (token) => {
                    emit('success', token)
                },
                'error-callback': (error) => {
                    emit('error', error)
                },
                'expired-callback': () => {
                    emit('expired')
                }
            })
        } catch (error) {
            console.error('Failed to render Turnstile:', error)
        }
    }
}

const reset = () => {
    if (window.turnstile && widgetId !== null) {
        try {
            window.turnstile.reset(widgetId)
        } catch (error) {
            console.error('Failed to reset Turnstile:', error)
        }
    }
}

const getResponse = () => {
    if (window.turnstile && widgetId !== null) {
        try {
            return window.turnstile.getResponse(widgetId)
        } catch (error) {
            console.error('Failed to get Turnstile response:', error)
            return null
        }
    }
    return null
}

defineExpose({
    reset,
    getResponse
})

onMounted(() => {
    loadTurnstile()
})

onUnmounted(() => {
    if (window.turnstile && widgetId !== null) {
        try {
            window.turnstile.remove(widgetId)
            widgetId = null
        } catch (error) {
            console.error('Failed to remove Turnstile widget:', error)
        }
    }
})
</script>
