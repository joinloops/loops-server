<template>
    <router-link
        :to="to"
        class="flex items-center p-4 py-2 font-light rounded-lg hover:bg-gray-100 dark:hover:bg-gray-900"
        :class="[
            isRouteActive(to) ? 'text-red-500 font-medium' : 'text-gray-700 dark:text-gray-400'
        ]"
    >
        <i class="mr-3 text-gray-400" :class="icon" />
        <span>{{ name }}</span>
    </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const props = defineProps({
    icon: String,
    name: String,
    to: String,
    highlight: Boolean
})

const isRouteActive = (navPath) => {
    const currentPath = route.path

    if (currentPath === navPath) {
        return true
    }

    if (currentPath.startsWith(navPath + '/')) {
        return true
    }

    return false
}
</script>
