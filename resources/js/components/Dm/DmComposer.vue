<template>
    <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
        <div class="flex items-end gap-2">
            <div class="relative flex-1">
                <textarea
                    ref="input"
                    v-model="body"
                    rows="1"
                    :maxlength="MAX_LENGTH"
                    placeholder="Send a message..."
                    class="block max-h-36 min-h-10 w-full resize-none overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-4 pr-16 text-sm leading-5 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-slate-700"
                    @input="resize"
                />

                <span
                    class="pointer-events-none absolute bottom-3 right-3 text-xs tabular-nums text-slate-400 dark:text-slate-500"
                    aria-hidden="true"
                >
                    {{ body.length }}/{{ MAX_LENGTH }}
                </span>
            </div>

            <button
                v-if="hasKlipy"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                aria-label="Open media picker"
                @click="pickerOpen = true"
            >
                <GifIcon class="h-7 w-7" />
            </button>

            <button
                type="button"
                :disabled="!body.trim()"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#F02C56] text-white transition enabled:hover:bg-[#d5264c] disabled:cursor-not-allowed disabled:opacity-40"
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
import { nextTick, ref } from 'vue'
import { GifIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline'

const MAX_LENGTH = 500
const MAX_HEIGHT = 144

const emit = defineEmits(['send', 'sendMedia'])

const hasKlipy = window.appConfig?.hasKlipy
const body = ref('')
const input = ref(null)
const pickerOpen = ref(false)

function resize() {
    const el = input.value

    if (!el) {
        return
    }

    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, MAX_HEIGHT)}px`
    el.style.overflowY = el.scrollHeight > MAX_HEIGHT ? 'auto' : 'hidden'
}

async function resetInput() {
    body.value = ''

    await nextTick()

    resize()
    input.value?.focus()
}

function onSelectKlipy({ type, item }) {
    pickerOpen.value = false

    emit('sendMedia', body.value.trim(), type, item)
    resetInput()
}

function submit() {
    const text = body.value.trim()

    if (!text) {
        return
    }

    emit('send', text)
    resetInput()
}
</script>
