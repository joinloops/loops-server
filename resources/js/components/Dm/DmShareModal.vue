<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

        <div
            class="relative flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-t-2xl bg-white sm:rounded-2xl dark:bg-slate-950"
        >
            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                    {{ video ? 'Share loop' : 'New message' }}
                </h2>
                <button
                    type="button"
                    class="rounded-full p-1.5 text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-900"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <div v-if="video" class="flex items-center gap-3 px-4 py-3">
                <div class="h-16 w-9 shrink-0 overflow-hidden rounded-lg bg-slate-900">
                    <img
                        v-if="video.thumbnail"
                        :src="video.thumbnail"
                        alt=""
                        class="h-full w-full object-cover"
                    />
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300">Sharing this loop</p>
            </div>

            <div class="px-4 pb-2" :class="video ? '' : 'pt-3'">
                <div
                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900"
                >
                    <MagnifyingGlassIcon class="h-4 w-4 shrink-0 text-slate-400" />
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Search people"
                        class="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-slate-100"
                    />
                </div>
            </div>

            <div v-if="selected.length" class="flex flex-wrap gap-1.5 px-4 pb-2">
                <button
                    v-for="account in selected"
                    :key="account.id"
                    type="button"
                    class="flex items-center gap-1 rounded-full bg-slate-100 py-1 pl-2.5 pr-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="toggle(account)"
                >
                    @{{ account.username }}
                    <XMarkIcon class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="min-h-32 flex-1 overflow-y-auto">
                <div class="flex items-center justify-between px-4 pb-2 text-xs">
                    <span class="text-slate-400"> Select up to {{ MAX_SELECTED }} people </span>

                    <span
                        :class="
                            selected.length >= MAX_SELECTED
                                ? 'font-medium text-[#F02C56]'
                                : 'text-slate-400'
                        "
                    >
                        {{ selected.length }}/{{ MAX_SELECTED }}
                    </span>
                </div>
                <button
                    v-for="account in results"
                    :key="account.id"
                    type="button"
                    :disabled="isSelectionLimitReached() && !isSelected(account)"
                    :class="[
                        'flex w-full items-center gap-3 px-4 py-2.5 text-left transition',
                        isSelectionLimitReached() && !isSelected(account)
                            ? 'cursor-not-allowed opacity-40'
                            : 'hover:bg-slate-50 dark:hover:bg-slate-900/60'
                    ]"
                    @click="toggle(account)"
                >
                    <img
                        v-if="account.avatar"
                        :src="account.avatar"
                        :alt="account.username"
                        class="h-9 w-9 rounded-full object-cover"
                        onerror="
                            this.src = '/storage/avatars/default.jpg'
                            this.onerror = null
                        "
                    />
                    <div
                        v-else
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ (account.username ?? '?').slice(0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ account.name || account.username }}
                        </p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                            @{{ account.username }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition',
                            isSelected(account)
                                ? 'border-[#F02C56] bg-[#F02C56]'
                                : 'border-slate-300 dark:border-slate-700'
                        ]"
                    >
                        <span v-if="isSelected(account)" class="h-2 w-2 rounded-full bg-white" />
                    </span>
                </button>

                <p v-if="searching" class="px-4 py-3 text-xs text-slate-400">Searching</p>
                <p
                    v-else-if="query.trim().length >= 2 && !results.length"
                    class="px-4 py-3 text-xs text-slate-400"
                >
                    {{
                        restricted
                            ? 'No mutual followers matched. Some accounts, like yours, can only message mutuals.'
                            : 'No people found. Try a full address like user@server.tld.'
                    }}
                </p>
            </div>

            <div v-if="errors.length" class="px-4 pb-1">
                <p v-for="error in errors" :key="error" class="text-xs text-red-500">{{ error }}</p>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                <div class="relative">
                    <textarea
                        v-model="body"
                        rows="2"
                        :maxlength="MAX_BODY_LENGTH"
                        :placeholder="video ? 'Add a message (optional)' : 'Write a message'"
                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3.5 pb-7 pt-2.5 pr-16 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-slate-700"
                    />

                    <span
                        class="pointer-events-none absolute bottom-2.5 right-3 text-xs tabular-nums"
                        :class="
                            body.length >= MAX_BODY_LENGTH
                                ? 'font-medium text-[#F02C56]'
                                : 'text-slate-400 dark:text-slate-500'
                        "
                        aria-live="polite"
                    >
                        {{ body.length }}/{{ MAX_BODY_LENGTH }}
                    </span>
                </div>

                <p v-if="selected.length > 1" class="mt-2 text-center text-[11px] text-slate-400">
                    This starts a group conversation with {{ selected.length }} people.
                </p>

                <button
                    type="button"
                    :disabled="sending || !selected.length || (!video && !body.trim())"
                    class="mt-2 w-full rounded-full bg-[#F02C56] py-2.5 text-sm font-semibold text-white transition enabled:hover:bg-[#d5264c] disabled:opacity-40"
                    @click="send"
                >
                    {{ sending ? 'Sending' : selected.length > 1 ? 'Send to group' : 'Send' }}
                </button>
                <p class="mt-2 text-center text-[11px] text-slate-400">
                    Messages are not end-to-end encrypted.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import dmApi from '~/api/dm'
import { useDmStore } from '~/stores/dm'

const props = defineProps({
    video: { type: Object, default: null }
})
const emit = defineEmits(['close', 'sent'])

const store = useDmStore()

const MAX_SELECTED = 11
const MAX_BODY_LENGTH = 500

const query = ref('')
const results = ref([])
const searching = ref(false)
const restricted = ref(false)
const selected = ref([])
const body = ref('')
const sending = ref(false)
const errors = ref([])
const createdConversationId = ref(null)
let timer = null

watch(query, (value) => {
    clearTimeout(timer)
    const q = value.trim()
    if (q.length < 2) {
        results.value = []
        return
    }
    timer = setTimeout(async () => {
        searching.value = true
        try {
            const { data } = await dmApi.searchAccounts(q)
            const accounts = Array.isArray(data) ? data : (data.data ?? [])
            restricted.value = Boolean(data?.meta?.restricted)
            results.value = accounts.filter((a) => String(a.id) !== String(store.meId))
        } catch {
            results.value = []
        } finally {
            searching.value = false
        }
    }, 300)
})

watch(selected, () => {
    createdConversationId.value = null
})

function isSelectionLimitReached() {
    return selected.value.length >= MAX_SELECTED
}

function isSelected(account) {
    return selected.value.some((a) => String(a.id) === String(account.id))
}

function toggle(account) {
    if (isSelected(account)) {
        selected.value = selected.value.filter((a) => String(a.id) !== String(account.id))

        return
    }

    if (isSelectionLimitReached()) {
        return
    }

    selected.value = [...selected.value, account]
}

async function send() {
    if (sending.value || !selected.value.length) return
    if (!props.video && !body.value.trim()) return
    sending.value = true
    errors.value = []

    const messagePayload = {
        type: props.video ? 'loop_share' : 'text',
        body: body.value.trim() || null,
        videoId: props.video?.id ?? null
    }

    try {
        if (selected.value.length === 1 && !createdConversationId.value) {
            const message = await store.sendMessage({
                recipientId: selected.value[0].id,
                ...messagePayload
            })
            emit('sent', String(message.conversation_id))
            emit('close')
            return
        }

        let conversationId = createdConversationId.value

        if (!conversationId) {
            const conversation = await store.createGroup(
                selected.value.map((account) => account.id)
            )
            conversationId = String(conversation.id)
            createdConversationId.value = conversationId
        }

        await store.sendMessage({
            conversationId,
            ...messagePayload
        })

        emit('sent', conversationId)
        emit('close')
    } catch (err) {
        errors.value = [err.response?.data?.message ?? "Couldn't send your message."]
    } finally {
        sending.value = false
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>
