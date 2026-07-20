import { defineStore } from 'pinia'
import dmApi from '~/api/dm'
import { useAuthStore } from '~/stores/auth'

const emptyTab = () => ({ ids: [], cursor: null, loading: false, loaded: false, done: false })
const emptyThread = () => ({
    messages: [],
    cursor: null,
    loading: false,
    loaded: false,
    done: false
})

export const useDmStore = defineStore('dm', {
    state: () => ({
        activeId: null,
        conversations: {},
        tabs: {
            primary: emptyTab(),
            requests: emptyTab(),
            hidden: emptyTab()
        },
        threads: {}
    }),

    getters: {
        meId: () => {
            const auth = useAuthStore()
            const id = auth.id || auth.user?.id
            return id ? String(id) : null
        },
        unreadCount: (state) =>
            state.tabs.primary.ids.filter((id) => state.conversations[id]?.unread).length,
        requestCount: (state) => state.tabs.requests.ids.length,
        activeConversation: (state) =>
            state.activeId ? (state.conversations[state.activeId] ?? null) : null
    },

    actions: {
        thread(id) {
            const key = String(id)
            if (!this.threads[key]) {
                this.threads[key] = emptyThread()
            }
            return this.threads[key]
        },

        async fetchConversations(filter = 'primary', refresh = false) {
            const tab = this.tabs[filter]
            if (tab.loading || (tab.done && !refresh)) return
            if (refresh) Object.assign(tab, emptyTab())
            tab.loading = true
            try {
                const { data } = await dmApi.conversations({
                    filter,
                    cursor: tab.cursor || undefined
                })
                const ids = data.data.map((c) => String(c.id))
                data.data.forEach((c) => {
                    this.conversations[String(c.id)] = c
                })
                tab.ids = refresh ? ids : [...new Set([...tab.ids, ...ids])]
                tab.cursor = data.meta?.next_cursor || null
                tab.done = !tab.cursor
                tab.loaded = true
            } finally {
                tab.loading = false
            }
        },

        async fetchConversation(id) {
            const { data } = await dmApi.conversation(id)
            this.conversations[String(id)] = data.data
            return data.data
        },

        async fetchMessages(id, older = false) {
            const key = String(id)
            const t = this.thread(key)
            if (t.loading || (older && t.done)) return
            t.loading = true
            try {
                const { data } = await dmApi.messages(key, {
                    cursor: older ? t.cursor : undefined
                })
                const page = [...data.data].reverse()
                t.messages = older ? [...page, ...t.messages] : page
                t.cursor = data.meta?.next_cursor || null
                t.done = !t.cursor
                t.loaded = true
            } finally {
                t.loading = false
            }
        },

        async openConversation(id) {
            const key = String(id)
            this.activeId = key
            if (!this.conversations[key]) {
                try {
                    await this.fetchConversation(key)
                } catch {
                    return
                }
            }
            const t = this.thread(key)
            if (!t.loaded) await this.fetchMessages(key)
            const c = this.conversations[key]
            if (c?.unread) this.markRead(key, c.last_message?.id)
        },

        async sendMessage({
            conversationId,
            recipientId,
            type = 'text',
            body = null,
            videoId = null
        }) {
            const payload = { type }
            if (body) payload.body = body
            if (conversationId) payload.conversation_id = conversationId
            if (recipientId) payload.recipient_id = recipientId
            if (videoId) payload.video_id = videoId

            let temp = null
            if (conversationId) {
                temp = {
                    id: `temp-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    conversation_id: String(conversationId),
                    sender_id: this.meId,
                    type,
                    body,
                    created_at: new Date().toISOString(),
                    pending: true
                }
                this.thread(conversationId).messages.push(temp)
            }

            try {
                const { data } = await dmApi.send(payload)
                this.applySendResult(temp, data.data)
                return data.data
            } catch (e) {
                if (temp) {
                    temp.pending = false
                    temp.failed = true
                }
                throw e
            }
        },

        removeLocalMessage(conversationId, id) {
            const t = this.thread(conversationId)
            t.messages = t.messages.filter((m) => m.id !== id)
        },

        applySendResult(temp, message) {
            const cid = String(message.conversation_id)
            const t = this.thread(cid)
            const byId = t.messages.findIndex((m) => m.id === message.id)
            const byTemp = temp ? t.messages.findIndex((m) => m.id === temp.id) : -1
            if (byId !== -1) {
                t.messages.splice(byId, 1, message)
                if (byTemp !== -1) {
                    t.messages.splice(
                        t.messages.findIndex((m) => m.id === temp.id),
                        1
                    )
                }
            } else if (byTemp !== -1) {
                t.messages.splice(byTemp, 1, message)
            } else {
                t.messages.push(message)
            }
            this.touchConversation(cid, message, true)
        },

        async sendKlipyMedia({ conversationId, recipientId, body = null, type, item }) {
            const payload = { type, item }
            if (body) payload.body = body
            if (conversationId) payload.conversation_id = conversationId
            if (recipientId) payload.recipient_id = recipientId

            let temp = null
            if (conversationId) {
                const url = item.mp4?.url || item.full?.url
                temp = {
                    id: `temp-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    conversation_id: String(conversationId),
                    sender_id: this.meId,
                    type: 'media',
                    body,
                    media: [
                        {
                            id: 'temp-media',
                            type: type === 'gifs' ? 'gif' : type === 'clips' ? 'video' : 'image',
                            mime_type: item.mp4?.url ? 'video/mp4' : null,
                            url,
                            preview_url: item.preview?.url ?? null,
                            width: item.full?.width ?? item.width ?? null,
                            height: item.full?.height ?? item.height ?? null,
                            description: item.title ?? null
                        }
                    ],
                    klipy: { type, item },
                    created_at: new Date().toISOString(),
                    pending: true
                }
                this.thread(conversationId).messages.push(temp)
            }

            try {
                const { data } = await dmApi.sendMedia(payload)
                this.applySendResult(temp, data.data)
                return data.data
            } catch (e) {
                if (temp) {
                    temp.pending = false
                    temp.failed = true
                }
                throw e
            }
        },

        insertByRecency(tabKey, key) {
            const tab = this.tabs[tabKey]
            if (!tab.loaded || tab.ids.includes(key)) return
            const stamp = new Date(this.conversations[key]?.updated_at ?? 0).getTime()
            const index = tab.ids.findIndex(
                (id) => new Date(this.conversations[id]?.updated_at ?? 0).getTime() <= stamp
            )
            if (index === -1) {
                if (tab.done) tab.ids.push(key)
            } else {
                tab.ids.splice(index, 0, key)
            }
        },

        touchConversation(id, message, own) {
            const key = String(id)
            const c = this.conversations[key]
            if (c) {
                c.last_message = message
                c.updated_at = message.created_at
                if (!own && this.activeId !== key) c.unread = true
                if (!own && c.pending_acceptance) c.pending_acceptance = false
            }
            if (this.tabs.hidden.ids.includes(key)) {
                this.tabs.hidden.ids = this.tabs.hidden.ids.filter((i) => i !== key)
                if (c) c.hidden = false
                if (this.tabs.primary.loaded && !this.tabs.primary.ids.includes(key)) {
                    this.tabs.primary.ids.unshift(key)
                }
                return
            }
            for (const tabKey of ['primary', 'requests']) {
                const tab = this.tabs[tabKey]
                const i = tab.ids.indexOf(key)
                if (i > 0) {
                    tab.ids.splice(i, 1)
                    tab.ids.unshift(key)
                }
            }
        },

        onSocketMessage(payload) {
            const cid = String(payload.conversation_id)
            const message = payload.message
            const own = String(message.sender_id) === this.meId

            const t = this.threads[cid]
            if (t?.loaded && !t.messages.some((m) => m.id === message.id)) {
                if (own) {
                    const i = t.messages.findIndex((m) => m.pending && m.type === message.type)
                    if (i !== -1) t.messages.splice(i, 1, message)
                    else t.messages.push(message)
                } else {
                    t.messages.push(message)
                }
            }

            if (this.conversations[cid]) {
                this.touchConversation(cid, message, own)
                if (!own && this.activeId === cid && document.hasFocus()) {
                    this.markRead(cid, message.id)
                }
            } else {
                this.absorbUnknownConversation(cid)
            }
        },

        onSocketMessageDeleted(payload) {
            const cid = String(payload.conversation_id)
            const mid = String(payload.message_id)
            const t = this.threads[cid]
            if (t?.loaded) {
                t.messages = t.messages.filter((m) => String(m.id) !== mid)
            }
            const c = this.conversations[cid]
            if (c?.last_message && String(c.last_message.id) === mid) {
                c.last_message = t?.messages?.[t.messages.length - 1] ?? null
            }
        },

        async absorbUnknownConversation(id) {
            const key = String(id)
            try {
                const c = await this.fetchConversation(key)
                const tabKey = c.state === 'request' ? 'requests' : 'primary'
                this.insertByRecency(tabKey, key)
            } catch {
                return
            }
        },

        markRead(id, messageId = null) {
            const key = String(id)
            const c = this.conversations[key]
            if (c) {
                c.unread = false
                if (messageId) c.last_read_message_id = String(messageId)
            }
            dmApi.markRead(key, messageId).catch(() => {})
        },

        async accept(id) {
            const key = String(id)
            const { data } = await dmApi.accept(key)
            this.conversations[key] = data.data
            this.tabs.requests.ids = this.tabs.requests.ids.filter((i) => i !== key)
            this.insertByRecency('primary', key)
        },

        async decline(id) {
            const key = String(id)
            await dmApi.decline(key)
            this.removeEverywhere(key)
        },

        async hide(id) {
            const key = String(id)
            await dmApi.hide(key)
            const c = this.conversations[key]
            if (c) c.hidden = true
            this.tabs.primary.ids = this.tabs.primary.ids.filter((i) => i !== key)
            this.insertByRecency('hidden', key)
        },

        async unhide(id) {
            const key = String(id)
            const { data } = await dmApi.unhide(key)
            this.conversations[key] = data.data
            this.tabs.hidden.ids = this.tabs.hidden.ids.filter((i) => i !== key)
            this.insertByRecency('primary', key)
        },

        async toggleMute(id) {
            const key = String(id)
            const c = this.conversations[key]
            if (!c) return
            if (c.muted) {
                await dmApi.unmute(key)
                c.muted = false
            } else {
                await dmApi.mute(key)
                c.muted = true
            }
        },

        async deleteMessage(conversationId, messageId) {
            const key = String(conversationId)
            await dmApi.deleteMessage(messageId)
            const t = this.thread(key)
            t.messages = t.messages.filter((m) => m.id !== messageId)
            const c = this.conversations[key]
            if (c?.last_message?.id === messageId) {
                c.last_message = t.messages[t.messages.length - 1] ?? null
            }
        },

        removeEverywhere(key) {
            this.tabs.primary.ids = this.tabs.primary.ids.filter((i) => i !== key)
            this.tabs.requests.ids = this.tabs.requests.ids.filter((i) => i !== key)
            this.tabs.hidden.ids = this.tabs.hidden.ids.filter((i) => i !== key)
            delete this.threads[key]
            delete this.conversations[key]
            if (this.activeId === key) this.activeId = null
        }
    }
})
