<template>
    <div class="relative">
        <div
            ref="editorRef"
            contenteditable="true"
            @input="handleInput"
            @keydown="handleKeyDown"
            @click="handleClick"
            @blur="handleBlur"
            @focus="handleFocus"
            :class="[
                'w-full p-2 border rounded-lg resize-none text-sm focus:outline-none overflow-y-auto whitespace-pre-wrap',
                disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-text',
                borderClass
            ]"
            :style="{ minHeight: minHeight, maxHeight: maxHeight }"
            :aria-label="placeholder"
            :aria-disabled="disabled"
            role="textbox"
            aria-multiline="true"
            @paste="handlePaste"
        ></div>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="showDropdown && filteredResults.length > 0"
                ref="dropdownRef"
                class="absolute z-50 mt-1 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto"
                :style="dropdownStyle"
                role="listbox"
                :aria-label="`${autocompleteType} suggestions`"
            >
                <button
                    v-for="(result, index) in filteredResults"
                    :key="result.id"
                    @mousedown.prevent="selectResult(result)"
                    @mouseenter="selectedIndex = index"
                    :class="[
                        'w-full px-4 py-2.5 text-left hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center space-x-3',
                        selectedIndex === index ? 'bg-gray-100 dark:bg-gray-700' : ''
                    ]"
                    role="option"
                    :aria-selected="selectedIndex === index"
                >
                    <div
                        v-if="result.avatar"
                        class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center text-white font-semibold text-xs flex-shrink-0"
                        :style="{
                            backgroundImage: result.avatar ? `url(${result.avatar})` : undefined,
                            backgroundSize: 'cover'
                        }"
                    >
                        <span v-if="!result.avatar">{{ getInitials(result.username) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                            {{
                                autocompleteType === 'mention'
                                    ? '@' + result.username
                                    : '#' + result.name
                            }}
                        </div>
                        <div
                            v-if="result.subtitle"
                            class="text-xs text-gray-500 dark:text-gray-400 truncate"
                        >
                            {{ result.subtitle }}
                        </div>
                    </div>
                </button>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Type @ to mention or # for hashtags...'
    },
    disabled: {
        type: Boolean,
        default: false
    },
    borderClass: {
        type: String,
        default:
            'border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-300 focus:border-[#F02C56]'
    },
    minHeight: {
        type: String,
        default: '80px'
    },
    maxHeight: {
        type: String,
        default: '200px'
    },
    fetchMentions: {
        type: Function,
        required: false
    },
    fetchHashtags: {
        type: Function,
        required: false
    },
    debounceMs: {
        type: Number,
        default: 300
    },
    validateMentions: {
        type: Boolean,
        default: false
    },
    validateHashtags: {
        type: Boolean,
        default: false
    },
    initialValidatedMentions: {
        type: Array,
        default: () => []
    },
    initialValidatedHashtags: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

const editorRef = ref(null)
const dropdownRef = ref(null)
const showDropdown = ref(false)
const autocompleteType = ref(null)
const searchQuery = ref('')
const selectedIndex = ref(0)
const results = ref([])
const isLoading = ref(false)
const dropdownStyle = ref({})
const isUpdatingContent = ref(false)
const validatedMentions = ref(new Set())
const validatedHashtags = ref(new Set())
let debounceTimer = null

const filteredResults = computed(() => results.value)

const handleFocus = (e) => {
    emit('focus', e)
    checkForAutocomplete()
}

const handleBlur = (e) => {
    emit('blur', e)
    setTimeout(() => {
        hideDropdown()
    }, 200)
}

const getPlainText = () => {
    if (!editorRef.value) return ''

    const clone = editorRef.value.cloneNode(true)

    const brs = clone.querySelectorAll('br')
    brs.forEach((br) => {
        br.replaceWith('\n')
    })

    const text = clone.textContent || ''
    return text.replace(/\u00A0/g, ' ')
}

const saveCursorPosition = () => {
    const selection = window.getSelection()
    if (!selection.rangeCount) return null

    const range = selection.getRangeAt(0)
    const preCaretRange = range.cloneRange()
    preCaretRange.selectNodeContents(editorRef.value)
    preCaretRange.setEnd(range.endContainer, range.endOffset)

    const clone = document.createElement('div')
    clone.appendChild(preCaretRange.cloneContents())
    const brs = clone.querySelectorAll('br')
    brs.forEach((br) => {
        br.replaceWith('\n')
    })

    return (clone.textContent || '').length
}

// Restore cursor position
const restoreCursorPosition = (position) => {
    if (!editorRef.value || position === null) return

    const selection = window.getSelection()
    const range = document.createRange()

    let charCount = 0
    const nodeStack = [editorRef.value]
    let node, foundNode, foundOffset

    while (!foundNode && (node = nodeStack.pop())) {
        if (node.nodeType === Node.TEXT_NODE) {
            const nextCharCount = charCount + node.length
            if (position <= nextCharCount) {
                foundNode = node
                foundOffset = position - charCount
            }
            charCount = nextCharCount
        } else if (node.nodeName === 'BR') {
            // Count <br> as 1 character (newline)
            if (position === charCount) {
                // Cursor is right at the <br>
                foundNode = node.parentNode
                foundOffset = Array.from(node.parentNode.childNodes).indexOf(node) + 1
                break
            }
            charCount += 1
        } else {
            // Traverse child nodes in reverse order
            for (let i = node.childNodes.length - 1; i >= 0; i--) {
                nodeStack.push(node.childNodes[i])
            }
        }
    }

    if (foundNode) {
        try {
            if (foundNode.nodeType === Node.TEXT_NODE) {
                range.setStart(foundNode, foundOffset)
            } else {
                range.setStart(foundNode, foundOffset)
            }
            range.collapse(true)
            selection.removeAllRanges()
            selection.addRange(range)
        } catch (e) {
            console.warn('Could not restore cursor position:', e)
        }
    }
}

const setContent = (text, cursorPos = null) => {
    if (!editorRef.value) return

    isUpdatingContent.value = true

    let rendered = renderContent(text)

    if (rendered.endsWith('</span>') || rendered.endsWith('<br>') || rendered.endsWith(' ')) {
        rendered += '\u200B'
    }

    editorRef.value.innerHTML = rendered

    if (cursorPos !== null) {
        restoreCursorPosition(cursorPos)
    }

    nextTick(() => {
        isUpdatingContent.value = false
    })
}

const renderContent = (text) => {
    if (!text) return ''

    // Split by line breaks to preserve them
    const lines = text.split('\n')

    const renderedLines = lines.map((line) => {
        // Escape HTML entities but not our tags
        let escaped = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

        // Match mentions: @ followed by username (with ._-) and optional @domain for webfinger
        // Format: @username or @username@domain.com
        // Username can contain: letters, numbers, underscore, dot, hyphen
        escaped = escaped.replace(
            /(^|[\s])@([\w._-]+(?:@[\w.-]+\.\w+)?)(?=[\s.,!?;:]|$)/g,
            (match, prefix, username) => {
                if (props.validateMentions && !validatedMentions.value.has(username)) {
                    return match
                }
                return `${prefix}<span class="text-[#F02C56] font-semibold">@${username}</span>`
            }
        )

        escaped = escaped.replace(
            /(^|[\s])#([\w_]+)(?=[\s.,!?;:]|$)/g,
            (match, prefix, hashtag) => {
                if (props.validateHashtags && !validatedHashtags.value.has(hashtag)) {
                    return match
                }
                return `${prefix}<span class="text-blue-500 dark:text-blue-400 font-semibold">#${hashtag}</span>`
            }
        )

        return escaped || ''
    })

    return renderedLines.join('<br>')
}

// Handle input changes
const handleInput = (e) => {
    if (isUpdatingContent.value) return

    const text = getPlainText()
    const cursorPos = saveCursorPosition()

    emit('update:modelValue', text)

    // Re-render with styling
    setContent(text, cursorPos)

    // Check for autocomplete after rendering
    nextTick(() => {
        checkForAutocomplete()
    })
}

// Handle paste - strip formatting
const handlePaste = (e) => {
    e.preventDefault()
    const text = e.clipboardData.getData('text/plain')
    document.execCommand('insertText', false, text)
}

// Check if we should show autocomplete
const checkForAutocomplete = () => {
    if (!editorRef.value) return

    const selection = window.getSelection()
    if (!selection.rangeCount) return

    const range = selection.getRangeAt(0)

    // Get text content before cursor from entire editor
    const fullText = getPlainText()
    const cursorPos = saveCursorPosition()

    if (cursorPos === null) {
        hideDropdown()
        return
    }

    const textBeforeCursor = fullText.substring(0, cursorPos)

    // Find the last @ or # before cursor that's not followed by a space yet
    // Match @ followed by username (with ._-) and optional @domain for webfinger
    // Format: @username or @username@domain.com
    const mentionMatch = textBeforeCursor.match(/(^|[\s])@([\w._-]*(?:@[\w.-]*\.?\w*)?)$/)
    const hashtagMatch = textBeforeCursor.match(/(^|[\s])#([\w_]*)$/)

    if (mentionMatch && props.fetchMentions) {
        autocompleteType.value = 'mention'
        searchQuery.value = mentionMatch[2]
        positionDropdown()
        showDropdown.value = true
        debouncedFetch()
    } else if (hashtagMatch && props.fetchHashtags) {
        autocompleteType.value = 'hashtag'
        searchQuery.value = hashtagMatch[2]
        positionDropdown()
        showDropdown.value = true
        debouncedFetch()
    } else {
        hideDropdown()
    }
}

// Debounced fetch
const debouncedFetch = () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(async () => {
        isLoading.value = true
        try {
            if (autocompleteType.value === 'mention' && props.fetchMentions) {
                results.value = await props.fetchMentions(searchQuery.value)

                // Add all returned mentions to validated set if validation is enabled
                if (props.validateMentions) {
                    results.value.forEach((result) => {
                        validatedMentions.value.add(result.username)
                    })
                    // Trigger re-render to update styling
                    const text = getPlainText()
                    const cursorPos = saveCursorPosition()
                    setContent(text, cursorPos)
                }
            } else if (autocompleteType.value === 'hashtag' && props.fetchHashtags) {
                results.value = await props.fetchHashtags(searchQuery.value)

                // Add all returned hashtags to validated set if validation is enabled
                if (props.validateHashtags) {
                    results.value.forEach((result) => {
                        validatedHashtags.value.add(result.name)
                    })
                    // Trigger re-render to update styling
                    const text = getPlainText()
                    const cursorPos = saveCursorPosition()
                    setContent(text, cursorPos)
                }
            }
        } catch (error) {
            console.error('Autocomplete fetch error:', error)
            results.value = []
        } finally {
            isLoading.value = false
        }
        selectedIndex.value = 0
    }, props.debounceMs)
}

// Position dropdown near cursor with smart overflow handling
const positionDropdown = () => {
    if (!editorRef.value) return

    const selection = window.getSelection()
    if (!selection.rangeCount) return

    const range = selection.getRangeAt(0)
    const rect = range.getBoundingClientRect()
    const editorRect = editorRef.value.getBoundingClientRect()

    // Dropdown dimensions (approximate)
    const dropdownHeight = 240 // max-h-60 (15rem = 240px)
    const dropdownWidth = 256 // w-64 (16rem = 256px)

    // Available space
    const spaceBelow = window.innerHeight - rect.bottom
    const spaceAbove = rect.top
    const spaceRight = window.innerWidth - rect.left

    // Determine vertical position
    let top, bottom
    if (spaceBelow >= dropdownHeight || spaceBelow > spaceAbove) {
        // Position below cursor
        top = `${rect.bottom - editorRect.top + 4}px`
        bottom = 'auto'
    } else {
        // Position above cursor
        bottom = `${editorRect.bottom - rect.top + 4}px`
        top = 'auto'
    }

    // Determine horizontal position
    let left = rect.left - editorRect.left

    // Adjust if dropdown would overflow right edge
    if (rect.left + dropdownWidth > window.innerWidth) {
        left = Math.max(0, window.innerWidth - dropdownWidth - editorRect.left - 16)
    }

    dropdownStyle.value = {
        top,
        bottom,
        left: `${left}px`
    }
}

// Hide dropdown
const hideDropdown = () => {
    showDropdown.value = false
    autocompleteType.value = null
    searchQuery.value = ''
    results.value = []
    selectedIndex.value = 0
}

// Handle keyboard navigation
const handleKeyDown = (e) => {
    if (e.key === 'Enter' && e.shiftKey) {
        return
    }

    if (showDropdown.value && filteredResults.value.length > 0) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault()
                selectedIndex.value = (selectedIndex.value + 1) % filteredResults.value.length
                scrollToSelected()
                break
            case 'ArrowUp':
                e.preventDefault()
                selectedIndex.value =
                    selectedIndex.value === 0
                        ? filteredResults.value.length - 1
                        : selectedIndex.value - 1
                scrollToSelected()
                break
            case 'Enter':
                e.preventDefault()
                selectResult(filteredResults.value[selectedIndex.value])
                break
            case 'Escape':
                e.preventDefault()
                hideDropdown()
                break
        }
    }
}

// Scroll selected item into view
const scrollToSelected = () => {
    nextTick(() => {
        if (!dropdownRef.value) return
        const selected = dropdownRef.value.children[selectedIndex.value]
        if (selected) {
            selected.scrollIntoView({ block: 'nearest' })
        }
    })
}

// Select a result
const selectResult = (result) => {
    if (!editorRef.value || !result) return

    const fullText = getPlainText()
    const cursorPos = saveCursorPosition()

    if (cursorPos === null) return

    const textBeforeCursor = fullText.substring(0, cursorPos)
    const textAfterCursor = fullText.substring(cursorPos)

    // Find the trigger character position
    const triggerChar = autocompleteType.value === 'mention' ? '@' : '#'
    const pattern =
        autocompleteType.value === 'mention'
            ? /(^|[\s])@([\w._-]*(?:@[\w.-]*\.?\w*)?)$/
            : /(^|[\s])#([\w_]*)$/

    const match = textBeforeCursor.match(pattern)

    if (!match) return

    let mentionType = undefined

    // Add to validated set
    if (autocompleteType.value === 'mention' && props.validateMentions) {
        validatedMentions.value.add(result.username)
        mentionType = result.username
    } else if (autocompleteType.value === 'hashtag' && props.validateHashtags) {
        validatedHashtags.value.add(result.name)
        mentionType = result.name
    }

    // Calculate the start position of the match (including the prefix space if any)
    const matchStart = cursorPos - match[0].length
    const prefixSpace = match[1] // The space or start of line

    // Build the new text
    const before = fullText.substring(0, matchStart)
    const replacement = `${prefixSpace}${triggerChar}${mentionType} `
    const after = textAfterCursor

    const newText = before + replacement + after
    const newCursorPos = matchStart + replacement.length

    emit('update:modelValue', newText)

    hideDropdown()

    // Update content and restore cursor
    setContent(newText, newCursorPos)
}

// Move cursor to end
const moveCursorToEnd = () => {
    if (!editorRef.value) return

    const range = document.createRange()
    const selection = window.getSelection()

    range.selectNodeContents(editorRef.value)
    range.collapse(false)
    selection.removeAllRanges()
    selection.addRange(range)
}

// Handle clicks
const handleClick = () => {
    checkForAutocomplete()
}

// Get initials for avatar
const getInitials = (name) => {
    if (!name) return '?'
    return name.substring(0, 2).toUpperCase()
}

// Watch for external value changes
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue && props.validateMentions) {
            // Extract mentions from initial value
            const mentionRegex = /@([\w._-]+(?:@[\w.-]+\.\w+)?)/g
            const matches = [...newValue.matchAll(mentionRegex)]

            matches.forEach((match) => {
                const username = match[1]
                if (!props.initialValidatedMentions.includes(username)) {
                    validatedMentions.value.add(username)
                }
            })
        }

        const currentText = getPlainText()
        if (newValue !== currentText && !isUpdatingContent.value) {
            setContent(newValue)
        }
    },
    { immediate: true }
)

// Reposition dropdown when it becomes visible or results change
watch([showDropdown, () => results.value.length], () => {
    if (showDropdown.value) {
        nextTick(() => {
            positionDropdown()
        })
    }
})

// Initialize
onMounted(() => {
    // Initialize validated sets with initial values
    if (props.initialValidatedMentions.length > 0) {
        validatedMentions.value = new Set(props.initialValidatedMentions)
    }
    if (props.initialValidatedHashtags.length > 0) {
        validatedHashtags.value = new Set(props.initialValidatedHashtags)
    }

    if (props.modelValue) {
        setContent(props.modelValue)
    }

    // Set placeholder attribute
    if (props.placeholder && editorRef.value) {
        editorRef.value.setAttribute('data-placeholder', props.placeholder)
    }

    // Reposition dropdown on scroll
    const handleScroll = () => {
        if (showDropdown.value) {
            positionDropdown()
        }
    }

    window.addEventListener('scroll', handleScroll, true)
    window.addEventListener('resize', handleScroll)

    // Cleanup
    onBeforeUnmount(() => {
        window.removeEventListener('scroll', handleScroll, true)
        window.removeEventListener('resize', handleScroll)
    })
})

onBeforeUnmount(() => {
    clearTimeout(debounceTimer)
})

const insertAtCursor = (text) => {
    if (!editorRef.value) return

    const currentText = getPlainText()
    const cursorPos = saveCursorPosition()

    if (cursorPos === null) {
        // If no cursor position, append to end
        const newText = currentText + text
        emit('update:modelValue', newText)
        setContent(newText)
        moveCursorToEnd()
        return
    }

    // Insert at cursor position
    const before = currentText.substring(0, cursorPos)
    const after = currentText.substring(cursorPos)
    const newText = before + text + after
    const newCursorPos = cursorPos + text.length

    emit('update:modelValue', newText)
    setContent(newText, newCursorPos)
}

// Expose methods for parent component
defineExpose({
    focus: () => editorRef.value?.focus(),
    moveCursorToEnd,
    insertAtCursor,
    clear: () => {
        if (editorRef.value) {
            editorRef.value.innerHTML = ''
            emit('update:modelValue', '')
        }
    },
    addValidatedMention: (username) => {
        validatedMentions.value.add(username)
        // Re-render to update styling
        const text = getPlainText()
        const cursorPos = saveCursorPosition()
        setContent(text, cursorPos)
    },
    addValidatedHashtag: (hashtag) => {
        validatedHashtags.value.add(hashtag)
        // Re-render to update styling
        const text = getPlainText()
        const cursorPos = saveCursorPosition()
        setContent(text, cursorPos)
    },
    clearValidatedMentions: () => {
        validatedMentions.value.clear()
        // Re-render to update styling
        const text = getPlainText()
        const cursorPos = saveCursorPosition()
        setContent(text, cursorPos)
    },
    clearValidatedHashtags: () => {
        validatedHashtags.value.clear()
        // Re-render to update styling
        const text = getPlainText()
        const cursorPos = saveCursorPosition()
        setContent(text, cursorPos)
    }
})
</script>

<style scoped>
/* Placeholder styling */
[contenteditable]:empty:before {
    content: attr(data-placeholder);
    color: #9ca3af;
    pointer-events: none;
}

:deep(.dark) [contenteditable]:empty:before {
    color: #6b7280;
}

/* Custom scrollbar */
[contenteditable] {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.3) transparent;
}

[contenteditable]::-webkit-scrollbar {
    width: 6px;
}

[contenteditable]::-webkit-scrollbar-track {
    background: transparent;
}

[contenteditable]::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 3px;
}

[contenteditable]::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.5);
}

/* Dropdown scrollbar */
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.3) transparent;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.5);
}
</style>
