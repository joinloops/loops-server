<template>
    <BaseModal v-model="show" :title="isEditing ? 'Edit rule' : 'Add rule'" max-width="xl">
        <div class="space-y-5">
            <div
                v-if="!isEditing"
                class="grid grid-cols-3 gap-2"
                role="radiogroup"
                aria-label="Rule type"
            >
                <button
                    v-for="option in typeOptions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.type === option.value"
                    :class="choiceClass(form.type === option.value)"
                    @click="setType(option.value)"
                >
                    <component :is="option.icon" class="h-4 w-4" />
                    {{ option.label }}
                </button>
            </div>

            <div v-if="form.type !== 'ip'" class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Match</span>
                <button
                    v-for="option in matchOptions"
                    :key="option.value"
                    type="button"
                    :class="pillClass(form.match_type === option.value)"
                    @click="setMatchType(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div>
                <label
                    for="access-rule-value"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ valueLabel }}
                </label>
                <input
                    id="access-rule-value"
                    v-model="form.value"
                    type="text"
                    :placeholder="valuePlaceholder"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ valueHelp }}</p>

                <div
                    v-if="preview?.valid && form.type === 'ip'"
                    class="mt-3 grid grid-cols-3 gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60"
                >
                    <div>
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            Parsed as
                        </p>
                        <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                            IPv{{ preview.details.version }} {{ matchLabel(preview.match_type) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            Addresses
                        </p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ preview.details.size }}
                        </p>
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            Range
                        </p>
                        <p
                            class="truncate font-mono text-xs text-gray-900 dark:text-white"
                            :title="`${preview.details.first} to ${preview.details.last}`"
                        >
                            {{ preview.details.first }} to {{ preview.details.last }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="preview && !preview.valid"
                    class="mt-2 text-sm text-rose-600 dark:text-rose-400"
                >
                    {{ preview.error }}
                </p>
                <p
                    v-else-if="preview?.valid && preview.value !== form.value.trim()"
                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                >
                    Saved as <span class="font-mono">{{ preview.value }}</span>
                </p>
            </div>

            <div v-if="form.type !== 'username'" class="space-y-2">
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Blocks
                </span>
                <div
                    v-for="toggle in toggles"
                    :key="toggle.key"
                    class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-3.5 py-3 dark:border-gray-700"
                >
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ toggle.label }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ toggle.description }}
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form[toggle.key]"
                        :aria-label="`Block ${toggle.label.toLowerCase()}`"
                        :class="[
                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition',
                            form[toggle.key] ? 'bg-[#F02C56]' : 'bg-gray-300 dark:bg-gray-600'
                        ]"
                        @click="form[toggle.key] = !form[toggle.key]"
                    >
                        <span
                            :class="[
                                'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all',
                                form[toggle.key] ? 'left-[22px]' : 'left-0.5'
                            ]"
                        ></span>
                    </button>
                </div>
            </div>
            <div
                v-else
                class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400"
            >
                Username rules apply to sign up, admin invites, curated applications and username
                changes.
            </div>

            <div class="space-y-2">
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Expires</span
                >
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in expiryPresets"
                        :key="option.value"
                        type="button"
                        :class="pillClass(expiryPreset === option.value, true)"
                        @click="setExpiry(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <input
                    v-if="expiryPreset === 'custom'"
                    v-model="customExpiry"
                    type="datetime-local"
                    aria-label="Custom expiry"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    @input="expiryDirty = true"
                />
            </div>

            <div>
                <label
                    for="access-rule-note"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Note <span class="font-normal text-gray-400">(optional, admins only)</span>
                </label>
                <textarea
                    id="access-rule-note"
                    v-model="form.note"
                    rows="2"
                    maxlength="500"
                    placeholder="Why was this added?"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                ></textarea>
            </div>

            <div
                v-if="preview?.existing_rule_id"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
            >
                A rule for this value already exists.
            </div>

            <div
                v-if="preview?.admin_conflict && form.blocks_login && form.type !== 'username'"
                class="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300"
            >
                <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 flex-shrink-0" />
                <span
                    >{{ preview.admin_conflict }} Turn off sign in to save it as sign up only.</span
                >
            </div>

            <label
                v-if="preview?.valid && preview.details?.broad"
                class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 dark:border-amber-800 dark:bg-amber-900/20"
            >
                <input
                    v-model="confirmBroad"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-gray-300"
                />
                <span class="text-sm text-amber-800 dark:text-amber-300">
                    This range covers {{ preview.details.size }} addresses. I understand this is a
                    broad block.
                </span>
            </label>

            <div
                v-if="form.value.trim() && (previewLoading || preview?.valid)"
                :class="[
                    'flex items-center justify-between gap-3 rounded-xl border px-3.5 py-3',
                    preview?.matches
                        ? 'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-900/20'
                        : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60'
                ]"
            >
                <div v-if="previewLoading" class="flex items-center gap-2 text-sm text-gray-500">
                    <ArrowPathIcon class="h-4 w-4 animate-spin" />
                    Checking existing accounts
                </div>
                <template v-else-if="preview?.matches">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex -space-x-2">
                            <img
                                v-for="account in preview.sample.slice(0, 3)"
                                :key="account.profile_id"
                                :src="account.avatar"
                                :alt="account.username"
                                class="h-8 w-8 rounded-full border-2 border-rose-50 object-cover dark:border-rose-900"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">
                                {{ formatNumber(preview.matches) }} existing
                                {{ preview.matches === 1 ? 'account matches' : 'accounts match' }}
                            </p>
                            <p class="truncate text-xs text-rose-700 dark:text-rose-300">
                                {{ matchHint }}
                            </p>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-gray-600 dark:text-gray-400">
                    No existing accounts match{{
                        preview?.admins ? `, ${preview.admins} admin account skipped` : ''
                    }}
                </p>
            </div>

            <p v-if="error" class="text-sm text-rose-600 dark:text-rose-400">{{ error }}</p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <AnimatedButton variant="light" pill @click="show = false">Cancel</AnimatedButton>
                <AnimatedButton
                    variant="primaryGradient"
                    pill
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    <div class="flex items-center gap-2">
                        <ArrowPathIcon v-if="saving" class="h-4 w-4 animate-spin" />
                        {{ isEditing ? 'Save changes' : 'Add rule' }}
                    </div>
                </AnimatedButton>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import {
    ArrowPathIcon,
    AtSymbolIcon,
    ExclamationTriangleIcon,
    GlobeAltIcon,
    UserIcon
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import { accessRulesApi, accessRuleErrorMessage } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    modelValue: Boolean,
    rule: { type: Object, default: null },
    initialType: { type: String, default: 'ip' }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { formatNumber } = useUtils()

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const isEditing = computed(() => !!props.rule?.id)

const typeOptions = [
    { value: 'ip', label: 'IP address', icon: GlobeAltIcon },
    { value: 'email', label: 'Email', icon: AtSymbolIcon },
    { value: 'username', label: 'Username', icon: UserIcon }
]

const expiryPresets = [
    { value: 'never', label: 'Never' },
    { value: '24h', label: '24 hours', hours: 24 },
    { value: '7d', label: '7 days', hours: 168 },
    { value: '30d', label: '30 days', hours: 720 },
    { value: 'custom', label: 'Custom date' }
]

const blankForm = (type) => ({
    type,
    value: '',
    match_type: type === 'ip' ? null : 'exact',
    blocks_login: type === 'ip',
    blocks_registration: true,
    note: ''
})

const form = ref(blankForm(props.initialType))
const expiryPreset = ref('never')
const customExpiry = ref('')
const expiryDirty = ref(false)
const confirmBroad = ref(false)
const matchTypeTouched = ref(false)
const preview = ref(null)
const previewLoading = ref(false)
const saving = ref(false)
const error = ref('')

let previewTimer = null
let previewSeq = 0

const matchOptions = computed(() =>
    form.value.type === 'email'
        ? [
              { value: 'exact', label: 'Exact address' },
              { value: 'domain', label: 'Whole domain' },
              { value: 'pattern', label: 'Pattern' }
          ]
        : [
              { value: 'exact', label: 'Exact username' },
              { value: 'pattern', label: 'Pattern' }
          ]
)

const valueLabel = computed(() => {
    if (form.value.type === 'ip') return 'IP address, CIDR or range'
    if (form.value.match_type === 'pattern') return 'Regular expression'
    if (form.value.type === 'email') {
        return form.value.match_type === 'domain' ? 'Email domain' : 'Email address'
    }
    return 'Username'
})

const valuePlaceholder = computed(() => {
    if (form.value.type === 'ip') return '203.0.113.0/24'
    if (form.value.match_type === 'pattern') {
        return form.value.type === 'email' ? '^promo[a-z0-9._-]*@' : '^(free|cheap)[-_]?likes'
    }
    if (form.value.type === 'email') {
        return form.value.match_type === 'domain' ? 'mail.example' : 'spammer@mail.example'
    }
    return 'username'
})

const valueHelp = computed(() => {
    if (form.value.type === 'ip') {
        return 'Accepts 198.51.100.7, 203.0.113.0/24, 192.0.2.10-192.0.2.80 or IPv6 like 2001:db8::/48'
    }
    if (form.value.match_type === 'pattern') {
        return 'Case insensitive. Use the pattern builder for a visual editor.'
    }
    if (form.value.match_type === 'domain') {
        return 'Also blocks subdomains. Common providers like gmail.com cannot be blocked as a whole.'
    }
    return 'Case insensitive.'
})

const toggles = computed(() =>
    form.value.type === 'ip'
        ? [
              {
                  key: 'blocks_login',
                  label: 'Sign in',
                  description: 'Password login, two factor, adding accounts and Sign in with Apple'
              },
              {
                  key: 'blocks_registration',
                  label: 'Sign up',
                  description: 'Registration, admin invites and curated applications'
              }
          ]
        : [
              {
                  key: 'blocks_login',
                  label: 'Sign in',
                  description: 'Stops existing accounts with a matching email from signing in'
              },
              {
                  key: 'blocks_registration',
                  label: 'Sign up',
                  description: 'Registration, invites, curated applications and email changes'
              }
          ]
)

const matchHint = computed(() => {
    if (form.value.type === 'ip') return 'Registered or last seen here. Review them after saving.'
    return 'Admins are excluded. Review and delete them after saving.'
})

const expiresAt = computed(() => {
    if (expiryPreset.value === 'never') return null
    if (expiryPreset.value === 'custom') {
        return customExpiry.value ? new Date(customExpiry.value).toISOString() : null
    }
    const preset = expiryPresets.find((option) => option.value === expiryPreset.value)
    return new Date(Date.now() + preset.hours * 3600 * 1000).toISOString()
})

const canSubmit = computed(() => {
    if (saving.value || previewLoading.value || !form.value.value.trim()) return false
    if (!preview.value?.valid || preview.value.existing_rule_id) return false
    if (preview.value.details?.broad && !confirmBroad.value) return false
    if (form.value.type !== 'username') {
        if (!form.value.blocks_login && !form.value.blocks_registration) return false
        if (form.value.blocks_login && preview.value.admin_conflict) return false
    }
    if (expiryPreset.value === 'custom' && !customExpiry.value) return false
    return true
})

const matchLabel = (match) => ({ exact: 'address', cidr: 'CIDR', range: 'range' })[match] || match

const choiceClass = (active) => [
    'inline-flex h-10 items-center justify-center gap-2 rounded-lg border text-sm font-medium transition',
    active
        ? 'border-rose-500 bg-rose-50 text-rose-700 dark:border-rose-400 dark:bg-rose-900/20 dark:text-rose-300'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
]

const pillClass = (active, dark = false) => [
    'h-8 rounded-full px-3.5 text-sm font-medium transition',
    active
        ? dark
            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
            : 'bg-red-500 text-white'
        : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
]

const toLocalInput = (iso) => {
    const date = new Date(iso)
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const setType = (type) => {
    const value = form.value.value
    form.value = { ...blankForm(type), value, note: form.value.note }
    matchTypeTouched.value = false
}

const setMatchType = (match) => {
    form.value.match_type = match
    matchTypeTouched.value = true
}

const setExpiry = (value) => {
    expiryPreset.value = value
    expiryDirty.value = true
    if (value === 'custom' && !customExpiry.value) {
        customExpiry.value = toLocalInput(Date.now() + 7 * 86400 * 1000)
    }
}

const runPreview = () => {
    clearTimeout(previewTimer)
    const value = form.value.value.trim()

    if (!value) {
        previewSeq++
        preview.value = null
        previewLoading.value = false
        return
    }

    previewLoading.value = true

    previewTimer = setTimeout(async () => {
        const seq = ++previewSeq
        try {
            const response = await accessRulesApi.preview({
                type: form.value.type,
                value,
                match_type: form.value.type === 'ip' ? null : form.value.match_type,
                blocks_login: form.value.type === 'username' ? false : form.value.blocks_login,
                ignore_id: props.rule?.id || null
            })
            if (seq === previewSeq) preview.value = response
        } catch (e) {
            if (seq === previewSeq)
                preview.value = { valid: false, error: accessRuleErrorMessage(e) }
        } finally {
            if (seq === previewSeq) previewLoading.value = false
        }
    }, 400)
}

watch(
    () => form.value.value,
    (value) => {
        if (form.value.type !== 'email' || form.value.match_type === 'pattern') return
        if (matchTypeTouched.value || !value.trim()) return
        form.value.match_type = value.includes('@') ? 'exact' : 'domain'
    }
)

watch(
    () => [form.value.type, form.value.value, form.value.match_type, form.value.blocks_login],
    () => {
        if (show.value) runPreview()
    }
)

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return
        error.value = ''
        preview.value = null
        expiryDirty.value = false
        customExpiry.value = ''

        if (props.rule) {
            form.value = {
                type: props.rule.type,
                value: props.rule.value,
                match_type: props.rule.type === 'ip' ? null : props.rule.match_type,
                blocks_login: !!props.rule.blocks_login,
                blocks_registration: !!props.rule.blocks_registration,
                note: props.rule.note || ''
            }
            matchTypeTouched.value = true
            confirmBroad.value = true
            if (props.rule.expires_at) {
                expiryPreset.value = 'custom'
                customExpiry.value = toLocalInput(props.rule.expires_at)
            } else {
                expiryPreset.value = 'never'
            }
        } else {
            form.value = blankForm(props.initialType)
            matchTypeTouched.value = false
            confirmBroad.value = false
            expiryPreset.value = 'never'
        }

        runPreview()
    }
)

const submit = async () => {
    if (!canSubmit.value) return
    saving.value = true
    error.value = ''

    const payload = {
        value: form.value.value.trim(),
        match_type: form.value.type === 'ip' ? null : form.value.match_type,
        blocks_login: form.value.type === 'username' ? false : form.value.blocks_login,
        blocks_registration: form.value.type === 'username' ? true : form.value.blocks_registration,
        note: form.value.note.trim() || null,
        confirm_broad: confirmBroad.value
    }

    if (!isEditing.value || expiryDirty.value) {
        payload.expires_at = expiresAt.value
    }

    try {
        const response = isEditing.value
            ? await accessRulesApi.updateRule(props.rule.id, payload)
            : await accessRulesApi.createRule({ ...payload, type: form.value.type })
        emit('saved', response?.data ?? response)
        show.value = false
    } catch (e) {
        error.value = accessRuleErrorMessage(e)
    } finally {
        saving.value = false
    }
}

onUnmounted(() => clearTimeout(previewTimer))
</script>
