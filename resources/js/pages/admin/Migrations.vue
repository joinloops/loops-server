<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Account migrations</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">
                Accounts moving between this server and other Loops servers, with signed manifests,
                receipts and optional Transit seals.
            </p>
        </div>

        <div
            v-if="toast"
            :class="[
                'mb-4 rounded-lg px-4 py-3 text-sm',
                toast.type === 'success'
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
            ]"
        >
            {{ toast.message }}
        </div>

        <div v-if="stats" class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="item in statItems"
                :key="item.key"
                class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-center gap-2">
                    <span :class="['h-2 w-2 rounded-full', item.dot]"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ item.label }}</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ item.value }}
                </div>
            </div>
        </div>

        <div
            class="mb-6 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between p-6 text-left cursor-pointer"
                @click="showSettings = !showSettings"
            >
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Settings</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Control who can move in and out, limits, and trusted Transit services.
                    </p>
                </div>
                <ChevronDownIcon
                    :class="[
                        'h-5 w-5 text-gray-400 transition-transform',
                        showSettings ? 'rotate-180' : ''
                    ]"
                />
            </button>

            <div
                v-if="showSettings && settings"
                class="border-t border-gray-200 p-6 dark:border-gray-700"
            >
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label
                        v-for="toggle in toggles"
                        :key="toggle.key"
                        class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700 cursor-pointer"
                    >
                        <input
                            v-model="settings[toggle.key]"
                            type="checkbox"
                            class="mt-1 h-4 w-4 accent-pink-600"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">
                                {{ toggle.label }}
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ toggle.help }}
                            </span>
                        </span>
                    </label>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Cooldown after moving in (days)
                        </label>
                        <input
                            v-model.number="settings.cooldown_days"
                            type="number"
                            min="0"
                            max="365"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Accounts that moved here must wait this long before moving again.
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Trusted Transit services
                        </label>
                        <textarea
                            v-model="transitText"
                            rows="3"
                            placeholder="https://transit.example"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            One URL per line, up to 5. The first one shared with the other server is
                            used to seal migrations.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <AnimatedButton
                        variant="primaryGradient"
                        :disabled="savingSettings"
                        @click="saveSettings"
                    >
                        {{ savingSettings ? 'Saving…' : 'Save settings' }}
                    </AnimatedButton>
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-6 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Migrations</h2>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select
                        v-model="filters.direction"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">All directions</option>
                        <option value="outbound">Moving away</option>
                        <option value="inbound">Moving here</option>
                    </select>
                    <select
                        v-model="filters.state"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">All states</option>
                        <option value="active">Active</option>
                        <option v-for="value in stateOptions" :key="value" :value="value">
                            {{ stateMeta(value).label }}
                        </option>
                    </select>
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Username, domain or ID"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        @keyup.enter="reload"
                    />
                </div>
            </div>

            <div v-if="loading" class="p-6 text-center">
                <Spinner />
            </div>

            <div v-else-if="migrations.length === 0" class="px-6 py-16 text-center">
                <ArrowsRightLeftIcon class="mx-auto h-8 w-8 text-gray-400" />
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">
                    No migrations yet
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Migrations appear here when accounts move to or from this server.
                </p>
            </div>

            <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <router-link
                    v-for="migration in migrations"
                    :key="migration.id"
                    :to="`/admin/migrations/${migration.id}`"
                    class="flex flex-col gap-3 p-5 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/40 sm:flex-row sm:items-center sm:p-6"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span
                            :class="[
                                'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                migration.direction === 'outbound'
                                    ? 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-300'
                                    : 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300'
                            ]"
                        >
                            <ArrowUpRightIcon
                                v-if="migration.direction === 'outbound'"
                                class="h-5 w-5"
                            />
                            <ArrowDownLeftIcon v-else class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                @{{ migration.local_account?.username || 'unknown' }}
                                <span class="text-gray-400">
                                    {{ migration.direction === 'outbound' ? '→' : '←' }}
                                </span>
                                {{ remoteLabel(migration) }}
                            </p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ formatDate(migration.created_at) }} · {{ migration.id }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="hidden w-40 sm:block">
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700"
                            >
                                <div
                                    class="h-full bg-pink-600"
                                    :style="{ width: percent(migration) + '%' }"
                                ></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ migration.progress.done }} / {{ migration.progress.total }} items
                            </p>
                        </div>
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                stateMeta(migration.state).badge
                            ]"
                        >
                            {{ stateMeta(migration.state).label }}
                        </span>
                    </div>
                </router-link>
            </div>

            <div
                v-if="nextCursor"
                class="border-t border-gray-200 p-4 text-center dark:border-gray-700"
            >
                <button
                    type="button"
                    class="text-sm font-medium text-pink-600 hover:text-pink-700 cursor-pointer"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
                    {{ loadingMore ? 'Loading…' : 'Load more' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
    ArrowDownLeftIcon,
    ArrowUpRightIcon,
    ArrowsRightLeftIcon,
    ChevronDownIcon
} from '@heroicons/vue/24/outline'
import AnimatedButton from '@/components/AnimatedButton.vue'
import { migrationsApi } from '~/services/adminApi'
import { useMigrationMeta } from '~/composables/useMigrationMeta'

const { stateMeta, stateOptions, formatBytes, formatDate, percent, remoteLabel } =
    useMigrationMeta()

const migrations = ref([])
const nextCursor = ref(null)
const loading = ref(true)
const loadingMore = ref(false)
const stats = ref(null)
const settings = ref(null)
const transitText = ref('')
const showSettings = ref(false)
const savingSettings = ref(false)
const toast = ref(null)
const filters = ref({ direction: '', state: '', q: '' })

const toggles = [
    {
        key: 'outbound_enabled',
        label: 'Allow moving away',
        help: 'Local accounts can move to other Loops servers.'
    },
    {
        key: 'inbound_enabled',
        label: 'Allow moving here',
        help: 'Accounts on other Loops servers can move to this server.'
    },
    {
        key: 'allow_over_limits',
        label: 'Allow videos over local limits',
        help: 'Import videos that exceed this server’s size or duration limits.'
    },
    {
        key: 'require_seal',
        label: 'Require a Transit seal',
        help: 'Only complete migrations sealed by a trusted Transit service.'
    }
]

const statItems = computed(() => {
    if (!stats.value) return []
    return [
        { key: 'active', label: 'Active', value: stats.value.active, dot: 'bg-amber-500' },
        {
            key: 'out',
            label: 'Moved away',
            value: stats.value.outbound?.finalized || 0,
            dot: 'bg-orange-500'
        },
        {
            key: 'in',
            label: 'Moved here',
            value: stats.value.inbound?.completed || 0,
            dot: 'bg-blue-500'
        },
        {
            key: 'bytes',
            label: 'Imported (30 days)',
            value: formatBytes(stats.value.last_30_days?.bytes_imported || 0),
            dot: 'bg-emerald-500'
        }
    ]
})

const showToast = (type, message) => {
    toast.value = { type, message }
    setTimeout(() => (toast.value = null), 4000)
}

const fetchPage = async (cursor = null) => {
    const res = await migrationsApi.getMigrations({ cursor, ...filters.value })
    nextCursor.value = res.meta?.next_cursor || null
    return res.data || []
}

const reload = async () => {
    loading.value = true
    try {
        migrations.value = await fetchPage()
    } catch (error) {
        showToast('error', 'Failed to load migrations')
    } finally {
        loading.value = false
    }
}

const loadMore = async () => {
    if (!nextCursor.value) return
    loadingMore.value = true
    try {
        migrations.value = migrations.value.concat(await fetchPage(nextCursor.value))
    } catch (error) {
        showToast('error', 'Failed to load more migrations')
    } finally {
        loadingMore.value = false
    }
}

const loadStats = async () => {
    try {
        stats.value = (await migrationsApi.getStats()).data
    } catch (error) {
        stats.value = null
    }
}

const loadSettings = async () => {
    try {
        settings.value = (await migrationsApi.getSettings()).data
        transitText.value = (settings.value.transit_urls || []).join('\n')
    } catch (error) {
        settings.value = null
    }
}

const saveSettings = async () => {
    savingSettings.value = true
    try {
        const payload = {
            ...settings.value,
            cooldown_days: Number(settings.value.cooldown_days || 0),
            transit_urls: transitText.value
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean)
        }
        settings.value = (await migrationsApi.updateSettings(payload)).data
        transitText.value = (settings.value.transit_urls || []).join('\n')
        showToast('success', 'Migration settings saved')
    } catch (error) {
        showToast('error', error.response?.data?.message || 'Could not save settings')
    } finally {
        savingSettings.value = false
    }
}

watch(
    () => [filters.value.direction, filters.value.state],
    () => reload()
)

onMounted(() => {
    reload()
    loadStats()
    loadSettings()
})
</script>
