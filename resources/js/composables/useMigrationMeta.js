const STATE_META = {
    building: { label: 'Preparing', tone: 'amber' },
    offered: { label: 'Offered', tone: 'amber' },
    pending: { label: 'Pending', tone: 'amber' },
    transferring: { label: 'Transferring', tone: 'blue' },
    sealing: { label: 'Sealing', tone: 'blue' },
    ready: { label: 'Ready', tone: 'violet' },
    finalizing: { label: 'Finalizing', tone: 'violet' },
    finalized: { label: 'Finalized', tone: 'emerald' },
    completed: { label: 'Completed', tone: 'emerald' },
    failed: { label: 'Failed', tone: 'red' },
    rejected: { label: 'Rejected', tone: 'red' },
    cancelled: { label: 'Cancelled', tone: 'gray' }
}

const TONES = {
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
}

export function useMigrationMeta() {
    const stateMeta = (state) => {
        const meta = STATE_META[state] || { label: state, tone: 'gray' }
        return { label: meta.label, badge: TONES[meta.tone] }
    }

    const stateOptions = Object.keys(STATE_META)

    const formatBytes = (bytes) => {
        const value = Number(bytes || 0)
        if (value < 1024) return `${value} B`
        const units = ['KB', 'MB', 'GB', 'TB']
        let size = value / 1024
        let unit = 0
        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024
            unit++
        }
        return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[unit]}`
    }

    const formatDate = (iso) => {
        if (!iso) return '-'
        return new Date(iso).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    const percent = (migration) => {
        const progress = migration?.progress
        if (!progress?.total) return 0
        const finished = progress.done + progress.failed + progress.skipped
        return Math.min(100, Math.round((finished / progress.total) * 100))
    }

    const remoteLabel = (migration) =>
        migration?.remote_account?.username
            ? `@${migration.remote_account.username}`
            : migration?.remote_domain

    return { stateMeta, stateOptions, formatBytes, formatDate, percent, remoteLabel }
}
