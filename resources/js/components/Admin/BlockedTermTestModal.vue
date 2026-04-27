<template>
    <BaseModal v-model="show" title="Test Filter" max-width="md">
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Type a query to see whether the filter would block it. Useful for verifying new
                rules without leaving the dashboard.
            </p>

            <div>
                <input
                    v-model="query"
                    type="text"
                    placeholder="Try a search query..."
                    autofocus
                    spellcheck="false"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
            </div>

            <div
                v-if="result"
                :class="[
                    'rounded-md border p-3 text-sm',
                    result.blocked
                        ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-400/30 dark:bg-rose-900/20 dark:text-rose-300'
                        : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-900/20 dark:text-emerald-300'
                ]"
            >
                <div class="font-medium">
                    {{ result.blocked ? 'Blocked' : 'Allowed' }}
                </div>
                <div v-if="result.matched_term" class="mt-1 font-mono text-xs opacity-75">
                    Matched: "{{ result.matched_term }}" ({{ result.match_type }})
                </div>
                <div v-if="result.normalized" class="mt-1 font-mono text-xs opacity-75">
                    Normalized: {{ result.normalized }}
                </div>
            </div>

            <div v-else-if="testing" class="text-center text-sm text-gray-500">Testing...</div>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import BaseModal from '@/components/BaseModal.vue'
import { blockedTermsApi } from '@/services/adminApi'

const props = defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue'])

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const query = ref('')
const result = ref(null)
const testing = ref(false)
let timeout = null

watch(query, (val) => {
    if (timeout) clearTimeout(timeout)
    if (!val.trim()) {
        result.value = null
        return
    }
    testing.value = true
    timeout = setTimeout(async () => {
        try {
            const response = await blockedTermsApi.testTerm(val)
            result.value = response
        } finally {
            testing.value = false
        }
    }, 250)
})

watch(show, (open) => {
    if (open) {
        query.value = ''
        result.value = null
    }
})
</script>
