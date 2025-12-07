<template>
    <div
        class="sticky top-0 z-40 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:hidden py-4">
                <div class="relative">
                    <i
                        class="bx bx-search absolute left-4 top-1/2 -translate-y-1/2 text-xl text-gray-400"
                    ></i>
                    <input
                        ref="searchInputRef"
                        v-model="localQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        :placeholder="$t('nav.search')"
                        class="w-full pl-12 pr-12 py-3 bg-gray-100 dark:bg-gray-800 border-0 rounded-full text-sm focus:ring-2 focus:ring-[#F02C56] dark:text-white placeholder-gray-500"
                    />
                    <button
                        v-if="localQuery"
                        @click="clearSearch"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <i class="bx bx-x text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="flex space-x-4 lg:space-x-8 overflow-x-auto no-scrollbar">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    @click="$emit('selectTab', tab.value)"
                    class="relative pb-3 px-4 text-sm font-medium whitespace-nowrap transition-colors cursor-pointer"
                    :class="
                        activeTab === tab.value
                            ? 'text-gray-900 dark:text-white'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                    "
                >
                    {{ tab.label }}
                    <span
                        v-if="activeTab === tab.value"
                        class="absolute bottom-0 left-0 right-0 h-0.5 bg-gray-900 dark:bg-white"
                    ></span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
    query: {
        type: String,
        default: ''
    },
    activeTab: {
        type: String,
        default: 'top'
    },
    results: {
        type: Object,
        default: () => ({})
    },
    loading: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['updateQuery', 'selectTab'])

const localQuery = ref(props.query)
const searchInputRef = ref(null)

const tabs = [
    { value: 'top', label: 'Top' },
    { value: 'users', label: 'Users' },
    { value: 'videos', label: 'Videos' },
    { value: 'tags', label: 'Tags' }
]

const handleSearch = () => {
    emit('updateQuery', localQuery.value)
}

const clearSearch = () => {
    localQuery.value = ''
    emit('updateQuery', '')
}

watch(
    () => props.query,
    (newQuery) => {
        localQuery.value = newQuery
    }
)

onMounted(() => {
    // Auto-focus search input when component mounts
    if (searchInputRef.value && !props.query) {
        searchInputRef.value.focus()
    }
})
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
