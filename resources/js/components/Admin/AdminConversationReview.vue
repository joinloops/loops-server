<template>
    <div
        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
    >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Conversation</h3>
                <span
                    class="px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full"
                >
                    {{ details.type === 'group' ? 'Group chat' : 'Direct message' }}
                </span>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ formatCount(details.messages_count) }}
                    {{ details.messages_count === 1 ? 'message' : 'messages' }}
                </span>
            </div>
            <button
                v-if="revealed"
                type="button"
                :disabled="loading"
                class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                @click="reload"
            >
                <ArrowPathIcon class="h-4 w-4" />
                Refresh
            </button>
        </div>

        <p v-if="details.title" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Group name:
            <span class="font-medium text-gray-900 dark:text-white">{{ details.title }}</span>
        </p>

        <div class="flex flex-wrap gap-3 mb-4">
            <router-link
                v-for="participant in details.participants"
                :key="participant.id"
                :to="`/admin/profiles/${participant.id}`"
                class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <img
                    :src="participant.avatar"
                    :alt="participant.username"
                    class="w-9 h-9 rounded-full flex-shrink-0 object-cover"
                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ participant.name || participant.username || 'Unknown account' }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                        {{ handleFor(participant) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-1">
                    <span
                        v-if="isReporter(participant.id)"
                        class="px-2 py-0.5 text-[11px] font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded-full"
                    >
                        Reporter
                    </span>
                    <span
                        v-if="participant.state && participant.state !== 'active'"
                        class="px-2 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 rounded-full"
                    >
                        {{ stateLabel(participant.state) }}
                    </span>
                    <span
                        v-if="participant.status && participant.status !== 'active'"
                        class="px-2 py-0.5 text-[11px] font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full capitalize"
                    >
                        {{ participant.status }}
                    </span>
                </div>
            </router-link>
        </div>

        <div
            v-if="!revealed"
            class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-6 text-center"
        >
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                These messages are private. Opening the conversation is recorded in the admin audit
                log.
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors cursor-pointer"
                @click="reveal"
            >
                <ChatBubbleLeftRightIcon class="h-4 w-4" />
                Review conversation
            </button>
        </div>

        <div v-else>
            <div
                v-if="error"
                class="rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300"
            >
                {{ error }}
            </div>

            <div
                v-else
                ref="scroller"
                class="max-h-[36rem] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4"
            >
                <div v-if="nextCursor" class="flex justify-center mb-4">
                    <button
                        type="button"
                        :disabled="loading"
                        class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-xs font-medium rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="loadOlder"
                    >
                        {{ loading ? 'Loading' : 'Load older messages' }}
                    </button>
                </div>

                <div v-if="loading && !messages.length" class="animate-pulse space-y-4">
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded w-2/3"></div>
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded w-3/5"></div>
                </div>

                <p
                    v-else-if="!messages.length"
                    class="py-8 text-center text-sm text-gray-600 dark:text-gray-400"
                >
                    This conversation has no messages.
                </p>

                <div v-else class="space-y-4">
                    <div v-for="message in messages" :key="message.id" class="flex gap-3">
                        <router-link
                            :to="`/admin/profiles/${message.sender_id}`"
                            class="flex-shrink-0"
                        >
                            <img
                                :src="message.sender?.avatar || '/storage/avatars/default.jpg'"
                                :alt="message.sender?.username"
                                class="w-8 h-8 rounded-full object-cover"
                                @error="$event.target.src = '/storage/avatars/default.jpg'"
                            />
                        </router-link>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1">
                                <router-link
                                    :to="`/admin/profiles/${message.sender_id}`"
                                    class="text-sm font-medium text-gray-900 dark:text-white hover:underline"
                                >
                                    {{ handleFor(message.sender) }}
                                </router-link>
                                <span
                                    v-if="isReporter(message.sender_id)"
                                    class="px-2 py-0.5 text-[11px] font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded-full"
                                >
                                    Reporter
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDateTime(message.created_at) }}
                                </span>
                                <span
                                    v-if="message.edited_at"
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Edited {{ formatDateTime(message.edited_at) }}
                                </span>
                                <span
                                    v-if="message.deleted_at"
                                    class="px-2 py-0.5 text-[11px] font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full"
                                >
                                    Deleted {{ formatDateTime(message.deleted_at) }}
                                </span>
                            </div>

                            <div
                                :class="[
                                    'inline-block max-w-full rounded-lg border px-3 py-2',
                                    message.deleted_at
                                        ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40'
                                        : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'
                                ]"
                            >
                                <router-link
                                    v-if="message.type === 'loop_share' && message.video"
                                    :to="`/admin/videos/${message.video.id}`"
                                    class="flex items-center gap-3 mb-1"
                                >
                                    <img
                                        :src="message.video.media?.thumbnail"
                                        alt=""
                                        class="w-12 h-16 rounded object-cover flex-shrink-0 bg-gray-200 dark:bg-gray-700"
                                        @error="
                                            $event.target.src =
                                                '/storage/videos/video-placeholder.jpg'
                                        "
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                        >
                                            Shared video
                                        </p>
                                        <p class="text-sm text-gray-900 dark:text-white truncate">
                                            {{ message.video.caption || 'No caption' }}
                                        </p>
                                        <p
                                            v-if="message.video.account?.username"
                                            class="text-xs text-gray-600 dark:text-gray-400"
                                        >
                                            by @{{ message.video.account.username }}
                                        </p>
                                    </div>
                                </router-link>
                                <p
                                    v-else-if="message.type === 'loop_share'"
                                    class="text-sm italic text-gray-500 dark:text-gray-400"
                                >
                                    Shared video is no longer available
                                </p>

                                <div
                                    v-if="message.media?.length"
                                    :class="[
                                        'w-72 max-w-full overflow-hidden rounded mb-1',
                                        message.media.length > 1 ? 'grid grid-cols-2 gap-1' : ''
                                    ]"
                                >
                                    <template v-for="media in message.media" :key="media.id">
                                        <video
                                            v-if="(media.mime_type || '').startsWith('video/')"
                                            controls
                                            playsinline
                                            preload="metadata"
                                            :poster="media.preview_url || undefined"
                                            class="h-auto w-full bg-gray-900"
                                        >
                                            <source
                                                :src="media.url"
                                                :type="media.mime_type || undefined"
                                            />
                                        </video>
                                        <audio
                                            v-else-if="media.type === 'audio'"
                                            controls
                                            preload="metadata"
                                            :src="media.url"
                                            class="w-full"
                                        />
                                        <a
                                            v-else-if="
                                                media.type === 'image' || media.type === 'gif'
                                            "
                                            :href="media.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <img
                                                :src="media.url"
                                                :alt="media.description || ''"
                                                loading="lazy"
                                                class="h-auto w-full bg-gray-200 dark:bg-gray-700"
                                            />
                                        </a>
                                        <a
                                            v-else
                                            :href="media.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block text-sm text-blue-600 dark:text-blue-400 underline"
                                        >
                                            {{ media.description || 'Attachment' }}
                                        </a>
                                    </template>
                                </div>

                                <p
                                    v-if="message.body"
                                    class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap break-words"
                                >
                                    {{ message.body }}
                                </p>
                                <p
                                    v-else-if="
                                        !message.media?.length && message.type !== 'loop_share'
                                    "
                                    class="text-sm italic text-gray-500 dark:text-gray-400"
                                >
                                    No text content
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import { ArrowPathIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { reportsApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    conversation: { type: Object, required: true },
    reporterId: { type: [String, Number], default: null }
})

const { formatCount, formatDateTime } = useUtils()

const details = ref(props.conversation)
const revealed = ref(false)
const loading = ref(false)
const error = ref('')
const messages = ref([])
const nextCursor = ref(null)
const scroller = ref(null)

const isReporter = (id) => props.reporterId !== null && String(id) === String(props.reporterId)

const handleFor = (profile) => {
    if (!profile?.username) return 'Unknown account'
    return profile.username.startsWith('@') ? profile.username : `@${profile.username}`
}

const stateLabel = (state) => {
    if (state === 'request') return 'Pending request'
    if (state === 'left') return 'Left'
    return state
}

const fetchMessages = async (cursor = null) => {
    const response = await reportsApi.getConversationMessages(details.value.id, cursor)
    nextCursor.value = response.meta?.next_cursor || null
    return [...response.data].reverse()
}

const load = async () => {
    loading.value = true
    error.value = ''

    try {
        const [conversation, page] = await Promise.all([
            reportsApi.getConversation(details.value.id),
            fetchMessages()
        ])
        details.value = conversation.data
        messages.value = page
        await nextTick()
        if (scroller.value) {
            scroller.value.scrollTop = scroller.value.scrollHeight
        }
    } catch (err) {
        console.error('Error fetching conversation:', err)
        error.value = 'Unable to load this conversation. It may have been removed.'
    } finally {
        loading.value = false
    }
}

const loadOlder = async () => {
    if (loading.value || !nextCursor.value) return

    loading.value = true
    const previousHeight = scroller.value?.scrollHeight ?? 0

    try {
        const older = await fetchMessages(nextCursor.value)
        messages.value = [...older, ...messages.value]
        await nextTick()
        if (scroller.value) {
            scroller.value.scrollTop += scroller.value.scrollHeight - previousHeight
        }
    } catch (err) {
        console.error('Error fetching older messages:', err)
    } finally {
        loading.value = false
    }
}

const reveal = async () => {
    revealed.value = true
    await load()
}

const reload = async () => {
    messages.value = []
    nextCursor.value = null
    await load()
}

watch(
    () => props.conversation,
    (conversation) => {
        details.value = conversation
        revealed.value = false
        error.value = ''
        messages.value = []
        nextCursor.value = null
    }
)
</script>
