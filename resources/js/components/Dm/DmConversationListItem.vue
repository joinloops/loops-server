<template>
    <button
        type="button"
        :class="[
            'flex w-full items-center gap-3 px-4 py-3 text-left transition',
            active
                ? 'bg-slate-100 dark:bg-slate-900'
                : 'hover:bg-slate-50 dark:hover:bg-slate-900/60'
        ]"
    >
        <DmGroupAvatar v-if="isGroup" :participants="activeParticipants" />

        <template v-else>
            <img
                v-if="participant.avatar"
                :src="participant.avatar"
                :alt="participant.username"
                class="h-12 w-12 shrink-0 rounded-full object-cover"
                onerror="
                    this.src = '/storage/avatars/default.jpg'
                    this.onerror = null
                "
            />
            <div
                v-else
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                {{ (participant.username ?? '?').slice(0, 1) }}
            </div>
        </template>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <span class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ displayName }}
                </span>
                <span
                    v-if="isGroup"
                    class="flex shrink-0 items-center gap-0.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-900 dark:text-slate-400"
                >
                    <UserGroupIcon class="h-3 w-3" />
                    {{ memberCount }}
                </span>
                <span
                    v-else-if="participant.is_remote"
                    class="shrink-0 truncate rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-900 dark:text-slate-400"
                >
                    {{ participant.domain }}
                </span>
                <SpeakerXMarkIcon
                    v-if="conversation.muted"
                    class="h-3.5 w-3.5 shrink-0 text-slate-400"
                />
            </div>
            <div class="mt-0.5 flex items-center gap-1.5">
                <PlayCircleIcon
                    v-if="['loop_share', 'media'].includes(conversation.last_message?.type)"
                    class="h-3.5 w-3.5 shrink-0 text-slate-400"
                />
                <span
                    :class="[
                        'truncate text-sm',
                        conversation.unread
                            ? 'font-semibold text-slate-900 dark:text-slate-100'
                            : 'text-slate-500 dark:text-slate-400'
                    ]"
                >
                    {{ previewPrefix }}{{ preview }}
                </span>
            </div>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-1.5">
            <span class="text-xs text-slate-400">{{ timeLabel }}</span>
            <span v-if="conversation.unread" class="h-2.5 w-2.5 rounded-full bg-[#F02C56]" />
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue'
import { PlayCircleIcon, SpeakerXMarkIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import { useDmStore } from '~/stores/dm'
import { useDmConversation } from '~/composables/useDmConversation'
import DmGroupAvatar from './DmGroupAvatar.vue'

const props = defineProps({
    conversation: { type: Object, required: true },
    active: { type: Boolean, default: false }
})

const store = useDmStore()
const { isGroup, activeParticipants, displayName, memberCount, senderFor } = useDmConversation(
    () => props.conversation
)

const participant = computed(() => props.conversation.participant ?? {})

const preview = computed(() => {
    const last = props.conversation.last_message
    if (!last) return ''
    if (last.type === 'loop_share') return 'Shared a loop'
    if (last.type === 'media') {
        const first = last.media?.[0]
        if (first?.type === 'gif') return last.body || 'Sent a GIF'
        if (first?.type === 'video') return last.body || 'Sent a video'
        if (first?.type === 'audio') return last.body || 'Sent audio'
        return last.body || 'Sent media'
    }
    return last.body ?? ''
})

const previewPrefix = computed(() => {
    if (!isGroup.value) return ''
    const last = props.conversation.last_message
    if (!last) return ''
    if (String(last.sender_id) === String(store.meId)) return 'You: '
    const sender = senderFor(last.sender_id)
    const name = sender?.name || (sender?.username ?? '').split('@')[0]
    return name ? `${name}: ` : ''
})

const timeLabel = computed(() => {
    const value = props.conversation.updated_at
    if (!value) return ''
    const diff = Date.now() - new Date(value).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'now'
    if (minutes < 60) return `${minutes}m`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d`
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
})
</script>
