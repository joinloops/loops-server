<template>
    <SettingsLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <button
                    class="flex items-center text-gray-500 hover:text-gray-400 cursor-pointer"
                    @click="$router.back()"
                >
                    <i class="bx bx-arrow-back text-[20px] mr-1"></i>
                </button>
                <h1 class="text-2xl font-semibold tracking-tight dark:text-gray-100">
                    {{ t('migration.title') }}
                </h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <div v-if="isLoading" class="flex justify-center py-16">
                <i class="bx bx-loader-alt bx-spin text-[28px] text-gray-400"></i>
            </div>

            <template v-else-if="state">
                <p class="mt-6 mb-8 text-gray-700 dark:text-gray-400">
                    {{ t('migration.intro') }}
                </p>

                <div
                    v-if="state.account.moved_to"
                    class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-lg p-4 mb-8"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <i
                                class="bx bx-transfer-alt text-blue-500 dark:text-blue-300 text-[22px] mt-0.5"
                            ></i>
                            <div class="min-w-0">
                                <h3 class="font-medium text-blue-800 dark:text-blue-300 mb-1">
                                    {{ t('migration.movedTitle') }}
                                </h3>
                                <p class="text-sm text-blue-700 dark:text-blue-200 break-words">
                                    {{
                                        t('migration.movedMessage', {
                                            account: handleOf(state.account.moved_to.username),
                                            date: formatDay(state.account.moved_at)
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                        <a
                            :href="state.account.moved_to.url"
                            target="_blank"
                            rel="noopener"
                            class="shrink-0 px-4 py-2 text-sm font-medium text-blue-700 dark:text-blue-200 bg-white dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900 transition-colors"
                        >
                            {{ t('migration.viewNewAccount') }}
                        </a>
                    </div>
                </div>

                <section v-if="activeMigration" class="mb-10">
                    <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                        {{
                            activeMigration.direction === 'inbound'
                                ? t('migration.incomingTitle')
                                : t('migration.progressTitle')
                        }}
                    </h2>
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm p-6">
                        <div class="flex items-center gap-4 mb-5">
                            <img
                                v-if="activeMigration.remote_account?.avatar"
                                :src="activeMigration.remote_account.avatar"
                                class="w-12 h-12 rounded-full object-cover bg-gray-100 dark:bg-gray-800"
                                alt=""
                                @error="onAvatarError"
                            />
                            <div
                                v-else
                                class="w-12 h-12 rounded-full bg-[#F02C56]/10 flex items-center justify-center"
                            >
                                <i class="bx bx-transfer text-[#F02C56] text-[22px]"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ remoteLabel(activeMigration) }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ stateLabel(activeMigration.state) }}
                                </p>
                            </div>
                            <span
                                v-if="activeMigration.sealed"
                                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300"
                            >
                                <i class="bx bx-check-shield text-[14px]"></i>
                                {{ t('migration.sealed') }}
                            </span>
                        </div>

                        <ol class="flex items-center gap-2 mb-6">
                            <li
                                v-for="(step, index) in steps(activeMigration)"
                                :key="step"
                                class="flex-1 h-1.5 rounded-full"
                                :class="
                                    index <= stepIndex(activeMigration)
                                        ? 'bg-[#F02C56]'
                                        : 'bg-gray-200 dark:bg-gray-800'
                                "
                            ></li>
                        </ol>

                        <div v-if="activeMigration.progress.total > 0" class="mb-5">
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{
                                        t('migration.itemsProgress', {
                                            done: finishedCount(activeMigration),
                                            total: activeMigration.progress.total
                                        })
                                    }}
                                </span>
                                <span
                                    v-if="activeMigration.progress.bytes_total"
                                    class="text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        t('migration.bytesProgress', {
                                            done: formatBytes(activeMigration.progress.bytes_done),
                                            total: formatBytes(activeMigration.progress.bytes_total)
                                        })
                                    }}
                                </span>
                            </div>
                            <div
                                class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden"
                            >
                                <div
                                    class="h-full bg-[#F02C56] transition-all duration-500"
                                    :style="{ width: percent(activeMigration) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <div
                            v-if="kindRows(activeMigration).length"
                            class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5"
                        >
                            <div
                                v-for="row in kindRows(activeMigration)"
                                :key="row.kind"
                                class="px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-100 dark:border-gray-800"
                            >
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ row.label }}
                                </p>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{
                                        row.done === null ? row.total : `${row.done} / ${row.total}`
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 mb-5">
                            <p
                                v-if="activeMigration.progress.failed"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{
                                    t('migration.failedItems', {
                                        count: activeMigration.progress.failed
                                    })
                                }}
                            </p>
                            <p
                                v-if="activeMigration.progress.skipped"
                                class="text-sm text-orange-600 dark:text-orange-400"
                            >
                                {{
                                    t('migration.skippedItems', {
                                        count: activeMigration.progress.skipped
                                    })
                                }}
                            </p>
                        </div>

                        <div
                            v-if="activeMigration.can_finalize"
                            class="mb-5 px-4 py-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-900"
                        >
                            <p class="text-sm text-orange-800 dark:text-orange-200">
                                {{
                                    t('migration.readyMessage', {
                                        count: activeMigration.progress.failed
                                    })
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button
                                v-if="activeMigration.can_finalize"
                                type="button"
                                :disabled="isBusy"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#F02C56] rounded-lg hover:bg-[#d81f48] disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                @click="finalizeMove"
                            >
                                {{ t('migration.finalize') }}
                            </button>
                            <button
                                v-if="activeMigration.can_cancel"
                                type="button"
                                :disabled="isBusy"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-900 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                @click="cancelMove"
                            >
                                {{ t('migration.cancel') }}
                            </button>
                        </div>
                    </div>
                </section>

                <section v-if="!state.account.moved_to" class="mb-10">
                    <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                        {{ t('migration.movingHereTitle') }}
                    </h2>
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
                            {{ t('migration.movingHereHelp') }}
                        </p>

                        <div
                            v-if="!state.enabled.inbound"
                            class="mb-5 px-4 py-3 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-gray-800"
                        >
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ t('migration.inboundDisabled') }}
                            </p>
                        </div>

                        <h3 class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-3">
                            {{ t('migration.aliasesTitle') }}
                        </h3>

                        <ul
                            v-if="state.aliases.length"
                            class="divide-y divide-gray-100 dark:divide-gray-800 mb-5"
                        >
                            <li
                                v-for="alias in state.aliases"
                                :key="alias.uri"
                                class="flex items-center gap-3 py-3"
                            >
                                <img
                                    v-if="alias.avatar"
                                    :src="alias.avatar"
                                    class="w-9 h-9 rounded-full object-cover bg-gray-100 dark:bg-gray-800"
                                    alt=""
                                    @error="onAvatarError"
                                />
                                <div
                                    v-else
                                    class="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
                                >
                                    <i class="bx bx-user text-gray-400 text-[18px]"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                                    >
                                        {{ alias.username ? handleOf(alias.username) : alias.uri }}
                                    </p>
                                    <p class="text-xs text-gray-500 truncate">{{ alias.uri }}</p>
                                </div>
                                <button
                                    type="button"
                                    :disabled="isBusy"
                                    class="text-sm font-medium text-red-500 hover:text-red-600 disabled:opacity-50 cursor-pointer"
                                    @click="removeAlias(alias.uri)"
                                >
                                    {{ t('migration.removeAlias') }}
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                            {{ t('migration.aliasesEmpty') }}
                        </p>

                        <form
                            v-if="state.aliases.length < state.max_aliases"
                            class="flex flex-col sm:flex-row gap-3"
                            @submit.prevent="addAlias"
                        >
                            <input
                                v-model="aliasHandle"
                                type="text"
                                autocomplete="off"
                                autocapitalize="none"
                                spellcheck="false"
                                maxlength="255"
                                :placeholder="t('migration.aliasPlaceholder')"
                                :disabled="isBusy"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-700 dark:text-gray-200 dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-[#F02C56]/60 outline-none"
                            />
                            <button
                                type="submit"
                                :disabled="isBusy || !aliasHandle.trim()"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#F02C56] rounded-lg hover:bg-[#d81f48] disabled:bg-gray-300 dark:disabled:text-gray-600 dark:disabled:bg-gray-900 disabled:cursor-not-allowed transition-colors cursor-pointer"
                            >
                                {{
                                    aliasBusy ? t('migration.addingAlias') : t('migration.addAlias')
                                }}
                            </button>
                        </form>
                        <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                            {{ t('migration.aliasLimit', { count: state.max_aliases }) }}
                        </p>
                        <p v-if="aliasError" class="text-sm text-red-600 dark:text-red-400 mt-2">
                            {{ aliasError }}
                        </p>
                    </div>
                </section>

                <section v-if="!state.account.moved_to && !activeMigration" class="mb-10">
                    <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                        {{ t('migration.movingAwayTitle') }}
                    </h2>
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm p-6">
                        <div
                            v-if="!state.enabled.outbound"
                            class="px-4 py-3 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-gray-800"
                        >
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ t('migration.outboundDisabled') }}
                            </p>
                        </div>

                        <form v-else @submit.prevent="startMove">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                {{ t('migration.movingAwayHelp') }}
                            </p>

                            <div
                                class="mb-5 px-4 py-3 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-100 dark:border-gray-800"
                            >
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('migration.yourHandle') }}
                                </p>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-gray-100 break-all"
                                >
                                    {{ state.account.handle }}
                                </p>
                            </div>

                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2"
                            >
                                {{ t('migration.newAccountLabel') }}
                            </label>
                            <input
                                v-model="targetHandle"
                                type="text"
                                autocomplete="off"
                                autocapitalize="none"
                                spellcheck="false"
                                maxlength="255"
                                :placeholder="t('migration.newAccountPlaceholder')"
                                :disabled="isBusy"
                                class="w-full mb-6 px-3 py-2 border border-gray-300 dark:border-gray-700 dark:text-gray-200 dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-[#F02C56]/60 outline-none"
                            />

                            <h3 class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-3">
                                {{ t('migration.whatToBring') }}
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                                <label
                                    v-for="scope in state.scopes"
                                    :key="scope"
                                    class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="
                                        selectedScopes.includes(scope)
                                            ? 'border-[#F02C56]/60 bg-[#F02C56]/5'
                                            : 'border-gray-200 dark:border-gray-800'
                                    "
                                >
                                    <input
                                        v-model="selectedScopes"
                                        type="checkbox"
                                        :value="scope"
                                        :disabled="isBusy"
                                        class="mt-1 accent-[#F02C56]"
                                    />
                                    <span>
                                        <span
                                            class="block text-sm font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{ t(`migration.scope.${scope}`) }}
                                        </span>
                                        <span
                                            class="block text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ t(`migration.scopeHelp.${scope}`) }}
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div
                                class="mb-6 px-4 py-4 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-100 dark:border-gray-800"
                            >
                                <h3
                                    class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2"
                                >
                                    {{ t('migration.whatHappensTitle') }}
                                </h3>
                                <ul class="flex flex-col gap-1.5">
                                    <li
                                        v-for="key in [
                                            'whatHappens1',
                                            'whatHappens2',
                                            'whatHappens3',
                                            'whatHappens4'
                                        ]"
                                        :key="key"
                                        class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <i class="bx bx-check text-green-500 text-[18px]"></i>
                                        <span>{{ t(`migration.${key}`) }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="state.requires_password" class="mb-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2"
                                >
                                    {{ t('migration.confirmPassword') }}
                                </label>
                                <input
                                    v-model="password"
                                    type="password"
                                    autocomplete="current-password"
                                    :placeholder="t('migration.passwordPlaceholder')"
                                    :disabled="isBusy"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:text-gray-200 dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-[#F02C56]/60 outline-none"
                                />
                            </div>

                            <div
                                v-if="state.cooldown_ends_at"
                                class="mb-4 flex items-start gap-2 text-sm text-orange-700 dark:text-orange-300"
                            >
                                <i class="bx bx-time text-[18px] mt-0.5"></i>
                                <span>{{
                                    t('migration.cooldownNotice', {
                                        time: relativeTime(state.cooldown_ends_at)
                                    })
                                }}</span>
                            </div>

                            <p
                                v-if="startError"
                                class="text-sm text-red-600 dark:text-red-400 mb-4"
                            >
                                {{ startError }}
                            </p>

                            <button
                                type="submit"
                                :disabled="!canStart"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#F02C56] rounded-lg hover:bg-[#d81f48] disabled:bg-gray-300 dark:disabled:text-gray-600 dark:disabled:bg-gray-900 disabled:cursor-not-allowed transition-colors cursor-pointer"
                            >
                                {{ startBusy ? t('migration.starting') : t('migration.startMove') }}
                            </button>
                        </form>
                    </div>
                </section>

                <section v-if="pastMigrations.length" class="mb-6">
                    <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                        {{ t('migration.historyTitle') }}
                    </h2>
                    <div
                        class="bg-white dark:bg-slate-950 rounded-lg shadow-sm divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <div
                            v-for="item in pastMigrations"
                            :key="item.id"
                            class="flex items-center justify-between gap-4 px-6 py-4"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                                >
                                    {{
                                        item.direction === 'inbound'
                                            ? t('migration.directionInbound')
                                            : t('migration.directionOutbound')
                                    }}
                                    · {{ remoteLabel(item) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDay(item.created_at) }}
                                    <template v-if="item.error"> · {{ item.error }}</template>
                                </p>
                            </div>
                            <span
                                class="shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="stateBadge(item.state)"
                            >
                                {{ stateLabel(item.state) }}
                            </span>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import axios from '~/plugins/axios'
import { useAlertModal } from '@/composables/useAlertModal.js'

const { t, locale } = useI18n()
const { alertModal, confirmModal } = useAlertModal()
const axiosInstance = axios.getAxiosInstance()

const ACTIVE_STATES = [
    'building',
    'offered',
    'pending',
    'transferring',
    'sealing',
    'ready',
    'finalizing'
]
const OUTBOUND_STEPS = ['building', 'offered', 'transferring', 'ready', 'finalized']
const INBOUND_STEPS = ['pending', 'transferring', 'sealing', 'completed']
const KIND_SCOPE = {
    profile: 'profile',
    video: 'videos',
    sound: 'sounds',
    playlist: 'playlists',
    follow: 'following',
    block: 'blocks',
    bookmark: 'bookmarks'
}

const state = ref(null)
const isLoading = ref(true)
const isBusy = ref(false)
const aliasBusy = ref(false)
const startBusy = ref(false)
const aliasHandle = ref('')
const aliasError = ref('')
const targetHandle = ref('')
const password = ref('')
const startError = ref('')
const selectedScopes = ref([
    'profile',
    'videos',
    'playlists',
    'sounds',
    'following',
    'blocks',
    'bookmarks'
])

let pollTimer = null

const isActive = (migration) => !!migration && ACTIVE_STATES.includes(migration.state)

const activeMigration = computed(() => {
    if (!state.value) return null
    if (isActive(state.value.outbound)) return state.value.outbound
    if (isActive(state.value.inbound)) return state.value.inbound
    return null
})

const pastMigrations = computed(() =>
    (state.value?.history || []).filter((item) => item.id !== activeMigration.value?.id)
)

const canStart = computed(() => {
    if (!state.value || isBusy.value || startBusy.value) return false
    if (!targetHandle.value.trim() || selectedScopes.value.length === 0) return false
    if (state.value.cooldown_ends_at) return false
    if (state.value.requires_password && !password.value) return false
    return true
})

const handleOf = (username) => `@${username}`

const remoteLabel = (migration) => {
    if (migration.remote_account?.username) {
        return handleOf(migration.remote_account.username)
    }
    return migration.remote_domain
}

const stateLabel = (value) => t(`migration.state.${value}`)

const stateBadge = (value) => {
    if (['finalized', 'completed'].includes(value)) {
        return 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
    }
    if (['failed', 'rejected'].includes(value)) {
        return 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
    }
    if (value === 'cancelled') {
        return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'
    }
    return 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
}

const steps = (migration) => (migration.direction === 'inbound' ? INBOUND_STEPS : OUTBOUND_STEPS)

const stepIndex = (migration) => {
    const list = steps(migration)
    const value = migration.state === 'finalizing' ? 'ready' : migration.state
    const index = list.indexOf(value)
    return index === -1 ? 0 : index
}

const finishedCount = (migration) =>
    migration.progress.done + migration.progress.failed + migration.progress.skipped

const percent = (migration) => {
    if (!migration.progress.total) return 0
    return Math.min(100, Math.round((finishedCount(migration) / migration.progress.total) * 100))
}

const kindRows = (migration) =>
    Object.entries(migration.kinds || {})
        .filter(([kind]) => KIND_SCOPE[kind])
        .map(([kind, counts]) => ({
            kind,
            label: t(`migration.scope.${KIND_SCOPE[kind]}`),
            done: migration.direction === 'inbound' ? counts.done || 0 : null,
            total: counts.total ?? Object.values(counts).reduce((sum, value) => sum + value, 0)
        }))

const formatBytes = (bytes) => {
    const value = Number(bytes || 0)
    if (value < 1024) return `${value} B`
    const units = ['KB', 'MB', 'GB', 'TB']
    let size = value / 1024
    let unit = 0
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024
        unit++
    }
    return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[unit]}`
}

const formatDay = (iso) => {
    if (!iso) return ''
    return new Date(iso).toLocaleDateString(locale.value || undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const relativeTime = (iso) => {
    if (!iso) return ''
    const diffSeconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000)
    const rtf = new Intl.RelativeTimeFormat(locale.value || 'en', { numeric: 'auto' })
    const units = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60]
    ]
    for (const [unit, size] of units) {
        if (Math.abs(diffSeconds) >= size) {
            return rtf.format(Math.round(diffSeconds / size), unit)
        }
    }
    return rtf.format(Math.max(diffSeconds, 1), 'second')
}

const onAvatarError = (event) => {
    event.target.src = '/storage/avatars/default.jpg'
}

const errorMessage = (error) =>
    error?.response?.data?.error?.message ||
    error?.response?.data?.message ||
    t('migration.somethingWentWrong')

const fetchState = async () => {
    try {
        const res = await axiosInstance.get('/api/v1/account/migration')
        state.value = res.data.data
    } catch (error) {
        console.error('Failed to load migration status:', error)
    } finally {
        isLoading.value = false
    }
}

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

const startPolling = () => {
    if (pollTimer) return
    pollTimer = setInterval(fetchState, 5000)
}

watch(activeMigration, (value) => {
    if (value) {
        startPolling()
    } else {
        stopPolling()
    }
})

const addAlias = async () => {
    const handle = aliasHandle.value.trim()
    if (!handle) return
    aliasError.value = ''
    aliasBusy.value = true
    isBusy.value = true
    try {
        const res = await axiosInstance.post('/api/v1/account/migration/aliases', { handle })
        state.value = res.data.data
        aliasHandle.value = ''
    } catch (error) {
        aliasError.value = errorMessage(error)
    } finally {
        aliasBusy.value = false
        isBusy.value = false
    }
}

const removeAlias = async (uri) => {
    isBusy.value = true
    try {
        const res = await axiosInstance.post('/api/v1/account/migration/aliases/remove', { uri })
        state.value = res.data.data
    } catch (error) {
        aliasError.value = errorMessage(error)
    } finally {
        isBusy.value = false
    }
}

const startMove = async () => {
    if (!canStart.value) return
    const handle = targetHandle.value.trim().replace(/^@+/, '')

    const confirmed = await confirmModal(
        t('migration.confirmStartTitle'),
        t('migration.confirmStartMessage', { account: `@${handle}` }),
        t('migration.startMove'),
        t('common.cancel')
    )
    if (!confirmed) return

    startError.value = ''
    startBusy.value = true
    isBusy.value = true
    try {
        const res = await axiosInstance.post('/api/v1/account/migration/start', {
            handle,
            scope: selectedScopes.value,
            password: password.value || null
        })
        state.value = res.data.data
        password.value = ''
    } catch (error) {
        startError.value = errorMessage(error)
    } finally {
        startBusy.value = false
        isBusy.value = false
    }
}

const cancelMove = async () => {
    const confirmed = await confirmModal(
        t('migration.confirmCancelTitle'),
        t('migration.confirmCancelMessage'),
        t('migration.cancel'),
        t('migration.keepGoing')
    )
    if (!confirmed) return

    isBusy.value = true
    try {
        const res = await axiosInstance.post('/api/v1/account/migration/cancel')
        state.value = res.data.data
    } catch (error) {
        alertModal(t('migration.title'), errorMessage(error))
    } finally {
        isBusy.value = false
    }
}

const finalizeMove = async () => {
    const confirmed = await confirmModal(
        t('migration.confirmFinalizeTitle'),
        t('migration.confirmFinalizeMessage'),
        t('migration.finalize'),
        t('migration.keepGoing')
    )
    if (!confirmed) return

    isBusy.value = true
    try {
        const res = await axiosInstance.post('/api/v1/account/migration/finalize')
        state.value = res.data.data
    } catch (error) {
        alertModal(t('migration.title'), errorMessage(error))
    } finally {
        isBusy.value = false
    }
}

onMounted(async () => {
    await fetchState()
    if (activeMigration.value) {
        startPolling()
    }
})

onBeforeUnmount(stopPolling)
</script>
