<template>
    <div>
        <div class="flex items-start justify-between gap-4">
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ t('studio.collab.addCollaborator') }}
                </span>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ t('studio.collab.addCollaboratorHelp') }}
                </p>
            </div>
        </div>

        <div v-if="modelValue" class="mt-3">
            <div
                class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-3 py-2.5"
            >
                <Avatar :src="modelValue.avatar" :width="36" />
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                        {{ modelValue.name || modelValue.username }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        @{{ modelValue.username }}
                    </div>
                </div>
                <button
                    type="button"
                    class="p-1.5 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    :aria-label="t('studio.collab.removeCollaborator')"
                    @click="clear"
                >
                    <XMarkIcon class="w-4 h-4 text-gray-500" />
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{
                    modelValue.auto_approve
                        ? t('studio.collab.autoApproveNotice', { username: modelValue.username })
                        : t('studio.collab.pendingNotice', { username: modelValue.username })
                }}
            </p>
        </div>

        <div v-else class="relative mt-3">
            <MagnifyingGlassIcon
                class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
            />
            <input
                v-model="query"
                type="text"
                autocomplete="off"
                :placeholder="t('studio.collab.searchPlaceholder')"
                class="w-full pl-9 pr-3 py-2.5 bg-[#FAFAFA] dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-[#F02C56] focus:ring-2 focus:ring-[#F02C56]/15 transition-all"
                @input="onInput"
                @focus="open = results.length > 0"
                @blur="onBlur"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="selectHighlighted"
                @keydown.escape="open = false"
            />

            <div
                v-if="open"
                class="absolute z-20 top-full left-0 right-0 mt-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-xl overflow-auto max-h-[280px]"
            >
                <div v-if="loading" class="px-3 py-3 text-sm text-gray-500">
                    {{ t('studio.collab.searching') }}
                </div>
                <template v-else-if="results.length">
                    <button
                        v-for="(account, idx) in results"
                        :key="account.id"
                        type="button"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors border-t first:border-t-0 border-gray-50 dark:border-gray-800"
                        :class="
                            highlighted === idx
                                ? 'bg-[#F02C56]/10'
                                : 'hover:bg-gray-50 dark:hover:bg-gray-800/60'
                        "
                        @mousedown.prevent="select(account)"
                    >
                        <Avatar :src="account.avatar" :width="32" />
                        <div class="min-w-0 flex-1">
                            <div
                                class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                            >
                                {{ account.name || account.username }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                @{{ account.username }}
                            </div>
                        </div>
                        <span
                            v-if="account.auto_approve"
                            class="shrink-0 rounded-full bg-green-100 dark:bg-green-900/40 px-2 py-0.5 text-[10px] font-semibold text-green-800 dark:text-green-200"
                        >
                            {{ t('studio.collab.autoApproves') }}
                        </span>
                    </button>
                </template>
                <div v-else class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('studio.collab.noResults') }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import collabApi from '~/api/collab'

defineProps({
    modelValue: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const query = ref('')
const results = ref([])
const loading = ref(false)
const open = ref(false)
const highlighted = ref(-1)
let timer = null
let lastRequest = 0

const onInput = () => {
    clearTimeout(timer)
    const q = query.value.trim().replace(/^@/, '')

    if (!q) {
        results.value = []
        open.value = false
        return
    }

    loading.value = true
    open.value = true
    timer = setTimeout(() => search(q), 250)
}

const search = async (q) => {
    const requestId = ++lastRequest

    try {
        const res = await collabApi.search(q)
        if (requestId !== lastRequest) return
        results.value = res.data?.data ?? []
        highlighted.value = results.value.length ? 0 : -1
    } catch (e) {
        if (requestId === lastRequest) results.value = []
    } finally {
        if (requestId === lastRequest) loading.value = false
    }
}

const move = (dir) => {
    if (!results.value.length) return
    const n = results.value.length
    highlighted.value = (highlighted.value + dir + n) % n
}

const selectHighlighted = () => {
    if (highlighted.value >= 0 && results.value[highlighted.value]) {
        select(results.value[highlighted.value])
    }
}

const select = (account) => {
    emit('update:modelValue', account)
    query.value = ''
    results.value = []
    open.value = false
}

const clear = () => emit('update:modelValue', null)

const onBlur = () => {
    setTimeout(() => (open.value = false), 120)
}
</script>
