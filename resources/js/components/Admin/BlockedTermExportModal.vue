<template>
    <BaseModal v-model="show" title="Bulk Export Terms" max-width="lg">
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    What to export
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="toggleType('all')" :class="typeButtonClass('all')">
                        <div class="text-sm font-medium">All terms</div>
                        <div class="mt-0.5 text-xs opacity-75">
                            {{ formatNumber(counts?.total ?? 0) }}
                        </div>
                    </button>
                    <button @click="toggleType('block')" :class="typeButtonClass('block', 'rose')">
                        <div class="text-sm font-medium">Blocked</div>
                        <div class="mt-0.5 text-xs opacity-75">
                            {{ formatNumber(counts?.block ?? 0) }}
                        </div>
                    </button>
                    <button
                        @click="toggleType('allow')"
                        :class="typeButtonClass('allow', 'emerald')"
                    >
                        <div class="text-sm font-medium">Allowed</div>
                        <div class="mt-0.5 text-xs opacity-75">
                            {{ formatNumber(counts?.allow ?? 0) }}
                        </div>
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Format
                </label>
                <div
                    class="grid gap-2"
                    :class="[form.type === 'all' ? 'grid-cols-2' : 'grid-cols-3']"
                >
                    <button
                        v-if="form.type !== 'all'"
                        @click="form.format = 'list'"
                        :class="formatButtonClass('list')"
                    >
                        <div class="flex items-center justify-center gap-2">
                            <ListBulletIcon class="h-4 w-4" />
                            <span>List</span>
                        </div>
                    </button>
                    <button @click="form.format = 'csv'" :class="formatButtonClass('csv')">
                        <div class="flex items-center justify-center gap-2">
                            <DocumentTextIcon class="h-4 w-4" />
                            <span>CSV</span>
                        </div>
                    </button>
                    <button @click="form.format = 'json'" :class="formatButtonClass('json')">
                        <div class="flex items-center justify-center gap-2">
                            <CodeBracketIcon class="h-4 w-4" />
                            <span>JSON</span>
                        </div>
                    </button>
                </div>
            </div>

            <div
                class="rounded-md bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800/50 dark:text-gray-400"
            >
                <span v-if="form.format === 'csv'">
                    CSV includes columns:
                    <span class="font-mono">term, type, note, created_at</span>. Best for
                    spreadsheets.
                </span>
                <span v-else-if="form.format === 'json'">
                    JSON preserves full metadata and is ideal for re-import or migration to another
                    instance.
                </span>
                <span v-else-if="form.format === 'list'">
                    List format provides a term only export that can be easily imported into Loops
                    again.
                </span>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <AnimatedButton @click="show = false" variant="light" pill> Cancel </AnimatedButton>

                <AnimatedButton
                    @click="submit"
                    :disabled="!canSubmit || loading"
                    variant="primaryGradient"
                    pill
                >
                    <div class="flex items-center gap-2">
                        <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                        <ArrowDownTrayIcon v-else class="h-4 w-4" />
                        Export {{ formatNumber(exportCount) }} Term{{
                            exportCount === 1 ? '' : 's'
                        }}
                    </div>
                </AnimatedButton>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
    ArrowPathIcon,
    ArrowDownTrayIcon,
    DocumentTextIcon,
    CodeBracketIcon,
    ListBulletIcon
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '../AnimatedButton.vue'
import { useUtils } from '@/composables/useUtils'

const { formatNumber } = useUtils()

const props = defineProps({
    modelValue: Boolean,
    loading: Boolean,
    counts: { type: Object, default: () => ({ total: 0, block: 0, allow: 0 }) }
})
const emit = defineEmits(['update:modelValue', 'export'])

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const form = ref({ type: 'all', format: 'csv' })

const exportCount = computed(() => {
    if (form.value.type === 'all') return props.counts?.total ?? 0
    if (form.value.type === 'block') return props.counts?.block ?? 0
    if (form.value.type === 'allow') return props.counts?.allow ?? 0
    return 0
})

const toggleType = (type) => {
    form.value.type = type
    if (type != 'all') {
        form.value.format = 'list'
    } else {
        form.value.format = 'csv'
    }
}

const canSubmit = computed(() => exportCount.value > 0)

watch(
    () => props.modelValue,
    (open) => {
        if (open) form.value = { type: 'all', format: 'csv' }
    }
)

const typeButtonClass = (type, accent = 'blue') => {
    const accents = {
        blue: 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-900/20 dark:text-blue-400',
        rose: 'border-rose-500 bg-rose-50 text-rose-700 dark:border-rose-400 dark:bg-rose-900/20 dark:text-rose-400',
        emerald:
            'border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-900/20 dark:text-emerald-400'
    }
    return [
        'rounded-md border px-3 py-3 transition',
        form.value.type === type
            ? accents[accent]
            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
    ]
}

const formatButtonClass = (format) => [
    'rounded-md border px-3 py-2 text-sm font-medium transition',
    form.value.format === format
        ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-900/20 dark:text-blue-400'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
]

const submit = () => {
    if (!canSubmit.value) return
    emit('export', { type: form.value.type, format: form.value.format })
}
</script>
