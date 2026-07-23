<template>
    <div :class="['group flex items-end gap-2', own ? 'justify-end' : 'justify-start']">
        <template v-if="!own">
            <img
                v-if="showAvatar && avatarUser.avatar"
                :src="avatarUser.avatar"
                :alt="avatarUser.username"
                class="h-7 w-7 rounded-full object-cover"
                onerror="
                    this.src = '/storage/avatars/default.jpg'
                    this.onerror = null
                "
            />
            <div
                v-else-if="showAvatar"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                {{ (avatarUser.username ?? '?').slice(0, 1) }}
            </div>
            <div v-else class="w-7 shrink-0" />
        </template>

        <span
            v-if="own"
            class="mb-1 shrink-0 text-[11px] text-slate-400 opacity-0 transition group-hover:opacity-100"
        >
            {{ timeLabel }}
        </span>

        <button
            v-if="deletable"
            type="button"
            class="rounded-full p-1.5 text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-red-500 dark:hover:bg-slate-900"
            aria-label="Delete message"
            @click="emit('delete', message)"
        >
            <TrashIcon class="h-4 w-4" />
        </button>

        <div :class="['max-w-[75%]', message.pending ? 'opacity-60' : '']" :title="timeTitle">
            <p
                v-if="showName && senderName"
                class="mb-0.5 px-1 text-[11px] font-medium text-slate-500 dark:text-slate-400"
            >
                {{ senderName }}
            </p>

            <div
                v-if="message.type === 'loop_share' && video"
                :class="[
                    'overflow-hidden rounded-2xl',
                    own
                        ? 'rounded-br-md bg-[#F02C56]'
                        : 'rounded-bl-md bg-slate-100 dark:bg-slate-800',
                    videoPortrait ? 'w-44' : 'w-72 max-w-full'
                ]"
            >
                <a :href="video.url" class="block bg-slate-900 transition hover:opacity-90">
                    <div class="relative" :style="{ aspectRatio: videoAspect }">
                        <img
                            v-if="videoThumb"
                            :src="videoThumb"
                            alt=""
                            :class="[
                                'absolute inset-0 h-full w-full object-cover',
                                video.is_sensitive ? 'scale-110 blur-2xl' : ''
                            ]"
                        />
                        <div
                            v-else
                            class="absolute inset-0 bg-gradient-to-br from-slate-800 to-slate-950"
                        />
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-black/50"
                            >
                                <PlayIcon class="h-5 w-5 translate-x-px text-white" />
                            </span>
                        </div>
                        <span
                            v-if="video.is_sensitive"
                            class="absolute left-2 top-2 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-medium text-white"
                        >
                            Sensitive
                        </span>
                        <span
                            v-if="videoDuration"
                            class="absolute right-2 top-2 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white"
                        >
                            {{ videoDuration }}
                        </span>
                        <div
                            v-if="video.account"
                            class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2.5 pb-2 pt-6"
                        >
                            <div class="flex items-center gap-1.5">
                                <img
                                    v-if="video.account.avatar"
                                    :src="video.account.avatar"
                                    alt=""
                                    class="h-4 w-4 rounded-full object-cover"
                                />
                                <span class="truncate text-xs font-medium text-white">
                                    @{{ video.account.username }}
                                </span>
                            </div>
                            <p
                                v-if="video.caption"
                                class="mt-0.5 truncate text-[11px] text-white/80"
                            >
                                {{ video.caption }}
                            </p>
                        </div>
                    </div>
                </a>

                <div
                    v-if="message.body"
                    :class="[
                        'whitespace-pre-wrap break-words px-3.5 py-2 text-sm',
                        own ? 'text-white' : 'text-slate-900 dark:text-slate-100'
                    ]"
                >
                    {{ message.body }}
                </div>
            </div>

            <div
                v-if="hasMedia || hasCaption"
                :class="[
                    'overflow-hidden rounded-2xl',
                    own
                        ? 'rounded-br-md bg-[#F02C56]'
                        : 'rounded-bl-md bg-slate-100 dark:bg-slate-800',
                    hasMedia ? 'w-72 max-w-full' : ''
                ]"
            >
                <div
                    v-if="hasMedia"
                    :class="message.media.length > 1 ? 'grid grid-cols-2 gap-0.5' : ''"
                >
                    <template v-for="media in message.media" :key="media.id">
                        <video
                            v-if="(media.mime_type || '').startsWith('video/')"
                            :autoplay="media.type === 'gif'"
                            :loop="media.type === 'gif'"
                            :muted="media.type === 'gif'"
                            :controls="media.type !== 'gif'"
                            playsinline
                            preload="metadata"
                            :poster="media.preview_url || undefined"
                            :width="media.width || undefined"
                            :height="media.height || undefined"
                            class="h-auto w-full bg-slate-900"
                        >
                            <source :src="media.url" :type="media.mime_type || undefined" />
                        </video>
                        <audio
                            v-else-if="media.type === 'audio'"
                            controls
                            preload="metadata"
                            :src="media.url"
                            class="w-full px-2 py-2"
                        />
                        <img
                            v-else-if="media.type === 'image' || media.type === 'gif'"
                            :src="media.url"
                            :alt="media.description || ''"
                            :width="media.width || undefined"
                            :height="media.height || undefined"
                            loading="lazy"
                            class="h-auto w-full bg-slate-200 dark:bg-slate-900"
                        />
                        <a
                            v-else
                            :href="media.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            :class="[
                                'block px-3.5 py-2 text-sm underline',
                                own ? 'text-white' : 'text-slate-700 dark:text-slate-200'
                            ]"
                        >
                            {{ media.description || 'Attachment' }}
                        </a>
                    </template>
                </div>

                <div
                    v-if="hasCaption"
                    :class="[
                        'whitespace-pre-wrap break-words px-3.5 py-2 text-sm',
                        own ? 'text-white' : 'text-slate-900 dark:text-slate-100'
                    ]"
                >
                    {{ message.body }}
                </div>
            </div>

            <button
                v-if="message.failed"
                type="button"
                class="mt-1 block text-xs font-medium text-red-500"
                @click="emit('retry', message)"
            >
                Failed to send. Tap to retry.
            </button>
        </div>

        <button
            v-if="!own && !message.reported"
            type="button"
            class="rounded-full p-1.5 text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-[#F02C56] dark:hover:bg-slate-900"
            aria-label="Report message"
            @click="emit('report', message)"
        >
            <FlagIcon class="h-4 w-4" />
        </button>
        <span v-else-if="!own && message.reported" class="mb-1 shrink-0 text-[11px] text-slate-400">
            Reported
        </span>

        <span
            v-if="!own"
            class="mb-1 shrink-0 text-[11px] text-slate-400 opacity-0 transition group-hover:opacity-100"
        >
            {{ timeLabel }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { FlagIcon, PlayIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    message: { type: Object, required: true },
    own: { type: Boolean, default: false },
    showAvatar: { type: Boolean, default: false },
    showName: { type: Boolean, default: false },
    sender: { type: Object, default: null },
    participant: { type: Object, default: () => ({}) }
})
const emit = defineEmits(['retry', 'delete', 'report'])

const avatarUser = computed(() => props.sender ?? props.participant ?? {})

const senderName = computed(() => {
    const user = avatarUser.value
    return user?.name || (user?.username ?? '').split('@')[0] || ''
})

const deletable = computed(
    () =>
        props.own &&
        !props.message.pending &&
        !props.message.failed &&
        !String(props.message.id).startsWith('temp-')
)

const hasMedia = computed(() => Boolean(props.message.media?.length))
const hasCaption = computed(
    () => Boolean(props.message.body) && (props.message.type !== 'loop_share' || !video.value)
)

const video = computed(() => props.message.video ?? null)

const videoThumb = computed(() => video.value?.media?.thumbnail ?? video.value?.thumbnail ?? null)

const videoAspect = computed(() => {
    const media = video.value?.media
    if (media?.width && media?.height) return `${media.width} / ${media.height}`
    return '9 / 16'
})

const videoPortrait = computed(() => {
    const media = video.value?.media
    if (media?.width && media?.height) return media.height >= media.width
    return true
})

const videoDuration = computed(() => {
    const seconds = video.value?.media?.duration
    if (seconds == null) return ''
    const minutes = Math.floor(seconds / 60)
    const rest = Math.round(seconds % 60)
    return `${minutes}:${String(rest).padStart(2, '0')}`
})

const timeTitle = computed(() =>
    props.message.created_at ? new Date(props.message.created_at).toLocaleString() : ''
)

const timeLabel = computed(() => {
    const value = props.message.created_at
    if (!value) return ''
    const date = new Date(value)
    const time = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
    if (date.toDateString() === new Date().toDateString()) return time
    return `${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}, ${time}`
})
</script>
