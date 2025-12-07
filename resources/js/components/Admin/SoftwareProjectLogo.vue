<template>
    <div v-if="['loops', 'pixelfed', 'mastodon'].indexOf(software) == -1">
        <ServerStackIcon class="w-16 h-16 text-white" />
    </div>
    <img
        v-else-if="software === 'pixelfed'"
        src="/storage/software-logos/pixelfed.png"
        class="rounded-full"
    />
    <img
        v-else-if="software === 'loops'"
        src="/storage/software-logos/loops.png"
        class="rounded-full"
    />
    <img
        v-else-if="software === 'mastodon'"
        src="/storage/software-logos/mastodon.png"
        class="rounded-full"
    />
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { ServerStackIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    software: {
        type: String,
        required: true
    }
})
</script>
