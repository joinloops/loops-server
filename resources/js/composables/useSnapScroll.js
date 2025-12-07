import { ref, computed } from 'vue'

export function useSnapScroll({
    containerRef,
    totalItems,
    currentIndex,
    sensitivity = 50,
    lockDuration = 600,
    onItemChange = null
}) {
    const isScrolling = ref(false)
    const scrollLocked = ref(false)
    const scrollTimeout = ref(null)
    const accumulatedDelta = ref(0)
    const isEnabled = ref(true)
    const lastScrollTime = ref(0)
    const targetIndex = ref(null)

    let touchStartY = 0
    let touchEndY = 0
    let touchStartX = 0
    let touchEndX = 0
    let touchStartTime = 0
    let isTouchOnInteractiveElement = false
    let touchIdentifier = null

    const lockScroll = () => {
        scrollLocked.value = true
        setTimeout(() => {
            scrollLocked.value = false
        }, lockDuration)
    }

    const scrollToIndex = (index, smooth = true) => {
        if (!containerRef.value || index < 0 || index >= totalItems.value) return false

        if (targetIndex.value !== null && targetIndex.value !== index) {
            return false
        }

        const container = containerRef.value
        const snapHeight = container.clientHeight
        const targetScroll = index * snapHeight
        const currentScroll = container.scrollTop

        if (Math.abs(currentScroll - targetScroll) < 5) {
            return false
        }

        targetIndex.value = index

        container.scrollTo({
            top: targetScroll,
            behavior: smooth ? 'smooth' : 'instant'
        })

        setTimeout(
            () => {
                targetIndex.value = null
            },
            smooth ? 800 : 100
        )

        return true
    }

    const updateCurrentItem = () => {
        if (!containerRef.value) return

        const containerHeight = containerRef.value.clientHeight
        const scrollPosition = containerRef.value.scrollTop
        const newIndex = Math.round(scrollPosition / containerHeight)

        if (newIndex !== currentIndex.value && newIndex < totalItems.value && newIndex >= 0) {
            const oldIndex = currentIndex.value
            currentIndex.value = newIndex

            if (onItemChange) {
                onItemChange(newIndex, oldIndex)
            }
        }
    }

    const performSnapScroll = (direction) => {
        if (isScrolling.value || scrollLocked.value || !containerRef.value || !isEnabled.value) {
            return false
        }

        const now = Date.now()
        if (now - lastScrollTime.value < 300) {
            return false
        }

        const container = containerRef.value
        const snapHeight = container.clientHeight
        const currentScroll = container.scrollTop
        const currentSnapIndex = Math.round(currentScroll / snapHeight)
        const nextSnapIndex = direction > 0 ? currentSnapIndex + 1 : currentSnapIndex - 1

        if (nextSnapIndex < 0 || nextSnapIndex >= totalItems.value) {
            accumulatedDelta.value = 0
            return false
        }

        const targetScroll = nextSnapIndex * snapHeight
        if (Math.abs(currentScroll - targetScroll) < 5) {
            accumulatedDelta.value = 0
            return false
        }

        isScrolling.value = true
        lastScrollTime.value = now
        lockScroll()

        if (scrollToIndex(nextSnapIndex)) {
            if (scrollTimeout.value) {
                clearTimeout(scrollTimeout.value)
            }

            scrollTimeout.value = setTimeout(() => {
                isScrolling.value = false
                accumulatedDelta.value = 0
                updateCurrentItem()
            }, 600)

            return true
        } else {
            isScrolling.value = false
            lockScroll.value = false
        }

        return false
    }

    const isInteractiveElement = (element) => {
        if (!element) return false

        const interactiveSelectors = [
            'button',
            'a',
            'input',
            'textarea',
            'select',
            'video',
            '[role="button"]',
            '[tabindex]',
            '.mobile-interaction-btn',
            '.play-button',
            '.comments-panel',
            '[data-interactive]'
        ]

        let current = element
        while (current && current !== containerRef.value) {
            if (
                interactiveSelectors.some((selector) => {
                    try {
                        return current.matches?.(selector)
                    } catch {
                        return false
                    }
                })
            ) {
                return true
            }

            if (current.classList) {
                if (
                    current.classList.contains('pointer-events-auto') ||
                    current.classList.contains('cursor-pointer')
                ) {
                    return true
                }
            }

            try {
                if (
                    current.closest?.(
                        '.comments-panel, .mobile-interaction-btn, [data-interactive]'
                    )
                ) {
                    return true
                }
            } catch {}

            current = current.parentElement
        }

        return false
    }

    const handleWheel = (container) => {
        let wheelLocked = false

        const wheelHandler = (e) => {
            if (isInteractiveElement(e.target)) {
                return
            }

            if (!isEnabled.value) return

            e.preventDefault()

            if (isScrolling.value || scrollLocked.value || wheelLocked) return

            accumulatedDelta.value += e.deltaY

            if (Math.abs(accumulatedDelta.value) >= sensitivity) {
                const direction = accumulatedDelta.value > 0 ? 1 : -1

                wheelLocked = true
                setTimeout(() => {
                    wheelLocked = false
                }, 500)

                if (performSnapScroll(direction)) {
                    accumulatedDelta.value = 0
                } else {
                    // Reset if scroll failed
                    accumulatedDelta.value = Math.sign(accumulatedDelta.value) * (sensitivity / 2)
                }
            }
        }

        container.addEventListener('wheel', wheelHandler, { passive: false })

        return () => {
            container.removeEventListener('wheel', wheelHandler)
        }
    }

    const handleTouch = (container) => {
        let isProcessingSwipe = false

        const touchStartHandler = (e) => {
            isTouchOnInteractiveElement = isInteractiveElement(e.target)

            if (isTouchOnInteractiveElement) {
                return
            }

            if (!isEnabled.value) return

            if (scrollLocked.value || isScrolling.value) {
                e.preventDefault()
                return
            }

            touchIdentifier = e.touches[0].identifier
            touchStartY = e.touches[0].clientY
            touchStartX = e.touches[0].clientX
            touchStartTime = Date.now()
            isProcessingSwipe = false
        }

        const touchMoveHandler = (e) => {
            if (isTouchOnInteractiveElement) {
                return
            }

            if (!isEnabled.value) return

            let touch = null
            for (let i = 0; i < e.touches.length; i++) {
                if (e.touches[i].identifier === touchIdentifier) {
                    touch = e.touches[i]
                    break
                }
            }

            if (!touch) return

            const deltaY = touch.clientY - touchStartY
            const deltaX = Math.abs(touch.clientX - touchStartX)

            if (
                (scrollLocked.value || isScrolling.value) &&
                Math.abs(deltaY) > deltaX &&
                Math.abs(deltaY) > 10
            ) {
                e.preventDefault()
            }

            touchEndY = touch.clientY
            touchEndX = touch.clientX
        }

        const touchEndHandler = (e) => {
            if (isTouchOnInteractiveElement) {
                isTouchOnInteractiveElement = false
                touchIdentifier = null
                return
            }

            if (!isEnabled.value || isProcessingSwipe) {
                touchIdentifier = null
                return
            }

            if (scrollLocked.value || isScrolling.value) {
                isTouchOnInteractiveElement = false
                touchIdentifier = null
                return
            }

            const touchDuration = Date.now() - touchStartTime
            const swipeDistanceY = touchStartY - touchEndY
            const swipeDistanceX = Math.abs(touchStartX - touchEndX)
            const SWIPE_THRESHOLD = sensitivity
            const MAX_TOUCH_DURATION = 500
            const MIN_VELOCITY = 0.3

            const velocity = Math.abs(swipeDistanceY) / touchDuration

            const isValidSwipe =
                touchDuration < MAX_TOUCH_DURATION &&
                Math.abs(swipeDistanceY) > SWIPE_THRESHOLD &&
                Math.abs(swipeDistanceY) > swipeDistanceX * 1.5 &&
                velocity > MIN_VELOCITY

            if (isValidSwipe && !isProcessingSwipe) {
                isProcessingSwipe = true
                const direction = swipeDistanceY > 0 ? 1 : -1

                performSnapScroll(direction)

                setTimeout(() => {
                    isProcessingSwipe = false
                }, 300)
            }

            isTouchOnInteractiveElement = false
            touchIdentifier = null
        }

        container.addEventListener('touchstart', touchStartHandler, {
            passive: true
        })
        container.addEventListener('touchmove', touchMoveHandler, {
            passive: false
        })
        container.addEventListener('touchend', touchEndHandler, {
            passive: true
        })

        const touchCancelHandler = () => {
            isTouchOnInteractiveElement = false
            touchIdentifier = null
            isProcessingSwipe = false
        }

        container.addEventListener('touchcancel', touchCancelHandler, {
            passive: true
        })

        return () => {
            container.removeEventListener('touchstart', touchStartHandler)
            container.removeEventListener('touchmove', touchMoveHandler)
            container.removeEventListener('touchend', touchEndHandler)
            container.removeEventListener('touchcancel', touchCancelHandler)
        }
    }

    const enableSnapScroll = () => {
        isEnabled.value = true
        accumulatedDelta.value = 0
    }

    const disableSnapScroll = () => {
        isEnabled.value = false
        accumulatedDelta.value = 0
    }

    return {
        isScrolling: computed(() => isScrolling.value),
        scrollLocked: computed(() => scrollLocked.value),
        isEnabled: computed(() => isEnabled.value),
        handleWheel,
        handleTouch,
        updateCurrentItem,
        scrollToIndex,
        performSnapScroll,
        enableSnapScroll,
        disableSnapScroll
    }
}
