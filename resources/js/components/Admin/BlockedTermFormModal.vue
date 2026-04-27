<template>
    <BaseModal v-model="show" :title="isEditing ? 'Edit Term' : 'Add Term'" max-width="md">
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Term
                </label>
                <input
                    v-model="form.term"
                    type="text"
                    placeholder="e.g. badword"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Will be lowercased automatically. Matches with leetspeak normalization.
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Type
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        @click="form.type = 'block'"
                        :class="typeButtonClass('block')"
                    >
                        <ShieldExclamationIcon class="h-4 w-4" />
                        Block
                    </button>
                    <button
                        type="button"
                        @click="form.type = 'allow'"
                        :class="typeButtonClass('allow')"
                    >
                        <ShieldCheckIcon class="h-4 w-4" />
                        Allow
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Allow-list entries override the block list (handles "scunthorpe" cases).
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Note <span class="text-gray-400">(optional)</span>
                </label>
                <textarea
                    v-model="form.note"
                    rows="2"
                    placeholder="Why was this added?"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                ></textarea>
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
                        {{ isEditing ? 'Save Changes' : 'Add Term' }}
                    </div>
                </AnimatedButton>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ShieldExclamationIcon, ShieldCheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'

const props = defineProps({
    modelValue: Boolean,
    term: { type: Object, default: null },
    loading: Boolean
})
const emit = defineEmits(['update:modelValue', 'save'])

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const form = ref({ term: '', type: 'block', note: '' })
const isEditing = computed(() => !!props.term?.id)
const canSubmit = computed(() => form.value.term.trim().length > 0)

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            form.value = props.term
                ? { term: props.term.term, type: props.term.type, note: props.term.note || '' }
                : { term: '', type: 'block', note: '' }
        }
    }
)

const typeButtonClass = (type) => [
    'inline-flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition',
    form.value.type === type
        ? type === 'block'
            ? 'border-rose-500 bg-rose-50 text-rose-700 dark:border-rose-400 dark:bg-rose-900/20 dark:text-rose-400'
            : 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-900/20 dark:text-emerald-400'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
]

const submit = () => {
    if (!canSubmit.value) return
    emit('save', {
        term: form.value.term.trim().toLowerCase(),
        type: form.value.type,
        note: form.value.note.trim() || null
    })
}
</script>
