<template>
    <SettingsLayout>
        <div class="p-6">
            <h1 class="text-2xl font-semibold tracking-tight mb-6 dark:text-gray-100">Safety</h1>
            <hr class="border-gray-300 dark:border-gray-700" />

            <section class="my-8">
                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    Blocking and restrictions
                </h2>
                <div class="flex flex-col gap-3">
                    <router-link
                        to="/dashboard/safety/blocked-accounts"
                        class="bg-white dark:bg-slate-950 rounded-lg shadow-sm"
                    >
                        <div class="flex justify-between items-center p-4">
                            <div class="flex items-center gap-5">
                                <h3 class="font-medium mb-0 dark:text-gray-300">
                                    Blocked accounts
                                </h3>
                                <p class="text-xs text-gray-500 -mb-1 font-light">
                                    Manage users you've blocked.
                                </p>
                            </div>
                            <div class="flex items-center">
                                <span class="mr-2 text-gray-700 text-sm"
                                    >{{ blockedAccountsCount }} blocked</span
                                >
                                <i class="bx bx-chevron-right text-[20px] text-gray-400"></i>
                            </div>
                        </div>
                    </router-link>
                </div>
            </section>

            <!-- <section class="mb-8">
                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Interactions</h2>
                <div class="flex flex-col gap-3">
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">Allow comments from everyone</h3>
                                <p class="text-xs text-gray-500 font-light">When disabled, only people you follow can comment on your videos.</p>
                            </div>
                            <ToggleSwitch v-model="allowAllComments" />
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">Allow duets and collaborations</h3>
                                <p class="text-xs text-gray-500 font-light">Let other users create duets or collaborations with your videos.</p>
                            </div>
                            <ToggleSwitch v-model="allowDuets" />
                        </div>
                    </div>
                </div>
            </section> -->

            <!--<section class="mb-8">
                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Reporting</h2>
                <div class="flex gap-3">
                    <div class="flex w-full justify-between items-center p-4 bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <span class="text-sm text-gray-600 dark:text-gray-300 font-light">Report a safety concern</span>
                        <button class="font-medium text-sm text-blue-500">Report</button>
                    </div>
                    <div class="flex w-full justify-between items-center p-4 bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <span class="text-sm text-gray-600  dark:text-gray-300 font-light">Safety center</span>
                        <router-link to="/help-center" class="font-medium text-sm text-blue-500">Learn more</router-link>
                    </div>
                </div>
            </section>-->
        </div>
    </SettingsLayout>
</template>

<script setup>
import { onMounted, ref, nextTick } from 'vue'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import ToggleSwitch from '@/components/Form/ToggleSwitch.vue'
import axios from '~/plugins/axios'
const axiosInstance = axios.getAxiosInstance()
const hideSensitiveContent = ref(true)
const filterExplicitLanguage = ref(false)
const blockedAccountsCount = ref(3)

const fetchTotalBlocked = async () => {
    try {
        await axiosInstance.get('/api/v1/account/settings/total-blocked-accounts').then((res) => {
            blockedAccountsCount.value = res.data.data.count
        })
    } catch {}
}

onMounted(async () => {
    await fetchTotalBlocked()
    await nextTick()
})
</script>
