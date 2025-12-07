<template>
    <component :is="'div'" style="display: contents">
        <button
            v-bind="$attrs"
            :disabled="loading || disabled"
            :class="[buttonClasses, sizeClasses]"
            :type="type"
            class="relative overflow-hidden transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:scale-100 cursor-pointer disabled:cursor-not-allowed group"
        >
            <div v-if="loading" class="absolute inset-0 bg-current opacity-10 animate-pulse"></div>

            <div class="relative flex items-center justify-center space-x-2">
                <svg
                    v-if="loading"
                    class="animate-spin"
                    :class="spinnerClasses"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>

                <span :class="{ 'hidden opacity-0': loading }">
                    <slot>{{ loading ? 'Loading...' : 'Button' }}</slot>
                </span>
            </div>

            <div
                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"
            ></div>
        </button>
    </component>
</template>

<script>
import { computed } from 'vue'

export default {
    name: 'AnimatedButton',
    inheritAttrs: false,
    props: {
        variant: {
            type: String,
            default: 'primary',
            validator: (value) =>
                ['primary', 'secondary', 'light', 'outline', 'ghost', 'primaryOutline'].includes(
                    value
                )
        },
        size: {
            type: String,
            default: 'md',
            validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value)
        },
        loading: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
        type: { type: String, default: 'button' }
    },
    setup(props) {
        const sizeClasses = computed(() => {
            switch (props.size) {
                case 'xs':
                    return 'px-3 py-1.5 text-xs rounded-md'
                case 'sm':
                    return 'px-4 py-2 text-sm rounded-md'
                case 'lg':
                    return 'px-8 py-3.5 text-base rounded-xl'
                case 'md':
                default:
                    return 'px-6 py-3 text-sm rounded-lg'
            }
        })

        const spinnerClasses = computed(() => {
            switch (props.size) {
                case 'xs':
                    return 'h-3 w-3'
                case 'sm':
                    return 'h-4 w-4'
                case 'lg':
                    return 'h-6 w-6'
                case 'md':
                default:
                    return 'h-5 w-5'
            }
        })

        const buttonClasses = computed(() => {
            const baseClasses =
                'font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-900'

            switch (props.variant) {
                case 'primary':
                    return [
                        baseClasses,
                        'bg-[#F02C56] dark:bg-[#F02C56]',
                        'text-white',
                        'hover:bg-[#D7284A] active:bg-[#C62445]',
                        'focus:ring-[#F02C56]',
                        'disabled:bg-opacity-50 disabled:opacity-50'
                    ].join(' ')
                case 'secondary':
                    return `${baseClasses} bg-gray-600 dark:bg-gray-500 text-white hover:bg-gray-700 dark:hover:bg-gray-600 focus:ring-gray-500 dark:focus:ring-gray-400 disabled:bg-gray-400 dark:disabled:bg-gray-600 disabled:opacity-50`
                case 'light':
                    return `${baseClasses}
                        bg-gray-100 dark:bg-gray-800
                        text-gray-900 dark:text-white
                        hover:bg-gray-200 dark:hover:bg-gray-700
                        active:bg-gray-300 dark:active:bg-gray-600
                        focus:ring-gray-300 dark:focus:ring-gray-600
                        disabled:opacity-50`
                case 'outline':
                    return `${baseClasses} border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500 dark:focus:ring-gray-400 disabled:opacity-50`
                case 'primaryOutline':
                    return `${baseClasses}
                        border border-[#F02C56]
                        text-[#F02C56]
                        bg-transparent dark:bg-transparent
                        hover:bg-[#F02C56]/10 dark:hover:bg-[#F02C56]/20
                        active:bg-[#F02C56]/20
                        focus:ring-[#F02C56]
                        disabled:opacity-50 disabled:border-opacity-50 disabled:text-opacity-50`
                case 'ghost':
                    return `${baseClasses} text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:ring-gray-500 dark:focus:ring-gray-400 disabled:opacity-50`
                default:
                    return baseClasses
            }
        })

        return { buttonClasses, sizeClasses, spinnerClasses }
    }
}
</script>
