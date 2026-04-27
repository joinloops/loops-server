<template>
    <BaseModal v-model="show" title="Bulk Import Terms" max-width="lg">
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Type
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="type = 'block'" :class="typeButtonClass('block')">
                        Block list
                    </button>
                    <button type="button" @click="type = 'allow'" :class="typeButtonClass('allow')">
                        Allow list
                    </button>
                </div>
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Terms <span class="text-gray-400">(one per line)</span>
                    </label>
                    <span
                        class="text-xs tabular-nums"
                        :class="
                            atCap
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-gray-500 dark:text-gray-400'
                        "
                    >
                        {{ termCount.toLocaleString() }} / {{ MAX_TERMS.toLocaleString() }}
                    </span>
                </div>
                <textarea
                    v-model="raw"
                    rows="10"
                    placeholder="word1&#10;word2&#10;word3"
                    spellcheck="false"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                ></textarea>
                <p
                    v-if="parsed.duplicates || parsed.tooLong || parsed.truncated"
                    class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                >
                    Skipped:
                    <span v-if="parsed.duplicates"
                        >{{ parsed.duplicates }} duplicate{{
                            parsed.duplicates === 1 ? '' : 's'
                        }}</span
                    >
                    <span v-if="parsed.duplicates && parsed.tooLong">, </span>
                    <span v-if="parsed.tooLong">{{ parsed.tooLong }} over 120 chars</span>
                    <span v-if="(parsed.duplicates || parsed.tooLong) && parsed.truncated">, </span>
                    <span v-if="parsed.truncated"
                        >{{ parsed.truncated }} beyond {{ MAX_TERMS.toLocaleString() }}-term
                        cap</span
                    >
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Note <span class="text-gray-400">(optional, applied to all)</span>
                </label>
                <input
                    v-model="note"
                    type="text"
                    placeholder="e.g. Imported from LDNOOBW list"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <AnimatedButton @click="show = false" variant="light" pill>Cancel</AnimatedButton>
                <AnimatedButton
                    @click="submit"
                    :disabled="!canSubmit || loading"
                    variant="primaryGradient"
                    pill
                >
                    <div class="flex items-center gap-2">
                        <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                        Import {{ termCount.toLocaleString() }} Term{{ termCount === 1 ? '' : 's' }}
                    </div>
                </AnimatedButton>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '../AnimatedButton.vue'

const props = defineProps({ modelValue: Boolean, loading: Boolean })
const emit = defineEmits(['update:modelValue', 'import'])

const MAX_TERMS = 1000
const MAX_LENGTH = 120

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const raw = ref('')
const type = ref('block')
const note = ref('')

const parsed = computed(() => {
    const seen = new Set()
    const terms = []
    let duplicates = 0
    let tooLong = 0
    let truncated = 0

    const lines = raw.value.split('\n')
    for (const line of lines) {
        const t = line.trim().toLowerCase()
        if (!t) continue
        if (t.length > MAX_LENGTH) {
            tooLong++
            continue
        }
        if (seen.has(t)) {
            duplicates++
            continue
        }
        if (terms.length >= MAX_TERMS) {
            truncated++
            continue
        }
        seen.add(t)
        terms.push(t)
    }

    return { terms, duplicates, tooLong, truncated }
})

const termCount = computed(() => parsed.value.terms.length)
const atCap = computed(() => termCount.value >= MAX_TERMS)
const canSubmit = computed(() => termCount.value > 0)

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            raw.value = ''
            type.value = 'block'
            note.value = ''
        }
    }
)

const typeButtonClass = (t) => [
    'rounded-md border px-3 py-2 text-sm font-medium transition',
    type.value === t
        ? t === 'block'
            ? 'border-rose-500 bg-rose-50 text-rose-700 dark:border-rose-400 dark:bg-rose-900/20 dark:text-rose-400'
            : 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-900/20 dark:text-emerald-400'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
]

const submit = () => {
    if (!canSubmit.value) return
    emit('import', {
        terms: parsed.value.terms,
        type: type.value,
        note: note.value.trim() || null
    })
}
</script>
