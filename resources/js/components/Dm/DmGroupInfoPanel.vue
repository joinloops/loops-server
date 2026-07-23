<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

        <div
            class="relative flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-t-2xl bg-white sm:rounded-2xl dark:bg-slate-950"
        >
            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                    Group details
                </h2>
                <button
                    type="button"
                    class="rounded-full p-1.5 text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-900"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <div class="flex items-center gap-3 px-4 py-3">
                <DmGroupAvatar :participants="activeParticipants" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ displayName }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ memberCount }} members
                    </p>
                </div>
            </div>

            <div v-if="canAdd" class="px-4 pb-2">
                <div
                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900"
                >
                    <MagnifyingGlassIcon class="h-4 w-4 shrink-0 text-slate-400" />
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Add people"
                        class="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-slate-100"
                    />
                </div>
            </div>
            <p v-else class="px-4 pb-2 text-xs text-slate-400">
                This group is full ({{ maxParticipants }} members max).
            </p>

            <div class="min-h-32 flex-1 overflow-y-auto">
                <template v-if="query.trim().length >= 2">
                    <button
                        v-for="account in results"
                        :key="account.id"
                        type="button"
                        :disabled="addingId !== null"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-slate-50 disabled:opacity-60 dark:hover:bg-slate-900/60"
                        @click="add(account)"
                    >
                        <img
                            v-if="account.avatar"
                            :src="account.avatar"
                            :alt="account.username"
                            class="h-9 w-9 rounded-full object-cover"
                            onerror="
                                this.src = '/storage/avatars/default.jpg'
                                this.onerror = null
                            "
                        />
                        <div
                            v-else
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ (account.username ?? '?').slice(0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ account.name || account.username }}
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                @{{ account.username }}
                            </p>
                        </div>
                        <span v-if="addingId === String(account.id)" class="text-xs text-slate-400">
                            Adding
                        </span>
                        <PlusCircleIcon v-else class="h-5 w-5 shrink-0 text-[#F02C56]" />
                    </button>

                    <p v-if="searching" class="px-4 py-3 text-xs text-slate-400">Searching</p>
                    <p v-else-if="!results.length" class="px-4 py-3 text-xs text-slate-400">
                        No people found. Try a full address like user@server.tld.
                    </p>
                </template>

                <template v-else>
                    <p class="px-4 pb-1 pt-2 text-xs font-medium text-slate-400">Members</p>
                    <router-link
                        v-for="person in visibleParticipants"
                        :key="person.id"
                        :to="`/@${person.username}`"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-slate-50 dark:hover:bg-slate-900/60"
                    >
                        <img
                            v-if="person.avatar"
                            :src="person.avatar"
                            :alt="person.username"
                            class="h-9 w-9 rounded-full object-cover"
                            onerror="
                                this.src = '/storage/avatars/default.jpg'
                                this.onerror = null
                            "
                        />
                        <div
                            v-else
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ (person.username ?? '?').slice(0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ person.name || person.username }}
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                @{{ person.username }}
                            </p>
                        </div>
                        <span
                            v-if="person.state === 'request'"
                            class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-900 dark:text-slate-400"
                        >
                            Invited
                        </span>
                        <span
                            v-else-if="person.is_remote"
                            class="shrink-0 truncate rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-900 dark:text-slate-400"
                        >
                            {{ person.domain }}
                        </span>
                    </router-link>
                </template>
            </div>

            <div v-if="error" class="px-4 pb-1">
                <p class="text-xs text-red-500">{{ error }}</p>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                <button
                    type="button"
                    :disabled="leaving"
                    class="w-full rounded-full border border-red-200 py-2.5 text-sm font-semibold text-red-500 transition enabled:hover:bg-red-50 disabled:opacity-40 dark:border-red-900/40 dark:enabled:hover:bg-red-950/40"
                    @click="leave"
                >
                    {{ leaving ? 'Leaving' : 'Leave group' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { MagnifyingGlassIcon, PlusCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import dmApi from '~/api/dm'
import { useDmStore } from '~/stores/dm'
import { useDmConversation } from '~/composables/useDmConversation'
import { useAlertModal } from '@/composables/useAlertModal.js'
import DmGroupAvatar from './DmGroupAvatar.vue'

const props = defineProps({
    conversationId: { type: String, required: true }
})
const emit = defineEmits(['close', 'left'])

const store = useDmStore()
const { confirmModal } = useAlertModal()

const MAX_GROUP_PARTICIPANTS = 12

const conversation = computed(() => store.conversations[props.conversationId])
const { activeParticipants, displayName, memberCount } = useDmConversation(conversation)

const visibleParticipants = computed(() =>
    (conversation.value?.participants ?? activeParticipants.value).filter(
        (p) => (p.state ?? 'active') !== 'left'
    )
)

const maxParticipants = MAX_GROUP_PARTICIPANTS
const canAdd = computed(() => memberCount.value < MAX_GROUP_PARTICIPANTS)

const query = ref('')
const results = ref([])
const searching = ref(false)
const addingId = ref(null)
const leaving = ref(false)
const error = ref(null)
let timer = null

const excludedIds = computed(() => {
    const ids = new Set(visibleParticipants.value.map((p) => String(p.id)))
    if (store.meId) ids.add(String(store.meId))
    return ids
})

watch(query, (value) => {
    clearTimeout(timer)
    error.value = null
    const q = value.trim()
    if (q.length < 2) {
        results.value = []
        return
    }
    timer = setTimeout(async () => {
        searching.value = true
        try {
            const { data } = await dmApi.searchAccounts(q)
            const accounts = Array.isArray(data) ? data : (data.data ?? [])
            results.value = accounts.filter((a) => !excludedIds.value.has(String(a.id)))
        } catch {
            results.value = []
        } finally {
            searching.value = false
        }
    }, 300)
})

async function add(account) {
    if (addingId.value) return
    addingId.value = String(account.id)
    error.value = null
    try {
        await store.addParticipants(props.conversationId, [account.id])
        query.value = ''
        results.value = []
    } catch (err) {
        error.value = err.response?.data?.message ?? `Couldn't add @${account.username}`
    } finally {
        addingId.value = null
    }
}

async function leave() {
    if (leaving.value) return
    const confirmed = await confirmModal(
        'Leave group',
        "You'll stop receiving messages from this conversation."
    )
    if (!confirmed) return
    leaving.value = true
    error.value = null
    try {
        await store.leaveGroup(props.conversationId)
        emit('left')
        emit('close')
    } catch (err) {
        error.value = err.response?.data?.message ?? "Couldn't leave the group."
    } finally {
        leaving.value = false
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>
