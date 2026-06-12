<template>
    <div class="max-w-lg mx-auto p-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
            <!-- Header -->
            <div class="p-6 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="bx bx-store text-[#F02C56]"></i>
                    {{ t('vendor.registerTitle') || 'Become a Vendor' }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ t('vendor.registerSubtitle') || 'Set up your vendor profile to start selling products on the platform.' }}
                </p>
            </div>

            <!-- Success Message -->
            <div v-if="submitted" class="p-6">
                <div class="flex flex-col items-center text-center py-6">
                    <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
                        <i class="bx bx-check-circle text-[36px] text-green-600 dark:text-green-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        {{ t('vendor.applicationSubmitted') || 'Application Submitted!' }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        {{ t('vendor.applicationPending') || 'Your vendor application is pending review. We\'ll notify you once it\'s approved.' }}
                    </p>
                    <div class="bg-gray-50 dark:bg-slate-800 rounded-lg px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                        <i class="bx bx-info-circle mr-1"></i>
                        {{ t('vendor.statusPending') || 'Status: Pending Review' }}
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form v-else @submit.prevent="handleSubmit" class="p-6 space-y-5">
                <!-- Business Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ t('vendor.businessName') || 'Business Name' }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.business_name"
                        type="text"
                        :placeholder="t('vendor.businessNamePlaceholder') || 'Your business or brand name'"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors"
                        :class="{ 'border-red-500 dark:border-red-500': errors.business_name }"
                    />
                    <p v-if="errors.business_name" class="text-xs text-red-500 mt-1">{{ errors.business_name }}</p>
                </div>

                <!-- Business Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ t('vendor.businessEmail') || 'Business Email' }}
                        <span class="text-gray-400 font-normal">({{ t('vendor.optional') || 'optional' }})</span>
                    </label>
                    <input
                        v-model="form.business_email"
                        type="email"
                        :placeholder="t('vendor.businessEmailPlaceholder') || 'business@example.com'"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors"
                    />
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ t('vendor.phone') || 'Phone Number' }}
                        <span class="text-gray-400 font-normal">({{ t('vendor.optional') || 'optional' }})</span>
                    </label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        :placeholder="t('vendor.phonePlaceholder') || '+1 (555) 123-4567'"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ t('vendor.description') || 'About Your Business' }}
                        <span class="text-gray-400 font-normal">({{ t('vendor.optional') || 'optional' }})</span>
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        :placeholder="t('vendor.descriptionPlaceholder') || 'Tell us about your business and what you plan to sell...'"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors resize-none"
                    ></textarea>
                </div>

                <!-- Error Alert -->
                <div
                    v-if="errorMessage"
                    class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-start gap-2"
                >
                    <i class="bx bx-error-circle text-[18px] mt-0.5 flex-shrink-0"></i>
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    :disabled="submitting"
                    class="w-full bg-[#F02C56] text-white py-3 rounded-xl font-semibold text-sm hover:bg-[#d61f48] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                    <i v-if="submitting" class="bx bx-loader-alt bx-spin"></i>
                    <i v-else class="bx bx-store-alt"></i>
                    {{ t('vendor.submitApplication') || 'Submit Application' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const emit = defineEmits(['registered'])

const form = reactive({
    business_name: '',
    business_email: '',
    phone: '',
    description: '',
})

const errors = reactive({
    business_name: '',
    business_email: '',
    phone: '',
    description: '',
})

const submitting = ref(false)
const submitted = ref(false)
const errorMessage = ref('')

function clearErrors() {
    errors.business_name = ''
    errors.business_email = ''
    errors.phone = ''
    errors.description = ''
    errorMessage.value = ''
}

async function handleSubmit() {
    clearErrors()

    if (!form.business_name.trim()) {
        errors.business_name = t('vendor.requiredBusinessName') || 'Business name is required.'
        return
    }

    submitting.value = true

    try {
        const payload = {
            business_name: form.business_name.trim(),
        }
        if (form.business_email.trim()) payload.business_email = form.business_email.trim()
        if (form.phone.trim()) payload.phone = form.phone.trim()
        if (form.description.trim()) payload.description = form.description.trim()

        await axios.post('/api/v1/commerce/vendor/register', payload)
        submitted.value = true
        emit('registered')
    } catch (e) {
        if (e.response?.status === 409) {
            errorMessage.value = t('vendor.alreadyRegistered') || 'You are already registered as a vendor.'
        } else if (e.response?.data?.errors) {
            const serverErrors = e.response.data.errors
            if (serverErrors.business_name) {
                errors.business_name = serverErrors.business_name[0]
            }
            if (serverErrors.business_email) {
                errors.business_email = serverErrors.business_email[0]
            }
            if (serverErrors.phone) {
                errors.phone = serverErrors.phone[0]
            }
            if (serverErrors.description) {
                errors.description = serverErrors.description[0]
            }
        } else {
            errorMessage.value = e.response?.data?.message || t('vendor.submitError') || 'Failed to submit application. Please try again.'
        }
    } finally {
        submitting.value = false
    }
}
</script>
