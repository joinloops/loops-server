<template>
    <section v-if="enabled" class="my-8">
        <h2 class="tracking-tight font-light mb-2 dark:text-gray-300">
            {{ t('settings.collab.title') }}
        </h2>
        <label class="block text-sm font-light text-gray-700 dark:text-gray-300 mb-3">
            {{ t('settings.collab.whoCanInvite') }}
        </label>
        <div
            class="bg-white dark:bg-gray-900 lg:rounded-xl lg:border border-gray-200 dark:border-gray-800 p-1 lg:p-6"
        >
            <div class="space-y-2 grid lg:grid-cols-2 lg:gap-x-4">
                <label
                    v-for="option in options"
                    :key="option.value"
                    class="flex items-center gap-3 py-2 px-4 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors border-2"
                    :class="
                        state === option.value
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                            : 'bg-gray-50 dark:bg-gray-800 border-transparent'
                    "
                >
                    <input
                        v-model="state"
                        type="radio"
                        :value="option.value"
                        class="mt-0.5 w-4 h-4 text-blue-600 focus:ring-blue-500"
                    />
                    <div class="flex-1">
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ option.label }}
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                            {{ option.description }}
                        </div>
                    </div>
                </label>
            </div>

            <div class="mt-6 px-3 lg:px-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ t('settings.collab.trustedTitle') }}
                </h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 mb-3">
                    {{ t('settings.collab.trustedHelp') }}
                </p>

                <p v-if="!trusted.length" class="text-xs text-gray-500 dark:text-gray-500 italic">
                    {{ t('settings.collab.trustedEmpty') }}
                </p>

                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li
                        v-for="account in trusted"
                        :key="account.id"
                        class="flex items-center gap-3 py-2"
                    >
                        <Avatar :src="account.avatar" :width="32" />
                        <router-link :to="`/@${account.username}`" class="min-w-0 flex-1">
                            <div
                                class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                            >
                                {{ account.name || account.username }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                @{{ account.username }}
                            </div>
                        </router-link>
                        <button
                            type="button"
                            class="text-xs font-semibold text-red-600 hover:text-red-800 dark:text-red-400"
                            @click="untrust(account)"
                        >
                            {{ t('settings.collab.stopAutoApproving') }}
                        </button>
                    </li>
                </ul>

                <button
                    v-if="nextCursor"
                    type="button"
                    class="mt-2 text-xs font-medium text-blue-600 dark:text-blue-400"
                    @click="loadTrusted(nextCursor)"
                >
                    {{ t('settings.collab.showMore') }}
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import collabApi from '~/api/collab'

const { t } = useI18n()

const enabled = ref(false)
const state = ref(null)
const trusted = ref([])
const nextCursor = ref(null)
let hydrated = false

const options = computed(() => [
    { value: 'off', label: t('settings.collab.off'), description: t('settings.collab.offHelp') },
    {
        value: 'following',
        label: t('settings.collab.following'),
        description: t('settings.collab.followingHelp')
    },
    {
        value: 'mutuals',
        label: t('settings.collab.mutuals'),
        description: t('settings.collab.mutualsHelp')
    },
    {
        value: 'everyone',
        label: t('settings.collab.everyone'),
        description: t('settings.collab.everyoneHelp')
    }
])

watch(state, async (value) => {
    if (!hydrated || !value) return
    try {
        await collabApi.updateSettings(value)
    } catch {}
})

const loadTrusted = async (cursor = null) => {
    try {
        const res = await collabApi.trusted({ cursor: cursor || undefined })
        const rows = res.data?.data ?? []
        trusted.value = cursor ? [...trusted.value, ...rows] : rows
        nextCursor.value = res.data?.next_cursor ?? null
    } catch {}
}

const untrust = async (account) => {
    try {
        await collabApi.untrust(account.id)
        trusted.value = trusted.value.filter((row) => row.id !== account.id)
    } catch {}
}

onMounted(async () => {
    try {
        const res = await collabApi.settings()
        enabled.value = res.data?.data?.enabled !== false
        state.value = res.data?.data?.state ?? 'following'
    } catch {
        enabled.value = false
        return
    }

    // Let the initial value settle before the watcher starts saving.
    setTimeout(() => (hydrated = true), 0)

    await loadTrusted()
})
</script>
