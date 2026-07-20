<template>
    <div class="flex h-full min-w-0 flex-col">
        <div
            class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800"
        >
            <button
                type="button"
                class="rounded-full p-1.5 text-slate-600 transition hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-900"
                aria-label="Back to messages"
                @click="emit('close')"
            >
                <ArrowLeftIcon class="h-5 w-5" />
            </button>

            <router-link v-if="participant.avatar" :to="`/@${participant.username}`">
                <img
                    :src="participant.avatar"
                    :alt="participant.username"
                    class="h-9 w-9 rounded-full object-cover"
                />
            </router-link>

            <div
                v-else
                class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                {{ (participant.username ?? '?').slice(0, 1) }}
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ participant.name || participant.username }}
                </p>
                <p class="truncate text-xs">
                    <router-link
                        :to="`/@${participant.username}`"
                        class="text-slate-500 dark:text-slate-400"
                    >
                        @{{ participant.username }}
                    </router-link>
                </p>
            </div>

            <div class="relative">
                <button
                    type="button"
                    class="rounded-full p-1.5 text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900"
                    aria-label="Conversation options"
                    @click="menuOpen = !menuOpen"
                >
                    <EllipsisHorizontalIcon class="h-5 w-5" />
                </button>
                <div
                    v-if="menuOpen"
                    class="absolute right-0 top-10 z-10 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-800 dark:bg-slate-900"
                >
                    <button
                        type="button"
                        class="block w-full px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="toggleMute"
                    >
                        {{ conversation?.muted ? 'Unmute' : 'Mute' }}
                    </button>
                    <button
                        type="button"
                        class="block w-full px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="toggleHidden"
                    >
                        {{ conversation?.hidden ? 'Unhide conversation' : 'Hide conversation' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="participant.is_remote"
            class="flex items-center gap-2 border-b border-slate-100 px-4 py-2 dark:border-slate-900"
        >
            <GlobeAltIcon class="h-4 w-4 shrink-0 text-slate-400" />
            <p class="text-xs text-slate-500 dark:text-slate-400">
                This conversation federates to {{ participant.domain }}. Messages are not end-to-end
                encrypted.
            </p>
        </div>

        <div ref="scroller" class="flex-1 overflow-y-auto px-4 py-4">
            <div ref="topSentinel" class="h-px" />
            <div v-if="thread.loading && !thread.loaded" class="flex justify-center py-8">
                <span class="text-xs text-slate-400">Loading messages</span>
            </div>
            <div class="flex flex-col gap-1">
                <DmMessageBubble
                    v-for="(message, index) in thread.messages"
                    :key="message.id"
                    :message="message"
                    :own="isOwn(message)"
                    :show-avatar="showAvatar(index)"
                    :participant="participant"
                    @retry="onRetry"
                    @delete="onDelete"
                    @report="onReport"
                />
            </div>
        </div>

        <div
            v-if="isRequest"
            class="border-t border-slate-200 px-4 py-4 dark:border-slate-800 w-full flex flex-between items-center"
        >
            <div class="flex-1 flex-col shrink-0">
                <p class="text-sm text-slate-700 dark:text-slate-200">
                    {{ participant.name || participant.username }} wants to send you messages.
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    They won't know you've seen this until you accept.
                </p>
            </div>
            <div class="flex gap-5">
                <button
                    type="button"
                    class="rounded-full bg-[#F02C56] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#d5264c]"
                    @click="accept"
                >
                    Accept
                </button>
                <button
                    type="button"
                    class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="decline"
                >
                    Decline
                </button>
            </div>
        </div>

        <div
            v-else-if="pendingAcceptance"
            class="border-t border-slate-200 px-4 py-4 dark:border-slate-800"
        >
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Request sent</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                You can send more messages once
                {{ participant.name || participant.username }} accepts.
            </p>
        </div>

        <DmComposer v-else @send="onSend" @send-media="onSendMedia" />
        <ReportModal />
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowLeftIcon, EllipsisHorizontalIcon, GlobeAltIcon } from '@heroicons/vue/24/outline'
import { useDmStore } from '~/stores/dm'
import DmMessageBubble from './DmMessageBubble.vue'
import DmComposer from './DmComposer.vue'
import { useReportModal } from '@/composables/useReportModal'
import ReportModal from '../ReportModal.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'

const props = defineProps({
    conversationId: { type: String, required: true }
})
const emit = defineEmits(['close', 'left'])
const { alertModal, confirmModal } = useAlertModal()
const { openReportModal } = useReportModal()
const store = useDmStore()
const scroller = ref(null)
const topSentinel = ref(null)
const menuOpen = ref(false)
const prepending = ref(false)
let observer = null

const conversation = computed(() => store.conversations[props.conversationId])
const thread = computed(() => store.thread(props.conversationId))
const participant = computed(() => conversation.value?.participant ?? {})
const isRequest = computed(() => conversation.value?.state === 'request')
const pendingAcceptance = computed(() => Boolean(conversation.value?.pending_acceptance))

function isOwn(message) {
    return String(message.sender_id) === String(store.meId)
}

function showAvatar(index) {
    const messages = thread.value.messages
    const message = messages[index]
    if (isOwn(message)) return false
    const next = messages[index + 1]
    return !next || String(next.sender_id) !== String(message.sender_id)
}

function scrollToBottom() {
    const el = scroller.value
    if (el) el.scrollTop = el.scrollHeight
}

async function loadOlder() {
    const t = thread.value
    if (!t.loaded || t.loading || t.done) return
    const el = scroller.value
    const prevHeight = el?.scrollHeight ?? 0
    const prevTop = el?.scrollTop ?? 0
    prepending.value = true
    try {
        await store.fetchMessages(props.conversationId, true)
        await nextTick()
        if (el) el.scrollTop = el.scrollHeight - prevHeight + prevTop
    } finally {
        prepending.value = false
    }
}

watch(
    () => thread.value.loaded,
    async (loaded) => {
        if (loaded) {
            await nextTick()
            scrollToBottom()
        }
    },
    { immediate: true }
)

watch(
    () => thread.value.messages.length,
    async (length, previous) => {
        if (prepending.value || !previous || length <= previous) return
        const el = scroller.value
        if (!el) return
        const last = thread.value.messages[length - 1]
        const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 240
        if ((last && isOwn(last)) || nearBottom) {
            await nextTick()
            scrollToBottom()
        }
    }
)

onMounted(() => {
    observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) loadOlder()
    })
    if (topSentinel.value) observer.observe(topSentinel.value)
})

onBeforeUnmount(() => observer?.disconnect())

async function onSend(text) {
    try {
        await store.sendMessage({
            conversationId: props.conversationId,
            type: 'text',
            body: text
        })
    } catch (err) {
        await alertModal('Error', err.response.data?.message)
        return
    }
}

async function onSendMedia(text, type, item) {
    try {
        await store.sendKlipyMedia({
            conversationId: props.conversationId,
            body: text || null,
            type,
            item
        })
    } catch (err) {
        await alertModal('Error', err.response.data?.message)
        return
    }
}

async function onRetry(message) {
    store.removeLocalMessage(props.conversationId, message.id)
    try {
        if (message.type === 'media' && message.klipy) {
            await store.sendKlipyMedia({
                conversationId: props.conversationId,
                body: message.body,
                type: message.klipy.type,
                item: message.klipy.item
            })
        } else {
            await store.sendMessage({
                conversationId: props.conversationId,
                type: message.type,
                body: message.body
            })
        }
    } catch {
        return
    }
}

async function onDelete(message) {
    if (!window.confirm('Delete this message for everyone?')) return
    await store.deleteMessage(props.conversationId, message.id)
}

async function onReport(message) {
    openReportModal('conversation', props.conversationId, window.location.href)
}

async function accept() {
    await store.accept(props.conversationId)
}

async function decline() {
    await store.decline(props.conversationId)
    emit('left')
}

async function toggleMute() {
    menuOpen.value = false
    await store.toggleMute(props.conversationId)
}

async function toggleHidden() {
    menuOpen.value = false
    if (conversation.value?.hidden) {
        await store.unhide(props.conversationId)
    } else {
        await store.hide(props.conversationId)
        emit('left')
    }
}
</script>
