<template>
    <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
        <div class="flex items-center gap-2">
            <textarea
                ref="input"
                v-model="body"
                rows="1"
                maxlength="2000"
                placeholder="Send a message..."
                class="max-h-36 flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-slate-700"
                @input="resize"
                @keydown="onKeydown"
            />
            <button
                v-if="hasKlipy"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                aria-label="Open media picker"
                @click="pickerOpen = true"
            >
                <GifIcon class="w-7 h-7" />
            </button>
            <button
                type="button"
                :disabled="!body.trim()"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#F02C56] text-white transition enabled:hover:bg-[#d5264c] disabled:opacity-40"
                aria-label="Send message"
                @click="submit"
            >
                <PaperAirplaneIcon class="h-4.5 w-4.5" />
            </button>
        </div>
        <KlipyPicker :open="pickerOpen" @close="pickerOpen = false" @select="onSelectKlipy" />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { PaperAirplaneIcon, GifIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['send', 'sendMedia'])
const hasKlipy = window.appConfig?.hasKlipy

const body = ref('')
const input = ref(null)
const pickerOpen = ref(false)

function resize() {
    const el = input.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`
}

const onSelectKlipy = async ({ type, item }) => {
    pickerOpen.value = false

    const text = body.value.trim()

    try {
        emit('sendMedia', text, type, item)
    } catch (error) {
        console.error('Failed to post media comment:', error)
    } finally {
        body.value = ''
        requestAnimationFrame(() => {
            resize()
            input.value?.focus()
        })
    }
}

function submit() {
    const text = body.value.trim()
    if (!text) return
    emit('send', text)
    body.value = ''
    requestAnimationFrame(() => {
        resize()
        input.value?.focus()
    })
}

function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault()
        submit()
    }
}
</script>
