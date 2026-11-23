<template>
    <div>
        <div class="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Access Controls</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    Block sign in and sign up by IP address, email or username, then clean up
                    accounts that match.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <AnimatedButton variant="outline" pill @click="openPatternBuilder">
                    <div class="flex items-center gap-2">
                        <CodeBracketIcon class="h-4 w-4" />
                        Pattern builder
                    </div>
                </AnimatedButton>
                <AnimatedButton variant="primaryGradient" pill @click="openCreateModal">
                    <div class="flex items-center gap-2">
                        <PlusIcon class="h-4 w-4" />
                        Add rule
                    </div>
                </AnimatedButton>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ card.label }}</p>
                <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ card.value === null ? '—' : formatNumber(card.value) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ card.detail }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div
                class="flex flex-wrap gap-1 rounded-full border border-gray-300 bg-gray-100 p-2 dark:border-gray-700 dark:bg-gray-800"
                role="tablist"
                aria-label="Access control type"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.value"
                    :class="[
                        'cursor-pointer rounded-full px-5 py-2 text-sm font-medium transition',
                        activeTab === tab.value
                            ? 'bg-red-500 text-white'
                            : 'bg-gray-200 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    ]"
                    @click="setTab(tab.value)"
                >
                    {{ tab.label }}
                    <span v-if="tab.count !== null" class="ml-1 text-xs opacity-75">
                        ({{ formatNumber(tab.count) }})
                    </span>
                </button>
            </div>

            <p
                v-if="activeTab === 'ip' && counts.current_ip"
                class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
            >
                <ShieldCheckIcon class="h-4 w-4 text-emerald-600" />
                Rules that would block sign in from your IP
                <span class="font-mono">{{ counts.current_ip }}</span> are refused
            </p>

            <div v-if="activeTab === 'suspended'" class="flex flex-wrap items-center gap-2">
                <template v-if="selected.size">
                    <div
                        class="flex items-center gap-3 rounded-full border border-rose-200 bg-rose-50 py-1.5 pl-4 pr-1.5 dark:border-rose-800 dark:bg-rose-900/20"
                    >
                        <span class="text-sm font-semibold text-rose-800 dark:text-rose-300">
                            {{ selected.size }} selected
                        </span>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-full bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-700"
                            @click="openBanModal([...selected])"
                        >
                            <NoSymbolIcon class="h-4 w-4" />
                            Ban &amp; delete selected
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400"
                        @click="selected = new Set()"
                    >
                        Clear
                    </button>
                </template>
                <button
                    v-else-if="suspendedRows.length"
                    type="button"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400"
                    @click="selectPage"
                >
                    Select this page
                </button>
            </div>
        </div>

        <DataTable
            v-if="activeTab !== 'suspended'"
            :key="`rules-${activeTab}`"
            :title="tableTitle"
            :columns="ruleColumns"
            :data="rules"
            :loading="loading"
            :has-previous="!!pagination.prev_cursor"
            :has-next="!!pagination.next_cursor"
            :sort-options="sortOptions"
            :initial-search-query="searchQuery"
            :has-actions="true"
            @search="handleSearch"
            @sort="handleSort"
            @refresh="refresh"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-value="{ item }">
                <div class="flex max-w-xs flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="break-all font-mono text-sm text-gray-900 dark:text-white">
                            {{ item.value }}
                        </span>
                        <AccessRuleBadge v-if="item.type === 'ip'" :match-type="item.match_type" />
                    </div>
                    <span
                        v-if="ruleDetail(item)"
                        class="truncate text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ ruleDetail(item) }}
                    </span>
                </div>
            </template>

            <template #cell-match_type="{ item }">
                <div class="flex flex-col items-start gap-1">
                    <AccessRuleBadge :match-type="item.match_type" />
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{
                        appliesText(item)
                    }}</span>
                </div>
            </template>

            <template #cell-blocks="{ item }">
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-if="item.blocks_login"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                    >
                        Sign in
                    </span>
                    <span
                        v-if="item.blocks_registration"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                    >
                        Sign up
                    </span>
                </div>
            </template>

            <template #cell-matches="{ item }">
                <span
                    v-if="matchCounts[item.id] === undefined"
                    class="inline-flex h-4 w-16 animate-pulse rounded-full bg-gray-200 dark:bg-gray-700"
                ></span>
                <button
                    v-else-if="matchCounts[item.id] > 0"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300"
                    @click="openMatches(item)"
                >
                    {{ formatNumber(matchCounts[item.id]) }}
                    {{ matchCounts[item.id] === 1 ? 'account' : 'accounts' }} · Review
                </button>
                <span v-else class="text-sm text-gray-500 dark:text-gray-400">None</span>
            </template>

            <template #cell-hits="{ item }">
                <div class="flex flex-col">
                    <span class="font-semibold text-gray-900 dark:text-white">
                        {{ formatNumber(item.hits) }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ item.last_hit_at ? formatRecentDate(item.last_hit_at) : 'Never hit' }}
                    </span>
                </div>
            </template>

            <template #cell-expires_at="{ item }">
                <span
                    v-if="item.is_expired"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                >
                    Expired
                </span>
                <span v-else class="text-sm text-gray-700 dark:text-gray-300">
                    {{ item.expires_at ? formatDateTime(item.expires_at) : 'Never' }}
                </span>
            </template>

            <template #cell-note="{ value }">
                <div
                    v-if="value"
                    class="max-w-[14rem] truncate text-sm text-gray-600 dark:text-gray-400"
                    :title="value"
                >
                    {{ value }}
                </div>
                <span v-else class="text-xs italic text-gray-400 dark:text-gray-600">None</span>
            </template>

            <template #cell-created_by="{ item }">
                <div class="flex flex-col">
                    <router-link
                        v-if="item.created_by_account?.username"
                        :to="`/admin/profiles/${item.created_by_account.id}`"
                        class="text-sm text-gray-700 hover:underline dark:text-gray-300"
                    >
                        @{{ item.created_by_account.username }}
                    </router-link>
                    <span v-else class="text-sm italic text-gray-400">System</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ formatRecentDate(item.created_at) }}
                    </span>
                </div>
            </template>

            <template #actions="{ item }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        type="button"
                        title="Edit"
                        aria-label="Edit rule"
                        class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        @click="openEditModal(item)"
                    >
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        title="Delete"
                        aria-label="Delete rule"
                        class="rounded p-1.5 text-gray-500 hover:bg-rose-50 hover:text-rose-600 dark:text-gray-400 dark:hover:bg-rose-900/20 dark:hover:text-rose-400"
                        @click="confirmDelete(item)"
                    >
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </DataTable>

        <DataTable
            v-else
            key="suspended"
            title="Suspended local accounts"
            :columns="suspendedColumns"
            :data="suspendedRows"
            :loading="loading"
            :has-previous="!!pagination.prev_cursor"
            :has-next="!!pagination.next_cursor"
            :initial-search-query="searchQuery"
            :has-actions="true"
            @search="handleSearch"
            @refresh="refresh"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-select="{ item }">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300"
                    :aria-label="`Select @${item.username}`"
                    :checked="selected.has(item.profile_id)"
                    @change="toggleSelected(item.profile_id)"
                />
            </template>

            <template #cell-username="{ item }">
                <div class="flex items-center gap-3">
                    <img
                        :src="item.avatar"
                        :alt="item.username"
                        class="h-9 w-9 flex-shrink-0 rounded-full object-cover"
                    />
                    <div class="flex min-w-0 flex-col">
                        <router-link
                            :to="`/admin/profiles/${item.profile_id}`"
                            class="font-semibold text-gray-900 hover:underline dark:text-white"
                        >
                            @{{ item.username }}
                        </router-link>
                        <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ item.email }}
                        </span>
                    </div>
                </div>
            </template>

            <template #cell-register_ip="{ value }">
                <span v-if="value" class="font-mono text-xs text-gray-700 dark:text-gray-300">
                    {{ value }}
                </span>
                <span v-else class="text-xs italic text-gray-400">Not recorded</span>
            </template>

            <template #cell-last_ip="{ value }">
                <span v-if="value" class="font-mono text-xs text-gray-700 dark:text-gray-300">
                    {{ value }}
                </span>
                <span v-else class="text-xs italic text-gray-400">Not recorded</span>
            </template>

            <template #cell-suspended_at="{ item }">
                <div class="flex flex-col">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ item.suspended_at ? formatDate(item.suspended_at) : 'Unknown' }}
                    </span>
                    <span
                        class="max-w-[14rem] truncate text-xs text-gray-500 dark:text-gray-400"
                        :title="item.admin_notes || ''"
                    >
                        {{
                            item.suspended_by_spam_check
                                ? 'Spam detection'
                                : item.admin_notes || 'No admin notes'
                        }}
                    </span>
                </div>
            </template>

            <template #cell-content="{ item }">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    {{ formatNumber(item.video_count) }} videos ·
                    {{ formatNumber(item.comment_count) }} comments
                </span>
            </template>

            <template #actions="{ item }">
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-full bg-rose-600 px-3.5 text-sm font-semibold text-white hover:bg-rose-700"
                        @click="openBanModal([item.profile_id])"
                    >
                        Ban &amp; delete
                    </button>
                </div>
            </template>
        </DataTable>

        <AccessRuleFormModal
            v-model="showFormModal"
            :rule="editingRule"
            :initial-type="formType"
            @saved="handleSaved"
        />

        <AccessRulePatternBuilderModal
            v-model="showPatternBuilder"
            :initial-type="activeTab === 'username' ? 'username' : 'email'"
            @saved="handleSaved"
        />

        <AccessRuleMatchesModal
            v-model="showMatchesModal"
            :rule="matchesRule"
            @purged="handlePurged"
        />

        <AccessRuleBanDeleteModal
            v-model="showBanModal"
            :profile-ids="banProfileIds"
            @completed="handleBanCompleted"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    CodeBracketIcon,
    NoSymbolIcon,
    PencilSquareIcon,
    PlusIcon,
    ShieldCheckIcon,
    TrashIcon
} from '@heroicons/vue/24/outline'
import DataTable from '@/components/DataTable.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import AccessRuleBadge from '@/components/Admin/AccessRuleBadge.vue'
import AccessRuleFormModal from '@/components/Admin/AccessRuleFormModal.vue'
import AccessRulePatternBuilderModal from '@/components/Admin/AccessRulePatternBuilderModal.vue'
import AccessRuleMatchesModal from '@/components/Admin/AccessRuleMatchesModal.vue'
import AccessRuleBanDeleteModal from '@/components/Admin/AccessRuleBanDeleteModal.vue'
import { accessRulesApi, accessRuleErrorMessage } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'

const route = useRoute()
const router = useRouter()
const { formatNumber, formatRecentDate, formatDate, formatDateTime } = useUtils()
const { alertModal, confirmModal } = useAlertModal()

const TABS = ['ip', 'email', 'username', 'suspended']

const activeTab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'ip')
const counts = ref({ ip: {}, email: {}, username: {}, suspended: null, attempts: {} })
const rules = ref([])
const suspendedRows = ref([])
const matchCounts = ref({})
const loading = ref(false)
const pagination = ref({ prev_cursor: null, next_cursor: null })
const searchQuery = ref('')
const sortBy = ref('')
const selected = ref(new Set())

const showFormModal = ref(false)
const editingRule = ref(null)
const formType = ref('ip')
const showPatternBuilder = ref(false)
const showMatchesModal = ref(false)
const matchesRule = ref(null)
const showBanModal = ref(false)
const banProfileIds = ref([])

let searchTimeout = null
let requestSeq = 0

const sortOptions = [
    { name: 'Newest', value: 'newest' },
    { name: 'Oldest', value: 'oldest' },
    { name: 'Most blocked', value: 'hits' }
]

const ruleColumns = computed(() =>
    activeTab.value === 'ip'
        ? [
              { key: 'value', label: 'Rule' },
              { key: 'blocks', label: 'Blocks' },
              { key: 'matches', label: 'Existing accounts' },
              { key: 'hits', label: 'Hits' },
              { key: 'expires_at', label: 'Expires' },
              { key: 'note', label: 'Note' }
          ]
        : [
              { key: 'value', label: 'Rule' },
              { key: 'match_type', label: 'Match type' },
              { key: 'matches', label: 'Existing accounts' },
              { key: 'hits', label: 'Hits' },
              { key: 'note', label: 'Note' },
              { key: 'created_by', label: 'Added' }
          ]
)

const suspendedColumns = [
    { key: 'select', label: '' },
    { key: 'username', label: 'Account' },
    { key: 'register_ip', label: 'Register IP' },
    { key: 'last_ip', label: 'Last IP' },
    { key: 'suspended_at', label: 'Suspended' },
    { key: 'content', label: 'Content' }
]

const tableTitle = computed(
    () =>
        ({
            ip: 'IP rules',
            email: 'Email and domain rules',
            username: 'Username rules'
        })[activeTab.value]
)

const count = (group, key = 'total') => counts.value?.[group]?.[key] ?? 0

const tabs = computed(() => [
    { value: 'ip', label: 'IP addresses', count: counts.value.ip?.total ?? null },
    { value: 'email', label: 'Emails & domains', count: counts.value.email?.total ?? null },
    { value: 'username', label: 'Usernames', count: counts.value.username?.total ?? null },
    { value: 'suspended', label: 'Suspended accounts', count: counts.value.suspended ?? null }
])

const statCards = computed(() => {
    const attempts = counts.value.attempts || {}
    const loaded = counts.value.ip?.total !== undefined
    return [
        {
            label: 'IP rules',
            value: loaded ? count('ip') : null,
            detail: `${formatNumber(count('ip', 'exact'))} exact · ${formatNumber(count('ip', 'cidr'))} CIDR · ${formatNumber(count('ip', 'range'))} range`
        },
        {
            label: 'Email rules',
            value: loaded ? count('email') : null,
            detail: `${formatNumber(count('email', 'exact'))} exact · ${formatNumber(count('email', 'domain'))} domains · ${formatNumber(count('email', 'pattern'))} patterns`
        },
        {
            label: 'Username rules',
            value: loaded ? count('username') : null,
            detail: `${formatNumber(count('username', 'exact'))} exact · ${formatNumber(count('username', 'pattern'))} patterns`
        },
        {
            label: 'Blocked attempts, last 7 days',
            value: loaded ? (attempts.login || 0) + (attempts.register || 0) : null,
            detail: `Sign in ${formatNumber(attempts.login || 0)} · Sign up ${formatNumber(attempts.register || 0)}`
        }
    ]
})

const ruleDetail = (rule) => {
    if (rule.type === 'ip') {
        if (rule.match_type === 'exact') return rule.note && rule.source_profile_id ? rule.note : ''
        return `${rule.details?.size} addresses · ${rule.details?.first} to ${rule.details?.last}`
    }
    return ''
}

const appliesText = (rule) => {
    const parts = []
    if (rule.match_type === 'domain') parts.push('Includes subdomains')
    if (rule.blocks_login) parts.push('also blocks sign in')
    if (rule.is_expired) parts.push('expired')
    else if (rule.expires_at) parts.push(`expires ${formatDate(rule.expires_at)}`)
    return parts.join(', ')
}

const fetchCounts = async () => {
    try {
        counts.value = await accessRulesApi.getCounts()
    } catch (e) {
        counts.value = { ...counts.value }
    }
}

const fetchMatchCounts = async (items) => {
    const ids = items.map((rule) => rule.id).filter((id) => matchCounts.value[id] === undefined)
    if (!ids.length) return
    try {
        const response = await accessRulesApi.matchCounts(ids)
        matchCounts.value = { ...matchCounts.value, ...(response.counts || {}) }
    } catch (e) {
        const fallback = {}
        ids.forEach((id) => (fallback[id] = 0))
        matchCounts.value = { ...matchCounts.value, ...fallback }
    }
}

const fetchRules = async (cursor = null) => {
    const seq = ++requestSeq
    loading.value = true
    try {
        const response = await accessRulesApi.getRules({
            type: activeTab.value,
            cursor,
            search: searchQuery.value,
            sort: sortBy.value
        })
        if (seq !== requestSeq) return
        rules.value = response.data || []
        pagination.value = response.meta || { prev_cursor: null, next_cursor: null }
        fetchMatchCounts(rules.value)
    } catch (e) {
        if (seq === requestSeq) alertModal('Error loading rules', accessRuleErrorMessage(e))
    } finally {
        if (seq === requestSeq) loading.value = false
    }
}

const fetchSuspended = async (cursor = null) => {
    const seq = ++requestSeq
    loading.value = true
    try {
        const response = await accessRulesApi.getSuspended({ cursor, search: searchQuery.value })
        if (seq !== requestSeq) return
        suspendedRows.value = (response.data || []).map((row) => ({ ...row, id: row.profile_id }))
        pagination.value = response.meta || { prev_cursor: null, next_cursor: null }
    } catch (e) {
        if (seq === requestSeq)
            alertModal('Error loading suspended accounts', accessRuleErrorMessage(e))
    } finally {
        if (seq === requestSeq) loading.value = false
    }
}

const load = (cursor = null) =>
    activeTab.value === 'suspended' ? fetchSuspended(cursor) : fetchRules(cursor)

const refresh = () => {
    matchCounts.value = {}
    fetchCounts()
    load()
}

const setTab = (tab) => {
    if (activeTab.value === tab) return
    activeTab.value = tab
}

watch(activeTab, (tab) => {
    searchQuery.value = ''
    sortBy.value = ''
    selected.value = new Set()
    rules.value = []
    suspendedRows.value = []
    pagination.value = { prev_cursor: null, next_cursor: null }
    router.replace({ query: { ...route.query, tab } })
    load()
})

const handleSearch = (query) => {
    searchQuery.value = query
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => load(), 300)
}

const handleSort = (sort) => {
    sortBy.value = sort
    load()
}

const nextPage = () => {
    if (pagination.value.next_cursor) load(pagination.value.next_cursor)
}

const previousPage = () => {
    if (pagination.value.prev_cursor) load(pagination.value.prev_cursor)
}

const openCreateModal = () => {
    editingRule.value = null
    formType.value = activeTab.value === 'suspended' ? 'ip' : activeTab.value
    showFormModal.value = true
}

const openEditModal = (rule) => {
    editingRule.value = rule
    formType.value = rule.type
    showFormModal.value = true
}

const openPatternBuilder = () => {
    showPatternBuilder.value = true
}

const openMatches = (rule) => {
    matchesRule.value = rule
    showMatchesModal.value = true
}

const openBanModal = (profileIds) => {
    banProfileIds.value = profileIds
    showBanModal.value = true
}

const toggleSelected = (id) => {
    const next = new Set(selected.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    selected.value = next
}

const selectPage = () => {
    selected.value = new Set(suspendedRows.value.map((row) => row.profile_id))
}

const handleSaved = (rule) => {
    fetchCounts()
    if (rule?.type && rule.type !== activeTab.value) {
        activeTab.value = rule.type
        return
    }
    if (rule?.id) {
        const next = { ...matchCounts.value }
        delete next[rule.id]
        matchCounts.value = next
    }
    load()
}

const handlePurged = () => {
    matchCounts.value = {}
    fetchCounts()
    load()
}

const handleBanCompleted = () => {
    selected.value = new Set()
    matchCounts.value = {}
    fetchCounts()
    load()
}

const confirmDelete = async (rule) => {
    const ok = await confirmModal(
        'Delete rule',
        `Remove the rule for <span class="font-mono">${escapeHtml(rule.value)}</span>? It stops applying immediately.`,
        'Delete',
        'Cancel'
    )
    if (!ok) return

    try {
        await accessRulesApi.deleteRule(rule.id)
        fetchCounts()
        load()
    } catch (e) {
        alertModal('Error deleting rule', accessRuleErrorMessage(e))
    }
}

const escapeHtml = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
    )

onMounted(() => {
    fetchCounts()
    load()
})

onUnmounted(() => {
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>
