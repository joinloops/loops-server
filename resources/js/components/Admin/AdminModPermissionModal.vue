<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="mod-permission-title"
        >
            <div class="flex min-h-screen items-center justify-center px-4 py-8 text-center">
                <div
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm dark:bg-black/70"
                    @click="handleClose"
                />

                <div
                    class="relative w-full max-w-md rounded-3xl border border-gray-200 bg-white p-6 text-left shadow-2xl dark:border-gray-800 dark:bg-gray-900"
                >
                    <div
                        :class="[
                            'mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full',
                            enabling
                                ? 'bg-amber-100 dark:bg-amber-900/30'
                                : 'bg-gray-100 dark:bg-gray-800'
                        ]"
                    >
                        <component
                            :is="permission?.icon || ExclamationTriangleIcon"
                            :class="[
                                'h-6 w-6',
                                enabling
                                    ? 'text-amber-600 dark:text-amber-400'
                                    : 'text-gray-500 dark:text-gray-400'
                            ]"
                        />
                    </div>

                    <h3
                        id="mod-permission-title"
                        class="text-center text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ enabling ? 'Enable' : 'Disable' }} {{ permission?.label }}
                    </h3>

                    <p class="mt-2 text-center text-sm leading-6 text-gray-600 dark:text-gray-400">
                        {{ description }}
                    </p>

                    <div
                        v-if="enabling"
                        class="mt-5 rounded-2xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40"
                    >
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="applyToExisting"
                                type="checkbox"
                                :disabled="saving"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-[#F02C56] focus:ring-[#F02C56]/60 disabled:cursor-not-allowed dark:border-gray-600 dark:bg-gray-900"
                            />
                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Apply to existing videos
                                </span>
                                <span
                                    class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400"
                                >
                                    Backfill the {{ labelName }} label onto all
                                    <strong class="font-semibold">{{
                                        formatNumber(profile?.post_count || 0)
                                    }}</strong>
                                    existing videos by @{{ profile?.username }}. This runs in the
                                    background and cannot be undone automatically.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div
                        v-if="enabling && applyToExisting"
                        class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/40 dark:bg-amber-900/20"
                    >
                        <p class="text-xs leading-5 text-amber-800 dark:text-amber-300">
                            Existing videos will be updated with this label, and this action cannot
                            be undone in bulk. You will have to manually remove the label from each
                            video if you decide to revert the label.
                        </p>
                    </div>

                    <div class="mt-5 flex gap-3">
                        <button
                            type="button"
                            :disabled="saving"
                            class="flex-1 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="handleClose"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="saving"
                            :class="[
                                'inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium text-white transition disabled:cursor-not-allowed disabled:opacity-60',
                                enabling
                                    ? 'bg-[#F02C56] hover:opacity-90'
                                    : 'bg-gray-900 hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600'
                            ]"
                            @click="handleConfirm"
                        >
                            <ArrowPathIcon v-if="saving" class="h-4 w-4 animate-spin" />
                            <CheckIcon v-else class="h-4 w-4" />
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { ArrowPathIcon, CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'

const { formatNumber } = useUtils()

const props = defineProps({
    show: Boolean,
    permission: { type: Object, default: null },
    profile: { type: Object, default: null },
    enabling: Boolean,
    saving: Boolean
})

const emit = defineEmits(['close', 'confirm'])

const applyToExisting = ref(false)

const labelNames = {
    enforce_ai_label: 'AI',
    enforce_ad_label: 'Ad',
    enforce_nsfw_label: 'NSFW'
}

const labelName = computed(() => labelNames[props.permission?.key] || 'content')

const description = computed(() => {
    if (!props.permission) return ''
    const username = props.profile?.username || 'this account'
    if (props.enabling) {
        return `Every new video posted by @${username} will automatically be labelled as ${labelName.value}. The user will not be able to remove this label.`
    }
    return `New videos posted by @${username} will no longer be automatically labelled as ${labelName.value}. Existing labels will remain in place.`
})

const confirmLabel = computed(() => {
    if (props.saving) return 'Saving...'
    if (props.enabling && applyToExisting.value) return 'Enable and backfill'
    return props.enabling ? 'Enable' : 'Disable'
})

const handleClose = () => {
    if (props.saving) return
    emit('close')
}

const handleConfirm = () => {
    if (props.saving) return
    emit('confirm', {
        key: props.permission?.key,
        value: props.enabling,
        applyToExisting: props.enabling ? applyToExisting.value : false
    })
}

const handleKeydown = (event) => {
    if (event.key === 'Escape') handleClose()
}

watch(
    () => props.show,
    (open) => {
        if (open) {
            applyToExisting.value = false
            document.body.style.overflow = 'hidden'
            window.addEventListener('keydown', handleKeydown)
        } else {
            document.body.style.overflow = ''
            window.removeEventListener('keydown', handleKeydown)
        }
    }
)

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
})
</script>
