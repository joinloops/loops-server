import { defineStore } from 'pinia'
import data from 'emoji-mart-vue-fast/data/all.json'
import { EmojiIndex } from 'emoji-mart-vue-fast/src'

export const useEmojiStore = defineStore('emoji', {
    state: () => ({
        emojiIndex: new EmojiIndex(data),
        recentEmojis: [],
        maxRecentEmojis: 20
    }),

    actions: {
        addRecentEmoji(emoji) {
            // Remove if exists and add to front
            this.recentEmojis = [
                emoji,
                ...this.recentEmojis.filter((e) => e.id !== emoji.id)
            ].slice(0, this.maxRecentEmojis)
        }
    }
})
