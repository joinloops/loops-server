<template>
    <div
        class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-200 dark:border-gray-700"
    >
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    {{ title }}
                </h3>
                <div class="flex items-center space-x-3">
                    <label
                        v-if="showLocalFilter"
                        class="flex items-center space-x-2 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        <input
                            v-model="localFilter"
                            type="checkbox"
                            class="w-4 h-6 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            @change="handleLocalChange"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300"> Local Only </span>
                    </label>

                    <div v-if="sortOptions && sortOptions.length > 0" class="relative">
                        <select
                            v-model="selectedSort"
                            class="pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none"
                            @change="handleSortChange"
                        >
                            <option value="">Sort by...</option>
                            <option
                                v-for="option in sortOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.name }}
                            </option>
                        </select>
                        <ChevronDownIcon
                            class="absolute right-2 top-2.5 h-5 w-5 text-gray-400 pointer-events-none"
                        />
                    </div>

                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search..."
                            class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            @input="$emit('search', searchQuery)"
                        />
                        <MagnifyingGlassIcon
                            class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
                        />
                    </div>

                    <button
                        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        @click="$emit('refresh')"
                    >
                        <ArrowPathIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                        >
                            {{ column.label }}
                        </th>
                        <th
                            v-if="hasActions"
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody
                    class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                >
                    <template v-if="loading">
                        <tr v-for="i in 5" :key="i">
                            <td v-for="column in columns" :key="column.key" class="px-6 py-4">
                                <div
                                    class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"
                                ></div>
                            </td>
                            <td v-if="hasActions" class="px-6 py-4">
                                <div
                                    class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse ml-auto"
                                ></div>
                            </td>
                        </tr>
                    </template>
                    <tr
                        v-for="item in data"
                        v-else
                        :key="item.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :item="item"
                                :value="item[column.key]"
                            >
                                {{ item[column.key] }}
                            </slot>
                        </td>
                        <td
                            v-if="hasActions"
                            class="px-6 py-4 whitespace-nowrap text-right text-sm"
                        >
                            <slot name="actions" :item="item">
                                <button
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3"
                                >
                                    View
                                </button>
                                <button
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    Delete
                                </button>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ data.length }} results
                </p>
                <div class="flex space-x-2">
                    <button
                        :disabled="!hasPrevious"
                        :class="[
                            'px-3 py-1 text-sm border rounded',
                            hasPrevious
                                ? 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                : 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                        ]"
                        @click="$emit('previous')"
                    >
                        Previous
                    </button>
                    <button
                        :disabled="!hasNext"
                        :class="[
                            'px-3 py-1 text-sm border rounded',
                            hasNext
                                ? 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                : 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                        ]"
                        @click="$emit('next')"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { MagnifyingGlassIcon, ArrowPathIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    title: String,
    columns: Array,
    data: Array,
    loading: Boolean,
    hasPrevious: Boolean,
    hasNext: Boolean,
    hasActions: {
        type: Boolean,
        default: true
    },
    sortOptions: {
        type: Array,
        default: () => []
    },
    initialSearchQuery: {
        type: String,
        default: ''
    },
    showLocalFilter: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['search', 'refresh', 'previous', 'next', 'sort', 'localChange'])

const searchQuery = ref(props.initialSearchQuery)
const selectedSort = ref('')
const localFilter = ref(false)

watch(
    () => props.initialSearchQuery,
    (newValue) => {
        searchQuery.value = newValue || ''
    }
)

const handleSortChange = () => {
    emit('sort', selectedSort.value)
}

const handleLocalChange = () => {
    emit('localChange', localFilter.value)
}
</script>
