<template>
    <div v-if="enabled && store.meId">
        <label
            class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 block"
        >
            Send via DM
        </label>
        <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1">
            <template v-if="loading && !accounts.length">
                <div
                    v-for="n in 5"
                    :key="n"
                    class="flex w-14 shrink-0 flex-col items-center gap-1.5"
                >
                    <div
                        class="h-12 w-12 animate-pulse rounded-full bg-gray-100 dark:bg-slate-800"
                    />
                    <div class="h-2.5 w-10 animate-pulse rounded bg-gray-100 dark:bg-slate-800" />
                </div>
            </template>

            <button
                v-for="account in accounts"
                :key="account.id"
                type="button"
                class="flex w-14 shrink-0 cursor-pointer flex-col items-center gap-1.5"
                :aria-label="`Send to ${account.username}`"
                @click="send(account)"
            >
                <div class="relative">
                    <img
                        v-if="account.avatar"
                        :src="account.avatar"
                        :alt="account.username"
                        :class="[
                            'h-12 w-12 rounded-full object-cover transition',
                            states[String(account.id)] === 'failed' ? 'ring-2 ring-red-500' : ''
                        ]"
                    />
                    <div
                        v-else
                        :class="[
                            'flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-[10px] font-semibold uppercase text-gray-600 dark:bg-slate-700 dark:text-gray-300',
                            states[String(account.id)] === 'failed' ? 'ring-2 ring-red-500' : ''
                        ]"
                    >
                        {{ (account.username ?? '?').slice(0, 1) }}
                    </div>

                    <div
                        v-if="states[String(account.id)] === 'sending'"
                        class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40"
                    >
                        <span
                            class="h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                        />
                    </div>

                    <span
                        v-if="states[String(account.id)] === 'sent'"
                        class="absolute -bottom-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-[#F02C56] ring-2 ring-white dark:ring-slate-900"
                    >
                        <CheckIcon class="h-3 w-3 text-white" />
                    </span>
                </div>
                <span
                    :class="[
                        'w-full truncate text-center text-[10px]',
                        states[String(account.id)] === 'failed'
                            ? 'text-red-500'
                            : 'text-gray-700 dark:text-gray-300'
                    ]"
                >
                    {{ label(account) }}
                </span>
            </button>

            <p v-if="failed" class="self-center text-xs text-red-500">Couldn't load suggestions</p>

            <button
                type="button"
                class="flex w-14 shrink-0 cursor-pointer flex-col items-center gap-1.5"
                aria-label="Search for someone"
                @click="emit('more')"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 dark:bg-slate-800 dark:text-gray-400 dark:hover:bg-slate-700"
                >
                    <MagnifyingGlassIcon class="h-5 w-5" />
                </div>
                <span
                    class="w-full truncate text-center text-[10px] text-gray-700 dark:text-gray-300"
                >
                    Other
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { CheckIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import dmApi from '~/api/dm'
import { useDmStore } from '~/stores/dm'

const props = defineProps({
    videoId: { type: [String, Number], default: null },
    shareUrl: { type: String, default: null }
})
const emit = defineEmits(['sent', 'more'])

const store = useDmStore()
const accounts = ref([])
const loading = ref(false)
const loaded = ref(false)
const failed = ref(false)
const states = ref({})

const enabled = computed(() => Boolean(props.videoId || props.shareUrl))

async function load() {
    if (loaded.value || loading.value || !enabled.value || !store.meId) return
    loading.value = true
    failed.value = false
    try {
        const { data } = await dmApi.suggestedRecipients()
        accounts.value = data.data ?? []
    } catch (error) {
        console.error('Failed to load DM suggestions', error)
        accounts.value = []
        failed.value = true
    } finally {
        loading.value = false
        loaded.value = true
    }
}

watch(
    () => store.meId,
    (id) => {
        if (id) load()
    },
    { immediate: true }
)

async function send(account) {
    const id = String(account.id)
    if (states.value[id] === 'sending' || states.value[id] === 'sent') return
    states.value[id] = 'sending'
    try {
        if (props.videoId) {
            await store.sendMessage({
                recipientId: id,
                type: 'loop_share',
                videoId: props.videoId
            })
        } else {
            await store.sendMessage({
                recipientId: id,
                type: 'text',
                body: props.shareUrl
            })
        }
        states.value[id] = 'sent'
        emit('sent', id)
    } catch {
        states.value[id] = 'failed'
    }
}

function label(account) {
    const state = states.value[String(account.id)]
    if (state === 'sent') return 'Sent'
    if (state === 'failed') return 'Retry'
    return account.username
}
</script>
