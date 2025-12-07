<template>
    <router-link :to="convertedPath">
        <slot></slot>
    </router-link>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useHashids } from '@/composables/useHashids'
const { encodeHashid } = useHashids()
interface Props {
    id: string
    prefix?: string
}

const props = withDefaults(defineProps<Props>(), {
    prefix: '/v'
})

const convertedPath = computed(() => {
    const base64Id = encodeHashid(props.id)
    return props.prefix ? `${props.prefix}/${base64Id}` : base64Id
})
</script>
