<template>
    <SettingsLayout>
        <div class="p-6">
            <h1 class="text-2xl font-semibold tracking-tight mb-6 dark:text-gray-100">
                {{ t('settings.manageYourLoopsAccount') }}
            </h1>
            <hr class="border-gray-300 dark:border-gray-700" />
            <section class="my-8">
                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ t('settings.accountControl') }}
                </h2>
                <div class="flex gap-3 flex-col lg:flex-row">
                    <div
                        class="flex w-full justify-between items-center p-4 bg-white dark:bg-slate-950 rounded-lg shadow-sm"
                    >
                        <span class="text-sm text-gray-600 font-light">{{
                            t('settings.deactivateAccount')
                        }}</span>
                        <router-link
                            to="/dashboard/account/deactivate"
                            class="font-medium text-sm text-red-500"
                            >{{ t('settings.deactivate') }}</router-link
                        >
                    </div>
                    <div
                        class="flex w-full justify-between items-center p-4 bg-white dark:bg-slate-950 rounded-lg shadow-sm"
                    >
                        <span class="text-sm text-gray-600 font-light">{{
                            t('settings.deleteAccount')
                        }}</span>
                        <router-link
                            to="/dashboard/account/delete"
                            class="font-medium text-sm text-red-500"
                            >{{ t('settings.delete') }}</router-link
                        >
                    </div>
                </div>
            </section>

            <section class="my-8">
                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ t('settings.accountInformation') }}
                </h2>
                <div class="flex flex-col gap-3">
                    <router-link
                        to="/dashboard/account/status"
                        class="bg-white dark:bg-slate-950 dark:hover:bg-slate-800 transition-colors hover:bg-gray-50 rounded-lg shadow-lg"
                    >
                        <div class="flex justify-between items-center p-4">
                            <div class="flex items-center gap-5">
                                <h3 class="font-medium mb-0 dark:text-gray-300">
                                    {{ t('settings.status') }}
                                </h3>
                                <p class="hidden lg:block text-xs text-gray-500 -mb-1 font-light">
                                    {{ t('settings.weMayRestrictAccounts') }}
                                    <a href="#" class="text-blue-500 font-medium text-xs">{{
                                        t('common.learnMore')
                                    }}</a>
                                </p>
                            </div>
                            <div class="flex items-center">
                                <span class="text-green-600 flex items-center text-sm gap-2"
                                    ><i class="bx bx-check text-[20px]"></i>
                                    {{ t('settings.noRestrictions') }}</span
                                >
                            </div>
                        </div>
                    </router-link>
                    <router-link
                        to="/dashboard/account/email"
                        class="bg-white dark:bg-slate-950 dark:hover:bg-slate-800 transition-colors hover:bg-gray-50 rounded-lg shadow-lg"
                    >
                        <div class="flex justify-between items-center p-4">
                            <div class="flex items-center gap-5">
                                <h3 class="font-medium mb-0 dark:text-gray-300">
                                    {{ t('settings.email') }}
                                </h3>
                                <p class="hidden lg:block text-xs text-gray-500 -mb-1 font-light">
                                    {{ t('settings.manageYourAccountEmail') }}
                                </p>
                            </div>
                            <div class="flex items-center">
                                <i class="bx bx-chevron-right text-[20px] text-gray-400"></i>
                            </div>
                        </div>
                    </router-link>
                    <router-link
                        to="/dashboard/account/security"
                        class="bg-white dark:bg-slate-950 dark:hover:bg-slate-800 transition-colors hover:bg-gray-50 rounded-lg shadow-lg"
                    >
                        <div class="flex justify-between items-center p-4">
                            <div class="flex items-center gap-5">
                                <h3 class="font-medium mb-0 dark:text-gray-300">
                                    {{ t('settings.security') }}
                                </h3>
                                <p class="hidden lg:block text-xs text-gray-500 -mb-1 font-light">
                                    {{ t('settings.manageYourAccountSecuritySettings') }}
                                </p>
                            </div>
                            <div class="flex items-center">
                                <i class="bx bx-chevron-right text-[20px] text-gray-400"></i>
                            </div>
                        </div>
                    </router-link>
                    <router-link
                        to="/dashboard/account/data"
                        class="bg-white dark:bg-slate-950 dark:hover:bg-slate-800 transition-colors hover:bg-gray-50 rounded-lg shadow-lg"
                    >
                        <div class="flex justify-between items-center p-4">
                            <div class="flex items-center gap-5">
                                <h3 class="font-medium mb-0 dark:text-gray-300">
                                    {{ t('settings.accountData') }}
                                </h3>
                                <p class="hidden lg:block text-xs text-gray-500 -mb-1 font-light">
                                    {{ t('settings.downloadYourAccountDataExport') }}
                                </p>
                            </div>
                            <div class="flex items-center">
                                <i class="bx bx-chevron-right text-[20px] text-gray-400"></i>
                            </div>
                        </div>
                    </router-link>

                    <router-link
                        to="/dashboard/safety/blocked-accounts"
                        class="lg:hidden bg-white dark:bg-slate-950 dark:hover:bg-slate-800 transition-colors hover:bg-gray-50 rounded-lg shadow-lg"
                    >
                        <div class="flex justify-between items-center p-4">
                            <div class="flex items-center gap-5">
                                <h3 class="font-medium mb-0 dark:text-gray-300">
                                    Blocked accounts
                                </h3>
                                <p class="hidden lg:block text-xs text-gray-500 -mb-1 font-light">
                                    Manage users you've blocked.
                                </p>
                            </div>
                            <div class="flex items-center">
                                <i class="bx bx-chevron-right text-[20px] text-gray-400"></i>
                            </div>
                        </div>
                    </router-link>
                </div>
            </section>

            <section class="hidden mb-8">
                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ t('settings.privacy') }}
                </h2>
                <div class="flex gap-3">
                    <div
                        class="bg-white dark:bg-slate-950 transition-colors rounded-lg shadow-sm flex-1"
                    >
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">
                                    {{ t('settings.privateAccount') }}
                                </h3>
                                <p class="text-xs text-gray-500 font-light">
                                    {{ t('settings.withAPrivateAccountMessage') }}
                                    <a href="#" class="text-blue-500 font-medium text-xs">{{
                                        t('common.learnMore')
                                    }}</a>
                                </p>
                            </div>
                            <ToggleSwitch v-model="isPrivateAccount" />
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-950 transition-colors rounded-lg shadow-sm flex-1"
                    >
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">
                                    {{ t('settings.enableFederation') }}
                                </h3>
                                <p class="text-xs text-gray-500 font-light">
                                    {{ t('settings.allowYourAccountAndVideosToBeShared') }}
                                    <a href="#" class="text-blue-500 font-medium text-xs">{{
                                        t('settings.learnMore')
                                    }}</a>
                                </p>
                            </div>
                            <ToggleSwitch v-model="isFederatedAccount" />
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import ToggleSwitch from '@/components/Form/ToggleSwitch.vue'
import { useI18n } from 'vue-i18n'

const isPrivateAccount = ref(false)
const isFederatedAccount = ref(true)
const { t } = useI18n()
</script>
