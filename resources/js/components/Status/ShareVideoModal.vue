<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4"
            >
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close" />

                <div
                    class="relative w-full sm:max-w-md bg-white dark:bg-slate-900 sm:rounded-2xl rounded-t-2xl shadow-2xl border border-gray-100 dark:border-slate-800 overflow-hidden max-h-[90vh] flex flex-col"
                >
                    <div
                        class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-800 flex-shrink-0"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="w-8 h-8 rounded-xl bg-[#F02C56]/10 flex items-center justify-center flex-shrink-0"
                            >
                                <ShareIcon class="w-4 h-4 text-[#F02C56]" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Share this loop</h3>
                        </div>
                        <button
                            @click="close"
                            class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 text-gray-400 transition-colors cursor-pointer"
                            aria-label="Close"
                        >
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="overflow-y-auto p-5 space-y-5">
                        <DmQuickShare v-if="videoId" :video-id="videoId" @more="openDmSearch" />

                        <div>
                            <label
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2 block"
                            >
                                Video link
                            </label>
                            <div class="flex items-stretch gap-2">
                                <div
                                    class="flex-1 min-w-0 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-2.5 text-sm text-gray-700 dark:text-gray-300 truncate"
                                >
                                    {{ url }}
                                </div>
                                <AnimatedButton
                                    variant="primaryGradient"
                                    pill
                                    :disabled="copied"
                                    @click="copyLink"
                                >
                                    <div class="flex items-center gap-2">
                                        <CheckIcon v-if="copied" class="w-4 h-4" />
                                        <ClipboardDocumentIcon v-else class="w-4 h-4" />
                                        {{ copied ? 'Copied' : 'Copy&nbsp;&nbsp;&nbsp;' }}
                                    </div>
                                </AnimatedButton>
                            </div>
                            <p
                                class="mt-2 text-xs text-gray-500 dark:text-gray-400 leading-relaxed"
                            >
                                Paste this link into the search bar of any fediverse server
                                (Mastodon, Pixelfed, Loops, etc.) to like, comment, or share
                                natively.
                            </p>
                        </div>

                        <AnimatedButton
                            v-if="canNativeShare"
                            variant="light"
                            pill
                            @click="nativeShare"
                            class="w-full mb-5 cursor-pointer"
                        >
                            <div class="flex items-center gap-2">
                                <ArrowUpOnSquareIcon class="w-4 h-4" />
                                Share to other
                            </div>
                        </AnimatedButton>

                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <GlobeAltIcon class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                <label
                                    class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide"
                                >
                                    Share to your fediverse server
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="platform in fediversePlatforms"
                                    :key="platform.id"
                                    @click="selectPlatform(platform.id)"
                                    class="flex flex-col items-center gap-1.5 p-3 rounded-lg border transition-colors cursor-pointer"
                                    :class="
                                        activePlatform === platform.id
                                            ? 'border-[#F02C56] bg-[#F02C56]/5'
                                            : 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800'
                                    "
                                >
                                    <img :src="platform.logo" class="w-8 h-8 flex-shrink-0" />
                                    <span
                                        class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate w-full text-center"
                                    >
                                        {{ platform.name }}
                                    </span>
                                </button>
                            </div>

                            <Transition name="expand">
                                <div
                                    v-if="activePlatform"
                                    class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700"
                                >
                                    <label
                                        class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block"
                                    >
                                        Your {{ activePlatformName }} server
                                    </label>
                                    <form @submit.prevent="submitServer" class="flex gap-2">
                                        <input
                                            ref="serverInputRef"
                                            v-model="serverInput"
                                            type="text"
                                            :placeholder="activePlatformPlaceholder"
                                            class="flex-1 min-w-0 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#F02C56]/40 focus:border-[#F02C56]"
                                            autocomplete="off"
                                            autocapitalize="off"
                                            spellcheck="false"
                                        />
                                        <button
                                            type="submit"
                                            :disabled="!serverInput.trim()"
                                            class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg bg-[#F02C56] hover:bg-[#F02C56]/90 disabled:opacity-50 disabled:cursor-not-allowed text-white transition-colors cursor-pointer"
                                        >
                                            Open
                                        </button>
                                    </form>
                                    <p v-if="serverError" class="mt-1.5 text-xs text-red-500">
                                        {{ serverError }}
                                    </p>
                                    <p
                                        v-else-if="rememberedServer"
                                        class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Using your saved server.
                                        <button
                                            type="button"
                                            @click="forgetServer"
                                            class="underline hover:text-gray-700 dark:hover:text-gray-300 cursor-pointer"
                                        >
                                            Forget
                                        </button>
                                    </p>
                                </div>
                            </Transition>
                        </div>

                        <div>
                            <label
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 block"
                            >
                                Other platforms
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <a
                                    v-for="platform in externalPlatforms"
                                    :key="platform.id"
                                    :href="platform.url(url, shareText)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex flex-col items-center gap-1.5 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <div
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-white flex-shrink-0"
                                        :style="{ backgroundColor: platform.color }"
                                    >
                                        <svg
                                            class="w-4 h-4"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path :d="platform.icon" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate w-full text-center"
                                    >
                                        {{ platform.name }}
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Teleport to="body">
        <DmShareModal
            v-if="showDmModal"
            :video="{ id: videoId, url }"
            @close="showDmModal = false"
            @sent="showDmModal = false"
        />
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import {
    XMarkIcon,
    ShareIcon,
    GlobeAltIcon,
    ClipboardDocumentIcon,
    CheckIcon,
    ArrowUpOnSquareIcon
} from '@heroicons/vue/24/outline'
import DmQuickShare from '@/components/Dm/DmQuickShare.vue'
import DmShareModal from '@/components/Dm/DmShareModal.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    url: { type: String, required: true },
    shareText: { type: String, default: 'Check out this loop' },
    videoId: { type: [String, Number], default: null }
})

const emit = defineEmits(['update:modelValue'])

const STORAGE_KEY = 'loops:share:fediverseServer'

const copied = ref(false)
const activePlatform = ref(null)
const serverInput = ref('')
const serverError = ref('')
const serverInputRef = ref(null)
const rememberedServer = ref(false)
const showDmModal = ref(false)

const fediversePlatforms = [
    {
        id: 'mastodon',
        name: 'Mastodon',
        logo: '/img/fediverse-logos/mastodon.svg',
        placeholder: 'mastodon.social',
        buildUrl: (server, url, text) =>
            `https://${server}/authorize_interaction?uri=${encodeURIComponent(url)}`
    },
    {
        id: 'pixelfed',
        name: 'Pixelfed',
        logo: '/img/fediverse-logos/pixelfed.svg',
        placeholder: 'pixelfed.social',
        buildUrl: (server, url) =>
            `https://${server}/authorize_interaction?uri=${encodeURIComponent(url)}`
    }
]

const externalPlatforms = [
    {
        id: 'bluesky',
        name: 'Bluesky',
        color: '#1185FE',
        icon: 'M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.815 2.736 3.713 3.66 6.383 3.364.136-.02.275-.039.415-.056-.138.022-.276.04-.415.056-3.911.58-7.386 2.005-2.83 7.078 5.013 5.19 6.87-1.113 7.823-4.308.953 3.195 2.05 9.271 7.733 4.308 4.267-4.308 1.172-6.498-2.74-7.078a8.741 8.741 0 0 1-.415-.056c.14.017.279.036.415.056 2.67.297 5.568-.628 6.383-3.364.246-.828.624-5.79.624-6.478 0-.69-.139-1.861-.902-2.206-.659-.298-1.664-.62-4.3 1.24C16.046 4.748 13.087 8.687 12 10.8Z',
        url: (url, text) =>
            `https://bsky.app/intent/compose?text=${encodeURIComponent(`${text} ${url}`)}`
    },
    {
        id: 'facebook',
        name: 'Facebook',
        color: '#1877F2',
        icon: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
        url: (url) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`
    },
    {
        id: 'linkedin',
        name: 'LinkedIn',
        color: '#0A66C2',
        icon: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
        url: (url) =>
            `https://www.linkedin.com/feed/?shareActive=true&text=${encodeURIComponent(url)}`
    },
    {
        id: 'pinterest',
        name: 'Pinterest',
        color: '#E60023',
        icon: 'M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z',
        url: (url) => `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(url)}`
    },
    {
        id: 'reddit',
        name: 'Reddit',
        color: '#FF4500',
        icon: 'M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z',
        url: (url) => `https://www.reddit.com/submit?url=${encodeURIComponent(url)}`
    },
    {
        id: 'telegram',
        name: 'Telegram',
        color: '#26A5E4',
        icon: 'M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z',
        url: (url, text) =>
            `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`
    },
    {
        id: 'threads',
        name: 'Threads',
        color: '#000000',
        icon: 'M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.78 3.631 2.695 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.75-1.757-.513-.586-1.308-.883-2.359-.89h-.029c-.844 0-1.992.232-2.721 1.32L7.734 7.847c.98-1.454 2.568-2.256 4.478-2.256h.044c3.194.02 5.097 1.975 5.287 5.388.108.046.216.094.321.143 1.49.7 2.58 1.761 3.154 3.07.797 1.82.871 4.79-1.548 7.158-1.85 1.81-4.094 2.628-7.277 2.65Zm1.003-11.69c-.242 0-.487.007-.739.021-1.836.103-2.98.946-2.916 2.143.067 1.256 1.452 1.839 2.784 1.767 1.224-.065 2.818-.543 3.086-3.71a10.5 10.5 0 0 0-2.215-.221z',
        url: (url, text) =>
            `https://www.threads.net/intent/post?text=${encodeURIComponent(`${text} ${url}`)}`
    },
    {
        id: 'twitter',
        name: 'X',
        color: '#000000',
        icon: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
        url: (url, text) =>
            `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`
    },
    {
        id: 'whatsapp',
        name: 'WhatsApp',
        color: '#25D366',
        icon: 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z',
        url: (url, text) => `https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`
    }
]

const canNativeShare = computed(() => {
    if (typeof navigator === 'undefined' || !navigator.share) return false
    if (!navigator.canShare) return true
    try {
        return navigator.canShare({
            title: 'Loops',
            text: props.shareText,
            url: props.url
        })
    } catch {
        return false
    }
})

const activePlatformName = computed(() => {
    return fediversePlatforms.find((p) => p.id === activePlatform.value)?.name ?? ''
})

const activePlatformPlaceholder = computed(() => {
    return (
        fediversePlatforms.find((p) => p.id === activePlatform.value)?.placeholder ?? 'your.server'
    )
})

const close = () => {
    emit('update:modelValue', false)
}

const openDmSearch = () => {
    showDmModal.value = true
    close()
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            copied.value = false
            activePlatform.value = null
            serverError.value = ''
            serverInput.value = ''
            rememberedServer.value = false
        }
    }
)

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.url)
        copied.value = true
        setTimeout(() => {
            copied.value = false
        }, 2000)
    } catch {
        const textarea = document.createElement('textarea')
        textarea.value = props.url
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        try {
            document.execCommand('copy')
            copied.value = true
            setTimeout(() => {
                copied.value = false
            }, 2000)
        } catch (err) {
            console.error('Copy failed', err)
        }
        document.body.removeChild(textarea)
    }
}

const nativeShare = async () => {
    try {
        await navigator.share({
            title: 'Loops',
            text: props.shareText,
            url: props.url
        })
    } catch (err) {
        if (err?.name !== 'AbortError') {
            console.error('Share failed', err)
        }
    }
}

const selectPlatform = (id) => {
    if (activePlatform.value === id) {
        activePlatform.value = null
        return
    }
    activePlatform.value = id
    serverError.value = ''

    let saved = ''
    try {
        saved = window.localStorage.getItem(STORAGE_KEY) || ''
    } catch {
        saved = ''
    }
    serverInput.value = saved
    rememberedServer.value = !!saved

    nextTick(() => {
        serverInputRef.value?.focus()
    })
}

const normalizeServer = (raw) => {
    let s = raw.trim().toLowerCase()
    s = s
        .replace(/^https?:\/\//, '')
        .replace(/\/.*$/, '')
        .replace(/\s/g, '')
    return s
}

const isValidServer = (s) => {
    return /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/.test(s)
}

const submitServer = () => {
    const server = normalizeServer(serverInput.value)

    if (!server || !isValidServer(server)) {
        serverError.value = 'Enter a valid server domain like mastodon.social'
        return
    }

    const platform = fediversePlatforms.find((p) => p.id === activePlatform.value)
    if (!platform) return

    try {
        window.localStorage.setItem(STORAGE_KEY, server)
    } catch {}

    const target = platform.buildUrl(server, props.url, props.shareText)
    window.open(target, '_blank', 'noopener,noreferrer')
    close()
}

const forgetServer = () => {
    try {
        window.localStorage.removeItem(STORAGE_KEY)
    } catch {}
    serverInput.value = ''
    rememberedServer.value = false
    nextTick(() => {
        serverInputRef.value?.focus()
    })
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-active > div:last-child,
.modal-leave-active > div:last-child {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from > div:last-child,
.modal-leave-to > div:last-child {
    transform: translateY(20px);
}

.expand-enter-active,
.expand-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
    margin-top: 0 !important;
}

.expand-enter-to,
.expand-leave-from {
    opacity: 1;
    max-height: 200px;
}
</style>
