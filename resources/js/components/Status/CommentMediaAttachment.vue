<template>
    <div class="mb-2 max-w-xs">
        <div
            class="relative overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800"
            :style="aspectStyle"
        >
            <button
                v-if="media.is_sensitive && !revealed"
                type="button"
                @click.stop="revealed = true"
                class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-1 bg-black/55 backdrop-blur-md text-white"
            >
                <EyeSlashIcon class="w-6 h-6" />
                <span class="text-sm font-medium">{{ $t('post.sensitiveContent') }}</span>
                <span class="text-xs opacity-80">{{ $t('post.tapToReveal') }}</span>
            </button>

            <img
                v-if="isImage"
                :src="media.url"
                :alt="media.description || ''"
                loading="lazy"
                draggable="false"
                @contextmenu.prevent
                @dragstart.prevent
                class="w-full h-full object-contain select-none"
            />

            <video
                v-else-if="isVideo"
                ref="videoEl"
                :src="media.url"
                muted
                loop
                playsinline
                preload="metadata"
                controlslist="nodownload noremoteplayback"
                disablepictureinpicture
                @contextmenu.prevent
                class="w-full h-full object-contain cursor-pointer"
                @mouseenter="onMouseEnter"
                @mouseleave="onMouseLeave"
                @click.stop="togglePlay"
                :aria-label="media.description || ''"
            />

            <div
                v-else
                class="absolute inset-0 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400"
            >
                {{ $t('post.unsupportedMedia') }}
            </div>

            <img
                v-if="media.provider === 'klipy' && (!media.is_sensitive || revealed)"
                src="/img/klipy/logo-light.svg"
                alt="Powered by KLIPY"
                class="absolute bottom-1.5 left-1.5 h-4 pointer-events-none select-none opacity-90 drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { EyeSlashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    media: {
        type: Object,
        required: true
    }
})

const videoEl = ref(null)
const revealed = ref(false)
let observer = null

const mime = computed(() => props.media.mime || props.media.mime_type || '')
const isImage = computed(() => mime.value.startsWith('image/'))
const isVideo = computed(() => mime.value.startsWith('video/'))

const canPlay = computed(() => !props.media.is_sensitive || revealed.value)

const aspectStyle = computed(() => {
    const w = Number(props.media.width) || 0
    const h = Number(props.media.height) || 0
    const ratio = w && h ? `${w} / ${h}` : '1 / 1'
    return { aspectRatio: ratio, maxHeight: '420px' }
})

const supportsHover = typeof window !== 'undefined' && window.matchMedia('(hover: hover)').matches

const play = () => {
    if (!canPlay.value || !videoEl.value) return
    videoEl.value.play().catch(() => {})
}

const pause = () => {
    videoEl.value?.pause()
}

const onMouseEnter = () => {
    if (supportsHover) play()
}

const onMouseLeave = () => {
    if (supportsHover) pause()
}

const togglePlay = () => {
    if (!videoEl.value) return
    if (videoEl.value.paused) play()
    else pause()
}

onMounted(() => {
    if (!isVideo.value || !videoEl.value) return

    if (!supportsHover) {
        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && canPlay.value) play()
                    else pause()
                })
            },
            { threshold: 0.4 }
        )
        observer.observe(videoEl.value)
    }
})

onBeforeUnmount(() => {
    observer?.disconnect()
    pause()
})
</script>
