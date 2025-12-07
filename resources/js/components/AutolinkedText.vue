<template>
    <div :class="[textSize, rootClass]">
        <component :is="renderedContent" />
        <span v-if="isTruncated">...</span>
        <button v-if="hasMore" @click="showMore" :class="readMoreClass">
            {{ $t('common.readMore') }}
        </button>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    caption: {
        type: String,
        default: ''
    },
    mentions: {
        type: Array,
        default: () => []
    },
    tags: {
        type: Array,
        default: () => []
    },
    mentionPathPrefix: {
        type: String,
        default: '/@'
    },
    hashtagPathPrefix: {
        type: String,
        default: '/tag/'
    },
    mentionClass: {
        type: String,
        default: 'text-blue-500 hover:text-blue-700 hover:underline font-semibold'
    },
    hashtagClass: {
        type: String,
        default: 'text-blue-500 hover:text-blue-700 hover:underline font-semibold'
    },
    textSize: {
        type: String,
        default: 'text-sm'
    },
    rootClass: {
        type: String,
        default: 'text-gray-800 dark:text-slate-300 whitespace-pre-wrap break-all leading-relaxed'
    },
    maxCharLimit: {
        type: Number,
        default: null
    },
    moreCharLimit: {
        type: Number,
        default: 200
    },
    readMoreClass: {
        type: String,
        default: 'text-gray-500 hover:text-gray-700 ml-1 font-semibold cursor-pointer'
    }
})

const { autolinkCaption } = useUtils()
const currentCharLimit = ref(props.maxCharLimit)

const truncatedCaption = computed(() => {
    if (!props.maxCharLimit || !props.caption) {
        return props.caption
    }

    if (props.caption.length <= currentCharLimit.value) {
        return props.caption
    }

    let truncateAt = currentCharLimit.value
    const textUpToLimit = props.caption.substring(0, truncateAt)

    const lastSpace = textUpToLimit.lastIndexOf(' ')
    if (lastSpace > currentCharLimit.value * 0.8) {
        truncateAt = lastSpace
    }

    return props.caption.substring(0, truncateAt)
})

const isTruncated = computed(() => {
    return props.maxCharLimit && props.caption && props.caption.length > currentCharLimit.value
})

const hasMore = computed(() => {
    return isTruncated.value
})

const renderedContent = computed(() => {
    return autolinkCaption(truncatedCaption.value, props.mentions, props.tags, {
        mentionPath: (mention) => `${props.mentionPathPrefix}${mention.username}`,
        hashtagPath: (tag) => `${props.hashtagPathPrefix}${tag.toLowerCase()}`,
        mentionClass: props.mentionClass,
        hashtagClass: props.hashtagClass
    })
})

const showMore = () => {
    currentCharLimit.value += props.moreCharLimit
}
</script>
