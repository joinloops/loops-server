<script setup lang="ts">
import { computed } from 'vue'

interface Props {
    src: string
    width?: number
    class?: string
    alt?: string
    fallbackSrc?: string
    shape?: 'circle' | 'square'
}

const props = withDefaults(defineProps<Props>(), {
    width: 40,
    alt: 'User avatar',
    shape: 'circle',
    class: ''
})

const classes = computed(() => {
    const defaultClasses = [
        'inline-block',
        'object-cover',
        props.shape === 'circle' ? 'rounded-full' : 'rounded-lg'
    ]

    return [...defaultClasses, props.class].filter(Boolean).join(' ')
})

const handleError = (e: Event) => {
    const img = e.target as HTMLImageElement
    if (props.fallbackSrc) {
        img.src = props.fallbackSrc
    } else {
        img.src = '/storage/avatars/default.jpg'
    }
}
</script>

<template>
    <img
        :src="src"
        :width="width"
        :height="width"
        :alt="alt"
        :class="classes"
        @error="handleError"
    />
</template>

<script lang="ts">
export default {
    name: 'Avatar'
}
</script>

<style scoped>
img {
    aspect-ratio: 1;
}
</style>
