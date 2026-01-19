<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ActivityPub Relays</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Manage relay subscriptions to federate content across the network
            </p>
        </div>

        <div v-if="stats" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Relays</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_relays }}</div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg shadow-sm border border-green-200 dark:border-green-800">
                <div class="text-sm text-green-600 dark:text-green-400">Active</div>
                <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ stats.active_relays }}</div>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg shadow-sm border border-yellow-200 dark:border-yellow-800">
                <div class="text-sm text-yellow-600 dark:text-yellow-400">Pending</div>
                <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ stats.pending_relays }}</div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg shadow-sm border border-blue-200 dark:border-blue-800">
                <div class="text-sm text-blue-600 dark:text-blue-400">Sent</div>
                <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ stats.total_sent }}</div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg shadow-sm border border-purple-200 dark:border-purple-800">
                <div class="text-sm text-purple-600 dark:text-purple-400">Received</div>
                <div class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ stats.total_received }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Relay Subscriptions</h2>
                    <button
                        @click="showAddModal = true"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Add Relay
                    </button>
                </div>
            </div>

            <div v-if="loading" class="p-6 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>

            <div v-else-if="relays.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                No relays configured. Click "Add Relay" to subscribe to a relay.
            </div>

            <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <div v-for="relay in relays" :key="relay.id" class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ relay.name || relay.relay_url }}
                                </h3>
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs font-semibold rounded-full',
                                        relay.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                        relay.status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                        relay.status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    ]"
                                >
                                    {{ relay.status }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ relay.relay_url }}</p>
                            <div class="mt-2 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span>Send:</span>
                                    <span :class="relay.send_public_posts ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ relay.send_public_posts ? '✓' : '✗' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span>Receive:</span>
                                    <span :class="relay.receive_content ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ relay.receive_content ? '✓' : '✗' }}
                                    </span>
                                </div>
                                <div>Sent: {{ relay.total_sent }}</div>
                                <div>Received: {{ relay.total_received }}</div>
                                <div v-if="relay.last_delivery_at">
                                    Last sent: {{ formatDate(relay.last_delivery_at) }}
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                v-if="relay.status === 'active'"
                                @click="disableRelay(relay)"
                                class="px-3 py-1 text-sm text-yellow-700 bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-200 rounded hover:bg-yellow-200 dark:hover:bg-yellow-800"
                            >
                                Disable
                            </button>
                            <button
                                v-if="relay.status === 'disabled'"
                                @click="enableRelay(relay)"
                                class="px-3 py-1 text-sm text-green-700 bg-green-100 dark:bg-green-900 dark:text-green-200 rounded hover:bg-green-200 dark:hover:bg-green-800"
                            >
                                Enable
                            </button>
                            <button
                                @click="confirmDelete(relay)"
                                class="px-3 py-1 text-sm text-red-700 bg-red-100 dark:bg-red-900 dark:text-red-200 rounded hover:bg-red-200 dark:hover:bg-red-800"
                            >
                                Unsubscribe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showAddModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Relay Subscription</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Relay URL</label>
                        <input
                            v-model="newRelay.relay_url"
                            type="url"
                            placeholder="https://relay.example.com"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Name (optional)</label>
                        <input
                            v-model="newRelay.name"
                            type="text"
                            placeholder="My Relay"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input v-model="newRelay.send_public_posts" type="checkbox" class="rounded" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Send public posts</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="newRelay.receive_content" type="checkbox" class="rounded" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Receive content</span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showAddModal = false"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="addRelay"
                        :disabled="!newRelay.relay_url"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Subscribe
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { relaysApi } from '~/services/adminApi'

const relays = ref([])
const stats = ref(null)
const loading = ref(true)
const showAddModal = ref(false)
const newRelay = ref({
    relay_url: '',
    name: '',
    send_public_posts: true,
    receive_content: true
})

const loadRelays = async () => {
    try {
        loading.value = true
        const response = await relaysApi.getRelays()
        relays.value = response.data.relays
    } catch (error) {
        console.error('Error loading relays:', error)
    } finally {
        loading.value = false
    }
}

const loadStats = async () => {
    try {
        const response = await relaysApi.getStats()
        stats.value = response.data.stats
    } catch (error) {
        console.error('Error loading stats:', error)
    }
}

const addRelay = async () => {
    try {
        await relaysApi.createRelay(newRelay.value)
        showAddModal.value = false
        newRelay.value = {
            relay_url: '',
            name: '',
            send_public_posts: true,
            receive_content: true
        }
        await loadRelays()
        await loadStats()
    } catch (error) {
        console.error('Error adding relay:', error)
        alert('Failed to add relay: ' + (error.response?.data?.message || error.message))
    }
}

const enableRelay = async (relay) => {
    try {
        await relaysApi.enableRelay(relay.id)
        await loadRelays()
        await loadStats()
    } catch (error) {
        console.error('Error enabling relay:', error)
    }
}

const disableRelay = async (relay) => {
    try {
        await relaysApi.disableRelay(relay.id)
        await loadRelays()
        await loadStats()
    } catch (error) {
        console.error('Error disabling relay:', error)
    }
}

const confirmDelete = async (relay) => {
    if (confirm(`Are you sure you want to unsubscribe from ${relay.name || relay.relay_url}?`)) {
        try {
            await relaysApi.deleteRelay(relay.id)
            await loadRelays()
            await loadStats()
        } catch (error) {
            console.error('Error deleting relay:', error)
        }
    }
}

const formatDate = (dateString) => {
    const date = new Date(dateString)
    const now = new Date()
    const diff = now - date
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(diff / 3600000)
    const days = Math.floor(diff / 86400000)
    
    if (minutes < 60) return `${minutes}m ago`
    if (hours < 24) return `${hours}h ago`
    return `${days}d ago`
}

onMounted(() => {
    loadRelays()
    loadStats()
})
</script>
