<template>
    <SettingsLayout>
        <div class="p-6">
            <h1 class="text-2xl font-semibold tracking-tight mb-6 dark:text-gray-100">
                {{ $t('settings.privacy') }}
            </h1>
            <hr class="border-gray-300 dark:border-gray-700" />
            <section class="my-8">
                <h2 class="tracking-tight font-light mb-2 dark:text-gray-300">Direct Messages</h2>
                <label class="block text-sm font-light text-gray-700 dark:text-gray-300 mb-3">
                    Who can direct message you?
                </label>
                <div
                    class="bg-white dark:bg-gray-900 lg:rounded-xl lg:border border-gray-200 dark:border-gray-800 p-1 lg:p-6"
                >
                    <div>
                        <div class="space-y-2 grid lg:grid-cols-2 lg:space-x-4">
                            <label
                                v-for="option in dmVisibilityOptions"
                                :key="option.value"
                                class="flex items-center gap-3 py-2 px-4 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors border-2"
                                :class="
                                    form.dmVisibility === option.value
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                                        : 'bg-gray-50 dark:bg-gray-800 border-transparent'
                                "
                            >
                                <input
                                    type="radio"
                                    :value="option.value"
                                    v-model="form.dmVisibility"
                                    class="mt-0.5 w-4 h-4 text-blue-600 focus:ring-blue-500"
                                />
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ option.label }}
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                        {{ option.description }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <section class="my-8">
                <h2 class="tracking-tight font-light mb-2 dark:text-gray-300">Starter Kits</h2>
                <label class="block text-sm font-light text-gray-700 dark:text-gray-300 mb-3">
                    Who can add you to their starter kits?
                </label>
                <div
                    class="bg-white dark:bg-gray-900 lg:rounded-xl lg:border border-gray-200 dark:border-gray-800 p-1 lg:p-6"
                >
                    <div>
                        <div class="space-y-2 grid lg:grid-cols-2 lg:space-x-4">
                            <label
                                v-for="option in visibilityOptions"
                                :key="option.value"
                                class="flex items-center gap-3 py-2 px-4 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors border-2"
                                :class="
                                    form.visibility === option.value
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                                        : 'bg-gray-50 dark:bg-gray-800 border-transparent'
                                "
                            >
                                <input
                                    type="radio"
                                    :value="option.value"
                                    v-model="form.visibility"
                                    class="mt-0.5 w-4 h-4 text-blue-600 focus:ring-blue-500"
                                />
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ option.label }}
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                        {{ option.description }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <router-link to="/starter-kits/my-kits">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <i class="bx bx-book-add text-blue-500 text-[20px]"></i>
                                    <div class="flex flex-col gap-1">
                                        <h3 class="font-medium text-gray-800 dark:text-gray-100">
                                            Manage my Starter Kits
                                        </h3>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">
                                            View, edit and delete your Starter Kits
                                        </p>
                                    </div>
                                </div>
                                <i class="bx bx-chevron-right text-gray-400 dark:text-gray-500"></i>
                            </div>
                        </div>
                    </router-link>

                    <router-link to="/starter-kits/about">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <i class="bx bx-info-circle text-green-500 text-[20px]"></i>
                                    <div class="flex flex-col gap-1">
                                        <h3 class="font-medium text-gray-800 dark:text-gray-100">
                                            About Starter Kits
                                        </h3>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">
                                            Learn more about the Starter Kits feature
                                        </p>
                                    </div>
                                </div>
                                <i class="bx bx-chevron-right text-gray-400 dark:text-gray-500"></i>
                            </div>
                        </div>
                    </router-link>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { onMounted, ref, nextTick, watch } from 'vue'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import ToggleSwitch from '@/components/Form/ToggleSwitch.vue'
import axios from '~/plugins/axios'
const axiosInstance = axios.getAxiosInstance()

const form = ref({
    visibility: null,
    dmVisibility: null
})

watch(
    () => form.value.visibility,
    async (newVal, oldVal) => {
        if (oldVal === null) return
        try {
            await axiosInstance.post('/api/v1/account/settings/starter-kits/update', {
                state: newVal
            })
        } catch {}
    }
)

watch(
    () => form.value.dmVisibility,
    async (newVal, oldVal) => {
        if (oldVal === null) return
        try {
            await axiosInstance.post('/api/v1/account/settings/dm/update', {
                state: newVal
            })
        } catch {}
    }
)

const fetchPrivacyData = async () => {
    try {
        await axiosInstance.get('/api/v1/account/settings/starter-kits/status').then((res) => {
            form.value.visibility = res.data.data.state_int
        })
        await axiosInstance.get('/api/v1/account/settings/dm/status').then((res) => {
            form.value.dmVisibility = res.data.data.state
        })
    } catch {}
}

const visibilityOptions = [
    {
        value: 0,
        label: 'Disabled',
        description: 'Nobody can add you to their starter kits'
    },
    {
        value: 1,
        label: 'Accounts I Follow Only',
        description: 'People you follow can add you automatically (no approval needed)'
    },
    {
        value: 2,
        label: 'Accounts I Follow (With Approval)',
        description: 'People you follow can request to add you (requires your approval)'
    },
    // {
    //     value: 3,
    //     label: 'Local Accounts Only',
    //     description: 'Local Loops users can add you automatically'
    // },
    // {
    //     value: 4,
    //     label: 'Local Accounts (With Approval)',
    //     description: 'Local users can request to add you (requires your approval)'
    // },
    {
        value: 5,
        label: 'Permission Required',
        description: 'Anyone can request to add you, but you must approve each request'
    },
    {
        value: 6,
        label: 'Auto-Allow Everyone',
        description: 'Anyone (local and remote) can add you to starter kits automatically'
    }
]

const dmVisibilityOptions = [
    {
        value: 'off',
        label: 'Off',
        description: 'Nobody can message you'
    },
    {
        value: 'following',
        label: 'Accounts that follow me',
        description: 'People that follow you can message you'
    },
    {
        value: 'everyone',
        label: 'Everyone',
        description: 'Anyone can message you'
    }
]

onMounted(async () => {
    await fetchPrivacyData()
    await nextTick()
})
</script>
