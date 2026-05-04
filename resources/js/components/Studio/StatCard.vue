<template>
    <div
        class="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-2xl p-4 sm:p-5"
    >
        <div class="flex items-center justify-between mb-2 sm:mb-3">
            <div
                :class="[
                    'w-8 h-8 sm:w-9 sm:h-9 rounded-lg flex items-center justify-center',
                    accentBg
                ]"
            >
                <component :is="icon" :class="['w-4 h-4', accentText]" />
            </div>
            <div
                v-if="change !== null && !loading"
                :class="[
                    'flex items-center gap-1 px-1.5 sm:px-2 py-0.5 rounded-md',
                    change > 0
                        ? 'bg-emerald-50 dark:bg-emerald-500/10'
                        : change < 0
                          ? 'bg-rose-50 dark:bg-rose-500/10'
                          : 'bg-gray-100 dark:bg-gray-800'
                ]"
            >
                <ArrowUpIcon
                    v-if="change > 0"
                    class="w-3 h-3 text-emerald-600 dark:text-emerald-400"
                />
                <ArrowDownIcon
                    v-else-if="change < 0"
                    class="w-3 h-3 text-rose-600 dark:text-rose-400"
                />
                <span
                    :class="[
                        'text-xs font-semibold tabular-nums',
                        change > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : change < 0
                              ? 'text-rose-600 dark:text-rose-400'
                              : 'text-gray-500 dark:text-gray-400'
                    ]"
                >
                    {{ Math.abs(change) }}%
                </span>
            </div>
        </div>
        <p
            class="text-[10px] sm:text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider truncate"
        >
            {{ label }}
        </p>
        <div class="mt-1">
            <span
                v-if="loading"
                class="inline-block w-16 h-7 rounded bg-gray-100 dark:bg-gray-800 animate-pulse"
            />
            <span
                v-else
                class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white tabular-nums"
            >
                {{ formatCount(value) }}
            </span>
            <p
                v-if="change !== null"
                class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-0.5"
            >
                vs prev. {{ range }}d
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/solid'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    label: String,
    value: Number,
    change: { type: Number, default: null },
    icon: { type: [Object, Function], required: true },
    accent: { type: String, default: 'indigo' },
    loading: Boolean,
    range: { type: Number, default: 30 }
})

const { formatCount } = useUtils()

const accentMap = {
    indigo: {
        bg: 'bg-indigo-100 dark:bg-indigo-500/10',
        text: 'text-indigo-600 dark:text-indigo-400'
    },
    emerald: {
        bg: 'bg-emerald-100 dark:bg-emerald-500/10',
        text: 'text-emerald-600 dark:text-emerald-400'
    },
    rose: { bg: 'bg-rose-100 dark:bg-rose-500/10', text: 'text-rose-600 dark:text-rose-400' },
    purple: {
        bg: 'bg-purple-100 dark:bg-purple-500/10',
        text: 'text-purple-600 dark:text-purple-400'
    },
    amber: { bg: 'bg-amber-100 dark:bg-amber-500/10', text: 'text-amber-600 dark:text-amber-400' },
    cyan: { bg: 'bg-cyan-100 dark:bg-cyan-500/10', text: 'text-cyan-600 dark:text-cyan-400' }
}

const accentBg = computed(() => accentMap[props.accent].bg)
const accentText = computed(() => accentMap[props.accent].text)
</script>
