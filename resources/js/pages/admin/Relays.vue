<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Relays</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">
                A discovery firehose for the fediverse. Manage relay subscriptions to federate
                content across the network.
            </p>
        </div>

        <div
            class="relative mb-6 overflow-hidden rounded-xl border border-red-300/60 bg-gradient-to-br from-orange-600 to-pink-600 shadow-sm dark:border-red-500/30 dark:from-orange-600 dark:via-pink-700 dark:to-rose-700"
        >
            <svg
                class="pointer-events-none absolute -right-6 -top-10 h-48 w-48 text-white/10 opacity-50"
                viewBox="0 0 100 100"
                fill="none"
                aria-hidden="true"
            >
                <circle cx="50" cy="50" r="16" stroke="currentColor" stroke-width="1.5" />
                <circle cx="50" cy="50" r="28" stroke="currentColor" stroke-width="1.5" />
                <circle cx="50" cy="50" r="40" stroke="currentColor" stroke-width="1.5" />
                <circle cx="50" cy="50" r="4" fill="currentColor" />
            </svg>

            <div class="relative flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
                <div class="flex flex-1 items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-inset ring-white/25"
                    >
                        <svg
                            class="h-6 w-6 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.6"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M12 12h.008v.008H12V12Z"
                            />
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-white">
                                Loops Discovery Service
                            </h2>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 text-xs font-semibold text-white ring-1 ring-inset ring-white/25"
                            >
                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                Official
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-pink-100 md:max-w-[70%]">
                            Connect once and a steady stream of public videos starts flowing into
                            your instance, sourced from a trusted, large, and actively moderated
                            community.
                        </p>
                        <p class="mt-1.5 font-mono text-xs text-pink-200/80">
                            relay.loopsplatform.com
                        </p>
                    </div>
                </div>

                <div class="shrink-0 sm:pl-2">
                    <div
                        v-if="officialRelay && officialRelay.status === 'active'"
                        class="flex items-center gap-3 rounded-lg bg-white/15 px-4 py-2.5 ring-1 ring-inset ring-white/20"
                    >
                        <span class="flex h-2 w-2">
                            <span
                                class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-emerald-300 opacity-75"
                            ></span>
                            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                        </span>
                        <div class="text-sm">
                            <div class="font-semibold text-white">Connected</div>
                            <div class="text-xs text-pink-100">
                                {{ formatNumber(officialRelay.total_received) }} videos recieved
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="officialRelay"
                        class="rounded-lg bg-white/15 px-4 py-2.5 text-sm font-medium text-white ring-1 ring-inset ring-white/20"
                    >
                        {{
                            officialRelay.status === 'pending' ? 'Awaiting acceptance' : 'Disabled'
                        }}
                    </div>

                    <button
                        v-else
                        @click="joinOfficialRelay"
                        :disabled="joiningOfficial"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-white/70 bg-transparent px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-pink-600 disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto"
                    >
                        <svg
                            v-if="joiningOfficial"
                            class="h-4 w-4 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"
                            />
                        </svg>
                        {{ joiningOfficial ? 'Connecting…' : 'Connect' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="stats" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div
                v-for="item in statItems"
                :key="item.key"
                class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-center gap-2">
                    <span :class="['h-2 w-2 rounded-full', item.dot]"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ item.label }}</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ formatNumber(item.value) }}
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 p-6 dark:border-gray-700"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Relay subscriptions
                </h2>
                <AnimatedButton variant="primaryGradient" @click="openAddModal">
                    <div class="flex flex-row items-center gap-1">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M10 5a.75.75 0 0 1 .75.75v3.5h3.5a.75.75 0 0 1 0 1.5h-3.5v3.5a.75.75 0 0 1-1.5 0v-3.5h-3.5a.75.75 0 0 1 0-1.5h3.5v-3.5A.75.75 0 0 1 10 5Z"
                            />
                        </svg>
                        Add relay
                    </div>
                </AnimatedButton>
            </div>

            <div v-if="loading" class="p-6 text-center">
                <Spinner />
            </div>

            <div v-else-if="relays.length === 0" class="px-6 py-16 text-center">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700"
                >
                    <svg
                        class="h-6 w-6 text-gray-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.6"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M12 12h.008v.008H12V12Z"
                        />
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">
                    No relays yet
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Connect the official relay above, or add one manually to start federating.
                </p>
            </div>

            <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <div v-for="relay in relays" :key="relay.id" class="p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <div
                            class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 sm:flex"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.6"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M12 12h.008v.008H12V12Z"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3
                                    class="truncate text-base font-medium text-gray-900 dark:text-white"
                                >
                                    {{ relayName(relay) }}
                                </h3>

                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        statusMeta(relay.status).badge
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            statusMeta(relay.status).dot
                                        ]"
                                    ></span>
                                    {{ statusMeta(relay.status).label }}
                                </span>

                                <span
                                    v-if="isOfficial(relay)"
                                    class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                >
                                    Official
                                </span>

                                <span
                                    v-if="relay.relay_info?.name"
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    {{ relay.relay_info.name }}
                                </span>
                            </div>

                            <p
                                class="mt-1 truncate font-mono text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ relay.relay_url }}
                            </p>

                            <p
                                v-if="relay.relay_info?.summary"
                                class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ relay.relay_info.summary }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium',
                                        relay.send_public_posts
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                    ]"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    {{ relay.send_public_posts ? 'Sending' : 'Send off' }}
                                </span>

                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium',
                                        relay.receive_content
                                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                    ]"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    {{ relay.receive_content ? 'Receiving' : 'Receive off' }}
                                </span>

                                <span class="text-gray-400 dark:text-gray-600">·</span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ formatNumber(relay.total_sent) }} sent
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ formatNumber(relay.total_received) }} received
                                </span>
                                <template v-if="formatDate(relay.last_delivery_at)">
                                    <span class="text-gray-400 dark:text-gray-600">·</span>
                                    <span class="text-gray-500 dark:text-gray-400">
                                        Last sent {{ formatDate(relay.last_delivery_at) }}
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                v-if="relay.status === 'active'"
                                @click="disableRelay(relay)"
                                :disabled="isBusy(relay.id)"
                                class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Disable
                            </button>
                            <button
                                v-if="relay.status === 'disabled'"
                                @click="enableRelay(relay)"
                                :disabled="isBusy(relay.id)"
                                class="rounded-md px-3 py-1.5 text-sm font-medium text-emerald-700 transition-colors hover:bg-emerald-50 disabled:opacity-50 dark:text-emerald-400 dark:hover:bg-emerald-900/30"
                            >
                                Enable
                            </button>
                            <button
                                @click="relayToDelete = relay"
                                :disabled="isBusy(relay.id)"
                                class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-900/30"
                            >
                                Unsubscribe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="showAddModal = false"
            >
                <div
                    class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Add relay subscription
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Enter the relay's actor or endpoint URL.
                    </p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Relay URL</label
                            >
                            <input
                                v-model="newRelay.relay_url"
                                type="url"
                                placeholder="https://relay.example.com"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Name <span class="text-gray-400">(optional)</span></label
                            >
                            <input
                                v-model="newRelay.name"
                                type="text"
                                placeholder="My relay"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <label
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700"
                        >
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Send public posts
                            </span>
                            <span class="relative inline-flex shrink-0">
                                <input
                                    v-model="newRelay.send_public_posts"
                                    type="checkbox"
                                    class="peer sr-only"
                                />
                                <span
                                    class="h-6 w-11 rounded-full bg-gray-300 transition-colors peer-checked:bg-blue-600 dark:bg-gray-600"
                                ></span>
                                <span
                                    class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"
                                ></span>
                            </span>
                        </label>

                        <label
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700"
                        >
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Receive content
                            </span>
                            <span class="relative inline-flex shrink-0">
                                <input
                                    v-model="newRelay.receive_content"
                                    type="checkbox"
                                    class="peer sr-only"
                                />
                                <span
                                    class="h-6 w-11 rounded-full bg-gray-300 transition-colors peer-checked:bg-blue-600 dark:bg-gray-600"
                                ></span>
                                <span
                                    class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"
                                ></span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="showAddModal = false"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <AnimatedButton
                            @click="addRelay"
                            :disabled="!newRelay.relay_url || addingRelay"
                        >
                            {{ addingRelay ? 'Subscribing…' : 'Subscribe' }}
                        </AnimatedButton>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="relayToDelete"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="relayToDelete = null"
            >
                <div
                    class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40"
                    >
                        <svg
                            class="h-5 w-5 text-red-600 dark:text-red-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                            />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                        Unsubscribe from relay
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        You'll stop federating with
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{
                            relayName(relayToDelete)
                        }}</span
                        >. This can be re-added later.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="relayToDelete = null"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <AnimatedButton @click="deleteRelay" :disabled="deletingRelay">
                            {{ deletingRelay ? 'Removing…' : 'Unsubscribe' }}
                        </AnimatedButton>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="toast"
                class="fixed bottom-4 right-4 z-[60] flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
                :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900 dark:bg-gray-700'"
            >
                {{ toast.message }}
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import AnimatedButton from '@/components/AnimatedButton.vue'
import { ref, computed, onMounted } from 'vue'
import { relaysApi } from '~/services/adminApi'

const OFFICIAL_RELAY_URL = 'https://relay.loopsplatform.com'
const OFFICIAL_RELAY_HOST = 'relay.loopsplatform.com'

const relays = ref([])
const stats = ref(null)
const loading = ref(true)

const showAddModal = ref(false)
const addingRelay = ref(false)
const joiningOfficial = ref(false)
const relayToDelete = ref(null)
const deletingRelay = ref(false)
const busyIds = ref(new Set())
const toast = ref(null)

const newRelay = ref({
    relay_url: '',
    name: '',
    send_public_posts: true,
    receive_content: true
})

const hostOf = (url) => {
    try {
        return new URL(url).hostname
    } catch {
        return url
    }
}

const isOfficial = (relay) => hostOf(relay.relay_url) === OFFICIAL_RELAY_HOST

const officialRelay = computed(() => relays.value.find(isOfficial) || null)

const relayName = (relay) =>
    relay?.name || relay?.relay_info?.name || hostOf(relay?.relay_url || '')

const isBusy = (id) => busyIds.value.has(id)

const statItems = computed(() => {
    if (!stats.value) return []
    return [
        {
            key: 'total',
            label: 'Total relays',
            value: stats.value.total_relays,
            dot: 'bg-gray-400'
        },
        { key: 'active', label: 'Active', value: stats.value.active_relays, dot: 'bg-emerald-500' },
        {
            key: 'pending',
            label: 'Pending',
            value: stats.value.pending_relays,
            dot: 'bg-amber-500'
        },
        { key: 'sent', label: 'Posts sent', value: stats.value.total_sent, dot: 'bg-blue-500' },
        {
            key: 'received',
            label: 'Received',
            value: stats.value.total_received,
            dot: 'bg-violet-500'
        }
    ]
})

const STATUS_META = {
    active: {
        label: 'Active',
        badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        dot: 'bg-emerald-500'
    },
    pending: {
        label: 'Pending',
        badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        dot: 'bg-amber-500'
    },
    rejected: {
        label: 'Rejected',
        badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        dot: 'bg-red-500'
    },
    disabled: {
        label: 'Disabled',
        badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        dot: 'bg-gray-400'
    }
}

const statusMeta = (status) =>
    STATUS_META[status] || {
        label: status,
        badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        dot: 'bg-gray-400'
    }

const formatNumber = (value) => (value ?? 0).toLocaleString()

const formatDate = (dateString) => {
    if (!dateString) return null
    const date = new Date(dateString)
    const diff = Date.now() - date.getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 30) return `${days}d ago`
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

const showToast = (type, message) => {
    toast.value = { type, message }
    setTimeout(() => (toast.value = null), 4000)
}

const loadRelays = async () => {
    try {
        loading.value = true
        const response = await relaysApi.getRelays()
        relays.value = response.data.relays
    } catch (error) {
        console.error('Error loading relays:', error)
        showToast('error', 'Failed to load relays')
    } finally {
        loading.value = false
    }
}

const loadStats = async () => {
    try {
        const response = await relaysApi.getStats()
        stats.value = response.data.stats
    } catch (error) {
        console.error('Error loading stats:', error)
    }
}

const refresh = () => Promise.all([loadRelays(), loadStats()])

const openAddModal = () => {
    newRelay.value = {
        relay_url: '',
        name: '',
        send_public_posts: true,
        receive_content: true
    }
    showAddModal.value = true
}

const joinOfficialRelay = async () => {
    try {
        joiningOfficial.value = true
        await relaysApi.createRelay({
            relay_url: OFFICIAL_RELAY_URL + '/actor',
            name: 'Loops Official Relay',
            send_public_posts: true,
            receive_content: true
        })
        await refresh()
        showToast('success', 'Connected to the official relay')
    } catch (error) {
        await refresh()
        console.error('Error joining official relay:', error)
        showToast('error', error.response?.data?.message || 'Could not connect to the relay')
    } finally {
        joiningOfficial.value = false
    }
}

const addRelay = async () => {
    try {
        addingRelay.value = true
        await relaysApi.createRelay(newRelay.value)
        showAddModal.value = false
        await refresh()
        showToast('success', 'Relay subscription added')
    } catch (error) {
        console.error('Error adding relay:', error)
        await refresh()
        addingRelay.value = false
        showAddModal.value = false
        showToast('error', error.response?.data?.message || 'Failed to add relay')
    } finally {
        addingRelay.value = false
    }
}

const withBusy = async (id, fn, failMessage) => {
    busyIds.value.add(id)
    try {
        await fn()
        await refresh()
    } catch (error) {
        console.error(failMessage, error)
        showToast('error', failMessage)
    } finally {
        busyIds.value.delete(id)
    }
}

const enableRelay = (relay) =>
    withBusy(relay.id, () => relaysApi.enableRelay(relay.id), 'Failed to enable relay')

const disableRelay = (relay) =>
    withBusy(relay.id, () => relaysApi.disableRelay(relay.id), 'Failed to disable relay')

const deleteRelay = async () => {
    if (!relayToDelete.value) return
    const target = relayToDelete.value
    try {
        deletingRelay.value = true
        await relaysApi.deleteRelay(target.id)
        relayToDelete.value = null
        await refresh()
        showToast('success', `Unsubscribed from ${relayName(target)}`)
    } catch (error) {
        console.error('Error deleting relay:', error)
        showToast('error', 'Failed to unsubscribe')
    } finally {
        deletingRelay.value = false
    }
}

onMounted(refresh)
</script>
