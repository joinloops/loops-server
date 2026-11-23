<template>
    <MainLayout>
        <div v-if="loading" class="min-h-screen bg-white dark:bg-[#0A0A0A]">
            <div class="max-w-2xl mx-auto px-4 lg:px-8 py-6">
                <div class="h-5 w-28 bg-gray-200 dark:bg-gray-800 rounded animate-pulse mb-8"></div>
                <div
                    class="h-8 w-2/3 bg-gray-200 dark:bg-gray-800 rounded animate-pulse mb-3"
                ></div>
                <div
                    class="h-4 w-full bg-gray-200 dark:bg-gray-800 rounded animate-pulse mb-2"
                ></div>
                <div
                    class="h-4 w-3/4 bg-gray-200 dark:bg-gray-800 rounded animate-pulse mb-8"
                ></div>
                <div
                    class="h-32 w-full bg-gray-100 dark:bg-gray-900 rounded-2xl animate-pulse"
                ></div>
            </div>
        </div>

        <KitFeatureDisabled v-else-if="isDisabled && !loading" />

        <div
            v-else-if="error"
            class="min-h-screen bg-white dark:bg-[#0A0A0A] flex flex-col items-center justify-center px-4"
        >
            <div class="text-center max-w-sm">
                <div
                    class="w-16 h-16 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-100 dark:border-red-900/50 flex items-center justify-center mx-auto mb-5"
                >
                    <svg
                        class="w-8 h-8 text-red-500 dark:text-red-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                        />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    {{
                        error === 'not_found'
                            ? $t('common.kitNotFound')
                            : $t('common.somethingWentWrong')
                    }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    {{
                        error === 'not_found'
                            ? t('common.thisStarterKitDoesNotExistOrMayHaveBeenRemoved')
                            : t('common.weCouldNotLoadThisStarterKitPleaseTryAgain')
                    }}
                </p>
                <div class="flex gap-3 justify-center">
                    <AnimatedButton @click="retryLoad">
                        {{ t('common.tryAgain') }}
                    </AnimatedButton>
                    <AnimatedButton @click="router.push('/starter-kits')" variant="light">
                        {{ t('common.browseKits') }}
                    </AnimatedButton>
                </div>
            </div>
        </div>

        <div
            v-else-if="alreadyResponded"
            class="min-h-screen bg-white dark:bg-[#0A0A0A] flex flex-col items-center justify-center px-4"
        >
            <div class="text-center max-w-sm">
                <div
                    :class="[
                        'w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5 border',
                        accountStatus === 1
                            ? 'bg-green-50 dark:bg-green-950/40 border-green-100 dark:border-green-900/50'
                            : 'bg-red-50 dark:bg-red-950/40 border-red-100 dark:border-red-900/50'
                    ]"
                >
                    <CheckCircleIcon
                        v-if="accountStatus === 1"
                        class="w-8 h-8 text-green-500 dark:text-green-400"
                    />
                    <XCircleIcon v-else class="w-8 h-8 text-red-500 dark:text-red-400" />
                </div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    {{
                        accountStatus === 1
                            ? t('common.youreInTheKit') + '!'
                            : t('common.invitationDeclined')
                    }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    {{
                        accountStatus === 1
                            ? `${t('common.youveAlreadyAcceptedYourSpotIn')} "${starterKit.title}".`
                            : `${t('common.youveAlreadyDeclinedTheInvitationTo')} "${starterKit.title}".`
                    }}
                </p>
                <router-link
                    :to="`/starter-kits/${route.params.id}/${route.params.slug}`"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors"
                >
                    {{ t('common.viewKit') }}
                </router-link>
            </div>
        </div>

        <div v-else class="min-h-screen bg-white dark:bg-gray-950">
            <div class="max-w-4xl mx-auto px-4 lg:px-8">
                <div class="pt-5 pb-2">
                    <router-link
                        :to="`/starter-kits/${route.params.id}/${route.params.slug}`"
                        class="inline-flex items-center gap-1.5 font-bold text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors group"
                    >
                        <ChevronLeftIcon
                            class="w-4 h-4 transition-transform group-hover:-translate-x-0.5"
                        />
                        {{ t('common.starterKit') }}
                    </router-link>
                </div>

                <div class="py-5 border-b border-gray-100 dark:border-gray-800/60">
                    <div
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-full text-xs font-medium text-amber-700 dark:text-amber-400 mb-4"
                    >
                        <ClockIcon class="w-3.5 h-3.5" />
                        {{ t('common.invitationPendingYourResponse') }}
                    </div>
                    <h1
                        class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mb-1.5 leading-tight"
                    >
                        {{ t('common.youveBeenAddedToAStarterKit') }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            t(
                                'common.reviewTheStarterKitBelowAndChooseToAcceptOrDeclineYourInclusion'
                            )
                        }}.
                    </p>
                </div>

                <div class="py-6 border-b border-gray-100 dark:border-gray-800/60">
                    <div class="flex items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-white mb-1.5 leading-snug"
                            >
                                {{ starterKit.title }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                {{ starterKit.description }}
                            </p>

                            <div
                                v-if="starterKit.hashtags?.length"
                                class="flex flex-wrap gap-1.5 mt-3"
                            >
                                <span
                                    v-for="tag in starterKit.hashtags"
                                    :key="tag"
                                    class="px-2.5 py-1 bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 rounded-full text-xs font-medium"
                                >
                                    #{{ tag }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-4 mt-5 text-sm text-gray-500 dark:text-gray-400 flex-wrap"
                    >
                        <span>
                            <router-link
                                :to="starterKit.path"
                                class="text-gray-400 dark:text-gray-500 text-xs font-bold"
                                >{{ t('common.viewKit') }}</router-link
                            >
                        </span>
                        <span class="text-gray-300 dark:text-gray-700">·</span>
                        <span class="flex items-center gap-2">
                            <span class="text-gray-400 dark:text-gray-500 text-xs">{{
                                t('common.curatedBy')
                            }}</span>
                            <router-link
                                :to="`/@${starterKit.creator.username}`"
                                class="flex items-center gap-1.5 hover:text-gray-900 dark:hover:text-white transition-colors"
                            >
                                <img
                                    :src="starterKit.creator.avatar"
                                    :alt="starterKit.creator.name"
                                    class="w-5 h-5 rounded-full object-cover"
                                />
                                <span class="font-medium text-xs"
                                    >@{{ starterKit.creator.username }}</span
                                >
                            </router-link>
                        </span>
                        <span class="text-gray-300 dark:text-gray-700">·</span>
                        <span class="text-xs"
                            >{{ formatCount(starterKit.approved_accounts) }}
                            {{ t('common.accounts') }}</span
                        >
                        <span class="text-gray-300 dark:text-gray-700">·</span>
                        <span class="text-xs"
                            >{{ formatCount(starterKit.uses) }} {{ t('common.uses') }}</span
                        >
                    </div>
                </div>

                <div class="py-5 border-b border-gray-100 dark:border-gray-800/60">
                    <h3
                        class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3"
                    >
                        {{ t('common.alsoInThiskit') }}
                    </h3>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800/60">
                        <div
                            v-for="account in previewAccounts"
                            :key="account.id"
                            class="flex items-center gap-3 py-2.5"
                        >
                            <img
                                :src="account.avatar"
                                :alt="account.name"
                                class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                            />
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white truncate leading-tight"
                                >
                                    {{ account.name }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 leading-tight">
                                    @{{ account.username }}
                                </p>
                            </div>
                            <div
                                class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500"
                            >
                                <UsersIcon class="w-3.5 h-3.5" />
                                {{ formatCount(account.follower_count) }}
                            </div>
                        </div>

                        <div
                            v-if="starterKit.approved_accounts > previewAccounts.length"
                            class="pt-5 text-center text-gray-400 dark:text-gray-500"
                        >
                            + {{ starterKit.approved_accounts - previewAccounts.length }}
                            {{ t('common.moreAccounts') }}
                        </div>

                        <div
                            v-if="previewAccounts.length === 0"
                            class="py-4 text-xs text-gray-400 dark:text-gray-500"
                        >
                            {{ t('common.youdBeTheFirstAccountInThisStarterKit') }}.
                        </div>
                    </div>
                </div>

                <div class="py-6">
                    <div v-if="decisionMade" class="flex flex-col items-center text-center py-6">
                        <div
                            :class="[
                                'w-14 h-14 rounded-2xl flex items-center justify-center mb-4 border',
                                decision === 'approved'
                                    ? 'bg-green-50 dark:bg-green-950/40 border-green-100 dark:border-green-900/50'
                                    : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-800'
                            ]"
                        >
                            <CheckCircleIcon
                                v-if="decision === 'approved'"
                                class="w-7 h-7 text-green-500 dark:text-green-400"
                            />
                            <XCircleIcon v-else class="w-7 h-7 text-gray-400 dark:text-gray-500" />
                        </div>
                        <p class="text-base font-semibold text-gray-900 dark:text-white mb-1">
                            {{
                                decision === 'approved'
                                    ? t('common.youreIn')
                                    : t('common.invitationDeclined')
                            }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                            {{
                                decision === 'approved'
                                    ? t('common.yourProfileIsNowVisibleInThisStarterKit') + '.'
                                    : t('common.youWontAppearInThisStarterKit')
                            }}
                        </p>
                        <router-link
                            :to="`/starter-kits/${route.params.id}/${route.params.slug}`"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors"
                        >
                            {{ t('common.viewKit') }}
                        </router-link>
                    </div>

                    <template v-else>
                        <p class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-4">
                            {{ t('common.doYouWantToBeIncludedIn') }}
                            <span class="font-semibold text-gray-900 dark:text-white">{{
                                starterKit.title
                            }}</span>?
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <AnimatedButton
                                @click="handleDecision('approved')"
                                :disabled="isSubmitting"
                                class="flex-1 flex items-center justify-center"
                            >
                                <div
                                    v-if="isSubmitting && pendingDecision === 'approved'"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <Spinner size="xs" />
                                    {{ t('common.accepting') }}...
                                </div>
                                <div v-else class="flex items-center justify-center gap-2">
                                    <CheckCircleIcon class="w-6 h-6" />
                                    {{ t('common.acceptInvitation') }}
                                </div>
                            </AnimatedButton>

                            <AnimatedButton
                                @click="handleDecision('rejected')"
                                :disabled="isSubmitting"
                                class="flex-1 flex items-center justify-center"
                                variant="light"
                            >
                                <div
                                    v-if="isSubmitting && pendingDecision === 'rejected'"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <Spinner size="xs" theme="slate" />
                                    {{ t('common.declining') }}...
                                </div>
                                <div v-else class="flex items-center justify-center gap-2">
                                    <XMarkIcon class="w-6 h-6" />
                                    {{ t('common.decline') }}
                                </div>
                            </AnimatedButton>
                        </div>
                        <p class="text-sm text-center text-gray-400 dark:text-gray-500 mt-3">
                            {{ t('common.youCanChangeYourMindLaterAndRemoveYourselfAtAnyTime') }}.
                        </p>
                        <div
                            class="flex justify-center items-center text-xs text-gray-400 dark:text-gray-500 mt-3 gap-10"
                        >
                            <button
                                class="text-red-400 font-medium cursor-pointer"
                                @click="handleReport"
                            >
                                {{ t('common.reportStarterKit') }}
                            </button>
                            <router-link to="/starter-kits/about" class="text-gray-400 font-medium"
                                >{{ t('common.whatAreStarterKits') }}?</router-link
                            >
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <ReportModal />
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/layouts/MainLayout.vue'
import axios from '@/plugins/axios'
import {
    ChevronLeftIcon,
    CheckCircleIcon,
    XCircleIcon,
    XMarkIcon,
    ClockIcon,
    UsersIcon
} from '@heroicons/vue/24/outline'
import { ref, computed, onMounted, inject } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHashids } from '@/composables/useHashids'
import { useUtils } from '@/composables/useUtils'
import AnimatedButton from '@/components/AnimatedButton.vue'
import { useReportModal } from '@/composables/useReportModal'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { decodeHashid } = useHashids()
const { formatCount } = useUtils()
const { openReportModal } = useReportModal()

const authStore = inject('authStore')
const appConfig = inject('appConfig')
const kitId = decodeHashid(route.params.id)

const starterKit = ref(null)
const loading = ref(true)
const error = ref(false)
const isDisabled = ref(false)

const accountStatus = ref(null)

const alreadyResponded = computed(() => accountStatus.value === 1 || accountStatus.value === 2)

const previewAccounts = computed(() => {
    if (!starterKit.value?.accounts) return []
    return starterKit.value.accounts.filter((a) => a.kit_status === 1).slice(0, 3)
})

const isSubmitting = ref(false)
const pendingDecision = ref(null)
const decisionMade = ref(false)
const decision = ref(null)

const loadKit = async () => {
    if (appConfig.starterKits === false) {
        isDisabled.value = true
        loading.value = false
        return
    }
    try {
        if (!kitId) throw new Error('invalid_id')
        const api = axios.getAxiosInstance()

        const kitRes = await api.get(`/api/v1/starter-kits/details/${kitId}`)
        starterKit.value = kitRes.data.data

        const memberRes = await api.get(`/api/v1/starter-kits/details/${kitId}/membership`)
        const { in_kit, kit_status } = memberRes.data.data

        if (!in_kit) {
            router.replace(`/starter-kits/${route.params.id}/${route.params.slug}`)
            return
        }

        accountStatus.value = kit_status

        const accountsRes = await api.get(`/api/v1/starter-kits/details/${kitId}/accounts`)
        starterKit.value.accounts = accountsRes.data.data
    } catch (e) {
        error.value = e?.response?.status === 404 ? 'not_found' : 'generic'
    } finally {
        loading.value = false
    }
}

const retryLoad = () => {
    loading.value = true
    error.value = false
    loadKit()
}

const handleReport = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    openReportModal('starter_kit', starterKit.value.id, window.location.href)
}

const handleDecision = async (choice) => {
    isSubmitting.value = true
    pendingDecision.value = choice
    try {
        const api = axios.getAxiosInstance()
        await api.post(`/api/v1/starter-kits/details/${kitId}/membership`, { decision: choice })
        decision.value = choice
        decisionMade.value = true
        accountStatus.value = choice === 'approved' ? 1 : 2
    } catch (e) {
        console.error(e)
    } finally {
        isSubmitting.value = false
        pendingDecision.value = null
    }
}

onMounted(loadKit)
</script>
