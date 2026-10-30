<template>
    <div v-if="collab" class="mt-1.5 min-w-0">
        <p class="flex min-w-0 items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
            <i class="bx bx-group shrink-0 text-gray-400"></i>
            <span class="truncate">
                {{ t('studio.collab.withUser', { username: collab.account?.username || 'user' }) }}
                <span class="text-gray-400 dark:text-gray-500">· {{ stateText }}</span>
            </span>
        </p>

        <div v-if="actions.length" class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="action in actions"
                :key="action.key"
                type="button"
                :disabled="busy"
                class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition-colors disabled:opacity-50"
                :class="
                    action.danger
                        ? 'border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40'
                        : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'
                "
                @click="action.run"
            >
                {{ action.label }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAlertModal } from '@/composables/useAlertModal.js'
import collabApi from '~/api/collab'

const props = defineProps({
    post: { type: Object, required: true }
})

const emit = defineEmits(['changed'])
const { t } = useI18n()
const { alertModal, confirmModal } = useAlertModal()
const busy = ref(false)

const collab = computed(() => props.post?.collab || null)
const username = computed(() => collab.value?.account?.username || 'user')

const stateText = computed(() => {
    switch (collab.value?.state) {
        case 'pending':
            return t('studio.collab.statePending')
        case 'approved':
            return t('studio.collab.stateApproved')
        case 'declined':
            return t('studio.collab.stateDeclined')
        case 'expired':
            return t('studio.collab.stateExpired')
        default:
            return ''
    }
})

const errorText = (error) =>
    error?.response?.data?.errors
        ? Object.values(error.response.data.errors)[0]?.[0]
        : error?.response?.data?.message || t('studio.collab.genericError')

const run = async (fn) => {
    busy.value = true
    try {
        await fn()
        emit('changed')
    } catch (error) {
        await alertModal(t('studio.collab.actionFailed'), `<p>${errorText(error)}</p>`)
    } finally {
        busy.value = false
    }
}

const postSolo = async () => {
    const ok = await confirmModal(
        t('studio.collab.postSoloTitle'),
        `<p class="text-gray-700">${t('studio.collab.postSoloBody', { username: username.value })}</p>`,
        t('studio.collab.postSolo'),
        t('common.cancel')
    )
    if (ok) await run(() => collabApi.removeFromVideo(props.post.id))
}

const discard = async () => {
    const ok = await confirmModal(
        t('studio.collab.discardTitle'),
        `<p class="text-gray-700">${t('studio.collab.discardBody')}</p>`,
        t('common.delete'),
        t('common.cancel')
    )
    if (ok) await run(() => collabApi.discard(props.post.id))
}

const removeCoauthor = async () => {
    const ok = await confirmModal(
        t('studio.collab.removeTitle'),
        `<p class="text-gray-700">${t('studio.collab.removeBody', { username: username.value })}</p>`,
        t('studio.collab.removeCollaborator'),
        t('common.cancel')
    )
    if (ok) await run(() => collabApi.removeFromVideo(props.post.id))
}

const actions = computed(() => {
    const state = collab.value?.state

    if (!state) return []

    if (props.post.status === 'published' && state === 'approved') {
        return [
            { key: 'remove', label: t('studio.collab.removeCollaborator'), run: removeCoauthor }
        ]
    }

    if (state === 'pending') {
        return [{ key: 'solo', label: t('studio.collab.postSolo'), run: postSolo }]
    }

    if (['declined', 'expired'].includes(state)) {
        return [
            { key: 'solo', label: t('studio.collab.postSolo'), run: postSolo },
            { key: 'discard', label: t('common.delete'), run: discard, danger: true }
        ]
    }

    return []
})
</script>
