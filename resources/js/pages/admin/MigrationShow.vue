<template>
    <div>
        <router-link
            to="/admin/migrations"
            class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <ArrowLeftIcon class="h-4 w-4" />
            All migrations
        </router-link>

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

        <div v-if="loading" class="p-6 text-center">
            <Spinner />
        </div>

        <template v-else-if="migration">
            <div
                class="mb-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{
                                    migration.direction === 'outbound'
                                        ? 'Moving away'
                                        : 'Moving here'
                                }}
                            </h1>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    stateMeta(migration.state).badge
                                ]"
                            >
                                {{ stateMeta(migration.state).label }}
                            </span>
                            <span
                                v-if="migration.sealed"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                            >
                                <ShieldCheckIcon class="h-3.5 w-3.5" />
                                Sealed
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300 break-all">
                            <router-link
                                v-if="migration.local_account"
                                :to="`/admin/profiles/${migration.local_account.id}`"
                                class="font-medium text-pink-600 hover:underline"
                            >
                                @{{ migration.local_account.username }}
                            </router-link>
                            <span class="mx-1 text-gray-400">{{
                                migration.direction === 'outbound' ? '→' : '←'
                            }}</span>
                            <a
                                :href="migration.remote_actor"
                                target="_blank"
                                rel="noopener"
                                class="hover:underline"
                                >{{ remoteLabel(migration) }}</a
                            >
                        </p>
                        <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ migration.id }}
                        </p>
                    </div>
                    <button
                        v-if="isActive"
                        type="button"
                        :disabled="cancelling"
                        class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 cursor-pointer"
                        @click="cancel"
                    >
                        {{ cancelling ? 'Cancelling…' : 'Cancel migration' }}
                    </button>
                </div>

                <div
                    v-if="migration.error"
                    class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300"
                >
                    {{ migration.error }}
                </div>

                <div class="mt-6">
                    <div class="mb-1.5 flex justify-between text-sm">
                        <span class="text-gray-700 dark:text-gray-300">
                            {{ migration.progress.done }} done ·
                            {{ migration.progress.failed }} failed ·
                            {{ migration.progress.skipped }} skipped ·
                            {{ migration.progress.total }} total
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ formatBytes(migration.progress.bytes_done) }} /
                            {{ formatBytes(migration.progress.bytes_total) }}
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div
                            class="h-full bg-pink-600 transition-all"
                            :style="{ width: percent(migration) + '%' }"
                        ></div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                    <div
                        v-for="(counts, kind) in migration.kinds"
                        :key="kind"
                        class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                    >
                        <p class="text-xs capitalize text-gray-500 dark:text-gray-400">
                            {{ kind }}
                        </p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{
                                counts.total !== undefined
                                    ? counts.total
                                    : `${counts.done || 0} / ${sumCounts(counts)}`
                            }}
                        </p>
                    </div>
                </div>

                <dl
                    class="mt-6 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div v-for="row in timeline" :key="row.label">
                        <dt class="text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                        <dd class="text-gray-900 dark:text-white">{{ formatDate(row.value) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Scope</dt>
                        <dd class="text-gray-900 dark:text-white">
                            {{ (migration.scope || []).join(', ') || '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Transit</dt>
                        <dd class="break-all text-gray-900 dark:text-white">
                            {{ migration.transit_url || 'None' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="mb-6 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-200 p-6 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Attestations
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Signed intent from the source server, signed receipt from the destination,
                        and the Transit seal.
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-6 lg:grid-cols-2">
                    <div v-for="block in attestationBlocks" :key="block.label">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ block.label }}
                            </h3>
                            <span v-if="block.hint" class="font-mono text-xs text-gray-400">
                                {{ block.hint }}
                            </span>
                        </div>
                        <pre
                            class="max-h-72 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200"
                            >{{
                                block.value ? JSON.stringify(block.value, null, 2) : 'Not available'
                            }}</pre>
                    </div>
                </div>
            </div>

            <div
                class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="flex flex-col gap-3 border-b border-gray-200 p-6 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between"
                >
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Items</h2>
                    <div class="flex gap-2">
                        <select
                            v-model="itemFilters.kind"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">All kinds</option>
                            <option v-for="kind in kinds" :key="kind" :value="kind">
                                {{ kind }}
                            </option>
                        </select>
                        <select
                            v-model="itemFilters.state"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">All states</option>
                            <option value="pending">Pending</option>
                            <option value="done">Done</option>
                            <option value="skipped">Skipped</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <div v-if="itemsLoading" class="p-6 text-center">
                    <Spinner />
                </div>
                <div
                    v-else-if="items.length === 0"
                    class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    No items match these filters.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kind</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">
                                    Source
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">
                                    Target
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">State</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Size</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="item in items" :key="item.position">
                                <td class="px-4 py-3 text-gray-500">{{ item.position }}</td>
                                <td class="px-4 py-3 capitalize text-gray-900 dark:text-white">
                                    {{ item.kind }}
                                </td>
                                <td class="max-w-xs px-4 py-3">
                                    <a
                                        :href="item.source_id"
                                        target="_blank"
                                        rel="noopener"
                                        class="block truncate text-gray-700 hover:underline dark:text-gray-300"
                                        >{{ item.source_id }}</a
                                    >
                                </td>
                                <td class="max-w-xs px-4 py-3">
                                    <a
                                        v-if="item.target_url || item.target_id"
                                        :href="item.target_url || item.target_id"
                                        target="_blank"
                                        rel="noopener"
                                        class="block truncate text-gray-700 hover:underline dark:text-gray-300"
                                        >{{ item.target_url || item.target_id }}</a
                                    >
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            itemBadge(item.state)
                                        ]"
                                    >
                                        {{ item.state }}
                                    </span>
                                    <p
                                        v-if="item.error"
                                        class="mt-1 max-w-xs truncate text-xs text-gray-500"
                                        :title="item.error"
                                    >
                                        {{ item.error }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">
                                    {{ item.bytes ? formatBytes(item.bytes) : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="itemsCursor"
                    class="border-t border-gray-200 p-4 text-center dark:border-gray-700"
                >
                    <button
                        type="button"
                        class="text-sm font-medium text-pink-600 hover:text-pink-700 cursor-pointer"
                        @click="loadItems(true)"
                    >
                        Load more
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowLeftIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { migrationsApi } from '~/services/adminApi'
import { useMigrationMeta } from '~/composables/useMigrationMeta'

const ACTIVE = ['building', 'offered', 'pending', 'transferring', 'sealing', 'ready', 'finalizing']

const route = useRoute()
const { stateMeta, formatBytes, formatDate, percent, remoteLabel } = useMigrationMeta()

const migration = ref(null)
const loading = ref(true)
const cancelling = ref(false)
const toast = ref(null)
const items = ref([])
const itemsCursor = ref(null)
const itemsLoading = ref(true)
const itemFilters = ref({ kind: '', state: '' })
const kinds = ['profile', 'video', 'sound', 'playlist', 'follow', 'block', 'bookmark']

let pollTimer = null

const isActive = computed(() => !!migration.value && ACTIVE.includes(migration.value.state))

const timeline = computed(() => {
    const m = migration.value
    if (!m) return []
    return [
        { label: 'Created', value: m.created_at },
        { label: 'Offered', value: m.offered_at },
        { label: 'Accepted', value: m.accepted_at },
        { label: 'Completed', value: m.completed_at },
        { label: 'Finalized', value: m.finalized_at },
        { label: 'Expires', value: m.expires_at }
    ]
})

const attestationBlocks = computed(() => {
    const m = migration.value
    if (!m) return []
    return [
        { label: 'Intent', value: m.intent?.payload, hint: m.intent?.header?.kid },
        { label: 'Receipt', value: m.receipt?.payload, hint: m.receipt?.header?.kid },
        { label: 'Seal', value: m.seal },
        {
            label: 'Roots',
            value: {
                manifest: m.manifest_root,
                map: m.map_root,
                remote_migration: m.remote_migration_id
            }
        }
    ]
})

const sumCounts = (counts) => Object.values(counts || {}).reduce((sum, value) => sum + value, 0)

const itemBadge = (state) =>
    ({
        done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        skipped: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
    })[state] || 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'

const showToast = (type, message) => {
    toast.value = { type, message }
    setTimeout(() => (toast.value = null), 4000)
}

const loadMigration = async () => {
    try {
        migration.value = (await migrationsApi.getMigration(route.params.id)).data
    } catch (error) {
        showToast('error', 'Failed to load this migration')
    } finally {
        loading.value = false
    }
}

const loadItems = async (append = false) => {
    if (!append) itemsLoading.value = true
    try {
        const res = await migrationsApi.getItems(route.params.id, {
            cursor: append ? itemsCursor.value : null,
            ...itemFilters.value
        })
        items.value = append ? items.value.concat(res.data || []) : res.data || []
        itemsCursor.value = res.meta?.next_cursor || null
    } catch (error) {
        showToast('error', 'Failed to load items')
    } finally {
        itemsLoading.value = false
    }
}

const cancel = async () => {
    if (!window.confirm('Cancel this migration? Content already copied stays where it is.')) {
        return
    }
    cancelling.value = true
    try {
        migration.value = (await migrationsApi.cancelMigration(route.params.id)).data
        showToast('success', 'Migration cancelled')
    } catch (error) {
        showToast('error', error.response?.data?.error?.message || 'Could not cancel migration')
    } finally {
        cancelling.value = false
    }
}

watch(itemFilters, () => loadItems(), { deep: true })

watch(isActive, (active) => {
    if (active && !pollTimer) {
        pollTimer = setInterval(loadMigration, 8000)
    } else if (!active && pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
})

onMounted(async () => {
    await loadMigration()
    loadItems()
    if (isActive.value) {
        pollTimer = setInterval(loadMigration, 8000)
    }
})

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer)
})
</script>
