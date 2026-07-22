<template>
    <div class="flex w-full px-4 lg:px-0">
        <div class="flex justify-between flex-col w-full">
            <div class="flex flex-col lg:flex-row lg:space-x-10 lg:items-center mb-5">
                <div class="flex justify-center lg:justify-start mb-4 lg:mb-0">
                    <img
                        class="w-24 h-24 sm:w-32 sm:h-32 lg:max-w-[200px] lg:w-[200px] lg:h-[200px] rounded-full object-cover border border-gray-200 dark:border-gray-800"
                        :src="profile.avatar"
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                        fetchpriority="high"
                        :alt="`${profile.username}'s avatar`"
                    />
                </div>

                <div class="w-full text-center lg:text-left">
                    <div
                        class="flex flex-col sm:flex-row sm:gap-5 sm:items-end justify-center lg:justify-start"
                    >
                        <div
                            class="text-lg sm:text-[20px] font-bold truncate dark:text-slate-50"
                            :title="profile.name"
                        >
                            {{ textTruncate(profile.name || profile.username, 30) }}
                        </div>
                        <div
                            class="text-base sm:text-[20px] text-gray-600 dark:text-slate-400"
                            :title="profile.username"
                        >
                            &commat;{{ textTruncate(profile.username, 50) }}
                        </div>
                        <div v-if="profile.is_admin" class="mt-1 md:mt-0">
                            <Tooltip
                                title="Verified Staff Account"
                                body="This acccount belongs to an admin or staff member of this server."
                            >
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-600 dark:text-red-400"
                                >
                                    <CheckBadgeIcon class="w-3.5 h-3.5" /> Staff
                                </span>
                            </Tooltip>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-center lg:justify-start gap-8 lg:gap-6 flex-wrap mt-3 sm:mt-1"
                    >
                        <div class="text-center lg:flex items-center gap-1 lg:text-left">
                            <div class="text-base">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.postCount)
                                }}</span>
                            </div>
                            <div class="dark:text-slate-300 font-light">
                                {{ t('common.videos') }}
                            </div>
                        </div>
                        <div
                            @click="handleFollowersModal()"
                            class="text-center lg:text-left lg:flex items-center gap-1 cursor-pointer hover:opacity-80"
                        >
                            <div class="text-base">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.followerCount)
                                }}</span>
                            </div>
                            <div class="dark:text-slate-300 font-light">
                                {{ t('common.followers') }}
                            </div>
                        </div>
                        <div
                            @click="handleFollowingModal()"
                            class="text-center lg:text-left lg:flex items-center gap-1 cursor-pointer hover:opacity-80"
                        >
                            <div class="text-base">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.followingCount)
                                }}</span>
                            </div>
                            <div class="dark:text-slate-300 font-light">
                                {{ t('common.following') }}
                            </div>
                        </div>
                        <div class="text-center lg:text-left lg:flex items-center gap-1">
                            <div class="text-base">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.allLikes)
                                }}</span>
                            </div>
                            <div class="dark:text-slate-300 font-light">
                                {{ t('profile.likes') }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-center lg:justify-start gap-2 sm:gap-3 mt-3 flex-wrap"
                    >
                        <template v-if="authStore.authenticated">
                            <button
                                v-if="profile.id == authStore.getUser.id"
                                @click="openEditProfile"
                                class="flex items-center gap-2 sm:gap-3 rounded-xl py-[5px] px-4 sm:px-6 text-sm sm:text-[15px] font-semibold border hover:bg-gray-100 dark:text-slate-400 dark:border-slate-500 dark:hover:bg-slate-900 cursor-pointer"
                            >
                                <div>{{ t('profile.editProfile') }}</div>
                            </button>

                            <template v-else>
                                <button
                                    v-if="profile.relationship.blocking"
                                    @click="handleUnblock"
                                    class="flex item-center rounded-xl py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-[#F02C56] border-[#F02C56] font-semibold border dark:border-[#F02C56] cursor-pointer"
                                >
                                    {{ t('profile.blocked') }}
                                </button>
                                <button
                                    v-else-if="isFollowingRequestPending"
                                    @click="handleUndoFollowRequest"
                                    class="flex item-center gap-2 rounded-xl py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-[#F02C56] border-[#F02C56] font-semibold border dark:border-border-[#F02C56] cursor-pointer"
                                >
                                    <Spinner v-if="isPollingFollowState" size="xs" />
                                    {{ t('profile.followRequestPending') }}
                                </button>
                                <button
                                    v-else-if="!profile.isFollowing"
                                    @click="handleToggleFollow"
                                    class="flex item-center rounded-xl py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-white bg-red-500 hover:bg-red-400 font-semibold border dark:border-slate-950 cursor-pointer"
                                >
                                    {{
                                        profile.manuallyApprovesFollowers
                                            ? t('common.requestToFollow')
                                            : t('common.follow')
                                    }}
                                </button>
                                <button
                                    v-else
                                    @click="handleToggleFollow"
                                    class="flex item-center rounded-xl py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-[#F02C56] border-[#F02C56] font-semibold border dark:border-border-[#F02C56] cursor-pointer"
                                >
                                    {{ t('common.unfollow') }}
                                </button>
                            </template>

                            <button
                                v-if="
                                    profile.acceptsDms &&
                                    profile.id !== authStore.getUser.id &&
                                    !profile.relationship.blocking
                                "
                                @click="handleMessage"
                                :disabled="isMessagingLoading"
                                type="button"
                                class="flex items-center gap-2 rounded-xl py-[5px] px-4 sm:px-6 text-sm sm:text-[15px] font-semibold border hover:bg-gray-100 dark:text-slate-400 dark:border-slate-500 dark:hover:bg-slate-900 cursor-pointer disabled:opacity-60 disabled:cursor-wait"
                            >
                                <Spinner v-if="isMessagingLoading" size="xs" />
                                <ChatBubbleOvalLeftIcon v-else class="w-4 h-4" />
                                <div>Message</div>
                            </button>
                        </template>

                        <button
                            v-if="!authStore.authenticated"
                            @click="showRemoteFollowModal = true"
                            class="flex item-center rounded-xl py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-white bg-red-500 hover:bg-red-400 font-semibold border dark:border-slate-950 cursor-pointer"
                        >
                            {{ t('common.follow') }}
                        </button>

                        <button
                            @click="showProfileInfoModal = true"
                            class="flex items-center justify-center w-8 h-8 rounded-xl border border-gray-300 hover:bg-gray-100 dark:border-slate-500 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            aria-label="Account details"
                        >
                            <IdentificationIcon class="w-4 h-4 dark:text-slate-400" />
                        </button>

                        <ShareModal
                            type="profile"
                            :url="profile.local ? profile.url : profile.remote_url"
                            :username="profile.username"
                            class="flex items-center justify-center w-8 h-8 rounded-xl border border-gray-300 hover:bg-gray-100 dark:border-slate-500 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            aria-label="Open share modal"
                        >
                            <ShareIcon class="w-4 h-4 text-gray-600 dark:text-slate-400" />
                        </ShareModal>

                        <ShareModal
                            v-if="profile.hasAtom"
                            type="atom"
                            :url="profile.hasAtomUrl"
                            :username="profile.username"
                            class="flex items-center justify-center w-8 h-8 rounded-xl border border-gray-300 hover:bg-gray-100 dark:border-slate-500 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            aria-label="Open Atom feed modal"
                        >
                            <RssIcon class="w-4 h-4 text-gray-600 dark:text-slate-400" />
                        </ShareModal>

                        <div
                            v-if="
                                (!authStore.authenticated &&
                                    !profile.local &&
                                    profile.remote_url) ||
                                (authStore.authenticated && profile.id !== authStore.getUser.id)
                            "
                            class="relative"
                            ref="menuRef"
                        >
                            <button
                                @click="toggleMenu"
                                type="button"
                                aria-haspopup="menu"
                                :aria-expanded="showMenu"
                                class="flex items-center justify-center w-8 h-8 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800/80 border border-gray-200/70 dark:border-gray-800/70 bg-white/70 dark:bg-gray-950/40 backdrop-blur transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-950"
                            >
                                <EllipsisHorizontalIcon
                                    class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                />
                            </button>

                            <Transition
                                enter-active-class="transition ease-out duration-120"
                                enter-from-class="opacity-0 translate-y-1 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition ease-in duration-90"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 translate-y-1 scale-95"
                            >
                                <div
                                    v-if="showMenu"
                                    role="menu"
                                    class="absolute right-0 mt-2 w-56 origin-top-right z-50"
                                    @click.stop
                                >
                                    <div
                                        class="rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur shadow-xl shadow-black/5 dark:shadow-black/30 ring-1 ring-black/5 dark:ring-white/10 border border-gray-200/70 dark:border-gray-800/70 p-1"
                                    >
                                        <a
                                            v-if="!profile.local && profile.remote_url"
                                            :href="profile.remote_url"
                                            target="_blank"
                                            rel="noopener nofollow noreferrer"
                                            role="menuitem"
                                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-800/70"
                                        >
                                            <ArrowTopRightOnSquareIcon
                                                class="w-4 h-4 text-gray-500 dark:text-gray-400"
                                            />
                                            <span class="flex-1">View full profile</span>
                                        </a>

                                        <template v-if="authStore.authenticated">
                                            <div
                                                v-if="!profile.local && profile.remote_url"
                                                class="my-1 h-px bg-gray-100 dark:bg-gray-800"
                                            ></div>

                                            <button
                                                @click="handleReport"
                                                type="button"
                                                role="menuitem"
                                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-800/70"
                                            >
                                                <FlagIcon
                                                    class="w-4 h-4 text-gray-500 dark:text-gray-400"
                                                />
                                                <span class="flex-1">{{ t('common.report') }}</span>
                                            </button>

                                            <div
                                                class="my-1 h-px bg-gray-100 dark:bg-gray-800"
                                            ></div>

                                            <button
                                                v-if="profile.relationship.blocking"
                                                @click="handleUnblock"
                                                type="button"
                                                role="menuitem"
                                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10"
                                            >
                                                <NoSymbolIcon
                                                    class="w-4 h-4 text-red-600/80 dark:text-red-300/80"
                                                />
                                                <span class="flex-1">{{
                                                    t('profile.unblock')
                                                }}</span>
                                            </button>

                                            <button
                                                v-else
                                                @click="handleBlock"
                                                type="button"
                                                role="menuitem"
                                                class="w-full flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-left text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10"
                                            >
                                                <NoSymbolIcon
                                                    class="w-4 h-4 text-red-600/80 dark:text-red-300/80"
                                                />
                                                <span class="flex-1">{{ t('profile.block') }}</span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <div
                        v-if="profile.bio"
                        class="pt-4 max-w-full lg:max-w-[500px] mx-auto lg:mx-0 text-center lg:text-left"
                    >
                        <div
                            ref="bioWrapperRef"
                            class="overflow-hidden transition-[max-height] duration-300 ease-out"
                            :style="{
                                maxHeight: bioExpanded
                                    ? `${bioFullHeight}px`
                                    : `${bioCollapsedHeight}px`,
                                transitionTimingFunction: bioExpanded
                                    ? 'cubic-bezier(0.22, 1, 0.36, 1)'
                                    : 'cubic-bezier(0.4, 0, 0.2, 1)'
                            }"
                        >
                            <p
                                ref="bioRef"
                                class="text-gray-500 dark:text-slate-400 font-light text-sm sm:text-[15px] whitespace-pre-wrap break-words"
                            >
                                {{ profile.bio }}
                            </p>
                        </div>
                        <button
                            v-if="bioOverflowing"
                            @click="bioExpanded = !bioExpanded"
                            type="button"
                            class="mt-1 text-xs font-semibold text-gray-700 dark:text-slate-300 hover:text-gray-900 dark:hover:text-slate-100 transition-colors cursor-pointer"
                        >
                            {{ bioExpanded ? t('common.showLess') : t('common.showMore') }}
                        </button>
                    </div>

                    <div
                        v-if="authStore.authenticated && profile.links && profile.links.length > 0"
                        class="pt-4 flex flex-wrap gap-2 justify-center lg:justify-start"
                    >
                        <a
                            v-for="(linkItem, index) in profile.links"
                            :key="index"
                            :href="linkItem.link"
                            target="_blank"
                            rel="noopener noreferrer"
                            :class="index === 0 ? 'inline-flex' : 'hidden lg:inline-flex'"
                            class="items-center gap-2 px-3 py-[3px] text-xs rounded-2xl border border-gray-200 font-medium dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-950 transition-colors cursor-pointer"
                            :title="linkItem.url"
                        >
                            <LinkIcon class="w-3 h-3 flex-shrink-0" />
                            <span class="truncate max-w-[150px] sm:max-w-[200px]">
                                {{ linkItem.url }}
                            </span>
                            <CheckBadgeIcon
                                v-if="linkItem.is_verified"
                                class="w-4 h-4 text-blue-500 dark:text-blue-400 flex-shrink-0"
                            />
                        </a>

                        <button
                            v-if="profile.links.length > 1"
                            @click="showProfileInfoModal = true"
                            type="button"
                            class="lg:hidden inline-flex items-center px-3 py-[3px] text-xs rounded-2xl border border-gray-200 font-semibold dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-950 transition-colors cursor-pointer"
                        >
                            +{{ profile.links.length - 1 }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <FollowersModal
        :show="showFollowersModal"
        :followers="profile.followers"
        :followings="profile.following"
        @close="showFollowersModal = false"
        @gotoProfile="gotoProfile"
        :tab="followersTab"
    />

    <Teleport to="body">
        <RemoteFollowModal
            v-model="showRemoteFollowModal"
            :username="profile.username"
            :url="profile.url"
        />

        <ProfileHeaderInfoModal
            v-model="showProfileInfoModal"
            :profile="profile"
            :username="profile.username"
            :url="profile.local ? profile.url : profile.remote_url"
        />
        <EditModal
            v-if="showEditModal"
            @close="showEditModal = false"
            @updated="showEditModal = false"
        />
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useProfileStore } from '~/stores/profile'
import { useRouter } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
import FollowersModal from '~/components/Profile/FollowersModal.vue'
import EditModal from '~/components/Profile/EditModal.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useReportModal } from '@/composables/useReportModal'
const { openReportModal } = useReportModal()
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'

import {
    CogIcon,
    PhotoIcon,
    GlobeAltIcon,
    ShareIcon,
    EllipsisHorizontalIcon,
    FlagIcon,
    NoSymbolIcon,
    LinkIcon,
    CheckBadgeIcon,
    ArrowTopRightOnSquareIcon,
    RssIcon,
    IdentificationIcon,
    CheckCircleIcon,
    ChatBubbleOvalLeftIcon
} from '@heroicons/vue/24/outline'
import RemoteFollowModal from './RemoteFollowModal.vue'

const { t } = useI18n()
const router = useRouter()
const profile = useProfileStore()
const { isPollingFollowState } = storeToRefs(profile)
const { toggleFollow } = useProfileStore()
const { alertModal, confirmModal } = useAlertModal()

const { formatCount, textTruncate } = useUtils()
const authStore = useAuthStore()
const showFollowersModal = ref(false)
const showEditModal = ref(false)
const showMenu = ref(false)
const showRemoteFollowModal = ref(false)
const showProfileInfoModal = ref(false)
const menuRef = ref(null)
const followersTab = ref('followers')
const isFollowing = computed(() => profile.isFollowing)
const isFollowingRequestPending = computed(() => profile.isFollowingRequestPending)
const BIO_MAX_LINES = 3
const bioRef = ref(null)
const bioWrapperRef = ref(null)
const bioExpanded = ref(false)
const bioOverflowing = ref(false)
const bioCollapsedHeight = ref(0)
const bioFullHeight = ref(0)
const isMessagingLoading = ref(false)

let resizeObserver = null

const handleToggleFollow = async () => {
    const state = isFollowing.value
    const action = state ? 'Unfollow' : 'Follow'
    const result = await confirmModal(
        `Confirm ${action}`,
        `Are you sure you want to ${action.toLowerCase()} <strong>${profile.username}</strong>?`,
        action,
        'Cancel'
    )
    if (result) {
        await toggleFollow()
    }
}

const measureBio = async () => {
    await nextTick()
    if (!bioRef.value) return

    const el = bioRef.value
    const style = window.getComputedStyle(el)
    let lineHeight = parseFloat(style.lineHeight)

    if (Number.isNaN(lineHeight)) {
        lineHeight = parseFloat(style.fontSize) * 1.4
    }

    bioFullHeight.value = el.scrollHeight
    bioCollapsedHeight.value = Math.ceil(lineHeight * BIO_MAX_LINES)
    bioOverflowing.value = bioFullHeight.value > bioCollapsedHeight.value + 1
}

const handleFollowersModal = () => {
    followersTab.value = 'followers'
    showFollowersModal.value = true
}

const handleFollowingModal = () => {
    followersTab.value = 'following'
    showFollowersModal.value = true
}

const openEditProfile = () => {
    showEditModal.value = true
}

const gotoProfile = (id) => {
    showFollowersModal.value = false
    router.push(`/@${id}`)
}

const handleShare = () => {
    console.log('Share profile:', profile.username)
}

const toggleMenu = () => {
    showMenu.value = !showMenu.value
}

const handleReport = () => {
    openReportModal('profile', profile.id, window.location.href, {
        id: profile.id,
        username: profile.username,
        is_owner: profile.id === authStore.profile?.id
    })
    showMenu.value = false
}

const handleMessage = async () => {
    if (isMessagingLoading.value) return
    isMessagingLoading.value = true

    try {
        const conversation = await profile.getOrCreateDmConversation()
        router.push(`/messages/${conversation.id}`)
    } catch (error) {
        const msg =
            error.response?.data?.message || 'Unable to start a conversation with this account.'
        await alertModal('Message unavailable', msg)
    } finally {
        isMessagingLoading.value = false
    }
}

const handleUndoFollowRequest = async () => {
    const result = await confirmModal(
        'Confirm Cancel Follow Request',
        `Are you sure you cancel your follow request to <strong>${profile.username}</strong>?`,
        'Confirm Cancel',
        'Close'
    )

    if (result) {
        await profile.undoFollowRequest()
    }
}

const handleBlock = async () => {
    showMenu.value = false

    const result = await confirmModal(
        'Confirm Block',
        `Are you sure you want to block <strong>${profile.username}</strong>?`,
        'Block',
        'Cancel'
    )

    if (result) {
        await profile.blockAccount()
        console.log('Block user:', profile.username)
    }
}

const handleUnblock = async () => {
    showMenu.value = false

    const result = await confirmModal(
        'Confirm Unblock',
        `Are you sure you want to unblock <strong>${profile.username}</strong>?`,
        'Unblock',
        'Cancel'
    )

    if (result) {
        await profile.unblockAccount()
        console.log('Block user:', profile.username)
    }
}

const handleClickOutside = (event) => {
    if (menuRef.value && !menuRef.value.contains(event.target)) {
        showMenu.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    measureBio()

    if (bioRef.value && 'ResizeObserver' in window) {
        resizeObserver = new ResizeObserver(() => measureBio())
        resizeObserver.observe(bioRef.value)
    }
    window.addEventListener('resize', measureBio)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', measureBio)
    resizeObserver?.disconnect()
})

watch(
    () => profile.bio,
    () => {
        bioExpanded.value = false
        measureBio()
    }
)
</script>
