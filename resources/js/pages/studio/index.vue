<template>
    <StudioLayout>
        <div class="bg-gray-50 dark:bg-gray-950">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-8 space-y-4 sm:space-y-6">
                <div
                    class="relative overflow-hidden border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-2xl"
                >
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-purple-500/5 dark:from-indigo-500/10 dark:to-purple-500/10 pointer-events-none"
                    ></div>
                    <div
                        class="relative p-4 sm:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-5"
                    >
                        <div class="flex items-center gap-4 sm:gap-5 min-w-0">
                            <div class="relative flex-shrink-0">
                                <div
                                    class="absolute -inset-1 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full opacity-20 blur-sm"
                                ></div>
                                <img
                                    :src="user.avatar"
                                    class="relative w-16 h-16 sm:w-20 sm:h-20 rounded-full border-2 border-white dark:border-gray-800 shadow-sm object-cover"
                                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5 sm:gap-2 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h1
                                        class="text-base sm:text-xl font-bold text-gray-900 dark:text-white tracking-tight truncate"
                                    >
                                        {{ user.username }}
                                    </h1>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                                    >
                                        Creator
                                    </span>
                                </div>
                                <div class="flex items-center gap-x-3 gap-y-1 flex-wrap">
                                    <div
                                        v-for="stat in userStats"
                                        :key="stat.label"
                                        class="flex items-center gap-1.5"
                                    >
                                        <span
                                            class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white tabular-nums"
                                        >
                                            {{ formatCount(stat.value) }}
                                        </span>
                                        <span
                                            class="text-xs sm:text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ stat.label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-800/60 rounded-xl self-start md:self-auto"
                        >
                            <button
                                v-for="opt in rangeOptions"
                                :key="opt.value"
                                @click="setRange(opt.value)"
                                :class="[
                                    'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-150 tabular-nums',
                                    range === opt.value
                                        ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <StatCard
                        v-for="card in statCards"
                        :key="card.key"
                        :label="card.label"
                        :value="card.value"
                        :change="card.change"
                        :icon="card.icon"
                        :accent="card.accent"
                        :loading="summaryLoading"
                        :range="range"
                    />
                </div>

                <div
                    class="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden"
                >
                    <div
                        class="px-4 sm:px-6 pt-4 sm:pt-5 border-b border-gray-100 dark:border-gray-800"
                    >
                        <div class="flex items-end justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ activeTab.label }}
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Last {{ range }} days
                                </p>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span
                                    class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white tabular-nums"
                                >
                                    {{ activeSeriesTotal.toLocaleString() }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    total
                                </span>
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-1 mt-3 sm:mt-4 -mx-1 overflow-x-auto scrollbar-none"
                        >
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="setActiveTab(tab.key)"
                                :class="[
                                    'relative px-3 sm:px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors duration-150 flex-shrink-0',
                                    activeTabKey === tab.key
                                        ? 'text-gray-900 dark:text-white'
                                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                <span class="flex items-center gap-1.5 sm:gap-2">
                                    <component :is="tab.icon" class="w-4 h-4" />
                                    {{ tab.label }}
                                </span>
                                <span
                                    v-if="activeTabKey === tab.key"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 rounded-t-full"
                                    :style="{ backgroundColor: tab.color }"
                                ></span>
                            </button>
                        </div>
                    </div>
                    <div class="p-3 sm:p-6 relative">
                        <div ref="chartRef" class="w-full h-56 sm:h-72"></div>
                        <Transition
                            enter-active-class="transition-opacity duration-150"
                            leave-active-class="transition-opacity duration-150"
                            enter-from-class="opacity-0"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="seriesLoading"
                                class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-gray-900/70 backdrop-blur-sm"
                            >
                                <div class="text-xs text-gray-400 dark:text-gray-600 animate-pulse">
                                    Loading…
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                    <div
                        class="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden"
                    >
                        <div
                            class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between"
                        >
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Recent Posts
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ summary?.total_posts?.toLocaleString() ?? 0 }} total posts
                                </p>
                            </div>
                            <a
                                href="/studio/posts"
                                class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 flex-shrink-0 ml-3"
                            >
                                View all →
                            </a>
                        </div>
                        <div
                            v-if="summaryLoading"
                            class="grid grid-cols-3 gap-2 sm:gap-3 p-4 sm:p-6"
                        >
                            <div
                                v-for="i in 6"
                                :key="i"
                                class="aspect-[9/16] rounded-lg bg-gray-100 dark:bg-gray-800 animate-pulse"
                            ></div>
                        </div>
                        <div
                            v-else-if="!summary?.recent_posts?.length"
                            class="px-4 sm:px-6 py-12 text-center"
                        >
                            <VideoCameraIcon
                                class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3"
                            />
                            <p class="text-sm text-gray-500 dark:text-gray-400">No posts yet</p>
                        </div>
                        <div v-else class="grid grid-cols-3 gap-2 sm:gap-3 p-4 sm:p-6">
                            <a
                                v-for="post in summary.recent_posts"
                                :key="post.id"
                                :href="`/v/${post.hid}`"
                                class="group relative aspect-[9/16] rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                            >
                                <img
                                    v-if="post.media?.thumbnail"
                                    :src="post.media.thumbnail"
                                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                />
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-black/0"
                                ></div>
                                <div
                                    class="absolute top-1.5 right-1.5 sm:top-2 sm:right-2 px-1.5 py-0.5 rounded bg-black/60 backdrop-blur-sm"
                                >
                                    <span
                                        class="text-[10px] sm:text-xs font-medium text-white tabular-nums"
                                    >
                                        {{ formatDuration(post.media?.duration) }}
                                    </span>
                                </div>
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-1.5 sm:p-2 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-1">
                                        <PlaySolidIcon class="w-3 h-3 text-white" />
                                        <span
                                            class="text-[10px] sm:text-xs font-semibold text-white tabular-nums"
                                        >
                                            {{ formatCount(post.views) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <HeartSolidIcon class="w-3 h-3 text-white" />
                                        <span
                                            class="text-[10px] sm:text-xs font-semibold text-white tabular-nums"
                                        >
                                            {{ formatCount(post.likes) }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div
                        class="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden flex flex-col"
                    >
                        <div
                            class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between"
                        >
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Profile Links
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ totalLinkClicks.toLocaleString() }} total clicks
                                </p>
                            </div>
                        </div>
                        <div v-if="summaryLoading" class="px-4 sm:px-6 py-4 space-y-3">
                            <div
                                v-for="i in 3"
                                :key="i"
                                class="h-12 rounded-lg bg-gray-100 dark:bg-gray-800 animate-pulse"
                            ></div>
                        </div>
                        <div
                            v-else-if="!profileLinks.length"
                            class="px-4 sm:px-6 py-12 text-center flex-1 flex flex-col items-center justify-center"
                        >
                            <LinkIcon class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No profile links added yet
                            </p>
                        </div>
                        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800 flex-1">
                            <li
                                v-for="link in profileLinks"
                                :key="link.id"
                                class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-150"
                            >
                                <a
                                    :href="link.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center gap-3 px-4 sm:px-6 py-3 sm:py-3.5"
                                >
                                    <div
                                        class="flex-shrink-0 w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center group-hover:bg-red-100 dark:group-hover:bg-red-500/10 transition-colors"
                                    >
                                        <LinkIcon
                                            class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-sm font-medium text-gray-900 dark:text-white truncate group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors"
                                        >
                                            {{ getHostname(link.url) }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5"
                                        >
                                            {{ link.url }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                                        <div
                                            class="hidden sm:block w-16 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden"
                                        >
                                            <div
                                                class="h-full rounded-full bg-red-500 dark:bg-red-400 transition-all duration-300"
                                                :style="{
                                                    width: linkPercentage(link.clicks) + '%'
                                                }"
                                            ></div>
                                        </div>
                                        <span
                                            class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums tabular-nums"
                                        >
                                            {{ link.clicks.toLocaleString() }}
                                        </span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="h-50"></div>
            </div>
        </div>
    </StudioLayout>
</template>

<script setup>
import {
    ref,
    reactive,
    computed,
    nextTick,
    watch,
    inject,
    onMounted,
    onBeforeUnmount,
    h
} from 'vue'
import * as echarts from 'echarts'
import { useUtils } from '@/composables/useUtils'
import { useApiClient } from '@/composables/useApiClient'
import {
    ArrowUpIcon,
    ArrowDownIcon,
    EyeIcon,
    HeartIcon,
    ChatBubbleOvalLeftIcon,
    ArrowPathRoundedSquareIcon,
    BookmarkIcon,
    UserPlusIcon,
    LinkIcon,
    VideoCameraIcon,
    PlayIcon
} from '@heroicons/vue/24/outline'
import { HeartIcon as HeartSolidIcon, PlayIcon as PlaySolidIcon } from '@heroicons/vue/24/solid'

const authStore = inject('authStore')
const { formatCount } = useUtils()
const api = useApiClient()

const user = computed(() => authStore.getUser)
const userStats = computed(() => [
    { label: 'Posts', value: user.value.post_count },
    { label: 'Likes', value: user.value.likes_count },
    { label: 'Followers', value: user.value.follower_count },
    { label: 'Following', value: user.value.following_count }
])

const range = ref(30)
const rangeOptions = [
    { label: '7d', value: 7 },
    { label: '30d', value: 30 },
    { label: '60d', value: 60 }
]

const summary = ref(null)
const summaryLoading = ref(true)

const seriesCache = reactive({})
const seriesLoading = ref(false)

const activeTabKey = ref('views')

const tabs = [
    {
        key: 'views',
        label: 'Video Views',
        icon: EyeIcon,
        color: '#6366f1',
        endpoint: '/api/v1/studio/analytics/views',
        valueKey: 'views'
    },
    {
        key: 'followers',
        label: 'Followers',
        icon: UserPlusIcon,
        color: '#10b981',
        endpoint: '/api/v1/studio/analytics/followers',
        valueKey: 'count'
    },
    {
        key: 'likes',
        label: 'Likes',
        icon: HeartIcon,
        color: '#f43f5e',
        endpoint: '/api/v1/studio/analytics/likes',
        valueKey: 'count'
    },
    {
        key: 'comments',
        label: 'Comments',
        icon: ChatBubbleOvalLeftIcon,
        color: '#f59e0b',
        endpoint: '/api/v1/studio/analytics/comments',
        valueKey: 'count'
    },
    {
        key: 'shares',
        label: 'Shares',
        icon: ArrowPathRoundedSquareIcon,
        color: '#06b6d4',
        endpoint: '/api/v1/studio/analytics/shares',
        valueKey: 'count'
    }
]

const activeTab = computed(() => tabs.find((t) => t.key === activeTabKey.value))
const cacheKey = (metric, r) => `${metric}:${r}`
const activeSeries = computed(() => seriesCache[cacheKey(activeTabKey.value, range.value)] ?? null)
const activeSeriesTotal = computed(() => activeSeries.value?.total ?? 0)

const statCards = computed(() => [
    {
        key: 'views',
        label: 'Post Views',
        value: summary.value?.views.total ?? 0,
        change: summary.value?.views.change_pct ?? 0,
        icon: EyeIcon,
        accent: 'indigo'
    },
    {
        key: 'followers',
        label: 'Net Followers',
        value: summary.value?.followers.total ?? 0,
        change: summary.value?.followers.change_pct ?? 0,
        icon: UserPlusIcon,
        accent: 'emerald'
    },
    {
        key: 'likes',
        label: 'Likes',
        value: summary.value?.likes.total ?? 0,
        change: summary.value?.likes.change_pct ?? 0,
        icon: HeartIcon,
        accent: 'rose'
    },
    {
        key: 'posts',
        label: 'Total Posts',
        value: summary.value?.total_posts ?? 0,
        change: null,
        icon: VideoCameraIcon,
        accent: 'purple'
    }
])

const profileLinks = computed(() => summary.value?.top_links ?? [])
const totalLinkClicks = computed(() => profileLinks.value.reduce((a, b) => a + b.clicks, 0))

function linkPercentage(clicks) {
    if (!totalLinkClicks.value) return 0
    return Math.round((clicks / totalLinkClicks.value) * 100)
}

function getHostname(url) {
    try {
        return new URL(url).hostname.replace(/^www\./, '')
    } catch {
        return url
    }
}

function formatDuration(seconds) {
    if (!seconds) return '0:00'
    const m = Math.floor(seconds / 60)
    const s = Math.floor(seconds % 60)
    return `${m}:${s.toString().padStart(2, '0')}`
}

async function fetchSummary() {
    summaryLoading.value = true
    try {
        const { data } = await api.get('/api/v1/studio/analytics/summary', {
            params: { range: range.value }
        })
        summary.value = data
    } catch (e) {
        console.error('Failed to fetch summary:', e)
    } finally {
        summaryLoading.value = false
    }
}

async function fetchSeries(tabKey) {
    const tab = tabs.find((t) => t.key === tabKey)
    if (!tab) return

    const key = cacheKey(tabKey, range.value)
    if (seriesCache[key]) return

    seriesLoading.value = true
    try {
        const { data } = await api.get(tab.endpoint, {
            params: { range: range.value }
        })
        seriesCache[key] = {
            data: data.data.map((d) => ({
                date: d.date,
                value: d[tab.valueKey] ?? 0
            })),
            total: data.total ?? 0
        }
    } catch (e) {
        console.error(`Failed to fetch ${tabKey}:`, e)
    } finally {
        seriesLoading.value = false
    }
}

function setRange(newRange) {
    if (range.value === newRange) return
    range.value = newRange
    fetchSummary()
    fetchSeries(activeTabKey.value)
}

function setActiveTab(key) {
    activeTabKey.value = key
    fetchSeries(key)
}

const chartRef = ref(null)
let chart = null

const isDark = ref(
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
)
const darkModeObserver = ref(null)

function chartColors() {
    const dark = isDark.value
    return {
        textColor: dark ? '#9ca3af' : '#6b7280',
        borderColor: dark ? '#1f2937' : '#f3f4f6',
        bg: dark ? '#1f2937' : '#ffffff',
        tooltipBorder: dark ? '#374151' : '#e5e7eb',
        tooltipText: dark ? '#f3f4f6' : '#111827'
    }
}

function formatDateLabel(dateStr) {
    const d = new Date(dateStr + 'T00:00:00')
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function buildChartOption() {
    const series = activeSeries.value
    if (!series) return null
    const colors = chartColors()
    const accent = activeTab.value.color
    const dates = series.data.map((d) => formatDateLabel(d.date))
    const values = series.data.map((d) => d.value)

    const hexToRgba = (hex, a) => {
        const n = parseInt(hex.slice(1), 16)
        return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`
    }

    const labelInterval = range.value <= 7 ? 0 : range.value <= 30 ? 4 : 8

    return {
        grid: { top: 10, right: 12, bottom: 24, left: 40 },
        tooltip: {
            trigger: 'axis',
            backgroundColor: colors.bg,
            borderColor: colors.tooltipBorder,
            textStyle: { color: colors.tooltipText, fontSize: 12 },
            axisPointer: { type: 'cross', crossStyle: { color: colors.textColor } }
        },
        xAxis: {
            type: 'category',
            data: dates,
            boundaryGap: false,
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: colors.textColor, fontSize: 10, interval: labelInterval },
            splitLine: { show: false }
        },
        yAxis: {
            type: 'value',
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: colors.textColor, fontSize: 10 },
            splitLine: { lineStyle: { color: colors.borderColor, type: 'dashed' } }
        },
        series: [
            {
                type: 'line',
                data: values,
                smooth: 0.4,
                symbol: 'none',
                lineStyle: { color: accent, width: 2.5 },
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: hexToRgba(accent, isDark.value ? 0.3 : 0.15) },
                        { offset: 1, color: hexToRgba(accent, 0) }
                    ])
                }
            }
        ]
    }
}

function renderChart() {
    if (!chartRef.value) return
    const opt = buildChartOption()
    if (!opt) return

    if (!chart) {
        chart = echarts.init(chartRef.value)
    }
    chart.setOption(opt, true)
}

function handleResize() {
    chart?.resize()
}

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
    darkModeObserver.value?.disconnect()
    chart?.dispose()
    chart = null
})

onMounted(async () => {
    await fetchSummary()
    await fetchSeries(activeTabKey.value)
    await nextTick()
    renderChart()

    window.addEventListener('resize', handleResize)

    darkModeObserver.value = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark')
    })
    darkModeObserver.value.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    })
})

watch([activeSeries, isDark], async () => {
    await nextTick()
    renderChart()
})
</script>
