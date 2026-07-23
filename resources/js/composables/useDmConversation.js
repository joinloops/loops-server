import { computed, toValue } from 'vue'

export function useDmConversation(conversation) {
    const convo = computed(() => toValue(conversation) ?? null)

    const isGroup = computed(() => convo.value?.type === 'group')

    const participants = computed(() => {
        const c = convo.value
        if (!c) return []
        if (Array.isArray(c.participants) && c.participants.length) return c.participants
        return c.participant ? [c.participant] : []
    })

    const activeParticipants = computed(() =>
        participants.value.filter((p) => (p.state ?? 'active') !== 'left')
    )

    const displayName = computed(() => {
        const c = convo.value
        if (!c) return ''
        if (!isGroup.value) {
            const p = participants.value[0] ?? {}
            return p.name || p.username || ''
        }
        if (c.title) return c.title
        const names = activeParticipants.value
            .map((p) => p.name || (p.username ?? '').split('@')[0])
            .filter(Boolean)
        if (!names.length) return 'Group conversation'
        if (names.length <= 3) return names.join(', ')
        return `${names.slice(0, 3).join(', ')} +${names.length - 3}`
    })

    const memberCount = computed(() => activeParticipants.value.length + 1)

    const remoteDomains = computed(() => [
        ...new Set(
            activeParticipants.value.filter((p) => p.is_remote && p.domain).map((p) => p.domain)
        )
    ])

    function senderFor(senderId) {
        if (senderId == null) return null
        const id = String(senderId)
        return participants.value.find((p) => String(p.id) === id) ?? null
    }

    return {
        isGroup,
        participants,
        activeParticipants,
        displayName,
        memberCount,
        remoteDomains,
        senderFor
    }
}
