<template>
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between px-4 pb-2 pt-4">
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Messages</h1>
            <AnimatedButton
                size="xs"
                variant="primaryGradient"
                pill
                aria-label="New message"
                @click="showCompose = true"
            >
                <div class="flex items-center gap-2">
                    <PencilSquareIcon class="h-5 w-5" />
                    New
                </div>
            </AnimatedButton>
        </div>

        <div class="border-b border-slate-200 px-4 dark:border-slate-800">
            <div class="-mb-px flex w-full">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    :class="[
                        'relative flex flex-1 justify-center items-center gap-1.5 border-b-1 py-3 text-sm font-medium transition-colors',
                        tab === item.key
                            ? 'border-slate-900 text-slate-950 dark:border-slate-100 dark:text-slate-50'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-900 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:text-slate-100'
                    ]"
                    @click="select(item.key)"
                >
                    {{ item.label }}
                    <span
                        v-if="item.key === 'requests' && store.requestCount"
                        class="inline-flex min-w-5 items-center justify-center rounded-full bg-[#F02C56] px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
                    >
                        {{ store.requestCount }}
                    </span>
                </button>
            </div>
        </div>

        <p
            v-if="explainer"
            class="border-b border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-900 dark:text-slate-400"
        >
            {{ explainer }}
        </p>

        <div class="flex-1 overflow-y-auto">
            <DmConversationListItem
                v-for="conversation in items"
                :key="conversation.id"
                :conversation="conversation"
                :active="store.activeId === conversation.id"
                @click="emit('open', conversation.id)"
            />

            <div
                v-if="current.loaded && !items.length"
                class="flex flex-col items-center gap-2 px-6 py-16 text-center"
            >
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ emptyTitle }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ emptyBody }}</p>
            </div>

            <div v-if="current.loading" class="space-y-3 px-4 py-3">
                <div v-for="n in 4" :key="n" class="flex animate-pulse items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-slate-100 dark:bg-slate-900" />
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-1/3 rounded bg-slate-100 dark:bg-slate-900" />
                        <div class="h-3 w-2/3 rounded bg-slate-100 dark:bg-slate-900" />
                    </div>
                </div>
            </div>

            <div ref="sentinel" class="h-px" />
        </div>

        <DmShareModal v-if="showCompose" @close="showCompose = false" @sent="onSent" />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'
import { useDmStore } from '@/stores/dm'
import DmConversationListItem from './DmConversationListItem.vue'
import DmShareModal from './DmShareModal.vue'

const emit = defineEmits(['open', 'sent'])
const store = useDmStore()

const tabs = [
    { key: 'primary', label: 'Inbox' },
    { key: 'requests', label: 'Requests' },
    { key: 'hidden', label: 'Hidden' }
]

const tab = ref('primary')
const showCompose = ref(false)
const sentinel = ref(null)
let observer = null

const current = computed(() => store.tabs[tab.value])
const items = computed(() => current.value.ids.map((id) => store.conversations[id]).filter(Boolean))

const explainer = computed(() => {
    if (tab.value === 'requests') {
        return "Requests come from people you don't follow. They won't know you've seen a request until you accept it."
    }
    if (tab.value === 'hidden') {
        return 'Hidden conversations move back to Inbox when you get a new message.'
    }
    return null
})

const emptyTitle = computed(() => {
    if (tab.value === 'requests') return 'No requests'
    if (tab.value === 'hidden') return 'No hidden conversations'
    return 'No messages yet'
})

const emptyBody = computed(() => {
    if (tab.value === 'requests') return 'New requests will show up here.'
    if (tab.value === 'hidden') return 'Conversations you hide will show up here.'
    return 'Share a loop with someone to start a conversation.'
})

onMounted(() => {
    observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) store.fetchConversations(tab.value)
    })
    if (sentinel.value) observer.observe(sentinel.value)
})

onBeforeUnmount(() => observer?.disconnect())

function select(next) {
    tab.value = next
    if (!store.tabs[next].loaded) store.fetchConversations(next)
}

function onSent(conversationId) {
    showCompose.value = false
    if (conversationId) emit('sent', conversationId)
}
</script>
