<template>
    <div>
        <div @click="toggleModal">
            <slot />
        </div>

        <Teleport to="body">
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="fixed inset-0 bg-black/50 transition-opacity" @click="close" />

                <div class="flex min-h-full items-center justify-center p-4">
                    <div
                        class="relative w-full max-w-md transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 p-6 text-left shadow-xl transition-all"
                    >
                        <button
                            type="button"
                            class="absolute right-4 top-4 text-gray-400 hover:text-gray-500"
                            @click="close"
                        >
                            <span class="sr-only">{{ t('common.close') }}</span>
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>

                        <div>
                            <h3
                                class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4"
                            >
                                {{ title }}
                            </h3>

                            <div class="mt-4">
                                <UrlCopyInput :url="url" />
                            </div>

                            <div class="mt-6 grid grid-cols-4 gap-x-4 gap-y-6">
                                <div
                                    v-for="provider in shareProviders"
                                    :key="provider.id"
                                    class="flex flex-col items-center gap-1"
                                >
                                    <button
                                        @click="openShareWindow(provider.url)"
                                        :title="`Share on ${provider.name}`"
                                        class="flex items-center justify-center w-12 h-12 rounded-full text-white hover:opacity-90 transition-opacity"
                                        :class="getBackgroundColor(provider.id)"
                                    >
                                        <svg
                                            v-if="provider.id === 'other'"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            class="size-6 text-gray-500"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                            />
                                        </svg>

                                        <svg
                                            v-else
                                            class="w-7 h-7"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path :d="provider.icon" />
                                        </svg>
                                    </button>
                                    <span
                                        class="text-[13px] font-medium text-black dark:text-slate-400"
                                        >{{ provider.name }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue'
import UrlCopyInput from '../Form/UrlCopyInput.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    url: {
        type: String,
        required: true
    },
    username: {
        type: String
    },
    type: {
        type: String,
        default: 'video'
    },
    enabled: {
        type: Array,
        default: () => [
            'whatsapp',
            'facebook',
            'twitter',
            'linkedin',
            'telegram',
            'reddit',
            'pinterest',
            'other'
        ]
    }
})

const { t } = useI18n()

const title = computed(() => {
    if (props.type === 'video') {
        return t('common.shareThisLoop')
    } else if (props.type === 'profile') {
        return t('common.shareThisAccount')
    }
    return 'Share this content'
})

const isOpen = ref(false)

const shareProviders = computed(() => {
    const providers = [
        {
            id: 'twitter',
            name: 'X',
            icon: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
            url: `https://twitter.com/intent/tweet?url=${encodeURIComponent(props.url)}`
        },
        {
            id: 'whatsapp',
            name: 'WhatsApp',
            icon: 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z',
            url: `https://wa.me/?text=${encodeURIComponent(props.url)}`
        },
        {
            id: 'facebook',
            name: 'Facebook',
            icon: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
            url: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.url)}`
        },
        {
            id: 'telegram',
            name: 'Telegram',
            icon: 'M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.002c.332.004.656-.17.832-.453.245-.766.902-2.984 1.246-4.11.344.188 1.389.807 2.446 1.427 1.359.796 2.726 1.592 3.407 1.991.344.2.748.147 1.058-.07.308-.214.494-.607.463-.995.002-.633.007-1.266.013-1.898.02-2.075.041-4.154-.012-6.22.036-.65-.067-1.407-.476-1.778-.324-.29-.717-.432-1.125-.448zm-.699 1.661c-.002.257-.001.513-.001.77-.001 2.036-.002 4.073-.001 6.107.006.461-.003.92-.003 1.38l-4.79-2.867-2.484-1.487c3.18-1.22 8.617-3.326 8.717-3.363a1.54 1.54 0 01-.076-.292c-.002-.084.01-.202.638-.248zm-8.314 4.409c.462 1.613.872 3.246 1.226 4.907-.265-.941-.557-1.871-.862-2.79-.311-.942-.637-1.878-.985-2.791.207.223.414.445.621.674zm2.132 5.77c-.177.572-.358 1.168-.545 1.774-1.106-.661-2.213-1.323-3.32-1.984.544-1.912 1.088-3.824 1.634-5.736 1.323.798 2.646 1.596 3.968 2.394l-1.737 3.552z',
            url: `https://t.me/share/url?url=${encodeURIComponent(props.url)}`
        },
        {
            id: 'linkedin',
            name: 'LinkedIn',
            icon: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
            url: `https://www.linkedin.com/feed/?shareActive=true&text=${encodeURIComponent(props.url)}`
        },
        {
            id: 'reddit',
            name: 'Reddit',
            icon: 'M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z',
            url: `https://www.reddit.com/submit?url=${encodeURIComponent(props.url)}`
        },
        {
            id: 'pinterest',
            name: 'Pinterest',
            icon: 'M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z',
            url: `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(props.url)}`
        },
        {
            id: 'other',
            name: 'Other',
            icon: 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            url: `#other`
        }
    ]

    return providers.filter((provider) => props.enabled.includes(provider.id))
})

const getShareText = () => {
    const opts = {
        video: `View ${props.username}'s video on Loops`,
        profile: `View ${props.username}'s account on Loops`
    }

    return opts[props.type]
}

const getBackgroundColor = (id) => {
    const colors = {
        facebook: 'bg-[#1877F2]',
        twitter: 'bg-black',
        whatsapp: 'bg-[#25D366]',
        telegram: 'bg-[#229ED9]',
        linkedin: 'bg-[#0077B5]',
        reddit: 'bg-[#FF4500]',
        pinterest: 'bg-[#E60023]',
        code: 'bg-[#5BC8EF]',
        mastodon: 'bg-[#6364FF]',
        hubzilla: 'bg-[#0093CC]',
        other: 'bg-gray-100',
        lemmy: 'bg-[#12d10e]'
    }
    return colors[id] || 'bg-gray-500'
}

const openShareWindow = (url) => {
    if (url === '#other') {
        return handleShare()
    }
    window.open(url, '_blank', 'width=600,height=600')
}

const handleShare = () => {
    const shareData = {
        title: 'Loops',
        text: getShareText(),
        url: props.url
    }

    if (!navigator.canShare) {
        return
    }

    if (!navigator.canShare(shareData)) {
        return
    }

    navigator.share(shareData)
}

const toggleModal = () => {
    isOpen.value = !isOpen.value
}

const close = () => {
    isOpen.value = false
}

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.url)
    } catch (err) {
        console.error('Failed to copy:', err)
    }
}
</script>
