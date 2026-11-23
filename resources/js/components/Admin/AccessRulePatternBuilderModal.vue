<template>
    <BaseModal
        v-model="show"
        title="Pattern builder"
        description="Stack blocks to build a pattern without writing regex. Matching ignores case."
        max-width="4xl"
    >
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="space-y-5 lg:col-span-3">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="space-y-1.5">
                        <span
                            class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            Applies to
                        </span>
                        <div class="flex gap-1 rounded-full bg-gray-100 p-1 dark:bg-gray-800">
                            <button
                                v-for="option in targetOptions"
                                :key="option.value"
                                type="button"
                                :class="segmentClass(target === option.value, true)"
                                @click="setTarget(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                    <div v-if="target === 'email'" class="space-y-1.5">
                        <span
                            class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            Match against
                        </span>
                        <div class="flex gap-1 rounded-full bg-gray-100 p-1 dark:bg-gray-800">
                            <button
                                v-for="option in scopeOptions"
                                :key="option.value"
                                type="button"
                                :class="segmentClass(scope === option.value)"
                                @click="scope = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!rawMode" class="space-y-2">
                    <span
                        class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Blocks
                    </span>
                    <div
                        class="flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800/50"
                    >
                        <button
                            type="button"
                            :aria-pressed="anchorStart"
                            :class="anchorClass(anchorStart)"
                            @click="anchorStart = !anchorStart"
                        >
                            {{ anchorStart ? 'Starts with' : 'Anywhere' }}
                        </button>
                        <div v-for="block in blocks" :key="block.id" :class="chipClass(block)">
                            <button
                                type="button"
                                class="flex min-w-0 items-center gap-2"
                                @click="selectedId = block.id"
                            >
                                <span :class="kindLabelClass(block.kind)">
                                    {{ kindLabels[block.kind] }}
                                </span>
                                <span
                                    class="max-w-[10rem] truncate font-mono text-xs text-gray-800 dark:text-gray-200"
                                >
                                    {{ chipSummary(block) }}
                                </span>
                                <span
                                    v-if="block.repeat !== 'once'"
                                    class="rounded bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    {{ repeatShort(block) }}
                                </span>
                            </button>
                            <button
                                type="button"
                                :aria-label="`Remove ${kindLabels[block.kind]} block`"
                                class="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                                @click="removeBlock(block.id)"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <button
                            type="button"
                            :aria-pressed="anchorEnd"
                            :class="anchorClass(anchorEnd)"
                            @click="anchorEnd = !anchorEnd"
                        >
                            {{ anchorEnd ? 'Ends here' : 'Anything after' }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="selectedBlock && !rawMode"
                    class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Editing: {{ kindLabels[selectedBlock.kind] }}
                        </span>
                        <div class="flex gap-1">
                            <button
                                type="button"
                                aria-label="Move block left"
                                :disabled="selectedIndex === 0"
                                class="flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800"
                                @click="moveBlock(-1)"
                            >
                                <ChevronLeftIcon class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                aria-label="Move block right"
                                :disabled="selectedIndex === blocks.length - 1"
                                class="flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800"
                                @click="moveBlock(1)"
                            >
                                <ChevronRightIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedBlock.kind === 'text'">
                        <label
                            for="pb-text"
                            class="mb-1 block text-xs text-gray-600 dark:text-gray-400"
                        >
                            Text to match
                        </label>
                        <input
                            id="pb-text"
                            v-model="selectedBlock.value"
                            type="text"
                            spellcheck="false"
                            autocomplete="off"
                            placeholder="promo"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>

                    <div v-if="selectedBlock.kind === 'oneOf'" class="flex flex-wrap gap-2">
                        <span
                            v-for="(option, index) in selectedBlock.options"
                            :key="`${option}-${index}`"
                            class="inline-flex h-8 items-center gap-1 rounded-full bg-violet-100 pl-3 pr-1 font-mono text-sm text-violet-800 dark:bg-violet-900/40 dark:text-violet-200"
                        >
                            {{ option }}
                            <button
                                type="button"
                                :aria-label="`Remove option ${option}`"
                                class="flex h-6 w-6 items-center justify-center rounded-full hover:bg-violet-200 dark:hover:bg-violet-800"
                                @click="selectedBlock.options.splice(index, 1)"
                            >
                                <XMarkIcon class="h-3 w-3" />
                            </button>
                        </span>
                        <input
                            v-model="optionDraft"
                            type="text"
                            aria-label="Add option"
                            spellcheck="false"
                            placeholder="Add option, press Enter"
                            class="h-8 min-w-[12rem] flex-1 rounded-full border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            @keydown.enter.prevent="addOption"
                            @blur="addOption"
                        />
                    </div>

                    <div v-if="selectedBlock.kind === 'anyOf'" class="space-y-2">
                        <div class="flex flex-wrap gap-4">
                            <label
                                class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="selectedBlock.letters"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                />
                                Letters a to z
                            </label>
                            <label
                                class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="selectedBlock.digits"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                />
                                Digits 0 to 9
                            </label>
                        </div>
                        <div>
                            <label
                                for="pb-symbols"
                                class="mb-1 block text-xs text-gray-600 dark:text-gray-400"
                            >
                                Also allow these characters
                            </label>
                            <input
                                id="pb-symbols"
                                v-model="selectedBlock.symbols"
                                type="text"
                                spellcheck="false"
                                placeholder="._-"
                                class="w-40 rounded-md border border-gray-300 bg-white px-3 py-1.5 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label for="pb-repeat" class="text-sm text-gray-700 dark:text-gray-300"
                            >Repeat</label
                        >
                        <select
                            id="pb-repeat"
                            v-model="selectedBlock.repeat"
                            class="h-8 rounded-md border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option
                                v-for="option in repeatOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <template v-if="selectedBlock.repeat === 'range'">
                            <input
                                v-model.number="selectedBlock.min"
                                type="number"
                                min="0"
                                max="64"
                                aria-label="Minimum repeats"
                                class="h-8 w-16 rounded-md border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                            <span class="text-sm text-gray-500">to</span>
                            <input
                                v-model.number="selectedBlock.max"
                                type="number"
                                min="0"
                                max="64"
                                aria-label="Maximum repeats"
                                class="h-8 w-16 rounded-md border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                        </template>
                    </div>
                </div>

                <div v-if="!rawMode" class="space-y-2">
                    <span
                        class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Add block
                    </span>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <button
                            v-for="kind in kindOrder"
                            :key="kind"
                            type="button"
                            class="h-10 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="addBlock(kind)"
                        >
                            {{ kindLabels[kind] }}
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span
                            class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            {{ rawMode ? 'Raw pattern' : 'Generated pattern' }}
                        </span>
                        <button
                            type="button"
                            class="text-sm font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400"
                            @click="toggleRaw"
                        >
                            {{ rawMode ? 'Back to builder' : 'Edit raw pattern' }}
                        </button>
                    </div>
                    <textarea
                        v-if="rawMode"
                        v-model="rawPattern"
                        rows="2"
                        spellcheck="false"
                        aria-label="Raw pattern"
                        class="w-full rounded-lg border border-gray-300 bg-gray-900 px-3.5 py-3 font-mono text-sm text-gray-50 dark:border-gray-600"
                    ></textarea>
                    <code
                        v-else
                        class="block min-h-[2.75rem] break-all rounded-lg bg-gray-900 px-3.5 py-3 font-mono text-sm text-gray-50"
                        >{{ pattern || ' ' }}</code
                    >
                    <p
                        v-if="!rawMode"
                        class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                    >
                        {{ description }}
                    </p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                        Raw edits are not reflected in the blocks. Going back to the builder
                        discards them.
                    </p>
                </div>
            </div>

            <div class="space-y-4 lg:col-span-2">
                <div class="space-y-2">
                    <span
                        class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Live test
                    </span>
                    <div
                        class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            v-for="(result, index) in sampleResults"
                            :key="`${result.value}-${index}`"
                            class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 dark:border-gray-800"
                        >
                            <span
                                class="break-all font-mono text-xs text-gray-900 dark:text-gray-100"
                            >
                                {{ result.value }}
                            </span>
                            <div class="flex flex-shrink-0 items-center gap-1">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                        result.matched
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                    ]"
                                >
                                    {{ result.matched ? 'BLOCKED' : 'ALLOWED' }}
                                </span>
                                <button
                                    type="button"
                                    :aria-label="`Remove ${result.value}`"
                                    class="flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-gray-600"
                                    @click="samples.splice(index, 1)"
                                >
                                    <XMarkIcon class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                        <input
                            v-model="sampleDraft"
                            type="text"
                            spellcheck="false"
                            :aria-label="`Add a test ${target}`"
                            :placeholder="
                                target === 'email'
                                    ? 'Type an email to test, press Enter'
                                    : 'Type a username to test, press Enter'
                            "
                            class="h-10 w-full border-0 bg-white px-3 text-sm focus:ring-0 dark:bg-gray-900 dark:text-white"
                            @keydown.enter.prevent="addSample"
                        />
                    </div>
                </div>

                <div
                    :class="[
                        'flex items-start gap-2.5 rounded-lg border px-3 py-2.5',
                        validation.tone === 'ok'
                            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
                            : validation.tone === 'error'
                              ? 'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-900/20'
                              : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60'
                    ]"
                >
                    <CheckCircleIcon
                        v-if="validation.tone === 'ok'"
                        class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-600"
                    />
                    <ExclamationTriangleIcon
                        v-else-if="validation.tone === 'error'"
                        class="mt-0.5 h-4 w-4 flex-shrink-0 text-rose-600"
                    />
                    <ArrowPathIcon
                        v-else-if="validation.tone === 'busy'"
                        class="mt-0.5 h-4 w-4 flex-shrink-0 animate-spin text-gray-400"
                    />
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ validation.title }}
                        </p>
                        <p
                            v-if="validation.detail"
                            class="text-xs text-gray-600 dark:text-gray-400"
                        >
                            {{ validation.detail }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="serverPreview?.valid"
                    :class="[
                        'space-y-1 rounded-xl border px-3.5 py-3',
                        serverPreview.matches
                            ? 'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-900/20'
                            : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60'
                    ]"
                >
                    <p
                        :class="[
                            'text-sm font-semibold',
                            serverPreview.matches
                                ? 'text-rose-900 dark:text-rose-200'
                                : 'text-gray-700 dark:text-gray-300'
                        ]"
                    >
                        {{
                            serverPreview.matches
                                ? `${formatNumber(serverPreview.matches)} existing ${serverPreview.matches === 1 ? 'account matches' : 'accounts match'}`
                                : 'No existing accounts match'
                        }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        Checked on the server against local accounts. Review and delete them from
                        the rules table after saving.
                    </p>
                    <div v-if="serverPreview.sample?.length" class="flex flex-wrap gap-1.5 pt-1">
                        <span
                            v-for="account in serverPreview.sample"
                            :key="account.profile_id"
                            class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-700 ring-1 ring-rose-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-rose-800"
                        >
                            @{{ account.username }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="serverPreview?.existing_rule_id"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                >
                    This pattern already exists as a rule.
                </div>

                <label
                    v-if="target === 'email'"
                    class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700"
                >
                    <input
                        v-model="alsoLogin"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Also block sign in for existing accounts with a matching email
                    </span>
                </label>
                <p
                    v-if="alsoLogin && serverPreview?.admin_conflict"
                    class="text-xs text-rose-600 dark:text-rose-400"
                >
                    {{ serverPreview.admin_conflict }}
                </p>

                <div>
                    <label
                        for="pb-note"
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Note
                    </label>
                    <input
                        id="pb-note"
                        v-model="note"
                        type="text"
                        maxlength="500"
                        placeholder="Why was this added?"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    />
                </div>

                <p v-if="error" class="text-sm text-rose-600 dark:text-rose-400">{{ error }}</p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <AnimatedButton variant="light" pill @click="show = false">Cancel</AnimatedButton>
                <AnimatedButton variant="primaryGradient" pill :disabled="!canSave" @click="save">
                    <div class="flex items-center gap-2">
                        <ArrowPathIcon v-if="saving" class="h-4 w-4 animate-spin" />
                        Save {{ target }} pattern
                    </div>
                </AnimatedButton>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import {
    ArrowPathIcon,
    CheckCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ExclamationTriangleIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/BaseModal.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import { accessRulesApi, accessRuleErrorMessage } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    modelValue: Boolean,
    initialType: { type: String, default: 'email' }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { formatNumber } = useUtils()

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const MAX_LENGTH = 255

const targetOptions = [
    { value: 'email', label: 'Email' },
    { value: 'username', label: 'Username' }
]

const scopeOptions = [
    { value: 'whole', label: 'Whole address' },
    { value: 'local', label: 'Before @' },
    { value: 'domain', label: 'Domain' }
]

const kindOrder = ['text', 'oneOf', 'anyOf', 'digits', 'anything']

const kindLabels = {
    text: 'Text',
    oneOf: 'One of',
    anyOf: 'Any of',
    digits: 'Digits',
    anything: 'Anything'
}

const repeatOptions = [
    { value: 'once', label: 'Exactly once' },
    { value: 'optional', label: 'Optional' },
    { value: 'oneOrMore', label: '1 or more' },
    { value: 'zeroOrMore', label: '0 or more' },
    { value: 'range', label: 'Between' }
]

let blockSeq = 0

const newBlock = (kind) => {
    blockSeq++
    const base = { id: blockSeq, kind, repeat: 'once', min: 1, max: 3 }
    if (kind === 'text') return { ...base, value: '' }
    if (kind === 'oneOf') return { ...base, options: [] }
    if (kind === 'anyOf') {
        return { ...base, letters: true, digits: true, symbols: '._-', repeat: 'zeroOrMore' }
    }
    if (kind === 'anything') return { ...base, repeat: 'zeroOrMore' }
    return base
}

const defaultSamples = (type) => (type === 'email' ? ['someone@example.com'] : ['someone'])

const target = ref(props.initialType)
const scope = ref('whole')
const anchorStart = ref(true)
const anchorEnd = ref(false)
const blocks = ref([newBlock('text')])
const selectedId = ref(blocks.value[0].id)
const optionDraft = ref('')
const rawMode = ref(false)
const rawPattern = ref('')
const samples = ref(defaultSamples(props.initialType))
const sampleDraft = ref('')
const alsoLogin = ref(false)
const note = ref('')
const serverPreview = ref(null)
const serverPattern = ref('')
const previewLoading = ref(false)
const saving = ref(false)
const error = ref('')

let previewTimer = null
let previewSeq = 0

const selectedIndex = computed(() =>
    blocks.value.findIndex((block) => block.id === selectedId.value)
)
const selectedBlock = computed(() => blocks.value[selectedIndex.value] || null)

const escapeText = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
const escapeClass = (value) => value.replace(/[\\\][^-]/g, '\\$&')

const quantifier = (block) => {
    if (block.repeat === 'optional') return '?'
    if (block.repeat === 'oneOrMore') return '+'
    if (block.repeat === 'zeroOrMore') return '*'
    if (block.repeat === 'range') {
        const min = Math.max(0, parseInt(block.min, 10) || 0)
        const max = parseInt(block.max, 10)
        return Number.isFinite(max) && max >= min ? `{${min},${max}}` : `{${min},}`
    }
    return ''
}

const fragment = (block) => {
    let core = ''
    let single = true

    if (block.kind === 'text') {
        if (!block.value) return ''
        core = escapeText(block.value)
        single = [...block.value].length === 1
    } else if (block.kind === 'oneOf') {
        const options = block.options.filter((option) => option !== '')
        if (!options.length) return ''
        if (options.length === 1) {
            core = escapeText(options[0])
            single = [...options[0]].length === 1
        } else {
            core = `(?:${options.map(escapeText).join('|')})`
        }
    } else if (block.kind === 'anyOf') {
        const set = `${block.letters ? 'a-z' : ''}${block.digits ? '0-9' : ''}${escapeClass(block.symbols || '')}`
        if (!set) return ''
        core = `[${set}]`
    } else if (block.kind === 'digits') {
        core = '\\d'
    } else {
        core = '.'
    }

    const q = quantifier(block)
    return q && !single ? `(?:${core})${q}` : `${core}${q}`
}

const body = computed(() => blocks.value.map(fragment).join(''))

const builtPattern = computed(() => {
    const content = body.value
    if (!content) return ''
    if (target.value === 'email' && scope.value === 'local') {
        return `^${anchorStart.value ? '' : '[^@]*'}${content}${anchorEnd.value ? '' : '[^@]*'}@`
    }
    if (target.value === 'email' && scope.value === 'domain') {
        return `@${anchorStart.value ? '' : '[^@]*'}${content}${anchorEnd.value ? '$' : ''}`
    }
    return `${anchorStart.value ? '^' : ''}${content}${anchorEnd.value ? '$' : ''}`
})

const pattern = computed(() => (rawMode.value ? rawPattern.value.trim() : builtPattern.value))

const repeatText = (block) => {
    if (block.repeat === 'optional') return ' (optional)'
    if (block.repeat === 'oneOrMore') return ' (1 or more)'
    if (block.repeat === 'zeroOrMore') return ' (0 or more)'
    if (block.repeat === 'range') return ` (${block.min || 0} to ${block.max || 'any'})`
    return ''
}

const listJoin = (parts) =>
    parts.length > 1 ? `${parts.slice(0, -1).join(', ')} or ${parts[parts.length - 1]}` : parts[0]

const describeBlock = (block) => {
    if (block.kind === 'text') return block.value ? `“${block.value}”${repeatText(block)}` : ''
    if (block.kind === 'oneOf') {
        const options = block.options.filter((option) => option !== '')
        return options.length
            ? `${options.map((o) => `“${o}”`).join(' or ')}${repeatText(block)}`
            : ''
    }
    if (block.kind === 'anyOf') {
        const parts = []
        if (block.letters) parts.push('letters')
        if (block.digits) parts.push('digits')
        if (block.symbols) parts.push(block.symbols.split('').join(' '))
        return parts.length ? `any of ${listJoin(parts)}${repeatText(block)}` : ''
    }
    if (block.kind === 'digits') return `a digit${repeatText(block)}`
    return `any character${repeatText(block)}`
}

const description = computed(() => {
    const parts = blocks.value.map(describeBlock).filter(Boolean)
    if (!parts.length) return 'Add a block to start building a pattern.'
    const subject =
        target.value === 'email' && scope.value === 'local'
            ? 'The part before @'
            : target.value === 'email' && scope.value === 'domain'
              ? 'The domain'
              : target.value === 'email'
                ? 'The address'
                : 'The username'
    const lead = anchorStart.value ? 'starts with' : 'contains'
    const tail = anchorEnd.value ? ', with nothing after it.' : '.'
    return `${subject} ${lead} ${parts.join(', then ')}${tail}`
})

const clientRegex = computed(() => {
    if (!pattern.value) return null
    try {
        return new RegExp(pattern.value, 'i')
    } catch (e) {
        return null
    }
})

const sampleResults = computed(() =>
    samples.value.map((value) => {
        const server =
            serverPattern.value === pattern.value
                ? serverPreview.value?.samples?.find((sample) => sample.value === value)
                : null
        const matched = server
            ? server.matched
            : clientRegex.value
              ? clientRegex.value.test(value.trim())
              : false
        return { value, matched }
    })
)

const previewIsCurrent = computed(
    () => serverPattern.value === pattern.value && !previewLoading.value
)

const validation = computed(() => {
    if (!pattern.value) {
        return { tone: 'idle', title: 'Add a block to build a pattern', detail: '' }
    }
    if (pattern.value.length > MAX_LENGTH) {
        return {
            tone: 'error',
            title: 'Pattern is too long',
            detail: `${pattern.value.length} of ${MAX_LENGTH} characters`
        }
    }
    if (!previewIsCurrent.value) {
        return { tone: 'busy', title: 'Checking pattern on the server', detail: '' }
    }
    if (!serverPreview.value?.valid) {
        return {
            tone: 'error',
            title: 'Pattern cannot be saved',
            detail: serverPreview.value?.error || ''
        }
    }
    return {
        tone: 'ok',
        title: 'Pattern is safe to save',
        detail: `Compiles on the server, ${pattern.value.length} of ${MAX_LENGTH} characters`
    }
})

const canSave = computed(
    () =>
        !saving.value &&
        validation.value.tone === 'ok' &&
        !serverPreview.value?.existing_rule_id &&
        !(alsoLogin.value && serverPreview.value?.admin_conflict)
)

const segmentClass = (active, accent = false) => [
    'h-8 rounded-full px-3.5 text-sm transition',
    active
        ? accent
            ? 'bg-red-500 font-semibold text-white'
            : 'bg-white font-semibold text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
        : 'font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
]

const anchorClass = (active) => [
    'inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold transition',
    active
        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
        : 'border border-dashed border-gray-400 text-gray-500 hover:text-gray-700 dark:border-gray-500 dark:text-gray-400'
]

const kindColors = {
    text: 'border-blue-300 dark:border-blue-700',
    oneOf: 'border-violet-300 dark:border-violet-700',
    anyOf: 'border-amber-300 dark:border-amber-700',
    digits: 'border-emerald-300 dark:border-emerald-700',
    anything: 'border-gray-300 dark:border-gray-600'
}

const kindTextColors = {
    text: 'text-blue-700 dark:text-blue-300',
    oneOf: 'text-violet-700 dark:text-violet-300',
    anyOf: 'text-amber-700 dark:text-amber-300',
    digits: 'text-emerald-700 dark:text-emerald-300',
    anything: 'text-gray-600 dark:text-gray-400'
}

const chipClass = (block) => [
    'flex h-9 max-w-full items-center gap-1 rounded-lg border bg-white pl-2.5 pr-1 dark:bg-gray-900',
    kindColors[block.kind],
    block.id === selectedId.value ? 'ring-2 ring-rose-400/60' : ''
]

const kindLabelClass = (kind) => ['text-[11px] font-bold uppercase', kindTextColors[kind]]

const chipSummary = (block) => {
    if (block.kind === 'text') return block.value || 'empty'
    if (block.kind === 'oneOf') return block.options.join(' | ') || 'no options'
    if (block.kind === 'anyOf') {
        return (
            [block.letters ? 'a-z' : '', block.digits ? '0-9' : '', block.symbols || '']
                .filter(Boolean)
                .join(' ') || 'nothing'
        )
    }
    if (block.kind === 'digits') return '0-9'
    return 'any'
}

const repeatShort = (block) => {
    if (block.repeat === 'optional') return 'optional'
    if (block.repeat === 'oneOrMore') return '1+'
    if (block.repeat === 'zeroOrMore') return '0+'
    if (block.repeat === 'range') return `${block.min || 0}-${block.max || 'n'}`
    return ''
}

const addBlock = (kind) => {
    const block = newBlock(kind)
    blocks.value.push(block)
    selectedId.value = block.id
}

const removeBlock = (id) => {
    const index = blocks.value.findIndex((block) => block.id === id)
    if (index === -1) return
    blocks.value.splice(index, 1)
    if (selectedId.value === id) {
        selectedId.value = blocks.value[Math.max(0, index - 1)]?.id ?? null
    }
}

const moveBlock = (direction) => {
    const index = selectedIndex.value
    const next = index + direction
    if (index < 0 || next < 0 || next >= blocks.value.length) return
    const list = [...blocks.value]
    const [block] = list.splice(index, 1)
    list.splice(next, 0, block)
    blocks.value = list
}

const addOption = () => {
    const value = optionDraft.value.trim()
    if (!value || !selectedBlock.value || selectedBlock.value.kind !== 'oneOf') return
    if (!selectedBlock.value.options.includes(value)) selectedBlock.value.options.push(value)
    optionDraft.value = ''
}

const addSample = () => {
    const value = sampleDraft.value.trim()
    if (!value) return
    if (!samples.value.includes(value) && samples.value.length < 20) samples.value.push(value)
    sampleDraft.value = ''
}

const setTarget = (value) => {
    if (target.value === value) return
    target.value = value
    scope.value = 'whole'
    alsoLogin.value = false
    samples.value = defaultSamples(value)
}

const toggleRaw = () => {
    if (!rawMode.value) rawPattern.value = builtPattern.value
    rawMode.value = !rawMode.value
}

const runPreview = () => {
    clearTimeout(previewTimer)
    const current = pattern.value

    if (!current || current.length > MAX_LENGTH) {
        previewSeq++
        serverPreview.value = null
        serverPattern.value = current
        previewLoading.value = false
        return
    }

    previewLoading.value = true

    previewTimer = setTimeout(async () => {
        const seq = ++previewSeq
        try {
            const response = await accessRulesApi.preview({
                type: target.value,
                value: current,
                match_type: 'pattern',
                blocks_login: target.value === 'email' && alsoLogin.value,
                samples: samples.value
            })
            if (seq === previewSeq) {
                serverPreview.value = response
                serverPattern.value = current
            }
        } catch (e) {
            if (seq === previewSeq) {
                serverPreview.value = { valid: false, error: accessRuleErrorMessage(e) }
                serverPattern.value = current
            }
        } finally {
            if (seq === previewSeq) previewLoading.value = false
        }
    }, 500)
}

watch(
    () => [pattern.value, target.value, alsoLogin.value, samples.value.join('\n')],
    () => {
        if (show.value) runPreview()
    }
)

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return
        target.value = props.initialType === 'username' ? 'username' : 'email'
        scope.value = 'whole'
        anchorStart.value = true
        anchorEnd.value = false
        blocks.value = [newBlock('text')]
        selectedId.value = blocks.value[0].id
        rawMode.value = false
        rawPattern.value = ''
        samples.value = defaultSamples(target.value)
        alsoLogin.value = false
        note.value = ''
        error.value = ''
        serverPreview.value = null
        serverPattern.value = ''
        runPreview()
    }
)

const save = async () => {
    if (!canSave.value) return
    saving.value = true
    error.value = ''

    try {
        const response = await accessRulesApi.createRule({
            type: target.value,
            value: pattern.value,
            match_type: 'pattern',
            blocks_login: target.value === 'email' && alsoLogin.value,
            blocks_registration: true,
            note: note.value.trim() || null
        })
        emit('saved', response?.data ?? response)
        show.value = false
    } catch (e) {
        error.value = accessRuleErrorMessage(e)
    } finally {
        saving.value = false
    }
}

onUnmounted(() => clearTimeout(previewTimer))
</script>
