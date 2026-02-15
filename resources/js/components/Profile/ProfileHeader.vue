<template>
    <div class="flex w-full lg:w-[calc(100vw-240px)] px-4 lg:px-0">
        <div class="flex justify-between flex-col w-full">
            <div class="flex flex-col lg:flex-row lg:space-x-10 lg:items-center mb-5">
                <div class="flex justify-center lg:justify-start mb-4 lg:mb-0">
                    <img
                        class="w-24 h-24 sm:w-32 sm:h-32 lg:max-w-[200px] lg:w-[200px] lg:h-[200px] rounded-full object-cover border border-gray-200 dark:border-gray-800"
                        :src="profile.avatar"
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                    />
                </div>

                <div class="w-full text-center lg:text-left">
                    <div
                        class="flex flex-col sm:flex-row sm:gap-5 sm:items-end justify-center lg:justify-start mb-3"
                    >
                        <div
                            class="text-lg sm:text-[20px] font-bold truncate dark:text-slate-50"
                            :title="profile.name"
                        >
                            {{ textTruncate(profile.name, 30) }}
                        </div>
                        <div
                            class="text-base sm:text-[20px] text-gray-600 dark:text-slate-400"
                            :title="profile.username"
                        >
                            &commat;{{ textTruncate(profile.username, 50) }}
                        </div>
                    </div>

                    <template v-if="authStore.authenticated">
                        <div
                            class="flex items-center justify-center lg:justify-start gap-2 sm:gap-3 mt-3 flex-wrap"
                        >
                            <button
                                v-if="profile.id == authStore.getUser.id"
                                @click="openEditProfile"
                                class="flex items-center gap-2 sm:gap-3 rounded-md py-1.5 px-4 sm:px-6 text-sm sm:text-[15px] font-semibold border hover:bg-gray-100 dark:text-slate-400 dark:border-slate-500 dark:hover:bg-slate-900 cursor-pointer"
                            >
                                <div>{{ t('profile.editProfile') }}</div>
                            </button>

                            <template v-else>
                                <button
                                    v-if="profile.relationship.blocking"
                                    @click="handleUnblock"
                                    class="flex item-center rounded-md py-[4px] px-6 sm:px-8 text-sm sm:text-[15px] text-[#F02C56] border-[#F02C56] font-semibold border dark:border-[#F02C56] cursor-pointer"
                                >
                                    {{ t('profile.blocked') }}
                                </button>
                                <button
                                    v-else-if="isFollowingRequestPending"
                                    @click="handleUndoFollowRequest"
                                    class="flex item-center gap-2 rounded-md py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-[#F02C56] border-[#F02C56] font-semibold border dark:border-border-[#F02C56] cursor-pointer"
                                >
                                    <Spinner v-if="isPollingFollowState" size="xs" />
                                    {{ t('profile.followRequestPending') }}
                                </button>
                                <button
                                    v-else-if="!profile.isFollowing"
                                    @click="handleToggleFollow"
                                    class="flex item-center rounded-md py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-white bg-red-500 hover:bg-red-400 font-semibold border dark:border-slate-950 cursor-pointer"
                                >
                                    {{ t('common.follow') }}
                                </button>
                                <button
                                    v-else
                                    @click="handleToggleFollow"
                                    class="flex item-center rounded-md py-[5px] px-6 sm:px-8 text-sm sm:text-[15px] text-[#F02C56] border-[#F02C56] font-semibold border dark:border-border-[#F02C56] cursor-pointer"
                                >
                                    {{ t('common.unfollow') }}
                                </button>
                            </template>

                            <ShareModal
                                type="profile"
                                :url="profile.url"
                                :username="profile.username"
                                class="flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 hover:bg-gray-100 dark:border-slate-500 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            >
                                <ShareIcon class="w-4 h-4 text-gray-600 dark:text-slate-400" />
                            </ShareModal>

                            <div
                                v-if="profile.id !== authStore.getUser.id"
                                class="relative"
                                ref="menuRef"
                            >
                                <button
                                    @click="toggleMenu"
                                    type="button"
                                    aria-haspopup="menu"
                                    :aria-expanded="showMenu"
                                    class="flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/80 border border-gray-200/70 dark:border-gray-800/70 bg-white/70 dark:bg-gray-950/40 backdrop-blur transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-950"
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

                                            <div
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
                                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10"
                                            >
                                                <NoSymbolIcon
                                                    class="w-4 h-4 text-red-600/80 dark:text-red-300/80"
                                                />
                                                <span class="flex-1">{{ t('profile.block') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </template>

                    <div
                        class="flex items-center justify-center lg:justify-start pt-4 gap-8 lg:gap-10 flex-wrap"
                    >
                        <div class="text-center lg:flex items-center gap-1 lg:text-left">
                            <div class="text-base sm:text-lg">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.postCount)
                                }}</span>
                            </div>
                            <div class="text-xs sm:text-sm lg:text-lg text-gray-500 font-light">
                                {{ t('common.videos') }}
                            </div>
                        </div>
                        <div
                            @click="showFollowersModal = true"
                            class="text-center lg:text-left lg:flex items-center gap-1 cursor-pointer hover:opacity-80"
                        >
                            <div class="text-base sm:text-lg">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.followerCount)
                                }}</span>
                            </div>
                            <div class="text-xs sm:text-sm lg:text-lg text-gray-500 font-light">
                                {{ t('common.followers') }}
                            </div>
                        </div>
                        <div class="text-center lg:text-left lg:flex items-center gap-1">
                            <div class="text-base sm:text-lg">
                                <span class="font-bold dark:text-slate-300">{{
                                    formatCount(profile.allLikes)
                                }}</span>
                            </div>
                            <div class="text-xs sm:text-sm lg:text-lg text-gray-500 font-light">
                                {{ t('profile.likes') }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="pt-4 text-gray-500 dark:text-slate-400 font-light text-sm sm:text-[15px] max-w-full lg:max-w-[500px] text-center lg:text-left"
                    >
                        {{ profile.bio }}
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
                            class="inline-flex items-center gap-2 px-3 py-[3px] text-xs text-xs rounded-2xl border border-gray-200 font-medium dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-950 transition-colors cursor-pointer"
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
    />

    <Teleport to="body">
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
    ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline'

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
const menuRef = ref(null)
const isFollowing = computed(() => profile.isFollowing)
const isFollowingRequestPending = computed(() => profile.isFollowingRequestPending)

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
    openReportModal('profile', profile.id, window.location.href)
    showMenu.value = false
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
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>
