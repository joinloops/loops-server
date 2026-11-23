<template>
    <BaseModal
        v-model="show"
        :title="isSingle ? 'Ban & delete account' : `Ban & delete ${accounts.length} accounts`"
        max-width="2xl"
        :close-on-backdrop="!working"
    >
        <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-gray-500">
            <ArrowPathIcon class="h-5 w-5 animate-spin" />
            Loading account details
        </div>

        <div v-else-if="loadError" class="py-10 text-center text-sm text-rose-600">
            {{ loadError }}
        </div>

        <div v-else-if="!accounts.length" class="space-y-2 py-10 text-center">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                None of the selected accounts can be deleted.
            </p>
            <p v-for="item in skipped" :key="item.profile_id" class="text-xs text-gray-500">
                @{{ item.username || item.profile_id }}: {{ item.reason }}
            </p>
        </div>

        <div v-else class="space-y-5">
            <div
                v-if="isSingle"
                class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-3 dark:border-gray-700 dark:bg-gray-800/60"
            >
                <img
                    :src="single.avatar"
                    :alt="single.username"
                    class="h-11 w-11 flex-shrink-0 rounded-full object-cover"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[15px] font-semibold text-gray-900 dark:text-white">
                            @{{ single.username }}
                        </span>
                        <span :class="statusClass(single.status)">{{ single.status }}</span>
                    </div>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ single.email }} · {{ formatNumber(single.video_count) }} videos ·
                        {{ formatNumber(single.comment_count) }} comments ·
                        {{ formatNumber(single.followers) }} followers
                    </p>
                </div>
            </div>

            <div
                v-else
                class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
            >
                <div
                    v-for="account in accounts"
                    :key="account.profile_id"
                    class="flex items-center gap-3 border-b border-gray-100 px-3.5 py-2.5 last:border-b-0 dark:border-gray-800"
                >
                    <img
                        :src="account.avatar"
                        :alt="account.username"
                        class="h-8 w-8 flex-shrink-0 rounded-full object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            @{{ account.username }}
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ account.email }}
                        </p>
                    </div>
                    <p class="hidden font-mono text-xs text-gray-600 sm:block dark:text-gray-400">
                        {{ account.register_ip || 'no IP recorded' }}
                    </p>
                </div>
            </div>

            <div
                v-if="skipped.length"
                class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800/60 dark:text-gray-400"
            >
                Skipping {{ skipped.length }}:
                {{
                    skipped
                        .slice(0, 5)
                        .map((item) => `@${item.username || item.profile_id} (${item.reason})`)
                        .join(', ')
                }}
            </div>

            <section class="space-y-2">
                <h3
                    class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    1. Ban IP addresses
                </h3>
                <template v-if="isSingle">
                    <label
                        v-for="row in singleIpRows"
                        :key="row.key"
                        :class="[
                            'flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700',
                            row.disabled ? 'opacity-60' : ''
                        ]"
                    >
                        <span class="flex items-center gap-2.5">
                            <input
                                v-model="options[row.key]"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300"
                                :disabled="row.disabled"
                            />
                            <span class="font-mono text-sm text-gray-900 dark:text-white">{{
                                row.ip || 'Not recorded'
                            }}</span>
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{
                            row.label
                        }}</span>
                    </label>
                </template>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <label
                        class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-2.5 text-sm dark:border-gray-700"
                    >
                        <input
                            v-model="options.register_ip"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        />
                        Register IPs
                    </label>
                    <label
                        class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-2.5 text-sm dark:border-gray-700"
                    >
                        <input
                            v-model="options.last_ip"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        />
                        Last IPs
                    </label>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Blocks sign in and sign up. Addresses already covered by a rule are skipped, and
                    sign in is never blocked for an address an admin uses.
                </p>
            </section>

            <section class="space-y-2">
                <h3
                    class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    2. Ban email
                </h3>
                <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Email ban">
                    <label
                        v-for="choice in emailChoices"
                        :key="choice.value"
                        :class="[
                            'flex cursor-pointer flex-col gap-1 rounded-lg px-3 py-2.5',
                            options.email === choice.value
                                ? 'border-2 border-rose-500 bg-rose-50 dark:border-rose-400 dark:bg-rose-900/20'
                                : 'border border-gray-200 dark:border-gray-700',
                            choice.disabled ? 'cursor-not-allowed opacity-60' : ''
                        ]"
                    >
                        <span class="flex items-center gap-2">
                            <input
                                v-model="options.email"
                                type="radio"
                                name="ban-email"
                                :value="choice.value"
                                :disabled="choice.disabled"
                            />
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ choice.label }}
                            </span>
                        </span>
                        <span :class="['break-all text-xs', choice.tone]">{{ choice.hint }}</span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Whole domain is not available for common providers like gmail.com, outlook.com
                    and proton.me{{ isSingle ? '' : ', those accounts get an exact address ban' }}.
                </p>
            </section>

            <section class="space-y-2">
                <h3
                    class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    3. Ban username
                </h3>
                <label
                    :class="[
                        'flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700',
                        usernameDisabled ? 'opacity-60' : ''
                    ]"
                >
                    <span class="flex items-center gap-2.5">
                        <input
                            v-model="options.username"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                            :disabled="usernameDisabled"
                        />
                        <span class="font-mono text-sm text-gray-900 dark:text-white">
                            {{ isSingle ? single.username : 'Their usernames' }}
                        </span>
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ usernameHint }}</span>
                </label>
            </section>

            <section
                class="space-y-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 dark:border-rose-800 dark:bg-rose-900/20"
            >
                <h3
                    class="text-[11px] font-semibold uppercase tracking-wider text-rose-800 dark:text-rose-300"
                >
                    4. Delete permanently
                </h3>
                <ul class="space-y-1.5">
                    <li
                        v-for="line in deleteLines"
                        :key="line"
                        class="flex items-start gap-2 text-sm text-rose-900 dark:text-rose-200"
                    >
                        <CheckIcon class="mt-0.5 h-4 w-4 flex-shrink-0" />
                        {{ line }}
                    </li>
                </ul>
                <div>
                    <label
                        for="ban-note"
                        class="mb-1 block text-xs font-semibold text-rose-900 dark:text-rose-200"
                    >
                        Note on the rules <span class="font-normal">(optional)</span>
                    </label>
                    <input
                        id="ban-note"
                        v-model="options.note"
                        type="text"
                        maxlength="500"
                        :placeholder="
                            isSingle ? `Banned with @${single.username}` : 'Banned with @username'
                        "
                        class="w-full rounded-md border border-rose-300 bg-white px-3 py-2 text-sm dark:border-rose-700 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div>
                    <label
                        for="ban-confirm"
                        class="mb-1 block text-xs font-semibold text-rose-900 dark:text-rose-200"
                    >
                        Type DELETE to confirm
                    </label>
                    <input
                        id="ban-confirm"
                        v-model="confirmText"
                        type="text"
                        placeholder="DELETE"
                        autocomplete="off"
                        class="w-full rounded-md border border-rose-300 bg-white px-3 py-2 font-mono text-sm dark:border-rose-700 dark:bg-gray-900 dark:text-white"
                    />
                </div>
            </section>

            <p v-if="progress" class="text-sm text-gray-600 dark:text-gray-400">{{ progress }}</p>
        </div>

        <template #footer>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    <template v-if="accounts.length">
                        Creates up to {{ ruleEstimate }}
                        {{ ruleEstimate === 1 ? 'rule' : 'rules' }} · deletes {{ accounts.length }}
                        {{ accounts.length === 1 ? 'account' : 'accounts' }}
                    </template>
                </span>
                <div class="flex gap-2">
                    <AnimatedButton variant="light" pill :disabled="working" @click="show = false">
                        Cancel
                    </AnimatedButton>
                    <button
                        type="button"
                        :disabled="!canSubmit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300 dark:disabled:bg-rose-900"
                        @click="submit"
                    >
                        <ArrowPathIcon v-if="working" class="h-4 w-4 animate-spin" />
                        <NoSymbolIcon v-else class="h-4 w-4" />
                        Ban &amp; delete
                    </button>
                </div>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ArrowPathIcon, CheckIcon, NoSymbolIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import { accessRulesApi, accessRuleErrorMessage } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'

const props = defineProps({
    modelValue: Boolean,
    profileIds: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue', 'completed'])

const { formatNumber } = useUtils()
const { alertModal } = useAlertModal()

const CHUNK = 100

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const defaultOptions = () => ({
    register_ip: true,
    last_ip: true,
    email: 'exact',
    username: true,
    note: ''
})

const loading = ref(false)
const loadError = ref('')
const accounts = ref([])
const skipped = ref([])
const options = ref(defaultOptions())
const confirmText = ref('')
const working = ref(false)
const progress = ref('')

const isSingle = computed(() => accounts.value.length === 1)
const single = computed(() => accounts.value[0] || {})

const singleIpRows = computed(() => {
    const account = single.value
    const sameIp = account.register_ip && account.register_ip === account.last_ip
    const rows = [
        {
            key: 'register_ip',
            ip: account.register_ip,
            blocked: account.register_ip_blocked,
            label: sameIp ? 'Register and last IP' : 'Register IP'
        }
    ]
    if (!sameIp) {
        rows.push({
            key: 'last_ip',
            ip: account.last_ip,
            blocked: account.last_ip_blocked,
            label: 'Last IP'
        })
    }
    return rows.map((row) => ({
        ...row,
        disabled: !row.ip || row.blocked,
        label: row.blocked ? `${row.label}, already blocked` : row.label
    }))
})

const emailChoices = computed(() => {
    if (!isSingle.value) {
        return [
            {
                value: 'exact',
                label: 'Exact addresses',
                hint: 'One rule per account',
                tone: 'text-gray-500'
            },
            {
                value: 'domain',
                label: 'Whole domains',
                hint: 'Blocks each email domain',
                tone: 'text-amber-700 dark:text-amber-400'
            },
            { value: 'none', label: "Don't ban", hint: 'Emails stay usable', tone: 'text-gray-500' }
        ]
    }

    const account = single.value
    let domainHint = account.email_domain || 'No email'
    let domainTone = 'text-gray-500'
    let domainDisabled = !account.email_domain

    if (account.domain_protected) {
        domainHint = `${account.email_domain} is a common provider`
        domainDisabled = true
    } else if (account.domain_blocked) {
        domainHint = `${account.email_domain} is already blocked`
        domainDisabled = true
    } else if (account.domain_accounts) {
        domainHint = `${account.email_domain} · ${account.domain_accounts} other ${account.domain_accounts === 1 ? 'account uses' : 'accounts use'} it`
        domainTone = 'text-amber-700 dark:text-amber-400'
    }

    return [
        {
            value: 'exact',
            label: 'Exact address',
            hint: account.email_blocked
                ? `${account.email}, already blocked`
                : account.email || 'No email',
            tone: 'text-gray-500',
            disabled: !account.email
        },
        {
            value: 'domain',
            label: 'Whole domain',
            hint: domainHint,
            tone: domainTone,
            disabled: domainDisabled
        },
        { value: 'none', label: "Don't ban", hint: 'Email stays usable', tone: 'text-gray-500' }
    ]
})

const usernameDisabled = computed(
    () => isSingle.value && (!single.value.username_bannable || single.value.username_blocked)
)

const usernameHint = computed(() => {
    if (!isSingle.value) return 'Saved before the names are released'
    if (!single.value.username_bannable) return 'Already released'
    if (single.value.username_blocked) return 'Already blocked'
    return 'Saved before the name is released'
})

const totals = computed(() =>
    accounts.value.reduce(
        (sum, account) => ({
            videos: sum.videos + (account.video_count || 0),
            comments: sum.comments + (account.comment_count || 0)
        }),
        { videos: 0, comments: 0 }
    )
)

const deleteLines = computed(() => [
    `Purges ${formatNumber(totals.value.videos)} ${totals.value.videos === 1 ? 'video' : 'videos'} and their media, only inside each account's own storage folder`,
    `Removes ${formatNumber(totals.value.comments)} ${totals.value.comments === 1 ? 'comment' : 'comments'}, keeping tombstones where others replied`,
    'Sends Delete(Person) to every known server inbox',
    'Writes an audit log entry listing every rule created'
])

const ruleEstimate = computed(() =>
    accounts.value.reduce((count, account) => {
        const ips = new Set()
        if (options.value.register_ip && account.register_ip && !account.register_ip_blocked) {
            ips.add(account.register_ip)
        }
        if (options.value.last_ip && account.last_ip && !account.last_ip_blocked) {
            ips.add(account.last_ip)
        }
        let total = count + ips.size
        if (account.email && options.value.email === 'exact' && !account.email_blocked) total++
        if (account.email && options.value.email === 'domain') {
            if (account.domain_protected) total += account.email_blocked ? 0 : 1
            else if (!account.domain_blocked) total++
        }
        if (options.value.username && account.username_bannable && !account.username_blocked)
            total++
        return total
    }, 0)
)

const canSubmit = computed(
    () => !working.value && accounts.value.length > 0 && confirmText.value === 'DELETE'
)

const statusClass = (status) => [
    'rounded-full px-2 py-0.5 text-[11px] font-bold uppercase',
    status === 'suspended'
        ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
        : status === 'active'
          ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
          : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
]

const escapeHtml = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
    )

const load = async () => {
    if (!props.profileIds.length) return
    loading.value = true
    loadError.value = ''
    try {
        const response = await accessRulesApi.banPreview(props.profileIds.slice(0, CHUNK))
        accounts.value = response.data || []
        skipped.value = response.skipped || []
        if (accounts.value.length === 1) {
            const account = accounts.value[0]
            if (!account.register_ip || account.register_ip_blocked)
                options.value.register_ip = false
            if (!account.last_ip || account.last_ip_blocked) options.value.last_ip = false
            if (!account.email) options.value.email = 'none'
            if (!account.username_bannable || account.username_blocked)
                options.value.username = false
        }
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
        skipped.value = []
        options.value = defaultOptions()
        confirmText.value = ''
        progress.value = ''
        load()
    }
)

const submit = async () => {
    if (!canSubmit.value) return
    working.value = true

    const ids = accounts.value.map((account) => account.profile_id)
    const result = { deleted: [], skipped: [], rules_created: 0 }

    try {
        for (let i = 0; i < ids.length; i += CHUNK) {
            const chunk = ids.slice(i, i + CHUNK)
            progress.value = `Working on ${Math.min(i + chunk.length, ids.length)} of ${ids.length}`
            const response = await accessRulesApi.banAndDelete({
                profile_ids: chunk,
                register_ip: options.value.register_ip,
                last_ip: options.value.last_ip,
                email: options.value.email,
                username: options.value.username,
                note: options.value.note.trim() || null,
                confirm: confirmText.value
            })
            result.deleted.push(...(response.deleted || []))
            result.skipped.push(...(response.skipped || []))
            result.rules_created += response.rules_created || 0
        }

        emit('completed', result)
        show.value = false

        const skippedText = result.skipped.length
            ? `<br/><br/>Skipped ${result.skipped.length}: ${result.skipped
                  .slice(0, 5)
                  .map(
                      (s) => `@${escapeHtml(s.username || s.profile_id)} (${escapeHtml(s.reason)})`
                  )
                  .join(', ')}`
            : ''

        alertModal(
            'Banned and deleted',
            `Deleted ${result.deleted.length} ${result.deleted.length === 1 ? 'account' : 'accounts'} and added ${result.rules_created} ${result.rules_created === 1 ? 'rule' : 'rules'}. Purging and federation continue in the background.${skippedText}`
        )
    } catch (e) {
        alertModal('Ban & delete failed', accessRuleErrorMessage(e))
        if (result.deleted.length) emit('completed', result)
    } finally {
        working.value = false
        progress.value = ''
    }
}
</script>
