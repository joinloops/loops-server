<template>
    <div class="max-w-5xl mx-auto p-4 lg:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="bx bx-link text-[#F02C56]"></i>
                {{ t('affiliate.dashboard') || 'Affiliate Dashboard' }}
            </h1>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <i class="bx bx-loader-alt bx-spin text-[32px] text-gray-400"></i>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-center py-16">
            <div class="w-16 h-16 mx-auto rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-4">
                <i class="bx bx-error-circle text-[32px] text-red-500"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ t('common.error') || 'Error Loading Data' }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ error }}</p>
        </div>

        <template v-else>
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <i class="bx bx-dollar-circle text-[20px] text-green-600 dark:text-green-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('affiliate.totalEarned') || 'Total Earned' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">${{ formatNumber(stats.total_earned) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i class="bx bx-pointer text-[20px] text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('affiliate.totalClicks') || 'Total Clicks' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.total_clicks }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="bx bx-check-double text-[20px] text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('affiliate.totalConversions') || 'Conversions' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.total_conversions }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i class="bx bx-link text-[20px] text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('affiliate.totalLinks') || 'Total Links' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.links_count }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Affiliate Link -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6 mb-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <i class="bx bx-plus-circle text-[#F02C56] mr-2"></i>
                    {{ t('affiliate.createLink') || 'Create Affiliate Link' }}
                </h2>
                <form @submit.prevent="createLink" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('affiliate.selectProduct') || 'Select Product' }}
                        </label>
                        <select
                            v-model="newLink.product_id"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#F02C56] focus:border-transparent"
                            required
                        >
                            <option value="">{{ t('affiliate.chooseProduct') || 'Choose a product...' }}</option>
                            <option
                                v-for="product in products"
                                :key="product.id"
                                :value="product.id"
                            >
                                {{ product.name }} - ${{ formatNumber(product.price) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('affiliate.commissionRate') || 'Commission Rate (%)' }}
                        </label>
                        <input
                            v-model.number="newLink.commission_rate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#F02C56] focus:border-transparent"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="creating"
                        class="flex items-center gap-2 px-4 py-2 bg-[#F02C56] text-white rounded-lg hover:bg-[#d0244a] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="creating" class="bx bx-loader-alt bx-spin"></i>
                        <i v-else class="bx bx-link"></i>
                        {{ creating ? (t('affiliate.creating') || 'Creating...') : (t('affiliate.generateLink') || 'Generate Link') }}
                    </button>
                </form>
                <div v-if="createSuccess" class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg flex items-center gap-2">
                    <i class="bx bx-check-circle text-green-600 dark:text-green-400"></i>
                    <span class="text-sm text-green-700 dark:text-green-300">{{ t('affiliate.linkCreated') || 'Affiliate link created successfully!' }}</span>
                </div>
            </div>

            <!-- My Links -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <i class="bx bx-link-alt text-[#F02C56] mr-2"></i>
                    {{ t('affiliate.myLinks') || 'My Affiliate Links' }}
                </h2>
                <div v-if="links.length === 0" class="text-center py-8 text-gray-400">
                    {{ t('affiliate.noLinks') || 'No affiliate links yet. Create your first one above.' }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-slate-700">
                                <th class="pb-2 font-medium whitespace-nowrap">{{ t('affiliate.code') || 'Code' }}</th>
                                <th class="pb-2 font-medium whitespace-nowrap">{{ t('affiliate.product') || 'Product' }}</th>
                                <th class="pb-2 font-medium text-right whitespace-nowrap">{{ t('affiliate.clicks') || 'Clicks' }}</th>
                                <th class="pb-2 font-medium text-right whitespace-nowrap">{{ t('affiliate.conversions') || 'Conversions' }}</th>
                                <th class="pb-2 font-medium text-right whitespace-nowrap">{{ t('affiliate.earned') || 'Earned' }}</th>
                                <th class="pb-2 font-medium text-right whitespace-nowrap">{{ t('affiliate.actions') || 'Actions' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="link in links"
                                :key="link.id"
                                class="border-b border-gray-50 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800"
                            >
                                <td class="py-3 font-mono text-xs text-gray-900 dark:text-white whitespace-nowrap">{{ link.referral_code }}</td>
                                <td class="py-3 text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ link.product ? link.product.name : '—' }}
                                </td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ link.total_clicks }}</td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ link.total_conversions }}</td>
                                <td class="py-3 text-right font-medium text-gray-900 dark:text-white whitespace-nowrap">${{ formatNumber(link.total_earned) }}</td>
                                <td class="py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="copyLink(link.referral_url)"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-[#F02C56] hover:bg-[#F02C56]/10 rounded-lg transition-colors"
                                        :title="t('affiliate.copyLink') || 'Copy referral link'"
                                    >
                                        <i :class="copiedCode === link.referral_code ? 'bx bx-check' : 'bx bx-copy'"></i>
                                        {{ copiedCode === link.referral_code ? (t('common.copied') || 'Copied!') : (t('common.copy') || 'Copy') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';

export default {
    name: 'AffiliateDashboard',
    setup() {
        const loading = ref(true);
        const error = ref(null);
        const creating = ref(false);
        const createSuccess = ref(false);
        const copiedCode = ref(null);
        const stats = ref({});
        const links = ref([]);
        const products = ref([]);

        const newLink = reactive({
            product_id: '',
            commission_rate: 10,
        });

        const formatNumber = (num) => {
            const n = parseFloat(num || 0);
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const copyLink = async (url) => {
            try {
                await navigator.clipboard.writeText(url);
                const code = links.value.find(l => l.referral_url === url)?.referral_code;
                if (code) {
                    copiedCode.value = code;
                    setTimeout(() => { copiedCode.value = null; }, 2000);
                }
            } catch {
                // Fallback
                const input = document.createElement('input');
                input.value = url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
            }
        };

        const fetchData = async () => {
            loading.value = true;
            error.value = null;

            try {
                const baseUrl = '/api/v1/commerce/affiliate';
                const [statsRes, linksRes, productsRes] = await Promise.all([
                    fetch(`${baseUrl}/dashboard`),
                    fetch(`${baseUrl}/my-links`),
                    fetch('/api/v1/commerce/products?per_page=100'),
                ]);

                if (!statsRes.ok) throw new Error('Failed to load affiliate data');

                stats.value = await statsRes.json();
                links.value = await linksRes.json();
                const productsData = await productsRes.json();
                products.value = productsData.data || productsData;
            } catch (e) {
                error.value = e.message;
            } finally {
                loading.value = false;
            }
        };

        const createLink = async () => {
            if (!newLink.product_id) return;
            creating.value = true;
            createSuccess.value = false;

            try {
                const res = await fetch('/api/v1/commerce/affiliate/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: newLink.product_id,
                        commission_rate: newLink.commission_rate,
                    }),
                });

                if (!res.ok) throw new Error('Failed to create affiliate link');

                const data = await res.json();
                createSuccess.value = true;
                newLink.product_id = '';
                newLink.commission_rate = 10;
                // Refresh links
                const linksRes = await fetch('/api/v1/commerce/affiliate/my-links');
                links.value = await linksRes.json();
                // Refresh stats
                const statsRes = await fetch('/api/v1/commerce/affiliate/dashboard');
                stats.value = await statsRes.json();

                setTimeout(() => { createSuccess.value = false; }, 3000);
            } catch (e) {
                error.value = e.message;
            } finally {
                creating.value = false;
            }
        };

        onMounted(fetchData);

        return {
            loading,
            error,
            creating,
            createSuccess,
            copiedCode,
            stats,
            links,
            products,
            newLink,
            formatNumber,
            copyLink,
            createLink,
        };
    },
};
</script>
