import { onBeforeUnmount, watch } from 'vue'
import { useDmStore } from '~/stores/dm'

export function useDmSocket() {
    const store = useDmStore()
    let subscribedId = null

    const stop = watch(
        () => store.meId,
        (id) => {
            if (!window.Echo) return
            if (subscribedId && subscribedId !== id) {
                window.Echo.leave(`dm.${subscribedId}`)
                subscribedId = null
            }
            if (id && id !== subscribedId) {
                window.Echo.private(`dm.${id}`).listen('.dm.message.created', (payload) => {
                    store.onSocketMessage(payload)
                })
                subscribedId = id
            }
        },
        { immediate: true }
    )

    onBeforeUnmount(() => {
        stop()
        if (window.Echo && subscribedId) {
            window.Echo.leave(`dm.${subscribedId}`)
        }
    })
}
