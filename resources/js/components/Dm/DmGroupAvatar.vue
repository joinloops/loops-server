<template>
    <div :class="['relative shrink-0', sizeClasses.wrap]">
        <template v-for="(person, index) in shown" :key="person.id ?? index">
            <img
                v-if="person.avatar"
                :src="person.avatar"
                :alt="person.username"
                :class="[
                    'absolute rounded-full object-cover ring-2 ring-white dark:ring-slate-950',
                    solo ? 'inset-0 h-full w-full' : sizeClasses.item,
                    solo ? '' : index === 0 ? 'left-0 top-0' : 'bottom-0 right-0'
                ]"
                onerror="
                    this.src = '/storage/avatars/default.jpg'
                    this.onerror = null
                "
            />
            <div
                v-else
                :class="[
                    'absolute flex items-center justify-center rounded-full bg-slate-200 font-semibold uppercase text-slate-600 ring-2 ring-white dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-950',
                    solo ? 'inset-0 h-full w-full' : sizeClasses.item,
                    solo ? '' : index === 0 ? 'left-0 top-0' : 'bottom-0 right-0',
                    sizeClasses.text
                ]"
            >
                {{ (person.username ?? '?').slice(0, 1) }}
            </div>
        </template>
        <span
            v-if="overflow > 0"
            :class="[
                'absolute bottom-0 right-0 flex items-center justify-center rounded-full bg-slate-200 font-semibold text-slate-600 ring-2 ring-white dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-950',
                sizeClasses.item,
                sizeClasses.text
            ]"
        >
            +{{ overflow }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    participants: { type: Array, default: () => [] },
    size: { type: String, default: 'md' }
})

const shown = computed(() => props.participants.slice(0, props.participants.length > 2 ? 1 : 2))

const overflow = computed(() => props.participants.length - shown.value.length)

const solo = computed(() => shown.value.length === 1 && overflow.value === 0)

const sizeClasses = computed(() =>
    props.size === 'sm'
        ? { wrap: 'h-9 w-9', item: 'h-6 w-6', text: 'text-[9px]' }
        : { wrap: 'h-12 w-12', item: 'h-8 w-8', text: 'text-xs' }
)
</script>
