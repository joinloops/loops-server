<template>
    <div class="emoji-picker-wrapper">
        <slot name="trigger" :toggle="togglePicker" />

        <Teleport to="body">
            <div v-if="isOpen" class="emoji-picker-overlay" @click="closeOnOverlay">
                <div class="emoji-picker-container" @click.stop>
                    <Picker :data="emojiStore.emojiIndex" set="apple" @select="handleSelect" />
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Picker } from 'emoji-mart-vue-fast/src'
import { useEmojiStore } from '@/stores/emojiStore'
import 'emoji-mart-vue-fast/css/emoji-mart.css'

const props = defineProps({
    closeOnOutsideClick: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update:modelValue', 'select'])

const emojiStore = useEmojiStore()
const isOpen = ref(false)

const togglePicker = () => {
    isOpen.value = !isOpen.value
}

const closeOnOverlay = (e) => {
    if (props.closeOnOutsideClick) {
        isOpen.value = false
    }
}

const handleSelect = (emoji) => {
    emojiStore.addRecentEmoji(emoji)
    emit('update:modelValue', emoji.native)
    emit('select', emoji)
    isOpen.value = false
}
</script>

<style scoped>
.emoji-picker-wrapper {
    display: inline-block;
}

.emoji-picker-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.emoji-picker-container {
    background: white;
    border-radius: 8px;
    padding: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
}
</style>
