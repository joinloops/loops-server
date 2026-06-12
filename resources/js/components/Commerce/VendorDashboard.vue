<template>
    <div class="max-w-5xl mx-auto p-4 lg:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="bx bx-store text-[#F02C56]"></i>
                    {{ t('vendor.dashboard') || 'Vendor Dashboard' }}
                </h1>
                <p v-if="vendor" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ vendor.business_name }}
                    <span
                        v-if="vendor.status === 'active'"
                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                    >
                        <i class="bx bx-check-circle mr-0.5"></i> Active
                    </span>
                    <span
                        v-else-if="vendor.status === 'pending'"
                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400"
                    >
                        <i class="bx bx-time mr-0.5"></i> Pending
                    </span>
                    <span
                        v-else-if="vendor.status === 'rejected'"
                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400"
                    >
                        <i class="bx bx-x-circle mr-0.5"></i> Rejected
                    </span>
                </p>
            </div>
            <button
                v-if="vendor"
                @click="editing = !editing"
                class="flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-slate-400 hover:text-[#F02C56] dark:hover:text-[#F02C56] px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
            >
                <i :class="editing ? 'bx bx-x' : 'bx bx-pencil'"></i>
                {{ editing ? (t('vendor.cancel') || 'Cancel') : (t('vendor.edit') || 'Edit Profile') }}
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <i class="bx bx-loader-alt bx-spin text-[32px] text-gray-400"></i>
        </div>

        <!-- No Vendor (Show registration prompt) -->
        <div v-else-if="!vendor && !loading" class="text-center py-16">
            <div class="w-20 h-20 mx-auto rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                <i class="bx bx-store text-[40px] text-gray-400"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                {{ t('vendor.notRegistered') || 'Not a Vendor Yet' }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ t('vendor.registerPrompt') || 'Register as a vendor to start selling products on the platform.' }}
            </p>
            <VendorRegistrationForm @registered="fetchVendor" />
        </div>

        <!-- Dashboard Content -->
        <template v-else-if="vendor">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i class="bx bx-package text-[20px] text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('vendor.products') || 'Products' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ dashboard.total_products }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <i class="bx bx-cart text-[20px] text-green-600 dark:text-green-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('vendor.orders') || 'Orders' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ dashboard.total_orders }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="bx bx-dollar-circle text-[20px] text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('vendor.totalSales') || 'Total Sales' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">${{ formatPrice(dashboard.total_sales) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                            <i class="bx bx-wallet text-[20px] text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('vendor.balance') || 'Balance' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">${{ formatPrice(dashboard.balance) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div v-if="editing" class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ t('vendor.editProfile') || 'Edit Vendor Profile' }}
                </h3>
                <form @submit.prevent="handleUpdate" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('vendor.businessName') || 'Business Name' }}
                            </label>
                            <input
                                v-model="editForm.business_name"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('vendor.businessEmail') || 'Business Email' }}
                            </label>
                            <input
                                v-model="editForm.business_email"
                                type="email"
                                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('vendor.phone') || 'Phone Number' }}
                            </label>
                            <input
                                v-model="editForm.phone"
                                type="tel"
                                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('vendor.description') || 'About Your Business' }}
                        </label>
                        <textarea
                            v-model="editForm.description"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#F02C56] focus:ring-1 focus:ring-[#F02C56] outline-none transition-colors resize-none"
                        ></textarea>
                    </div>

                    <div v-if="updateError" class="text-sm text-red-500">{{ updateError }}</div>
                    <div v-if="updateSuccess" class="text-sm text-green-500">{{ updateSuccess }}</div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            :disabled="updating"
                            class="bg-[#F02C56] text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-[#d61f48] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <i v-if="updating" class="bx bx-loader-alt bx-spin"></i>
                            {{ t('vendor.save') || 'Save Changes' }}
                        </button>
                        <button
                            type="button"
                            @click="editing = false"
                            class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                        >
                            {{ t('vendor.cancel') || 'Cancel' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Products & Orders Tabs -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="flex border-b border-gray-200 dark:border-slate-700">
                    <button
                        @click="activeTab = 'products'"
                        class="flex-1 py-3 px-4 text-sm font-medium text-center transition-colors"
                        :class="activeTab === 'products'
                            ? 'text-[#F02C56] border-b-2 border-[#F02C56] bg-gray-50 dark:bg-slate-800'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-800'"
                    >
                        <i class="bx bx-package mr-1"></i>
                        {{ t('vendor.products') || 'Products' }}
                        <span class="ml-1 text-xs text-gray-400">({{ dashboard.total_products }})</span>
                    </button>
                    <button
                        @click="activeTab = 'orders'"
                        class="flex-1 py-3 px-4 text-sm font-medium text-center transition-colors"
                        :class="activeTab === 'orders'
                            ? 'text-[#F02C56] border-b-2 border-[#F02C56] bg-gray-50 dark:bg-slate-800'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-800'"
                    >
                        <i class="bx bx-cart mr-1"></i>
                        {{ t('vendor.orders') || 'Orders' }}
                        <span class="ml-1 text-xs text-gray-400">({{ dashboard.total_orders }})</span>
                    </button>
                </div>

                <!-- Products Tab -->
                <div v-if="activeTab === 'products'" class="p-4">
                    <div v-if="productsLoading" class="flex items-center justify-center py-8">
                        <i class="bx bx-loader-alt bx-spin text-[24px] text-gray-400"></i>
                    </div>
                    <div v-else-if="products.length === 0" class="text-center py-8 text-gray-400">
                        <i class="bx bx-package text-[48px] mb-2"></i>
                        <p class="text-sm">{{ t('vendor.noProducts') || 'No products yet.' }}</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="product in products"
                            :key="product.id"
                            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800 rounded-lg"
                        >
                            <div class="w-12 h-12 rounded-lg bg-gray-200 dark:bg-slate-700 flex-shrink-0 overflow-hidden">
                                <img
                                    v-if="product.images && product.images.length > 0"
                                    :src="product.images[0]"
                                    :alt="product.product_name"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="flex items-center justify-center h-full">
                                    <i class="bx bx-package text-[18px] text-gray-400"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                    {{ product.product_name }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    ${{ formatPrice(product.price) }}
                                </p>
                            </div>
                            <span class="text-xs text-gray-400">
                                {{ product.sales_count || 0 }} {{ t('vendor.sold') || 'sold' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div v-if="activeTab === 'orders'" class="p-4">
                    <div v-if="ordersLoading" class="flex items-center justify-center py-8">
                        <i class="bx bx-loader-alt bx-spin text-[24px] text-gray-400"></i>
                    </div>
                    <div v-else-if="orders.length === 0" class="text-center py-8 text-gray-400">
                        <i class="bx bx-cart text-[48px] mb-2"></i>
                        <p class="text-sm">{{ t('vendor.noOrders') || 'No orders yet.' }}</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="order in orders"
                            :key="order.id"
                            class="p-3 bg-gray-50 dark:bg-slate-800 rounded-lg"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    #{{ order.id }}
                                </span>
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full font-medium"
                                    :class="order.status === 'completed'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : order.status === 'pending'
                                        ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400'"
                                >
                                    {{ order.status }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('vendor.total') || 'Total' }}: ${{ formatPrice(order.total) }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ formatDate(order.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import VendorRegistrationForm from '@/components/Commerce/VendorRegistrationForm.vue'
const { t } = useI18n()

const loading = ref(true)
const vendor = ref(null)
const dashboard = reactive({
    total_products: 0,
    total_orders: 0,
    total_sales: 0,
    balance: 0,
})
const editing = ref(false)
const updating = ref(false)
const updateError = ref('')
const updateSuccess = ref('')
const activeTab = ref('products')
const products = ref([])
const orders = ref([])
const productsLoading = ref(false)
const ordersLoading = ref(false)

const editForm = reactive({
    business_name: '',
    business_email: '',
    phone: '',
    description: '',
})

async function fetchVendor() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/v1/commerce/vendor')
        vendor.value = data
        editForm.business_name = data.business_name || ''
        editForm.business_email = data.business_email || ''
        editForm.phone = data.phone || ''
        editForm.description = data.description || ''
        await fetchDashboard()
    } catch (e) {
        if (e.response?.status !== 404) {
            console.warn('Failed to fetch vendor:', e)
        }
    } finally {
        loading.value = false
    }
}

async function fetchDashboard() {
    try {
        const { data } = await axios.get('/api/v1/commerce/vendor/dashboard')
        dashboard.total_products = data.total_products || 0
        dashboard.total_orders = data.total_orders || 0
        dashboard.total_sales = data.total_sales || 0
        dashboard.balance = data.balance || 0
    } catch (e) {
        console.warn('Failed to fetch dashboard:', e)
    }
}

async function fetchProducts() {
    productsLoading.value = true
    try {
        const { data } = await axios.get('/api/v1/commerce/vendor/products')
        products.value = data.data || []
    } catch (e) {
        console.warn('Failed to fetch products:', e)
    } finally {
        productsLoading.value = false
    }
}

async function fetchOrders() {
    ordersLoading.value = true
    try {
        const { data } = await axios.get('/api/v1/commerce/vendor/orders')
        orders.value = data.data || []
    } catch (e) {
        console.warn('Failed to fetch orders:', e)
    } finally {
        ordersLoading.value = false
    }
}

async function handleUpdate() {
    updating.value = true
    updateError.value = ''
    updateSuccess.value = ''

    try {
        const payload = {
            business_name: editForm.business_name.trim(),
        }
        if (editForm.business_email.trim()) payload.business_email = editForm.business_email.trim()
        if (editForm.phone.trim()) payload.phone = editForm.phone.trim()
        if (editForm.description.trim()) payload.description = editForm.description.trim()

        const { data } = await axios.put('/api/v1/commerce/vendor', payload)
        vendor.value = data
        updateSuccess.value = t('vendor.updatedSuccess') || 'Vendor profile updated successfully.'
        setTimeout(() => { updateSuccess.value = '' }, 3000)
    } catch (e) {
        updateError.value = e.response?.data?.message || t('vendor.updateError') || 'Failed to update vendor profile.'
    } finally {
        updating.value = false
    }
}

function formatPrice(price) {
    if (!price && price !== 0) return '0.00'
    return Number(price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateStr) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(() => {
    fetchVendor()
})
</script>
