<template>
    <div
        class="w-full flex items-center hover:bg-gray-100 dark:hover:bg-slate-800 p-2.5 rounded-lg mb-1 transition-colors"
        :class="{
            'justify-center lg:justify-start': !isMobile,
            'justify-start': isMobile
        }"
    >
        <div class="flex items-center" :class="{ 'lg:mx-0 mx-auto': !isMobile }">
            <i :class="iconClass" :style="`font-size: ${sizeString}px;`"></i>
            <span
                v-if="isMobile || isLargeScreen"
                class="font-medium text-[17px] pl-[20px] pr-4"
                :class="{ 'hidden lg:block': !isMobile }"
            >
                {{ iconString }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'

defineProps({
    iconString: String,
    sizeString: String,
    iconClass: String,
    isMobile: {
        type: Boolean,
        default: false
    }
})

const windowWidth = ref(window.innerWidth)

const isLargeScreen = computed(() => windowWidth.value >= 1024)

const handleResize = () => {
    windowWidth.value = window.innerWidth
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>
