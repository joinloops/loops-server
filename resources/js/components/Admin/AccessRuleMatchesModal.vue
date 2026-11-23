<template>
    <BaseModal v-model="show" max-width="4xl" :close-on-backdrop="!purging">
        <template #header>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Accounts matching
                    </h2>
                    <span class="break-all font-mono text-sm text-gray-900 dark:text-gray-100">
                        {{ rule?.value }}
                    </span>
                    <AccessRuleBadge v-if="rule" :match-type="rule.match_type" />
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ subtitle }}
                </p>
            </div>
        </template>

        <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-gray-500">
            <ArrowPathIcon class="h-5 w-5 animate-spin" />
            Finding matching accounts
        </div>

        <div v-else-if="loadError" class="py-10 text-center text-sm text-rose-600">
            {{ loadError }}
        </div>

        <div v-else-if="!accounts.length" class="py-12 text-center">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                No local accounts match this rule right now.
            </p>
            <p v-if="meta.admins_skipped" class="mt-1 text-xs text-gray-500">
                {{ meta.admins_skipped }} admin
                {{ meta.admins_skipped === 1 ? 'account was' : 'accounts were' }} skipped.
            </p>
        </div>

        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div
                    v-if="rule?.type === 'ip'"
                    class="flex gap-1 rounded-full bg-gray-100 p-1 dark:bg-gray-800"
                >
                    <button
                        v-for="option in filterOptions"
                        :key="option.value"
                        type="button"
                        :class="[
                            'h-8 rounded-full px-3.5 text-sm transition',
                            filter === option.value
                                ? 'bg-red-500 font-semibold text-white'
                                : 'font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400'
                        ]"
                        @click="filter = option.value"
                    >
                        {{ option.label }} ({{ option.count }})
                    </button>
                </div>
                <span v-else></span>
                <label
                    class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300"
                        :checked="allVisibleSelected"
                        :indeterminate="someVisibleSelected && !allVisibleSelected"
                        @change="toggleAllVisible"
                    />
                    Select all {{ visible.length }}
                </label>
            </div>

            <div
                class="max-h-[22rem] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
            >
                <div
                    v-for="account in visible"
                    :key="account.profile_id"
                    class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 dark:border-gray-800"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 flex-shrink-0 rounded border-gray-300"
                        :aria-label="`Select @${account.username}`"
                        :checked="selected.has(account.profile_id)"
                        @change="toggle(account.profile_id)"
                    />
                    <img
                        :src="account.avatar"
                        :alt="account.username"
                        class="h-9 w-9 flex-shrink-0 rounded-full object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <router-link
                            :to="`/admin/profiles/${account.profile_id}`"
                            class="text-sm font-semibold text-gray-900 hover:underline dark:text-white"
                            target="_blank"
                        >
                            @{{ account.username }}
                        </router-link>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ account.email }}
                        </p>
                    </div>
                    <span :class="statusClass(account.status)">{{ account.status }}</span>
                    <div class="hidden w-48 flex-shrink-0 sm:block">
                        <p class="truncate font-mono text-xs text-gray-900 dark:text-gray-100">
                            {{ matchedValue(account) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ matchedLabel(account) }}
                        </p>
                    </div>
                    <div
                        class="hidden w-28 flex-shrink-0 text-xs text-gray-600 md:block dark:text-gray-400"
                    >
                        {{ formatNumber(account.video_count) }} videos
                        <br />
                        joined {{ formatDate(account.created_at) }}
                    </div>
                </div>
            </div>

            <div
                v-if="meta.limited || meta.admins_skipped"
                class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800/60 dark:text-gray-400"
            >
                <template v-if="meta.limited">
                    Showing the first {{ accounts.length }} of {{ formatNumber(meta.total) }}
                    matches. Delete these, then reopen this list to continue.
                </template>
                <template v-if="meta.admins_skipped">
                    {{ meta.admins_skipped }} admin
                    {{ meta.admins_skipped === 1 ? 'account' : 'accounts' }} in this rule
                    {{ meta.admins_skipped === 1 ? 'was' : 'were' }} skipped and can't be deleted
                    from here.
                </template>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Also ban, so they can't come back
                    </p>
                    <label
                        class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700"
                    >
                        <input
                            v-model="banEmails"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300"
                        />
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                Their email addresses
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Adds exact email rules for the selected accounts
                            </span>
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700"
                    >
                        <input
                            v-model="banUsernames"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300"
                        />
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                Their usernames
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Saved before the usernames are released
                            </span>
                        </span>
                    </label>
                </div>
                <div
                    class="space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-3.5 dark:border-rose-800 dark:bg-rose-900/20"
                >
                    <div class="flex items-center gap-2">
                        <ExclamationTriangleIcon class="h-4 w-4 text-rose-700 dark:text-rose-300" />
                        <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">
                            Permanent, no 30 day grace period
                        </p>
                    </div>
                    <p class="text-xs leading-relaxed text-rose-800 dark:text-rose-300">
                        Videos, comments, DMs and media are purged and a Delete is sent to every
                        known server. Purges run in the background.
                    </p>
                    <label
                        for="matches-confirm"
                        class="block text-xs font-semibold text-rose-900 dark:text-rose-200"
                    >
                        Type DELETE to confirm
                    </label>
                    <input
                        id="matches-confirm"
                        v-model="confirmText"
                        type="text"
                        placeholder="DELETE"
                        autocomplete="off"
                        class="w-full rounded-md border border-rose-300 bg-white px-3 py-2 font-mono text-sm dark:border-rose-700 dark:bg-gray-900 dark:text-white"
                    />
                </div>
            </div>

            <p v-if="progress" class="text-sm text-gray-600 dark:text-gray-400">{{ progress }}</p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <AnimatedButton variant="light" pill :disabled="purging" @click="show = false">
                    Cancel
                </AnimatedButton>
                <button
                    type="button"
                    :disabled="!canPurge"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300 dark:disabled:bg-rose-900"
                    @click="purge"
                >
                    <ArrowPathIcon v-if="purging" class="h-4 w-4 animate-spin" />
                    <TrashIcon v-else class="h-4 w-4" />
                    Delete {{ selectedCount }} {{ selectedCount === 1 ? 'account' : 'accounts' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ArrowPathIcon, ExclamationTriangleIcon, TrashIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import AccessRuleBadge from '@/components/Admin/AccessRuleBadge.vue'
import { accessRulesApi, accessRuleErrorMessage } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'

const props = defineProps({
    modelValue: Boolean,
    rule: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'purged'])

const { formatNumber, formatDate } = useUtils()
const { alertModal } = useAlertModal()

const CHUNK = 100

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const loading = ref(false)
const loadError = ref('')
const accounts = ref([])
const meta = ref({ total: 0, admins_skipped: 0, limited: false })
const selected = ref(new Set())
const filter = ref('all')
const banEmails = ref(true)
const banUsernames = ref(true)
const confirmText = ref('')
const purging = ref(false)
const progress = ref('')

const subtitle = computed(() => {
    if (props.rule?.type === 'ip') {
        return 'Local accounts whose register IP or last IP falls in this rule. Admin accounts are never listed.'
    }
    if (props.rule?.type === 'email') return 'Local accounts with a matching email address.'
    return 'Local accounts with a matching username.'
})

const filterOptions = computed(() => [
    { value: 'all', label: 'All matches', count: accounts.value.length },
    {
        value: 'register',
        label: 'Register IP',
        count: accounts.value.filter((a) => ['register_ip', 'both'].includes(a.matched_on)).length
    },
    {
        value: 'last',
        label: 'Last IP',
        count: accounts.value.filter((a) => ['last_ip', 'both'].includes(a.matched_on)).length
    }
])

const visible = computed(() => {
    if (filter.value === 'register') {
        return accounts.value.filter((a) => ['register_ip', 'both'].includes(a.matched_on))
    }
    if (filter.value === 'last') {
        return accounts.value.filter((a) => ['last_ip', 'both'].includes(a.matched_on))
    }
    return accounts.value
})

const allVisibleSelected = computed(
    () => visible.value.length > 0 && visible.value.every((a) => selected.value.has(a.profile_id))
)

const someVisibleSelected = computed(() =>
    visible.value.some((a) => selected.value.has(a.profile_id))
)

const selectedCount = computed(() => selected.value.size)

const canPurge = computed(
    () => !purging.value && selectedCount.value > 0 && confirmText.value === 'DELETE'
)

const statusClass = (status) => [
    'hidden flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold capitalize sm:inline-flex',
    status === 'active'
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : status === 'suspended'
          ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
          : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
]

const matchedValue = (account) => {
    if (account.matched_on === 'register_ip' || account.matched_on === 'both')
        return account.register_ip
    if (account.matched_on === 'last_ip') return account.last_ip
    if (account.matched_on === 'email') return account.email
    return `@${account.username}`
}

const matchedLabel = (account) =>
    ({
        register_ip: 'Register IP',
        last_ip: 'Last IP',
        both: 'Register and last IP',
        email: 'Email',
        username: 'Username'
    })[account.matched_on] || ''

const escapeHtml = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
    )

const toggle = (id) => {
    const next = new Set(selected.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    selected.value = next
}

const toggleAllVisible = () => {
    const next = new Set(selected.value)
    if (allVisibleSelected.value) {
        visible.value.forEach((a) => next.delete(a.profile_id))
    } else {
        visible.value.forEach((a) => next.add(a.profile_id))
    }
    selected.value = next
}

const load = async () => {
    if (!props.rule?.id) return
    loading.value = true
    loadError.value = ''
    try {
        const response = await accessRulesApi.getMatches(props.rule.id)
        accounts.value = response.data || []
        meta.value = response.meta || { total: 0, admins_skipped: 0, limited: false }
        selected.value = new Set(accounts.value.map((a) => a.profile_id))
    } catch (e) {
        loadError.value = accessRuleErrorMessage(e)
    } finally {
        loading.value = false
    }
}

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return
        accounts.value = []
        selected.value = new Set()
        filter.value = 'all'
        banEmails.value = true
        banUsernames.value = true
        confirmText.value = ''
        progress.value = ''
        load()
    }
)

const purge = async () => {
    if (!canPurge.value) return
    purging.value = true

    const ids = [...selected.value]
    const totals = { deleted: [], skipped: [], rules_created: 0 }

    try {
        for (let i = 0; i < ids.length; i += CHUNK) {
            const chunk = ids.slice(i, i + CHUNK)
            progress.value = `Deleting ${Math.min(i + chunk.length, ids.length)} of ${ids.length}`
            const response = await accessRulesApi.purgeMatches(props.rule.id, {
                profile_ids: chunk,
                ban_emails: banEmails.value,
                ban_usernames: banUsernames.value,
                confirm: confirmText.value
            })
            totals.deleted.push(...(response.deleted || []))
            totals.skipped.push(...(response.skipped || []))
            totals.rules_created += response.rules_created || 0
        }

        emit('purged', totals)
        show.value = false

        const skippedText = totals.skipped.length
            ? `<br/><br/>Skipped ${totals.skipped.length}: ${totals.skipped
                  .slice(0, 5)
                  .map(
                      (s) => `@${escapeHtml(s.username || s.profile_id)} (${escapeHtml(s.reason)})`
                  )
                  .join(', ')}`
            : ''

        alertModal(
            'Accounts deleted',
            `Deleted ${totals.deleted.length} ${totals.deleted.length === 1 ? 'account' : 'accounts'} and added ${totals.rules_created} ${totals.rules_created === 1 ? 'rule' : 'rules'}. Purging continues in the background.${skippedText}`
        )
    } catch (e) {
        alertModal('Delete failed', accessRuleErrorMessage(e))
        if (totals.deleted.length) emit('purged', totals)
    } finally {
        purging.value = false
        progress.value = ''
    }
}
</script>
