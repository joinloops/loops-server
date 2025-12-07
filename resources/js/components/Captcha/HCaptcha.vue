<template>
    <div
        ref="hcaptchaRef"
        class="h-captcha"
        :data-sitekey="sitekey"
        :data-theme="theme"
        :data-size="size"
        data-callback="onHCaptchaSuccess"
        data-error-callback="onHCaptchaError"
        data-expired-callback="onHCaptchaExpired"
    ></div>
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
        default: 'light', // 'light', 'dark'
        validator: (value) => ['light', 'dark'].includes(value)
    },
    size: {
        type: String,
        default: 'normal', // 'normal', 'compact', 'invisible'
        validator: (value) => ['normal', 'compact', 'invisible'].includes(value)
    }
})

const emit = defineEmits(['success', 'error', 'expired'])

const hcaptchaRef = ref(null)
let widgetId = null

window.onHCaptchaSuccess = (token) => {
    emit('success', token)
}

window.onHCaptchaError = (error) => {
    emit('error', error)
}

window.onHCaptchaExpired = () => {
    emit('expired')
}

const loadHCaptcha = () => {
    if (window.hcaptcha) {
        renderHCaptcha()
        return
    }

    const script = document.createElement('script')
    script.src = 'https://js.hcaptcha.com/1/api.js'
    script.async = true
    script.defer = true
    script.onload = renderHCaptcha
    document.head.appendChild(script)
}

const renderHCaptcha = () => {
    if (window.hcaptcha && hcaptchaRef.value) {
        widgetId = window.hcaptcha.render(hcaptchaRef.value, {
            sitekey: props.sitekey,
            theme: props.theme,
            size: props.size,
            callback: window.onHCaptchaSuccess,
            'error-callback': window.onHCaptchaError,
            'expired-callback': window.onHCaptchaExpired
        })
    }
}

const reset = () => {
    if (window.hcaptcha && widgetId !== null) {
        window.hcaptcha.reset(widgetId)
    }
}

const getResponse = () => {
    if (window.hcaptcha && widgetId !== null) {
        return window.hcaptcha.getResponse(widgetId)
    }
    return null
}

const execute = () => {
    if (window.hcaptcha && widgetId !== null) {
        window.hcaptcha.execute(widgetId)
    }
}

defineExpose({
    reset,
    getResponse,
    execute
})

onMounted(() => {
    loadHCaptcha()
})

onUnmounted(() => {
    if (window.hcaptcha && widgetId !== null) {
        window.hcaptcha.remove(widgetId)
    }
})
</script>
