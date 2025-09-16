<template>
    <div
        @click="handleClick"
        @mouseenter="isHover(true)"
        @mouseleave="isHover(false)"
        class="relative brightness-90 hover:brightness-[1.1] cursor-pointer"
    >
        <div
            v-if="!isLoaded"
            class="absolute flex items-center justify-center top-0 left-0 aspect-[3/4] w-full object-cover rounded-md bg-black overflow-hidden"
        >
            <Icon
                class="animate-spin ml-1"
                name="mingcute:loading-line"
                size="100"
                color="#FFFFFF"
            />
        </div>

        <div
            v-if="post.is_sensitive && !isSensitiveRevealed"
            class="absolute inset-0 bg-black/60 rounded-md flex flex-col items-center justify-center z-10"
        >
            <Icon
                name="mingcute:eye-close-line"
                size="48"
                color="#FFFFFF"
                class="mb-2"
            />
            <span class="text-white text-sm font-medium mb-1">{{
                $t("post.sensitiveContent")
            }}</span>
            <span class="text-white/70 text-xs text-center px-4">
                {{ $t("common.clickToReveal") }}
            </span>
        </div>

        <div class="rounded-md overflow-hidden">
            <video
                ref="videoRef"
                muted
                loop
                :class="[
                    'aspect-[3/5] object-cover transition-all duration-300',
                    {
                        'blur-lg': post.is_sensitive && !isSensitiveRevealed,
                        'blur-none': !post.is_sensitive || isSensitiveRevealed,
                    },
                ]"
                :src="post.media.src_url"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useHashids } from "@/composables/useHashids";

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
});

const route = useRoute();
const router = useRouter();
const videoRef = ref(null);
const isLoaded = ref(false);
const isSensitiveRevealed = ref(false);

const displayPost = (post) => {
    const { encodeHashid } = useHashids();
    const postId = encodeHashid(post.id);
    setTimeout(() => router.push(`/v/${postId}`), 300);
};

const handleClick = (event) => {
    if (props.post.is_sensitive && !isSensitiveRevealed.value) {
        event.stopPropagation();
        isSensitiveRevealed.value = true;
        return;
    }

    displayPost(props.post);
};

const isHover = (bool) => {
    if (!videoRef.value) return;

    if (props.post.is_sensitive && !isSensitiveRevealed.value) {
        return;
    }

    if (bool) {
        videoRef.value.play();
    } else {
        videoRef.value.pause();
    }
};

onMounted(() => {
    if (videoRef.value) {
        videoRef.value.addEventListener("loadeddata", (e) => {
            if (e.target) {
                setTimeout(() => {
                    isLoaded.value = true;
                }, 200);
            }
        });
    }
});

onBeforeUnmount(() => {
    if (videoRef.value) {
        videoRef.value.pause();
        videoRef.value.currentTime = 0;
        videoRef.value.src = "";
    }
});
</script>
