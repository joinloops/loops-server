<template>
    <SettingsLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <button
                    class="flex items-center text-gray-500 hover:text-gray-400 cursor-pointer"
                    @click="$router.back()"
                >
                    <i class="bx bx-arrow-back text-[20px] mr-1"></i>
                </button>
                <h1 class="text-2xl font-semibold tracking-tight dark:text-gray-300">
                    {{ $t('settings.accountData') }}
                </h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <section class="my-8">
                <div
                    class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6"
                >
                    <div class="flex items-start gap-3">
                        <i class="bx bx-info-circle text-blue-500 text-[20px] mt-0.5"></i>
                        <div>
                            <h3 class="font-medium text-blue-800 dark:text-blue-300 mb-1">
                                {{ $t('settings.yourDataYourControl') }}
                            </h3>
                            <p class="text-sm text-blue-700 dark:text-blue-400">
                                {{ $t('settings.yourDataYourControlMessage') }}.
                            </p>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ $t('settings.dataExport') }}
                </h2>
                <div class="flex flex-col gap-3 mb-6">
                    <!-- <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="font-medium text-gray-800 mb-2 dark:text-gray-300">Complete account export</h3>
                                    <p class="text-sm text-gray-600">Download all your account data including videos, comments, likes, and account information.</p>
                                </div>
                                <button 
                                    class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                    :disabled="isExporting"
                                    @click="requestFullExport"
                                >
                                    {{ isExporting ? 'Preparing...' : 'Request export' }}
                                </button>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-slate-900 rounded-lg p-3">
                                <h4 class="font-medium text-gray-800 mb-2 dark:text-gray-300">What's included:</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                                    <div class="flex items-center gap-2">
                                        <i class="bx bx-check text-green-500 text-[14px]"></i>
                                        <span>All videos and media</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="bx bx-check text-green-500 text-[14px]"></i>
                                        <span>Comments and interactions</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="bx bx-check text-green-500 text-[14px]"></i>
                                        <span>Profile information</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="bx bx-check text-green-500 text-[14px]"></i>
                                        <span>Account settings</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="bx bx-check text-green-500 text-[14px]"></i>
                                        <span>Followers and following</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="bx bx-check text-green-500 text-[14px]"></i>
                                        <span>Federation interactions</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="p-6">
                            <h3 class="font-medium text-gray-800 mb-4 dark:text-gray-300">
                                {{ $t('settings.selectiveDataExport') }}
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                {{ $t('settings.selectiveDataExportMessage') }}
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <button
                                    v-for="exportType in selectiveExports"
                                    :key="exportType.id"
                                    class="p-3 border border-gray-200 dark:border-gray-800 rounded-lg hover:border-blue-300 dark:hover:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors text-left cursor-pointer"
                                    @click="requestSelectiveExport(exportType.id)"
                                >
                                    <div class="flex items-center gap-3">
                                        <i
                                            :class="exportType.icon"
                                            class="text-blue-500 text-[20px]"
                                        ></i>
                                        <div>
                                            <h4
                                                class="font-medium text-gray-800 dark:text-gray-300"
                                            >
                                                {{ exportType.name }}
                                            </h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-500">
                                                {{ exportType.description }}
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ $t('settings.exportHistory') }}
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="font-medium dark:text-gray-300">
                            {{ $t('settings.recentExports') }}
                        </h3>
                    </div>
                    <div class="h-96 overflow-y-auto">
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            <div
                                v-for="export_item in exportHistory"
                                :key="export_item.id"
                                class="p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
                                        >
                                            <i class="bx bx-download text-gray-600 text-[20px]"></i>
                                        </div>
                                        <div>
                                            <h4
                                                class="font-medium text-gray-800 dark:text-gray-300"
                                            >
                                                {{ export_item.type }}
                                            </h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Requested
                                                {{ formatTimeAgo(export_item.date) }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                File size:
                                                {{ export_item.size }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-5">
                                        <span
                                            class="text-xs px-2 py-1 rounded-full font-medium"
                                            :class="getStatusClass(export_item.status)"
                                        >
                                            {{ export_item.status }}
                                        </span>
                                        <button
                                            v-if="export_item.status === 'Ready'"
                                            class="text-sm text-blue-500 font-medium hover:text-blue-700 cursor-pointer"
                                            @click="downloadExport(export_item)"
                                        >
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="exportHistory.length === 0" class="p-8 text-center">
                                <i class="bx bx-folder-open text-gray-300 text-[48px] mb-4"></i>
                                <h3 class="font-medium text-gray-600 mb-2">
                                    {{ $t('settings.noExportsYet') }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    {{ $t('settings.noExportsYetMessage') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ $t('settings.dataInsights') }}
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <h3 class="font-medium text-gray-800 mb-4 dark:text-gray-300">
                            {{ $t('settings.yourAccountStatistics') }}
                        </h3>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600 dark:text-white mb-1">
                                    {{ dataStats.videos }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('settings.videosPosted') }}
                                </div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="text-2xl font-bold text-green-600 dark:text-white mb-1">
                                    {{ dataStats.comments }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('settings.commentsMade') }}
                                </div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div
                                    class="text-2xl font-bold text-purple-600 dark:text-white mb-1"
                                >
                                    {{ dataStats.likes }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('settings.likesGiven') }}
                                </div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div
                                    class="text-2xl font-bold text-orange-600 dark:text-white mb-1"
                                >
                                    {{ dataStats.watchTime }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('settings.hoursWatched') }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/30 rounded-lg p-3"
                        >
                            <div class="flex items-center gap-2">
                                <i
                                    class="bx bx-data text-blue-500 dark:text-blue-200 text-[16px]"
                                ></i>
                                <span class="text-sm font-medium text-blue-800 dark:text-blue-200"
                                    >{{ $t('settings.totalDataSize') }}
                                    {{ dataStats.totalSize }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ $t('settings.dataManagement') }}
                </h2>
                <div class="flex flex-col gap-3 mb-6">
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[70%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">
                                    {{ $t('settings.dataRetention') }}
                                </h3>
                                <p class="text-xs text-gray-500 font-light">
                                    {{ $t('settings.dataRetentionMessage') }}
                                    <router-link
                                        to="/site/kb/x34"
                                        class="font-medium text-blue-400"
                                        >{{ $t('common.learnMore') }}</router-link
                                    >
                                </p>
                            </div>
                            <select
                                v-model="dataRetentionPeriod"
                                class="px-3 py-2 dark:text-gray-300 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="1year">1 year</option>
                                <option value="2years">2 years</option>
                                <option value="5years">5 years</option>
                                <option value="never">Keep forever</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[80%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">
                                    {{ $t('settings.analyticsTracking') }}
                                </h3>
                                <p class="text-xs text-gray-500 font-light">
                                    {{ $t('settings.analyticsTrackingMessage') }}
                                    <router-link to="/privacy" class="font-medium text-blue-400">{{
                                        $t('common.learnMore')
                                    }}</router-link>
                                </p>
                            </div>
                            <ToggleSwitch v-model="analyticsTracking" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[80%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">
                                    {{ $t('settings.dataSharingForResearch') }}
                                </h3>
                                <p class="text-xs text-gray-500 font-light">
                                    {{ $t('settings.dataSharingForResearchMessage') }}

                                    <router-link
                                        to="/site/kb/x34"
                                        class="font-medium text-blue-400"
                                        >{{ $t('common.learnMore') }}</router-link
                                    >
                                </p>
                            </div>
                            <ToggleSwitch v-model="researchDataSharing" />
                        </div>
                    </div>
                </div>

                <!-- <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Data deletion</h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                    <div class="p-6">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <div class="flex items-start gap-3">
                                <i class="bx bx-error text-red-500 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-red-800 mb-1">Permanent data deletion</h4>
                                    <p class="text-sm text-red-700">This action cannot be undone. Consider downloading your data first.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <button class="p-3 border border-red-200 rounded-lg hover:border-red-300 hover:bg-red-50 transition-colors text-left">
                                <div class="flex items-center gap-3">
                                    <i class="bx bx-video-off text-red-500 text-[20px]"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-800 dark:text-gray-300">Delete all videos</h4>
                                        <p class="text-xs text-gray-600">Permanently remove all your videos</p>
                                    </div>
                                </div>
                            </button>
                            
                            <button class="p-3 border border-red-200 rounded-lg hover:border-red-300 hover:bg-red-50 transition-colors text-left">
                                <div class="flex items-center gap-3">
                                    <i class="bx bx-message-square-x text-red-500 text-[20px]"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-800 dark:text-gray-300">Delete all comments</h4>
                                        <p class="text-xs text-gray-600">Remove all your comments and replies</p>
                                    </div>
                                </div>
                            </button>
                            
                            <button class="p-3 border border-red-200 rounded-lg hover:border-red-300 hover:bg-red-50 transition-colors text-left">
                                <div class="flex items-center gap-3">
                                    <i class="bx bx-history text-red-500 text-[20px]"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-800 dark:text-gray-300">Clear watch history</h4>
                                        <p class="text-xs text-gray-600">Remove your viewing history</p>
                                    </div>
                                </div>
                            </button>
                            
                            <button class="p-3 border border-red-200 rounded-lg hover:border-red-300 hover:bg-red-50 transition-colors text-left">
                                <div class="flex items-center gap-3">
                                    <i class="bx bx-trash text-red-500 text-[20px]"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-800 dark:text-gray-300">Delete all data</h4>
                                        <p class="text-xs text-gray-600">Remove everything except account</p>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div> -->
            </section>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import ToggleSwitch from '@/components/Form/ToggleSwitch.vue'
import axios from '~/plugins/axios'
const axiosInstance = axios.getAxiosInstance()
import { useAlertModal } from '@/composables/useAlertModal.js'
const { alertModal, confirmModal } = useAlertModal()
import { useUtils } from '@/composables/useUtils'
const { formatTimeAgo } = useUtils()

const dataStats = ref({
    videos: 0,
    comments: 0,
    likes: 0,
    watchTime: 0,
    totalSize: 'Loading'
})
const isExporting = ref(false)
const isUpdatingSettings = ref(false)
const dataRetentionPeriod = ref('2years')
const analyticsTracking = ref(true)
const researchDataSharing = ref(false)
const exportHistory = ref([])

const isInitialDataLoaded = ref(false)

const fetchAccountData = async () => {
    try {
        await axiosInstance.get('/api/v1/account/data/insights').then((res) => {
            dataStats.value.videos = res.data.data.videos
            dataStats.value.comments = res.data.data.comments
            dataStats.value.likes = res.data.data.likes
            dataStats.value.watchTime = res.data.data.watchTime
            dataStats.value.totalSize = res.data.data.totalSize
        })

        await axiosInstance.get('/api/v1/account/data/export/history').then((res) => {
            exportHistory.value = res.data.data
        })

        await axiosInstance
            .get('/api/v1/account/data/settings')
            .then((res) => {
                dataRetentionPeriod.value = res.data.data.dataRetentionPeriod
                analyticsTracking.value = res.data.data.analyticsTracking
                researchDataSharing.value = res.data.data.researchDataSharing
            })
            .finally(() => {
                isInitialDataLoaded.value = true
            })
    } catch (error) {
        console.error('Error fetching account data:', error)
    }
}

const updateDataSettings = async () => {
    if (!isInitialDataLoaded.value) return

    try {
        isUpdatingSettings.value = true

        const payload = {
            data_retention_period: dataRetentionPeriod.value,
            analytics_tracking: analyticsTracking.value,
            research_data_sharing: researchDataSharing.value
        }

        const response = await axiosInstance.put('/api/v1/account/data/settings', payload)
    } catch (error) {
        console.error('Error updating settings:', error)
    } finally {
        isUpdatingSettings.value = false
    }
}

watch(
    [dataRetentionPeriod, analyticsTracking, researchDataSharing],
    () => {
        updateDataSettings()
    },
    {
        flush: 'post'
    }
)

const selectiveExports = ref([
    {
        id: 'videos',
        name: 'Videos only',
        description: 'All your videos and their metadata',
        icon: 'bx bx-video'
    },
    {
        id: 'profile',
        name: 'Profile data',
        description: 'Profile info, settings, and preferences',
        icon: 'bx bx-user'
    },
    {
        id: 'interactions',
        name: 'Interactions',
        description: 'Comments, likes, and activity history',
        icon: 'bx bx-heart'
    },
    {
        id: 'followers',
        name: 'Social connections',
        description: 'Followers, following, and relationships',
        icon: 'bx bx-group'
    }
])

const getStatusClass = (status) => {
    switch (status) {
        case 'Ready':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        case 'Processing':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
        case 'Expired':
        case 'Failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-300'
    }
}

const requestFullExport = () => {
    // todo: finish
}

const requestSelectiveExport = async (type) => {
    const exportType = selectiveExports.value.find((e) => e.id === type)

    const result = await confirmModal(
        'Selective data export',
        `Are you sure you want to export <strong>${exportType.name.toLowerCase()}</strong> that contains ${exportType.description.toLowerCase()}?`,
        'Confirm',
        'Cancel'
    )

    if (result) {
        await axiosInstance
            .post('/api/v1/account/data/export/selective', {
                type: exportType.id
            })
            .then((res) => {
                alertModal(
                    'Success!',
                    'We are preparing your data export, it may take a few minutes.',
                    [
                        {
                            type: 'primary',
                            text: 'Ok',
                            callback: () => {
                                window.location.reload()
                            }
                        }
                    ]
                )
            })
            .catch((err) => {
                if (err.response.data?.error?.message) {
                    alertModal('Oops!', err.response.data?.error?.message)
                } else {
                    alertModal(
                        'An Error Occured',
                        'An unknown error occured, please try again later or contact support.'
                    )
                }
            })
    }
}

const slugify = (str) => {
    return str
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '')
}

const downloadExport = async (exportId) => {
    try {
        const response = await axiosInstance.get(
            `/api/v1/account/data/export/${exportId.id}/download`,
            { responseType: 'blob' }
        )
        const slug = slugify(exportId.type)
        const blob = new Blob([response.data], {
            type: response.headers['content-type']
        })
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `loops_export_${slug}_${exportId.id}.zip`
        document.body.appendChild(a)
        a.click()
        a.remove()
        window.URL.revokeObjectURL(url)
    } catch (error) {
        alertModal(
            'Download failed!',
            'An error occured when attempting to download this export, this means it could be expired or is temporarily unavailable.<br/><br />Please try again later, or contact support for assistance.'
        )
        console.error('Download error:', error)
    }
}

onMounted(() => fetchAccountData())
</script>
