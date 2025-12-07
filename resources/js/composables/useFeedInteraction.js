import { ref } from 'vue'

// Global state for user interaction (shared across all components)
const globalHasInteracted = ref(false)
const globalMuted = ref(true) // Global mute preference, starts muted

export function useFeedInteraction() {
    const hasInteracted = globalHasInteracted
    const requiresInteraction = ref(!globalHasInteracted.value)

    const handleFirstInteraction = () => {
        if (!globalHasInteracted.value) {
            globalHasInteracted.value = true
            requiresInteraction.value = false
            console.log('User interaction detected - enabling unmuted playback')
        }
    }

    const setGlobalMuted = (muted) => {
        globalMuted.value = muted
        console.log('Global mute state changed to:', muted)
    }

    const resetInteraction = () => {
        globalHasInteracted.value = false
        requiresInteraction.value = true
        globalMuted.value = true
    }

    return {
        hasInteracted,
        requiresInteraction,
        globalMuted,
        handleFirstInteraction,
        setGlobalMuted,
        resetInteraction
    }
}
