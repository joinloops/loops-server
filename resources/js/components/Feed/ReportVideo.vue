<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
    videoId: {
        type: String,
        required: true
    },
    menuModelValue: {
        type: Boolean,
        default: undefined
    }
})

const emit = defineEmits(['update:menuModelValue'])

const isOpen = ref(false)
const currentStep = ref('main')
const selectedOption = ref(null)
const reportOptions = ref([])
const isLoading = ref(true)
const error = ref(null)

const fetchReportRules = async () => {
    try {
        isLoading.value = true
        error.value = null
        const response = await fetch('/api/v0/client/report-rules')
        if (!response.ok) {
            throw new Error('Failed to fetch report rules')
        }
        reportOptions.value = await response.json()
    } catch (err) {
        error.value = err.message
        console.error('Error fetching report rules:', err)
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchReportRules()
})

const openModal = () => {
    isOpen.value = true
    currentStep.value = 'main'
    selectedOption.value = null
}

const closeModal = () => {
    if (props.menuModelValue !== undefined) {
        emit('update:menuModelValue', false)
    }
    isOpen.value = false
}

const goToDetail = (option) => {
    selectedOption.value = option
    currentStep.value = 'detail'
}

const goBack = () => {
    currentStep.value = 'main'
    selectedOption.value = null
}

const handleSubmit = () => {
    console.log('Submitted report:', {
        videoId: props.videoId,
        reasonKey: selectedOption.value.key,
        reasonMessage: selectedOption.value.message
    })
    if (props.menuModelValue !== undefined) {
        emit('update:menuModelValue', false)
    }
    closeModal()
}

defineExpose({
    openModal,
    closeModal
})
</script>

<template>
    <slot name="trigger" :open="openModal">
        <button @click="openModal" class="px-4 py-2 bg-blue-500 text-white rounded">
            Open Report Modal
        </button>
    </slot>

    <Teleport to="body">
        <template v-if="isOpen">
            <div class="fixed inset-0 z-10 bg-black bg-opacity-50" @click="closeModal"></div>

            <div
                class="fixed inset-x-0 z-30 top-10 mx-auto max-w-lg max-h-[50dvh] bg-white dark:bg-slate-800 rounded-lg shadow-xl flex flex-col"
            >
                <div class="flex items-center justify-between p-4 border-b dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <button
                            v-if="currentStep === 'detail'"
                            @click="goBack"
                            class="p-1 hover:bg-gray-100 dark:text-slate-500 rounded"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                        <h2 class="text-lg font-semibold dark:text-slate-50">Report</h2>
                    </div>
                    <button
                        @click="closeModal"
                        class="p-1 hover:bg-gray-100 rounded dark:text-slate-500"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto flex-1">
                    <div v-if="currentStep === 'main'">
                        <div class="p-4">
                            <p
                                class="px-3 text-gray-600 mb-3 text-sm tracking-tight dark:text-slate-400"
                            >
                                Please select a scenario
                            </p>
                        </div>

                        <div v-if="isLoading" class="py-8 text-center">
                            <div
                                class="animate-spin h-8 w-8 mx-auto border-4 border-blue-500 border-t-transparent rounded-full"
                            ></div>
                        </div>

                        <div v-else-if="error" class="px-4 py-8 text-center">
                            <p class="text-red-500">{{ error }}</p>
                            <button
                                @click="fetchReportRules"
                                class="mt-4 px-4 py-2 text-sm text-blue-500 hover:text-blue-600"
                            >
                                Try again
                            </button>
                        </div>

                        <div v-else class="px-4 space-y-2 mb-10">
                            <button
                                v-for="option in reportOptions"
                                :key="option.key"
                                @click="goToDetail(option)"
                                class="w-full p-3 text-left bg-gray-50 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg flex items-center justify-between group"
                            >
                                <span class="text-gray-800 dark:text-slate-300">{{
                                    option.message
                                }}</span>
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-slate-500"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="currentStep === 'detail'">
                        <div class="bg-gray-100 dark:bg-slate-700 p-4">
                            <h3 class="font-light tracking-tight text-gray-500 dark:text-slate-400">
                                {{ selectedOption?.message }}
                            </h3>
                        </div>
                        <div class="px-4 py-10">
                            <div class="space-y-4 font-light text-gray-600 dark:text-slate-300">
                                <p>We don't allow the following:</p>
                                <ul class="list-disc ml-6">
                                    <li>
                                        Content that violates our community guidelines regarding
                                        {{ selectedOption?.message.toLowerCase() }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="currentStep === 'detail'"
                    class="p-4 border-t dark:border-slate-700 flex justify-end"
                >
                    <button
                        @click="handleSubmit"
                        class="py-2 px-4 bg-red-500 text-white font-medium text-sm rounded hover:bg-red-600"
                    >
                        Submit
                    </button>
                </div>
            </div>
        </template>
    </Teleport>
</template>
