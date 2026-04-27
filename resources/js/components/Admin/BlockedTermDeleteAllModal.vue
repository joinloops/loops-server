<template>
    <BaseModal v-model="show" title="Delete All Terms" max-width="lg">
        <div class="space-y-4">
            <div
                class="flex gap-3 rounded-md border border-rose-200 bg-rose-50 p-3 dark:border-rose-800/40 dark:bg-rose-900/20"
            >
                <ExclamationTriangleIcon
                    class="h-5 w-5 flex-shrink-0 text-rose-600 dark:text-rose-400"
                />
                <div class="text-sm text-rose-700 dark:text-rose-300">
                    <p class="font-medium">This action cannot be undone.</p>
                    <p class="mt-1 text-xs">
                        Selected terms will be permanently removed from the moderation list and the
                        change takes effect immediately for all users. Consider exporting first.
                    </p>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    What to delete
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="form.type = 'block'" :class="typeButtonClass('block')">
                        <div class="text-sm font-medium">Blocked</div>
                        <div class="mt-0.5 text-xs opacity-75">
                            {{ formatNumber(counts?.block ?? 0) }}
                        </div>
                    </button>
                    <button @click="form.type = 'allow'" :class="typeButtonClass('allow')">
                        <div class="text-sm font-medium">Allowed</div>
                        <div class="mt-0.5 text-xs opacity-75">
                            {{ formatNumber(counts?.allow ?? 0) }}
                        </div>
                    </button>
                    <button @click="form.type = 'all'" :class="typeButtonClass('all')">
                        <div class="text-sm font-medium">All terms</div>
                        <div class="mt-0.5 text-xs opacity-75">
                            {{ formatNumber(counts?.total ?? 0) }}
                        </div>
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Type
                    <span class="font-mono font-bold text-rose-600 dark:text-rose-400">DELETE</span>
                    to confirm
                </label>
                <input
                    v-model="form.confirmation"
                    type="text"
                    placeholder="DELETE"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-sm focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <AnimatedButton @click="show = false" variant="light" pill> Cancel </AnimatedButton>

                <button
                    @click="submit"
                    :disabled="!canSubmit || loading"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-rose-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-rose-500 dark:hover:bg-rose-600"
                >
                    <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                    <TrashIcon v-else class="h-4 w-4" />
                    Delete {{ formatNumber(deleteCount) }} Term{{ deleteCount === 1 ? '' : 's' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ArrowPathIcon, TrashIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '../AnimatedButton.vue'
import { useUtils } from '@/composables/useUtils'

const { formatNumber } = useUtils()

const props = defineProps({
    modelValue: Boolean,
    loading: Boolean,
    counts: { type: Object, default: () => ({ total: 0, block: 0, allow: 0 }) }
})
const emit = defineEmits(['update:modelValue', 'confirm'])

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const form = ref({ type: 'block', confirmation: '' })

const deleteCount = computed(() => {
    if (form.value.type === 'all') return props.counts?.total ?? 0
    if (form.value.type === 'block') return props.counts?.block ?? 0
    if (form.value.type === 'allow') return props.counts?.allow ?? 0
    return 0
})

const canSubmit = computed(
    () => deleteCount.value > 0 && form.value.confirmation.trim() === 'DELETE'
)

watch(
    () => props.modelValue,
    (open) => {
        if (open) form.value = { type: 'block', confirmation: '' }
    }
)

const typeButtonClass = (type) => [
    'rounded-md border px-3 py-3 transition',
    form.value.type === type
        ? 'border-rose-500 bg-rose-50 text-rose-700 dark:border-rose-400 dark:bg-rose-900/20 dark:text-rose-400'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
]

const submit = () => {
    if (!canSubmit.value) return
    emit('confirm', { type: form.value.type })
}
</script>
