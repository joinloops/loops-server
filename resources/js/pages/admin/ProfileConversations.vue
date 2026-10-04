<template>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <router-link
                :to="`/admin/profiles/${profileId}`"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <ArrowLeftIcon class="h-5 w-5" />
            </router-link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Conversations</h1>
                <p class="text-gray-600 dark:text-gray-400">
                    <template v-if="owner?.username">
                        Direct messages and group chats involving {{ handleFor(owner) }}
                    </template>
                    <template v-else>Direct messages and group chats for this account</template>
                </p>
            </div>
        </div>

        <div
            class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-200 dark:border-gray-700"
        >
            <div
                class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700"
            >
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Conversations</h3>
                <button
                    type="button"
                    class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors cursor-pointer"
                    @click="fetchConversations()"
                >
                    <ArrowPathIcon class="w-5 h-5" />
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <template v-if="loading">
                            <tr v-for="i in 5" :key="i">
                                <td v-for="column in columns" :key="column" class="px-6 py-4">
                                    <div
                                        class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"
                                    ></div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else-if="!conversations.length">
                            <td
                                :colspan="columns.length"
                                class="px-6 py-10 text-center text-sm text-gray-600 dark:text-gray-400"
                            >
                                This account has no conversations.
                            </td>
                        </tr>
                        <tr
                            v-for="conversation in conversations"
                            v-else
                            :key="conversation.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                            @click="openConversation(conversation)"
                        >
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="flex -space-x-2 flex-shrink-0">
                                        <img
                                            v-for="participant in othersFor(conversation).slice(
                                                0,
                                                3
                                            )"
                                            :key="participant.id"
                                            :src="participant.avatar"
                                            :alt="participant.username"
                                            class="w-8 h-8 rounded-full object-cover border-2 border-white dark:border-gray-800"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium truncate max-w-xs">
                                            {{ titleFor(conversation) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            ID: {{ conversation.id }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span
                                    class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py-1 rounded"
                                >
                                    {{ conversation.type === 'group' ? 'Group chat' : 'DM' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        stateClass(stateFor(conversation))
                                    ]"
                                >
                                    {{ stateLabel(stateFor(conversation)) }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                            >
                                {{ formatNumber(conversation.profile_messages_count || 0) }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                            >
                                {{ formatNumber(conversation.messages_count || 0) }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                            >
                                {{
                                    conversation.updated_at
                                        ? formatDateTime(conversation.updated_at)
                                        : 'No messages'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-700"
            >
                <button
                    type="button"
                    :disabled="!pagination.prev_cursor || loading"
                    class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="fetchConversations(pagination.prev_cursor)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    :disabled="!pagination.next_cursor || loading"
                    class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="fetchConversations(pagination.next_cursor)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import { profilesApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'

const { formatNumber, formatDateTime } = useUtils()

const route = useRoute()
const router = useRouter()

const columns = [
    'Conversation',
    'Type',
    'Account state',
    'Sent by account',
    'Messages',
    'Last message'
]

const conversations = ref([])
const loading = ref(false)
const pagination = ref({ prev_cursor: null, next_cursor: null })

const profileId = computed(() => String(route.params.id))

const selfFor = (conversation) =>
    (conversation.participants || []).find((p) => String(p.id) === profileId.value) || null

const othersFor = (conversation) =>
    (conversation.participants || []).filter((p) => String(p.id) !== profileId.value)

const owner = computed(() => {
    for (const conversation of conversations.value) {
        const self = selfFor(conversation)
        if (self) return self
    }
    return null
})

const handleFor = (profile) => {
    if (!profile?.username) return 'Unknown account'
    return profile.username.startsWith('@') ? profile.username : `@${profile.username}`
}

const titleFor = (conversation) => {
    if (conversation.title) return conversation.title
    const others = othersFor(conversation)
    if (!others.length) return 'No other participants'
    return others.map((p) => handleFor(p)).join(', ')
}

const stateFor = (conversation) => selfFor(conversation)?.state || 'unknown'

const stateLabel = (state) => {
    if (state === 'active') return 'Active'
    if (state === 'request') return 'Pending request'
    if (state === 'left') return 'Left'
    return 'Unknown'
}

const stateClass = (state) => {
    if (state === 'active') {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    }
    if (state === 'request') {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

const fetchConversations = async (cursor = null) => {
    loading.value = true
    try {
        const response = await profilesApi.getProfileConversations(profileId.value, cursor)
        conversations.value = response.data
        pagination.value = response.meta || { prev_cursor: null, next_cursor: null }
    } catch (error) {
        console.error('Error fetching conversations:', error)
        conversations.value = []
        pagination.value = { prev_cursor: null, next_cursor: null }
    } finally {
        loading.value = false
    }
}

const openConversation = (conversation) => {
    router.push(`/admin/conversations/${conversation.id}?profile=${profileId.value}`)
}

onMounted(() => {
    fetchConversations()
})

watch(profileId, () => {
    fetchConversations()
})
</script>
