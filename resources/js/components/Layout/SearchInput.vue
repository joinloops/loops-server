<template>
    <div class="w-full">
        <!-- Desktop/Expanded View -->
        <div v-if="isMobile || !isCollapsed" class="relative">
            <i
                class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-lg pointer-events-none"
            ></i>
            <input
                v-model="searchQuery"
                @keyup.enter="handleSearch"
                @focus="isFocused = true"
                @blur="handleBlur"
                type="text"
                :placeholder="$t('nav.search')"
                class="w-full pl-10 pr-10 py-2.5 bg-gray-100 dark:bg-slate-900 border-0 rounded-full text-sm focus:ring-2 focus:ring-[#F02C56] dark:text-white placeholder-gray-500 dark:placeholder-slate-500 transition-all"
                :class="{
                    'ring-2 ring-[#F02C56]': isFocused
                }"
            />
            <button
                v-if="searchQuery"
                @click="clearSearch"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                :title="$t('search.clear') || 'Clear search'"
            >
                <i class="bx bx-x text-xl"></i>
            </button>
        </div>

        <!-- Collapsed View (Icon Only) -->
        <button
            v-else
            @click="handleCollapsedClick"
            class="w-full flex items-center justify-center p-3 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-lg transition-colors group"
            :title="$t('search.placeholder') || 'Search'"
        >
            <i
                class="bx bx-search text-2xl text-gray-600 dark:text-slate-500 group-hover:text-[#F02C56] transition-colors"
            ></i>
        </button>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const props = defineProps({
    isMobile: {
        type: Boolean,
        default: false
    },
    isCollapsed: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['search'])

const router = useRouter()
const route = useRoute()
const searchQuery = ref('')
const isFocused = ref(false)

watch(
    () => route.query.q,
    (newQuery) => {
        searchQuery.value = newQuery || ''
    },
    { immediate: true }
)

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        emit('search', searchQuery.value.trim())
        isFocused.value = false
    }
}

const clearSearch = () => {
    searchQuery.value = ''
    if (route.path === '/search') {
        router.push('/search')
    }
}

const handleBlur = () => {
    setTimeout(() => {
        isFocused.value = false
    }, 200)
}

const handleCollapsedClick = () => {
    router.push('/search')
}
</script>
