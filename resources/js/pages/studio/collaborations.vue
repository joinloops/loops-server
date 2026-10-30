<template>
    <StudioLayout>
        <div class="w-full min-h-screen bg-gray-50 dark:bg-gray-950">
            <div class="container max-w-4xl mx-auto p-4 sm:p-8">
                <div class="mb-6 sm:mb-8">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('studio.collab.inboxTitle') }}
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ t('studio.collab.inboxSubtitle') }}
                    </p>
                </div>

                <div class="mb-5 inline-flex rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            filter === tab.value
                                ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                        "
                        @click="setFilter(tab.value)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div v-if="loading && !items.length" class="space-y-3">
                    <div
                        v-for="n in 2"
                        :key="n"
                        class="h-40 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 animate-pulse"
                    ></div>
                </div>

                <div
                    v-else-if="!items.length"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center"
                >
                    <i class="bx bx-group text-5xl text-gray-300 dark:text-gray-600"></i>
                    <h3 class="mt-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ t('studio.collab.inboxEmpty') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ t('studio.collab.inboxEmptyHelp') }}
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        :id="`collab-${item.id}`"
                        class="flex flex-col sm:flex-row gap-4 rounded-xl border bg-white dark:bg-gray-800 p-4"
                        :class="
                            highlightId === item.id
                                ? 'border-[#F02C56]'
                                : 'border-gray-200 dark:border-gray-700'
                        "
                    >
                        <div
                            class="relative w-full sm:w-36 shrink-0 aspect-[9/16] max-h-80 sm:max-h-none overflow-hidden rounded-lg bg-black"
                        >
                            <video
                                v-if="item.video?.src_url"
                                :src="item.video.src_url"
                                :poster="item.video.thumbnail"
                                class="h-full w-full object-cover"
                                controls
                                playsinline
                                preload="metadata"
                            ></video>
                            <img
                                v-else
                                :src="item.video?.thumbnail"
                                class="h-full w-full object-cover opacity-70"
                                alt=""
                            />
                            <span
                                v-if="item.video && !item.video.is_ready"
                                class="absolute inset-x-2 bottom-2 rounded-md bg-black/70 px-2 py-1 text-center text-[11px] text-white"
                            >
                                {{ t('studio.collab.stillProcessing') }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1 flex flex-col">
                            <router-link
                                :to="`/@${item.inviter.username}`"
                                class="flex items-center gap-3 min-w-0"
                            >
                                <Avatar :src="item.inviter.avatar" :width="40" />
                                <div class="min-w-0">
                                    <div
                                        class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"
                                    >
                                        {{ item.inviter.name || item.inviter.username }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        @{{ item.inviter.username }}
                                    </div>
                                </div>
                            </router-link>

                            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ t('studio.collab.invitedYou') }}
                            </p>
                            <p
                                v-if="item.video?.caption"
                                class="mt-2 text-sm text-gray-900 dark:text-gray-100 line-clamp-3 break-words"
                            >
                                “{{ item.video.caption }}”
                            </p>
                            <p
                                v-if="item.video?.scheduled_at"
                                class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    t('studio.collab.scheduledFor', {
                                        date: formatDate(item.video.scheduled_at)
                                    })
                                }}
                            </p>

                            <div class="mt-auto pt-4">
                                <template
                                    v-if="item.state === 'pending' || item.state === 'expired'"
                                >
                                    <label
                                        class="mb-3 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 cursor-pointer"
                                    >
                                        <input
                                            v-model="trustChoice[item.id]"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-[#F02C56] focus:ring-[#F02C56]"
                                        />
                                        {{
                                            t('studio.collab.alwaysAllow', {
                                                username: item.inviter.username
                                            })
                                        }}
                                    </label>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            :disabled="busyId === item.id"
                                            class="rounded-full bg-[#F02C56] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#d5264c] disabled:opacity-50"
                                            @click="accept(item)"
                                        >
                                            {{
                                                busyId === item.id
                                                    ? t('common.accepting')
                                                    : t('studio.collab.accept')
                                            }}
                                        </button>
                                        <button
                                            v-if="item.state === 'pending'"
                                            type="button"
                                            :disabled="busyId === item.id"
                                            class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 disabled:opacity-50"
                                            @click="decline(item)"
                                        >
                                            {{ t('common.decline') }}
                                        </button>
                                    </div>
                                    <p
                                        v-if="item.state === 'expired'"
                                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('studio.collab.expiredHelp') }}
                                    </p>
                                </template>

                                <template v-else-if="item.state === 'approved'">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200"
                                        >
                                            {{ t('studio.collab.stateApproved') }}
                                        </span>
                                        <router-link
                                            v-if="item.video?.url"
                                            :to="viewPath(item)"
                                            class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                        >
                                            {{ t('studio.view') }}
                                        </router-link>
                                        <button
                                            type="button"
                                            :disabled="busyId === item.id"
                                            class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 disabled:opacity-50"
                                            @click="leave(item)"
                                        >
                                            {{ t('studio.collab.leave') }}
                                        </button>
                                    </div>
                                </template>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    {{ t('studio.collab.stateDeclined') }}
                                </span>
                            </div>
                        </div>
                    </article>

                    <div v-if="nextCursor" class="text-center">
                        <button
                            type="button"
                            :disabled="loading"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                            @click="load(nextCursor)"
                        >
                            {{ t('studio.collab.loadMore') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </StudioLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'
import collabApi from '~/api/collab'

const { t } = useI18n()
const route = useRoute()
const { alertModal, confirmModal } = useAlertModal()
const { formatDate } = useUtils()

const items = ref([])
const loading = ref(false)
const nextCursor = ref(null)
const busyId = ref(null)
const filter = ref(route.query.tab === 'all' ? 'all' : 'pending')
const trustChoice = reactive({})
const highlightId = computed(() => (route.query.id ? String(route.query.id) : null))

const tabs = computed(() => [
    { value: 'pending', label: t('studio.collab.tabPending') },
    { value: 'all', label: t('studio.collab.tabAll') }
])

const errorHtml = (error) => {
    const errors = error?.response?.data?.errors
    const message = errors
        ? Object.values(errors)[0]?.[0]
        : error?.response?.data?.message || t('studio.collab.genericError')

    return `<p class="text-gray-700">${message}</p>`
}

const viewPath = (item) => {
    try {
        return new URL(item.video.url).pathname
    } catch {
        return item.video.url
    }
}

const load = async (cursor = null) => {
    loading.value = true
    try {
        const res = await collabApi.requests({ state: filter.value, cursor: cursor || undefined })
        const rows = res.data?.data ?? []
        items.value = cursor ? [...items.value, ...rows] : rows
        nextCursor.value = res.data?.meta?.next_cursor ?? null
    } catch (error) {
        await alertModal(t('studio.collab.actionFailed'), errorHtml(error))
    } finally {
        loading.value = false
    }
}

const setFilter = (value) => {
    if (filter.value === value) return
    filter.value = value
    items.value = []
    load()
}

// Keep answered invites in place so the result stays visible.
const replace = (updated) => {
    items.value = items.value.map((row) => (row.id === updated.id ? updated : row))
}

const accept = async (item) => {
    busyId.value = item.id
    try {
        const res = await collabApi.accept(item.id, !!trustChoice[item.id])
        replace(res.data?.data ?? { ...item, state: 'approved' })
    } catch (error) {
        await alertModal(t('studio.collab.actionFailed'), errorHtml(error))
    } finally {
        busyId.value = null
    }
}

const decline = async (item) => {
    const ok = await confirmModal(
        t('studio.collab.declineTitle'),
        `<p class="text-gray-700">${t('studio.collab.declineBody', { username: item.inviter.username })}</p>`,
        t('common.decline'),
        t('common.cancel')
    )
    if (!ok) return

    busyId.value = item.id
    try {
        const res = await collabApi.decline(item.id)
        replace(res.data?.data ?? { ...item, state: 'declined' })
    } catch (error) {
        await alertModal(t('studio.collab.actionFailed'), errorHtml(error))
    } finally {
        busyId.value = null
    }
}

const leave = async (item) => {
    const ok = await confirmModal(
        t('studio.collab.leaveTitle'),
        `<p class="text-gray-700">${t('studio.collab.leaveBody', { username: item.inviter.username })}</p>`,
        t('studio.collab.leave'),
        t('common.cancel')
    )
    if (!ok) return

    busyId.value = item.id
    try {
        await collabApi.leave(item.id)
        items.value = items.value.filter((row) => row.id !== item.id)
    } catch (error) {
        await alertModal(t('studio.collab.actionFailed'), errorHtml(error))
    } finally {
        busyId.value = null
    }
}

onMounted(() => load())
</script>
