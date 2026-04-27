<template>
    <Teleport to="body">
        <Transition name="klipy-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-label="Media picker"
                @click.self="emit('close')"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" />

                <Transition name="klipy-panel" appear>
                    <div
                        v-if="open"
                        class="relative flex w-full flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-black/5 rounded-t-2xl sm:w-[420px] sm:max-w-[92vw] sm:rounded-2xl sm:ring-1 dark:bg-zinc-900 dark:ring-white/10 h-[85vh] sm:h-[560px]"
                        @click.stop
                    >
                        <div class="flex justify-center pt-2 sm:hidden">
                            <div class="h-1.5 w-10 rounded-full bg-zinc-300 dark:bg-zinc-700" />
                        </div>

                        <div class="flex items-center justify-between px-3 pt-3 pb-2 sm:px-4">
                            <div class="flex gap-1 rounded-full bg-zinc-100 p-1 dark:bg-zinc-800">
                                <button
                                    v-for="tab in TABS"
                                    :key="tab.id"
                                    type="button"
                                    class="px-3.5 py-1.5 text-sm font-medium rounded-full transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                    :class="
                                        activeTab === tab.id
                                            ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white'
                                            : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200'
                                    "
                                    @click="toggleTab(tab)"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>

                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                aria-label="Close"
                                @click="emit('close')"
                            >
                                <XMarkIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="px-3 pb-3 sm:px-4">
                            <div class="relative">
                                <MagnifyingGlassIcon
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400 dark:text-zinc-500"
                                />
                                <input
                                    ref="searchInput"
                                    name="ksearch"
                                    v-model="query"
                                    type="search"
                                    :placeholder="searchPlaceholder"
                                    :disabled="isRateLimited"
                                    class="w-full rounded-full border-0 bg-zinc-100 py-2 pl-9 pr-24 text-sm text-zinc-900 placeholder-zinc-400 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                    autocomplete="off"
                                    spellcheck="false"
                                    @keydown.enter.prevent="submitSearch"
                                />

                                <div
                                    class="absolute right-1.5 top-1/2 flex -translate-y-1/2 items-center"
                                >
                                    <Transition name="klipy-hint" mode="out-in">
                                        <button
                                            v-if="query.length > 0"
                                            key="clear"
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded-full text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                            aria-label="Clear search"
                                            @click="clearQuery"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </Transition>
                                </div>
                            </div>
                        </div>

                        <div
                            ref="scrollArea"
                            class="flex-1 overflow-y-auto overscroll-contain px-3 pb-2 sm:px-4"
                        >
                            <Transition name="klipy-banner">
                                <div
                                    v-if="hasPendingSearch"
                                    class="sticky top-0 z-10 -mx-3 mb-2 flex items-center justify-between gap-3 border-b border-zinc-200 bg-white/85 px-3 pb-2 backdrop-blur sm:-mx-4 sm:px-4 dark:border-zinc-800 dark:bg-zinc-900/85"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-xs font-medium text-zinc-800 dark:text-zinc-200"
                                        >
                                            {{ $t('common.pressEnterToSearch') }}
                                            <span class="text-zinc-500 dark:text-zinc-500">{{
                                                $t('common.for')
                                            }}</span>
                                            <span class="text-zinc-900 dark:text-white"
                                                >"{{ query.trim() }}"</span
                                            >
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-blue-500 px-3 py-1 text-xs font-medium text-white hover:bg-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                        @click="submitSearch"
                                    >
                                        {{ $t('nav.search') }}
                                        <kbd
                                            class="rounded bg-white/20 px-1 font-mono text-[10px] leading-none"
                                            >↵</kbd
                                        >
                                    </button>
                                </div>
                            </Transition>

                            <Transition name="klipy-state" mode="out-in">
                                <div
                                    v-if="isRateLimited"
                                    key="ratelimit"
                                    class="flex h-full flex-col items-center justify-center gap-4 px-6 py-10 text-center"
                                >
                                    <div class="relative h-24 w-24">
                                        <svg viewBox="0 0 96 96" class="h-full w-full -rotate-90">
                                            <circle
                                                cx="48"
                                                cy="48"
                                                r="42"
                                                fill="none"
                                                class="stroke-zinc-200 dark:stroke-zinc-800"
                                                stroke-width="6"
                                            />
                                            <circle
                                                cx="48"
                                                cy="48"
                                                r="42"
                                                fill="none"
                                                class="stroke-red-500 transition-[stroke-dashoffset] duration-1000 ease-linear"
                                                stroke-width="6"
                                                stroke-linecap="round"
                                                :stroke-dasharray="ringCircumference"
                                                :stroke-dashoffset="ringOffset"
                                            />
                                        </svg>
                                        <div
                                            class="absolute inset-0 flex flex-col items-center justify-center"
                                        >
                                            <span
                                                class="text-2xl font-medium leading-none text-zinc-900 dark:text-white"
                                            >
                                                {{ rateLimitRemaining }}
                                            </span>
                                            <span
                                                class="mt-0.5 text-[11px] text-zinc-400 dark:text-zinc-500"
                                            >
                                                {{
                                                    rateLimitRemaining === 1 ? 'second' : 'seconds'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-medium text-red-500 dark:text-red-400"
                                        >
                                            {{ $t('common.slowDownAMoment') }}
                                        </p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">
                                            {{
                                                $t(
                                                    'common.youveHitTheSearchLimitWellTryAgainAutomatically'
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-else-if="error && items.length === 0"
                                    key="error"
                                    class="flex h-full flex-col items-center justify-center gap-3 py-10"
                                >
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ error }}
                                    </p>
                                    <button
                                        type="button"
                                        class="rounded-full bg-zinc-100 px-4 py-1.5 text-sm font-medium text-zinc-900 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                                        @click="retry"
                                    >
                                        {{ $t('common.tryAgain') }}
                                    </button>
                                </div>

                                <div
                                    v-else-if="!loading && items.length === 0 && isSearching"
                                    key="empty"
                                    class="flex h-full flex-col items-center justify-center gap-1 py-10"
                                >
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        {{ $t('nav.noResultsFound') }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-500">
                                        {{ $t('common.tryADifferentSearch') }}
                                    </p>
                                </div>

                                <div
                                    v-else-if="loading && items.length === 0"
                                    key="skeleton"
                                    class="columns-2 gap-2 sm:columns-3"
                                >
                                    <div
                                        v-for="n in 9"
                                        :key="n"
                                        class="mb-2 break-inside-avoid rounded-lg bg-zinc-100 dark:bg-zinc-800"
                                        :style="{ height: `${80 + ((n * 13) % 80)}px` }"
                                    />
                                </div>

                                <div v-else key="grid">
                                    <div class="columns-2 gap-2 sm:columns-3">
                                        <button
                                            v-for="item in items"
                                            :key="item.slug"
                                            type="button"
                                            class="group relative mb-2 block w-full overflow-hidden rounded-lg break-inside-avoid bg-zinc-100 dark:bg-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                            :style="
                                                item.blur_preview
                                                    ? {
                                                          backgroundImage: `url(${item.blur_preview})`,
                                                          backgroundSize: 'cover',
                                                          backgroundPosition: 'center'
                                                      }
                                                    : {}
                                            "
                                            @click="pick(item)"
                                        >
                                            <img
                                                :src="item.preview.url"
                                                :alt="item.title || ''"
                                                :width="item.preview.width || undefined"
                                                :height="item.preview.height || undefined"
                                                loading="lazy"
                                                class="block w-full opacity-0 transition-[opacity,transform] duration-300 group-hover:scale-[1.03]"
                                                @load="$event.target.classList.remove('opacity-0')"
                                            />
                                            <span
                                                v-if="item.is_ad"
                                                class="absolute right-1 top-1 rounded-sm bg-black/60 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white"
                                            >
                                                Ad
                                            </span>
                                        </button>

                                        <div ref="sentinel" class="h-4 w-full" />
                                    </div>

                                    <div
                                        v-if="loading && items.length > 0"
                                        class="flex items-center justify-center py-3"
                                        aria-label="Loading more"
                                    >
                                        <div
                                            class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-700 dark:border-zinc-700 dark:border-t-zinc-300"
                                        />
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <div
                            class="flex items-center justify-end border-t border-zinc-200 px-4 py-2 text-[11px] text-zinc-500 dark:border-zinc-800 dark:text-zinc-500"
                        >
                            <a
                                href="https://klipy.com"
                                target="_blank"
                                class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-300"
                            >
                                <img :src="attributionLogo" class="w-30" />
                            </a>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { XMarkIcon } from '@heroicons/vue/24/solid'
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, useTemplateRef } from 'vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    searchPlaceholder: { type: String, default: 'Search KLIPY' },
    locale: { type: String, default: null }
})

const api = useApiClient()

const emit = defineEmits(['close', 'select'])

const isDark = ref(document.documentElement.classList.contains('dark'))

const attributionLogo = computed(() => {
    return isDark.value ? '/img/klipy/powered-by-white.svg' : '/img/klipy/powered-by-black.svg'
})

const TABS = [
    { id: 'gifs', label: 'GIFs' },
    { id: 'stickers', label: 'Stickers' },
    { id: 'memes', label: 'Memes' },
    { id: 'clips', label: 'Clips' }
]

const activeTab = ref('gifs')
const query = ref('')
const submittedQuery = ref('')
const items = ref([])
const page = ref(1)
const hasNext = ref(false)
const loading = ref(false)
const error = ref(null)
const rateLimitResetAt = ref(null)
const rateLimitRemaining = ref(0)
const rateLimitTotal = ref(30)
let countdownTimer = null

const isRateLimited = computed(() => rateLimitRemaining.value > 0)

const searchInputRef = useTemplateRef('searchInput')
const sentinelRef = useTemplateRef('sentinel')
const scrollAreaRef = useTemplateRef('scrollArea')

let abortCtrl = null
let observer = null

const isSearching = computed(() => submittedQuery.value.trim().length > 0)

const hasPendingSearch = computed(() => {
    const trimmed = query.value.trim()
    return trimmed.length > 0 && trimmed !== submittedQuery.value
})

async function load({ append = false } = {}) {
    abortCtrl?.abort()
    abortCtrl = new AbortController()

    loading.value = true
    error.value = null

    const path = isSearching.value
        ? `/api/v1/klipy/${activeTab.value}/search`
        : `/api/v1/klipy/${activeTab.value}/trending`

    const params = new URLSearchParams({
        page: String(page.value),
        per_page: '50'
    })
    if (isSearching.value) params.set('q', submittedQuery.value)
    if (props.locale) params.set('locale', props.locale)

    try {
        const res = await fetch(`${path}?${params}`, {
            signal: abortCtrl.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })

        if (!res.ok) {
            if (res.status === 429) {
                const reset = parseInt(res.headers.get('x-ratelimit-reset') || '0', 10)
                const limit = parseInt(res.headers.get('x-ratelimit-limit') || '30', 10)
                const nowSec = Math.floor(Date.now() / 1000)
                const secs = Math.max(reset - nowSec, 1)

                rateLimitResetAt.value = reset
                rateLimitTotal.value = Math.max(secs, limit)
                rateLimitRemaining.value = secs
                startCountdown()

                error.value = null
            } else {
                error.value = "Couldn't load. Tap to retry."
            }
            return
        }

        const data = await res.json()
        items.value = append ? [...items.value, ...data.items] : data.items
        hasNext.value = !!data.has_next
    } catch (e) {
        if (e.name !== 'AbortError') error.value = 'Couldn\u2019t load. Tap to retry.'
    } finally {
        loading.value = false
    }
}

const ringCircumference = computed(() => 2 * Math.PI * 42)
const ringOffset = computed(() => {
    if (!rateLimitTotal.value) return 0
    const progress = rateLimitRemaining.value / rateLimitTotal.value
    return ringCircumference.value * (1 - progress)
})

const toggleTab = (tab) => {
    if (isRateLimited.value) {
        return
    }
    activeTab.value = tab.id
}

function reset() {
    page.value = 1
    items.value = []
    hasNext.value = false
}

function submitSearch() {
    if (isRateLimited.value) return
    const trimmed = query.value.trim()
    if (!trimmed || trimmed === submittedQuery.value) return
    submittedQuery.value = trimmed
    reset()
    load()
}

function clearQuery() {
    query.value = ''
    if (submittedQuery.value !== '') {
        submittedQuery.value = ''
        reset()
        load()
    }
    searchInputRef.value?.focus()
}

async function pick(item) {
    emit('select', { type: activeTab.value, item })
    emit('close')
}

function startCountdown() {
    stopCountdown()
    const tick = () => {
        if (!rateLimitResetAt.value) return
        const left = rateLimitResetAt.value - Math.floor(Date.now() / 1000)
        if (left <= 0) {
            rateLimitRemaining.value = 0
            rateLimitResetAt.value = null
            stopCountdown()
            load()
            return
        }
        rateLimitRemaining.value = left
    }
    tick()
    countdownTimer = setInterval(tick, 1000)
}

function stopCountdown() {
    if (countdownTimer) {
        clearInterval(countdownTimer)
        countdownTimer = null
    }
}

watch(activeTab, () => {
    submittedQuery.value = ''
    query.value = ''
    reset()
    load()
})

watch(query, (val) => {
    if (val === '' && submittedQuery.value !== '') {
        submittedQuery.value = ''
        reset()
        load()
    }
})

watch(
    () => props.open,
    async (val) => {
        if (val) {
            submittedQuery.value = ''
            query.value = ''
            reset()
            await nextTick()
            searchInputRef.value?.focus()
            load()
            setupInfiniteScroll()
        } else {
            abortCtrl?.abort()
            teardownInfiniteScroll()
        }
    }
)

function setupInfiniteScroll() {
    teardownInfiniteScroll()
    if (!sentinelRef.value || !scrollAreaRef.value) return
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting && hasNext.value && !loading.value) {
                page.value += 1
                load({ append: true })
            }
        },
        { root: scrollAreaRef.value, rootMargin: '200px' }
    )
    observer.observe(sentinelRef.value)
}

function teardownInfiniteScroll() {
    observer?.disconnect()
    observer = null
}

function onKeydown(e) {
    if (!props.open) return
    if (e.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown)
    abortCtrl?.abort()
    teardownInfiniteScroll()
})

function retry() {
    error.value = null
    load({ append: page.value > 1 })
}
</script>

<style scoped>
input[type='search']::-webkit-search-decoration,
input[type='search']::-webkit-search-cancel-button,
input[type='search']::-webkit-search-results-button,
input[type='search']::-webkit-search-results-decoration {
    -webkit-appearance: none;
    appearance: none;
}

.klipy-banner-enter-active,
.klipy-banner-leave-active {
    transition:
        opacity 180ms ease,
        transform 220ms cubic-bezier(0.32, 0.72, 0, 1);
}

.klipy-banner-enter-from,
.klipy-banner-leave-to {
    opacity: 0;
    transform: translateY(-100%);
}

.klipy-fade-enter-active,
.klipy-fade-leave-active {
    transition: opacity 200ms ease;
}

.klipy-fade-enter-from,
.klipy-fade-leave-to {
    opacity: 0;
}

.klipy-panel-enter-active {
    transition:
        transform 280ms cubic-bezier(0.32, 0.72, 0, 1),
        opacity 200ms ease;
}

.klipy-panel-leave-active {
    transition:
        transform 200ms ease,
        opacity 200ms ease;
}

.klipy-panel-enter-from,
.klipy-panel-leave-to {
    opacity: 0;
    transform: translateY(16px);
}

.klipy-state-enter-active,
.klipy-state-leave-active {
    transition:
        opacity 180ms ease,
        transform 220ms cubic-bezier(0.32, 0.72, 0, 1);
}

.klipy-state-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.klipy-state-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.klipy-hint-enter-active,
.klipy-hint-leave-active {
    transition:
        opacity 140ms ease,
        transform 160ms ease;
}

.klipy-hint-enter-from,
.klipy-hint-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

@media (min-width: 640px) {
    .klipy-panel-enter-from,
    .klipy-panel-leave-to {
        transform: translateY(8px) scale(0.98);
    }
}
</style>
