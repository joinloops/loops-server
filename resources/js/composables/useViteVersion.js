import { ref } from 'vue'

export function useViteVersion() {
    const updateAvailable = ref(false)
    const booted = document.querySelector('meta[name="app-version"]')?.content

    async function check() {
        try {
            const { version } = await (await fetch('/api/v1/web/vmh', { cache: 'no-store' })).json()
            if (booted && version && version !== booted) {
                updateAvailable.value = true
            }
        } catch (e) {
            console.debug('version check failed', e)
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            check()
        }
    })

    setInterval(check, 15 * 60 * 1000)

    return { updateAvailable, reload: () => location.reload() }
}
