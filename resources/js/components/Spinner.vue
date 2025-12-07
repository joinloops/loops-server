<template>
    <div
        class="flex items-center justify-center"
        role="status"
        aria-live="polite"
        aria-label="Loading"
    >
        <div :class="['rounded-full', sizeClass, themeClass, speedClass]" />
    </div>
</template>

<script setup lang="ts">
type Size = 'xs' | 'sm' | 'default' | 'lg' | 'xl'
type Theme = 'default' | 'brand' | 'indigo' | 'emerald' | 'sky' | 'slate' | 'amber'

const props = defineProps<{
    size?: Size
    theme?: Theme
    speed?: 'normal' | 'fast' | 'slow'
}>()

const sizeClass = {
    xs: 'h-4 w-4 border-2',
    sm: 'h-6 w-6 border-3',
    default: 'h-10 w-10 border-4',
    lg: 'h-12 w-12 border-4',
    xl: 'h-18 w-18 border-8'
}[props.size ?? 'default']

const themeClass = {
    default: 'border-gray-200 border-t-[#F02C56]',
    brand: 'border-white/30 border-t-white',
    indigo: 'border-gray-200 border-t-indigo-500',
    emerald: 'border-gray-200 border-t-emerald-500',
    sky: 'border-gray-200 border-t-sky-500',
    slate: 'border-gray-200 border-t-slate-500',
    amber: 'border-gray-200 border-t-amber-500'
}[props.theme ?? 'default']

const speedClass = {
    normal: 'animate-spin',
    fast: 'animate-spin [animation-duration:700ms]',
    slow: 'animate-spin [animation-duration:1.6s]'
}[props.speed ?? 'normal']
</script>
