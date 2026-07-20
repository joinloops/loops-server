<template>
    <MainLayout>
        <div
            class="mx-auto flex h-[calc(100vh-9.5rem)] lg:h-screen lg:-mt-5 w-full 4xl:max-w-6xl bg-white dark:bg-slate-950"
        >
            <div
                :class="[
                    'w-full flex-col border-slate-200 lg:flex lg:w-96 lg:shrink-0 lg:border-r dark:border-slate-800',
                    activeId ? 'hidden' : 'flex'
                ]"
            >
                <DmConversationList @open="openThread" @sent="openThread" />
            </div>

            <div :class="['min-w-0 w-full flex-1 flex-col lg:flex', activeId ? 'flex' : 'hidden']">
                <DmThread
                    v-if="activeId"
                    :key="activeId"
                    :conversation-id="activeId"
                    @close="closeThread"
                    @left="closeThread"
                />
                <div
                    v-else
                    class="hidden h-full flex-col items-center justify-center gap-3 text-center lg:flex"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-900"
                    >
                        <ChatBubbleLeftRightIcon class="h-7 w-7 text-slate-400" />
                    </div>
                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Your messages
                    </p>
                    <p class="max-w-xs text-sm text-slate-500 dark:text-slate-400">
                        Share a loop or send a message to start a conversation.
                    </p>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { computed, onMounted, watch, inject } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { useDmStore } from '@/stores/dm'
import { useDmSocket } from '@/composables/useDmSocket'
import DmConversationList from '@/components/Dm/DmConversationList.vue'
import DmThread from '@/components/Dm/DmThread.vue'
import MainLayout from '@/layouts/MainLayout.vue'

const route = useRoute()
const router = useRouter()
const store = useDmStore()
const authStore = inject('authStore')

const meId = authStore.id

// store.init(meId)
// useDmSocket(meId)

const activeId = computed(() => (route.params.id ? String(route.params.id) : null))

watch(
    activeId,
    (id) => {
        if (id) store.openConversation(id)
        else store.activeId = null
    },
    { immediate: true }
)

onMounted(() => {
    store.fetchConversations('primary')
    store.fetchConversations('requests')
})

function openThread(id) {
    router.push(`/messages/${id}`)
}

function closeThread() {
    router.push('/messages')
}
</script>
