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
                    {{ t('settings.usernameSettings') }}
                </h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <div v-if="isLoading" class="flex justify-center py-16">
                <i class="bx bx-loader-alt bx-spin text-[28px] text-gray-400"></i>
            </div>

            <section v-else-if="status" class="my-8">
                <p class="-mt-3 mb-6 text-gray-700 dark:text-gray-400">
                    {{ t('settings.usernameIntro') }}
                </p>

                <div
                    v-if="!status.enabled"
                    class="bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4 mb-6"
                >
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        {{ t('settings.usernameChangesDisabled') }}
                    </p>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ t('settings.usernameCurrent') }}
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div
                        class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-4 min-w-0">
                            <div
                                class="w-12 h-12 shrink-0 rounded-lg bg-[#F02C56]/10 flex items-center justify-center"
                            >
                                <i class="bx bx-at text-[#F02C56] text-[24px]"></i>
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="text-lg font-medium text-gray-800 dark:text-gray-200 truncate"
                                >
                                    @{{ status.username }}
                                </h3>
                                <p class="text-sm text-gray-500 truncate">
                                    {{ status.domain }}/@{{ status.username }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="self-start sm:self-auto shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
                            :class="
                                status.changes_remaining > 0
                                    ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                    : 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
                            "
                        >
                            <i class="bx bx-revision text-[14px]"></i>
                            {{
                                t('settings.usernameChangesRemaining', {
                                    remaining: status.changes_remaining,
                                    limit: status.limit
                                })
                            }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="status.reserved_username"
                    class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-lg p-4 mb-6"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <i
                                class="bx bx-lock-alt text-blue-500 dark:text-blue-300 text-[20px] mt-0.5"
                            ></i>
                            <div class="min-w-0">
                                <h3 class="font-medium text-blue-800 dark:text-blue-300 mb-1">
                                    {{ t('settings.usernameReservedForYou') }}
                                </h3>
                                <p class="text-sm text-blue-700 dark:text-blue-200 break-words">
                                    {{
                                        t('settings.usernameReservedMessage', {
                                            username: `@${status.reserved_username}`
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 px-4 py-2 text-sm font-medium text-blue-700 dark:text-blue-200 bg-white dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                            :disabled="!status.can_revert || isSaving"
                            @click="startRevert"
                        >
                            {{
                                t('settings.usernameRevert', {
                                    username: `@${status.reserved_username}`
                                })
                            }}
                        </button>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ t('settings.usernameNew') }}
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <form @submit.prevent="submitChange">
                            <div class="mb-4">
                                <div
                                    class="flex items-center w-full border rounded-lg bg-white dark:bg-gray-800 focus-within:ring-2 focus-within:ring-[#F02C56]/60 transition"
                                    :class="
                                        usernameError
                                            ? 'border-red-300 dark:border-red-800'
                                            : 'border-gray-300 dark:border-gray-700'
                                    "
                                >
                                    <span class="pl-3 text-gray-400 select-none">@</span>
                                    <input
                                        v-model="newUsername"
                                        type="text"
                                        autocomplete="off"
                                        autocapitalize="none"
                                        spellcheck="false"
                                        :maxlength="status.max_length"
                                        :placeholder="status.username"
                                        :disabled="formLocked"
                                        class="flex-1 min-w-0 px-1.5 py-2 bg-transparent outline-none text-gray-900 dark:text-gray-100 disabled:cursor-not-allowed"
                                    />
                                    <span class="pr-3 flex items-center">
                                        <i
                                            v-if="isChecking"
                                            class="bx bx-loader-alt bx-spin text-[18px] text-gray-400"
                                        ></i>
                                        <i
                                            v-else-if="checkResult?.available"
                                            class="bx bx-check-circle text-[18px] text-green-500"
                                        ></i>
                                        <i
                                            v-else-if="usernameError"
                                            class="bx bx-x-circle text-[18px] text-red-500"
                                        ></i>
                                    </span>
                                </div>
                                <div class="flex justify-between gap-4 mt-1.5">
                                    <p
                                        v-if="usernameError"
                                        class="text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{ usernameError }}
                                    </p>
                                    <p
                                        v-else-if="isChecking"
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('settings.usernameChecking') }}
                                    </p>
                                    <p
                                        v-else-if="checkResult?.available"
                                        class="text-sm text-green-600 dark:text-green-400"
                                    >
                                        {{ t('settings.usernameAvailable') }}
                                    </p>
                                    <span v-else></span>
                                    <span class="text-xs text-gray-400 shrink-0">
                                        {{ newUsername.length }}/{{ status.max_length }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="mb-4 px-4 py-3 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-100 dark:border-gray-800"
                            >
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('settings.usernameProfileUrlPreview') }}
                                </p>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-gray-100 break-all"
                                >
                                    {{ status.domain }}/@{{ cleanedUsername || status.username }}
                                </p>
                            </div>

                            <div v-if="status.requires_password" class="mb-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2"
                                >
                                    {{ t('settings.confirmYourPassword') }}
                                </label>
                                <input
                                    v-model="password"
                                    type="password"
                                    autocomplete="current-password"
                                    :placeholder="t('settings.enterYourCurrentPassword')"
                                    :disabled="formLocked"
                                    class="w-full px-3 py-2 border dark:text-gray-200 dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-[#F02C56]/60 outline-none"
                                    :class="
                                        passwordError
                                            ? 'border-red-300 dark:border-red-800'
                                            : 'border-gray-300 dark:border-gray-700'
                                    "
                                />
                                <p v-if="passwordError" class="text-sm text-red-600 mt-1">
                                    {{ passwordError }}
                                </p>
                            </div>

                            <div
                                v-if="blockedNotice"
                                class="mb-4 flex items-start gap-2 text-sm text-orange-700 dark:text-orange-300"
                            >
                                <i class="bx bx-time text-[18px] mt-0.5"></i>
                                <span>{{ blockedNotice }}</span>
                            </div>

                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#F02C56] rounded-lg hover:bg-[#d81f48] disabled:bg-gray-300 dark:disabled:text-gray-600 dark:disabled:bg-gray-900 disabled:cursor-not-allowed transition-colors cursor-pointer"
                            >
                                {{
                                    isSaving
                                        ? t('settings.usernameSaving')
                                        : t('settings.usernameSave')
                                }}
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                    {{
                        t('settings.usernameChangesRules', {
                            limit: status.limit,
                            days: status.window_days,
                            hours: status.cooldown_hours
                        })
                    }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                    {{ t('settings.usernameFederationNotice') }}
                </p>

                <template v-if="status.history?.length > 1">
                    <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                        {{ t('settings.usernameHistory') }}
                    </h2>
                    <div
                        class="bg-white dark:bg-slate-950 rounded-lg shadow-sm divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <div
                            v-for="(row, index) in status.history"
                            :key="`${row.username}-${row.started_at}-${index}`"
                            class="px-6 py-4 flex items-center justify-between gap-4"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-gray-200 truncate">
                                    @{{ row.username }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ formatDate(row.started_at) }}
                                    <template v-if="row.ended_at">
                                        <i class="bx bx-right-arrow-alt mx-1 align-middle"></i>
                                        {{ formatDate(row.ended_at) }}
                                    </template>
                                </p>
                            </div>
                            <span
                                v-if="!row.ended_at"
                                class="shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300"
                            >
                                {{ t('common.current') }}
                            </span>
                            <span
                                v-else-if="row.username === status.reserved_username"
                                class="shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                            >
                                {{ t('settings.usernameReservedForYou') }}
                            </span>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import axios from '~/plugins/axios'
import { useAuthStore } from '~/stores/auth'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'

const { t, locale } = useI18n()
const { formatDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const authStore = useAuthStore()
const axiosInstance = axios.getAxiosInstance()

const status = ref(null)
const isLoading = ref(true)
const newUsername = ref('')
const password = ref('')
const usernameError = ref('')
const passwordError = ref('')
const checkResult = ref(null)
const isChecking = ref(false)
const isSaving = ref(false)

let checkTimer = null
let checkToken = 0

const cleanedUsername = computed(() => newUsername.value.trim().replace(/^@+/, ''))

const formLocked = computed(() => !status.value?.enabled || isSaving.value)

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

const blockedNotice = computed(() => {
    if (!status.value?.enabled) return ''
    if (status.value.cooldown_ends_at) {
        return t('settings.usernameNextChangeAt', {
            time: relativeTime(status.value.cooldown_ends_at)
        })
    }
    if (status.value.changes_remaining === 0 && status.value.limit_resets_at) {
        return t('settings.usernameLimitResetsAt', {
            time: relativeTime(status.value.limit_resets_at)
        })
    }
    return ''
})

const canSubmit = computed(() => {
    if (!status.value?.enabled || isSaving.value || isChecking.value) return false
    if (!checkResult.value?.available || checkResult.value.username !== cleanedUsername.value) {
        return false
    }
    if (status.value.requires_password && !password.value) return false
    return true
})

const fetchStatus = async () => {
    try {
        const res = await axiosInstance.get('/api/v1/account/settings/username')
        status.value = res.data.data
    } catch (error) {
        console.error('Failed to load username settings:', error)
    } finally {
        isLoading.value = false
    }
}

const runCheck = async (value) => {
    const token = ++checkToken
    isChecking.value = true
    try {
        const res = await axiosInstance.post('/api/v1/account/settings/username/check', {
            username: value
        })
        if (token !== checkToken) return
        checkResult.value = res.data.data
        usernameError.value = res.data.data.available ? '' : res.data.data.message
    } catch (error) {
        if (token !== checkToken) return
        checkResult.value = null
        usernameError.value =
            error.response?.data?.message || 'Could not check this username. Please try again.'
    } finally {
        if (token === checkToken) isChecking.value = false
    }
}

watch(cleanedUsername, (value) => {
    clearTimeout(checkTimer)
    checkToken++
    checkResult.value = null
    usernameError.value = ''
    isChecking.value = false

    if (!value || !status.value || value === status.value.username) return

    checkTimer = setTimeout(() => runCheck(value), 400)
})

watch(password, () => {
    passwordError.value = ''
})

const applyChange = async (target, isRevert) => {
    const confirmed = await confirmModal(
        t('settings.usernameConfirmTitle'),
        isRevert
            ? t('settings.usernameRevertConfirmMessage', { new: `@${target}` })
            : t('settings.usernameConfirmMessage', {
                  new: `@${target}`,
                  old: `@${status.value.username}`
              }),
        t('settings.usernameSave'),
        t('common.cancel')
    )

    if (!confirmed) return

    isSaving.value = true
    usernameError.value = ''
    passwordError.value = ''

    try {
        const res = await axiosInstance.post('/api/v1/account/settings/username/update', {
            username: target,
            password: status.value.requires_password ? password.value : null
        })

        const account = res.data.data.account
        status.value = res.data.data.status

        if (authStore.user) {
            authStore.user = { ...authStore.user, ...account }
        }
        authStore.username = account.username

        newUsername.value = ''
        password.value = ''
        checkResult.value = null

        alertModal(
            t('settings.usernameChanged'),
            t('settings.usernameChangedMessage', { username: `@${account.username}` })
        )
    } catch (error) {
        const data = error.response?.data
        if (data?.reason === 'password') {
            passwordError.value = data.message
        } else {
            usernameError.value = data?.message || 'Something went wrong. Please try again.'
        }
        if (['cooldown', 'limit'].includes(data?.reason)) {
            await fetchStatus()
        }
    } finally {
        isSaving.value = false
    }
}

const submitChange = async () => {
    if (!canSubmit.value) return
    await applyChange(cleanedUsername.value, !!checkResult.value?.is_revert)
}

const startRevert = async () => {
    if (!status.value?.reserved_username) return
    if (status.value.requires_password && !password.value) {
        passwordError.value = t('settings.usernamePasswordHint')
        return
    }
    await applyChange(status.value.reserved_username, true)
}

onMounted(fetchStatus)

onBeforeUnmount(() => {
    clearTimeout(checkTimer)
})
</script>
