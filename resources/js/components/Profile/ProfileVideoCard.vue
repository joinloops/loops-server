<template>
    <div @click="handleClick" class="relative brightness-90 hover:brightness-[1.1] cursor-pointer">
        <div
            v-if="!isLoaded"
            class="absolute flex items-center justify-center top-0 left-0 aspect-[3/4] w-full object-cover rounded-md bg-black overflow-hidden z-20"
        >
            <svg
                class="animate-spin w-10 h-10 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>

        <div
            v-if="post.is_sensitive && !isSensitiveRevealed"
            class="absolute inset-0 bg-black/60 rounded-md flex flex-col items-center justify-center z-10"
        >
            <EyeSlashIcon class="w-12 h-12 text-white mb-2" />
            <span class="text-white text-sm font-medium mb-1">
                {{ $t('post.sensitiveContent') }}
            </span>
            <span class="text-white/70 text-xs text-center px-4">
                {{ $t('common.clickToReveal') }}
            </span>
        </div>

        <div class="relative rounded-md overflow-hidden">
            <img
                :src="post.media.thumbnail"
                @load="isLoaded = true"
                :class="[
                    'aspect-[3/4] w-full object-cover transition-all duration-300',
                    {
                        'blur-lg': post.is_sensitive && !isSensitiveRevealed,
                        'blur-none': !post.is_sensitive || isSensitiveRevealed
                    }
                ]"
                alt=""
            />

            <div class="pointer-events-none absolute inset-0">
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/40 to-transparent"
                ></div>

                <div
                    v-if="post.pinned"
                    class="absolute top-2 left-2 flex items-center gap-1 text-white bg-red-500 px-3 py-1 text-xs rounded font-bold"
                >
                    <span>{{ $t('post.pinned') }}</span>
                </div>

                <div
                    class="absolute bottom-3 left-3 flex items-center gap-1 text-white text-sm font-bold drop-shadow"
                >
                    <HeartIcon class="w-5 h-5 text-white" />
                    <span>{{ formatCount(post.likes) }}</span>
                </div>

                <div
                    v-if="post.permissions.can_comment"
                    class="absolute bottom-3 right-3 flex items-center gap-1 text-white text-sm font-bold drop-shadow"
                >
                    <ChatBubbleOvalLeftIcon class="w-5 h-5 text-white" />
                    <span>{{ formatCount(post.comments) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useHashids } from '@/composables/useHashids'
import { useUtils } from '@/composables/useUtils'
import { HeartIcon, ChatBubbleOvalLeftIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const { formatCount } = useUtils()

const props = defineProps({
    post: {
        type: Object,
        required: true
    }
})

const router = useRouter()
const isLoaded = ref(false)
const isSensitiveRevealed = ref(false)

const displayPost = (post) => {
    const { encodeHashid } = useHashids()
    const postId = encodeHashid(post.id)
    setTimeout(() => router.push(`/v/${postId}`), 300)
}

const handleClick = (event) => {
    if (props.post.is_sensitive && !isSensitiveRevealed.value) {
        event.stopPropagation()
        isSensitiveRevealed.value = true
        return
    }

    displayPost(props.post)
}
</script>
