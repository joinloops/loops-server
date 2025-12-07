<template>
    <div
        class="scroll-container"
        :class="{ 'mobile-navbar-padding': isMobileView }"
        style="scroll-snap-type: y mandatory"
        @scroll="handleScroll"
        ref="scrollContainerRef"
    >
        <div v-if="isLoading" class="snap-item">
            <component :is="loadingComponent" v-bind="loadingProps" />
        </div>

        <div v-else-if="error" class="snap-item">
            <component
                :is="errorComponent"
                :error="error"
                :retryAction="refetch"
                v-bind="errorProps"
            />
        </div>

        <template v-else>
            <template
                v-for="(group, groupIdx) in data?.pages"
                :key="group.meta?.next_cursor || groupIdx"
            >
                <div
                    v-for="(item, itemIdx) in group.data"
                    :key="getItemKey ? getItemKey(item) : item.id"
                    class="snap-item"
                    :data-index="getGlobalIndex(groupIdx, itemIdx)"
                >
                    <component
                        :is="itemComponent"
                        :ref="(el) => setItemRef(el, getGlobalIndex(groupIdx, itemIdx))"
                        v-bind="getItemProps(item, getGlobalIndex(groupIdx, itemIdx))"
                        @interaction="handleFirstInteraction"
                    />
                </div>
            </template>
        </template>

        <div v-if="hasNextPage && !isFetchingNextPage" class="snap-item">
            <component :is="loadingComponent" v-bind="loadingProps" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, shallowRef, markRaw, nextTick, watch } from 'vue'
import { useSnapScroll } from '~/composables/useSnapScroll'
import { useFeedInteraction } from '~/composables/useFeedInteraction'

const props = defineProps({
    feedData: {
        type: Object,
        required: true
    },

    itemComponent: {
        type: [Object, String],
        required: true
    },
    loadingComponent: {
        type: [Object, String],
        default: 'LoadingSpinner'
    },
    errorComponent: {
        type: [Object, String],
        default: 'ErrorDisplay'
    },

    getItemProps: {
        type: Function,
        required: true
    },
    loadingProps: {
        type: Object,
        default: () => ({})
    },
    errorProps: {
        type: Object,
        default: () => ({})
    },

    getItemKey: {
        type: Function,
        default: null
    },
    autoPlay: {
        type: Boolean,
        default: true
    },
    scrollThreshold: {
        type: Number,
        default: 1.5
    },
    snapSensitivity: {
        type: Number,
        default: 50
    },

    onItemVisible: {
        type: Function,
        default: null
    },
    onItemHidden: {
        type: Function,
        default: null
    }
})

const emit = defineEmits(['item-visible', 'item-hidden', 'interaction'])

const { data, error, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading, refetch } =
    props.feedData

const scrollContainerRef = ref(null)
const itemRefs = shallowRef({})
const currentItemIndex = ref(0)
const isChangingItem = ref(false)
const lastActionTime = ref(0)
const isMobileView = ref(false)

const scrollState = markRaw({
    isScrolling: false,
    scrollTimeout: null,
    lastScrollTop: 0,
    targetScrollTop: null,
    isAnimating: false
})

const totalItems = computed(() => {
    if (isLoading.value || !data.value?.pages) return 0
    return data.value.pages.reduce((acc, page) => acc + (page?.data?.length || 0), 0)
})

const { hasInteracted, requiresInteraction, handleFirstInteraction } = useFeedInteraction()

const handleItemChange = async (newIndex, oldIndex) => {
    if (newIndex === oldIndex || isChangingItem.value) return

    isChangingItem.value = true
    const now = Date.now()

    if (now - lastActionTime.value < 300) {
        isChangingItem.value = false
        return
    }

    lastActionTime.value = now

    try {
        if (oldIndex >= 0 && oldIndex < totalItems.value) {
            const oldItem = itemRefs.value[oldIndex]
            if (oldItem && typeof oldItem.pause === 'function') {
                oldItem.pause()
            }
            if (oldItem && typeof oldItem.cleanup === 'function') {
                oldItem.cleanup()
            }

            if (props.onItemHidden) {
                props.onItemHidden(oldIndex)
            }
            emit('item-hidden', oldIndex)
        }

        await new Promise((resolve) => setTimeout(resolve, 100))

        if (newIndex >= 0 && newIndex < totalItems.value) {
            const newItem = itemRefs.value[newIndex]

            if (newItem) {
                if (typeof newItem.onVisible === 'function') {
                    newItem.onVisible()
                }

                if (typeof newItem.preload === 'function') {
                    await newItem.preload()
                }

                if (props.autoPlay && typeof newItem.play === 'function') {
                    let playAttempts = 0
                    const maxAttempts = 3

                    while (playAttempts < maxAttempts) {
                        try {
                            await newItem.play()
                            break
                        } catch (error) {
                            playAttempts++
                            if (playAttempts >= maxAttempts) {
                                console.error(
                                    `Failed to play video ${newIndex} after ${maxAttempts} attempts:`,
                                    error
                                )
                            } else {
                                await new Promise((resolve) =>
                                    setTimeout(resolve, 100 * playAttempts)
                                )
                            }
                        }
                    }
                }

                if (props.onItemVisible) {
                    props.onItemVisible(newIndex)
                }
                emit('item-visible', newIndex)
            }
        }
    } finally {
        setTimeout(() => {
            isChangingItem.value = false
        }, 200)
    }
}

const {
    handleWheel,
    handleTouch,
    updateCurrentItem,
    enableSnapScroll,
    disableSnapScroll,
    scrollToIndex
} = useSnapScroll({
    containerRef: scrollContainerRef,
    totalItems: totalItems,
    currentIndex: currentItemIndex,
    sensitivity: props.snapSensitivity,
    onItemChange: handleItemChange
})

const getGlobalIndex = (pageIndex, itemIndex) => {
    let total = 0
    if (!data.value?.pages) return 0

    for (let i = 0; i < pageIndex; i++) {
        total += data.value.pages[i]?.data?.length || 0
    }
    return total + itemIndex
}

const setItemRef = (el, index) => {
    if (el) {
        itemRefs.value[index] = el
    } else {
        delete itemRefs.value[index]
    }
}

let scrollDebounceTimer = null
let lastScrollTime = 0

const handleScroll = (e) => {
    if (!scrollContainerRef.value) return

    const now = Date.now()
    const timeSinceLastScroll = now - lastScrollTime
    lastScrollTime = now

    if (scrollDebounceTimer) {
        clearTimeout(scrollDebounceTimer)
    }

    const scrollTop = scrollContainerRef.value.scrollTop
    const scrollDelta = Math.abs(scrollTop - scrollState.lastScrollTop)

    if (scrollDelta > 50 && timeSinceLastScroll < 200) {
        hideItemUI()
    }

    scrollState.lastScrollTop = scrollTop

    scrollDebounceTimer = setTimeout(() => {
        if (scrollState.isAnimating) return

        const containerHeight = scrollContainerRef.value?.clientHeight || 0
        const currentScrollTop = scrollContainerRef.value?.scrollTop || 0
        const calculatedIndex = Math.round(currentScrollTop / containerHeight)

        if (
            calculatedIndex !== currentItemIndex.value &&
            calculatedIndex >= 0 &&
            calculatedIndex < totalItems.value &&
            !isChangingItem.value
        ) {
            const oldIndex = currentItemIndex.value
            currentItemIndex.value = calculatedIndex
            handleItemChange(calculatedIndex, oldIndex)
        }

        checkLoadMore()
    }, 50)
}

const hideItemUI = () => {
    const visibleIndexes = getVisibleItemIndexes()
    visibleIndexes.forEach((index) => {
        const item = itemRefs.value[index]
        if (item && typeof item.hideUI === 'function') {
            try {
                item.hideUI()
            } catch (err) {
                console.warn('Failed to hide UI:', err)
            }
        }
    })
}

const getVisibleItemIndexes = () => {
    if (!scrollContainerRef.value) return []

    const containerHeight = scrollContainerRef.value.clientHeight
    const scrollTop = scrollContainerRef.value.scrollTop

    const currentIndex = Math.round(scrollTop / containerHeight)

    const indexes = []
    if (currentIndex > 0) indexes.push(currentIndex - 1)
    indexes.push(currentIndex)
    if (currentIndex < totalItems.value - 1) indexes.push(currentIndex + 1)

    return indexes
}

const checkLoadMore = () => {
    if (!scrollContainerRef.value || !hasNextPage.value || isFetchingNextPage.value) return

    const scrollHeight = scrollContainerRef.value.scrollHeight
    const scrollPos = scrollContainerRef.value.scrollTop + scrollContainerRef.value.clientHeight
    const viewportHeight = scrollContainerRef.value.clientHeight
    const threshold = viewportHeight * props.scrollThreshold

    if (scrollHeight - scrollPos < threshold && scrollPos > 0) {
        fetchNextPage()
    }
}

const checkMobileView = () => {
    isMobileView.value = window.innerWidth < 768
}

let dataWatcher = null
onMounted(async () => {
    if (!scrollContainerRef.value) return

    checkMobileView()
    window.addEventListener('resize', checkMobileView)

    scrollContainerRef.value.addEventListener('scroll', handleScroll, {
        passive: true
    })

    const cleanupWheel = handleWheel(scrollContainerRef.value)
    const cleanupTouch = handleTouch(scrollContainerRef.value)

    dataWatcher = computed(() => data.value?.pages?.[0]?.data?.[0])

    const stopWatcher = watch(
        dataWatcher,
        async (newData) => {
            if (newData && totalItems.value > 0) {
                await nextTick()
                setTimeout(() => {
                    if (currentItemIndex.value === 0) {
                        handleItemChange(0, -1)
                    }
                }, 500)
                stopWatcher()
            }
        },
        { immediate: true }
    )

    scrollContainerRef.value._cleanupWheel = cleanupWheel
    scrollContainerRef.value._cleanupTouch = cleanupTouch
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobileView)

    if (scrollDebounceTimer) {
        clearTimeout(scrollDebounceTimer)
    }

    if (scrollState.scrollTimeout) {
        clearTimeout(scrollState.scrollTimeout)
    }

    Object.values(itemRefs.value).forEach((item) => {
        if (item && typeof item.cleanup === 'function') {
            item.cleanup()
        }
    })

    if (scrollContainerRef.value) {
        if (scrollContainerRef.value._cleanupWheel) {
            scrollContainerRef.value._cleanupWheel()
        }
        if (scrollContainerRef.value._cleanupTouch) {
            scrollContainerRef.value._cleanupTouch()
        }
    }

    itemRefs.value = {}
})

defineExpose({
    scrollToItem: (index) => {
        if (!scrollContainerRef.value || index < 0 || index >= totalItems.value) return

        scrollToIndex(index)
    },
    getCurrentItemIndex: () => currentItemIndex.value,
    refresh: refetch,
    enableSnapScroll,
    disableSnapScroll
})
</script>

<style scoped>
.scroll-container {
    height: 100dvh;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
    width: 100%;
    scroll-snap-type: y mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
    will-change: scroll-position;
    transform: translateZ(0);
    overscroll-behavior-y: contain;
}

@media (max-width: 767px) {
    .scroll-container.mobile-navbar-padding {
        height: calc(100dvh - 80px);
    }
}

@supports (-webkit-touch-callout: none) {
    .scroll-container {
        height: 100vh;
        height: -webkit-fill-available;
        height: 100dvh;
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
    }

    @media (max-width: 767px) {
        .scroll-container.mobile-navbar-padding {
            height: calc(100dvh - 80px);
            padding-bottom: calc(env(safe-area-inset-bottom));
        }
    }
}

.scroll-container::-webkit-scrollbar {
    display: none;
}

.snap-item {
    height: 100vh;
    height: 100dvh;
    width: 100%;
    scroll-snap-align: start;
    scroll-snap-stop: always;
    display: flex;
    justify-content: center;
    align-items: center;
    contain: layout style paint;
    position: relative;
}

@media (max-width: 767px) {
    .mobile-navbar-padding .snap-item {
        height: calc(100dvh - 80px);
    }
}

@media (hover: none) and (pointer: coarse) {
    .scroll-container {
        scroll-snap-type: y mandatory;
        -webkit-scroll-snap-type: y mandatory;
        scroll-behavior: auto;
    }

    .snap-item {
        scroll-snap-align: center;
        -webkit-scroll-snap-align: center;
    }
}

@supports (-webkit-touch-callout: none) {
    .scroll-container {
        -webkit-scroll-snap-type: y mandatory;
        -webkit-scroll-snap-points-y: repeat(100dvh);
    }

    .snap-item {
        height: 100vh;
        height: 100dvh;
    }

    @media (max-width: 767px) {
        .scroll-container.mobile-navbar-padding .snap-item {
            height: calc(100dvh - 80px);
        }
    }
}
</style>
