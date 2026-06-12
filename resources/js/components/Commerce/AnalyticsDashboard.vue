<template>
    <div class="max-w-5xl mx-auto p-4 lg:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="bx bx-bar-chart text-[#F02C56]"></i>
                {{ t('commerce.analytics') || 'Analytics Dashboard' }}
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
            <!-- KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <i class="bx bx-dollar-circle text-[20px] text-green-600 dark:text-green-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('analytics.totalRevenue') || 'Total Revenue' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">${{ formatNumber(overview.total_revenue) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i class="bx bx-cart text-[20px] text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('analytics.totalOrders') || 'Total Orders' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ overview.total_orders }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="bx bx-line-chart text-[20px] text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('analytics.conversionRate') || 'Conversion Rate' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ overview.conversion_rate }}%</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i class="bx bx-receipt text-[20px] text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('analytics.avgOrderValue') || 'Avg Order Value' }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">${{ formatNumber(overview.avg_order_value) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6 mb-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <i class="bx bx-trending-up text-[#F02C56] mr-2"></i>
                    {{ t('analytics.revenue30Days') || 'Revenue (Last 30 Days)' }}
                </h2>
                <div v-if="revenueData.length === 0" class="text-center py-8 text-gray-400">
                    {{ t('analytics.noRevenueData') || 'No revenue data available' }}
                </div>
                <div v-else class="relative h-48 flex items-end gap-1">
                    <div
                        v-for="(item, index) in revenueData"
                        :key="index"
                        class="flex-1 flex flex-col items-center"
                    >
                        <div
                            class="w-full bg-gradient-to-t from-[#F02C56] to-pink-400 rounded-t transition-all duration-300 hover:opacity-80 cursor-pointer min-h-[2px]"
                            :style="{ height: barHeight(item.revenue) }"
                            :title="`${item.date}: $${formatNumber(item.revenue)}`"
                        ></div>
                    </div>
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-400">
                    <span>{{ revenueData.length > 0 ? revenueData[0].date : '' }}</span>
                    <span>{{ revenueData.length > 0 ? revenueData[revenueData.length - 1].date : '' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Top Products -->
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="bx bx-package text-[#F02C56] mr-2"></i>
                        {{ t('analytics.topProducts') || 'Top Selling Products' }}
                    </h2>
                    <div v-if="topProducts.length === 0" class="text-center py-8 text-gray-400">
                        {{ t('analytics.noProducts') || 'No product sales data' }}
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-slate-700">
                                <th class="pb-2 font-medium">{{ t('analytics.product') || 'Product' }}</th>
                                <th class="pb-2 font-medium text-right">{{ t('analytics.orders') || 'Orders' }}</th>
                                <th class="pb-2 font-medium text-right">{{ t('analytics.revenue') || 'Revenue' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in topProducts"
                                :key="product.id"
                                class="border-b border-gray-50 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800"
                            >
                                <td class="py-3 text-gray-900 dark:text-white">{{ product.name }}</td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ product.order_count }}</td>
                                <td class="py-3 text-right font-medium text-gray-900 dark:text-white">${{ formatNumber(product.total_revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- AI Accuracy -->
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="bx bx-brain text-[#F02C56] mr-2"></i>
                        {{ t('analytics.aiAccuracy') || 'AI Detection Accuracy' }}
                    </h2>
                    <div v-if="aiAccuracy.total_analyzed === 0" class="text-center py-8 text-gray-400">
                        {{ t('analytics.noAiData') || 'No AI detection data available' }}
                    </div>
                    <div v-else class="space-y-6">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-gray-900 dark:text-white">{{ aiAccuracy.accuracy_rate }}%</div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('analytics.accuracyRate') || 'Accuracy Rate' }}</p>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300">{{ t('analytics.totalAnalyzed') || 'Total Analyzed' }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ aiAccuracy.total_analyzed }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300">{{ t('analytics.aiTagged') || 'AI Tagged' }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ aiAccuracy.ai_tagged }}</span>
                            </div>
                        </div>
                        <!-- Progress bar -->
                        <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-3">
                            <div
                                class="bg-gradient-to-r from-[#F02C56] to-pink-400 h-3 rounded-full transition-all duration-500"
                                :style="{ width: aiAccuracy.accuracy_rate + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vendor Performance -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <i class="bx bx-store text-[#F02C56] mr-2"></i>
                    {{ t('analytics.vendorPerformance') || 'Vendor Performance' }}
                </h2>
                <div v-if="vendorPerformance.length === 0" class="text-center py-8 text-gray-400">
                    {{ t('analytics.noVendorData') || 'No vendor data available' }}
                </div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-slate-700">
                            <th class="pb-2 font-medium">{{ t('analytics.vendor') || 'Vendor' }}</th>
                            <th class="pb-2 font-medium text-right">{{ t('analytics.orders') || 'Orders' }}</th>
                            <th class="pb-2 font-medium text-right">{{ t('analytics.revenue') || 'Revenue' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="vendor in vendorPerformance"
                            :key="vendor.id"
                            class="border-b border-gray-50 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800"
                        >
                            <td class="py-3 text-gray-900 dark:text-white">{{ vendor.business_name }}</td>
                            <td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ vendor.order_count }}</td>
                            <td class="py-3 text-right font-medium text-gray-900 dark:text-white">${{ formatNumber(vendor.total_revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';

export default {
    name: 'AnalyticsDashboard',
    setup() {
        const loading = ref(true);
        const error = ref(null);
        const overview = ref({});
        const revenueData = ref([]);
        const topProducts = ref([]);
        const vendorPerformance = ref([]);
        const aiAccuracy = ref({});

        const maxRevenue = computed(() => {
            if (revenueData.value.length === 0) return 1;
            return Math.max(...revenueData.value.map(r => parseFloat(r.revenue || 0)), 1);
        });

        const barHeight = (revenue) => {
            const pct = (parseFloat(revenue || 0) / maxRevenue.value) * 100;
            return `${Math.max(pct, 2)}%`;
        };

        const formatNumber = (num) => {
            const n = parseFloat(num || 0);
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const fetchData = async () => {
            loading.value = true;
            error.value = null;

            try {
                const baseUrl = '/api/v1/commerce/analytics';
                const [overviewRes, revenueRes, productsRes, vendorRes, aiRes] = await Promise.all([
                    fetch(`${baseUrl}/overview`),
                    fetch(`${baseUrl}/revenue`),
                    fetch(`${baseUrl}/products`),
                    fetch(`${baseUrl}/vendor-performance`),
                    fetch(`${baseUrl}/ai-accuracy`),
                ]);

                if (!overviewRes.ok) throw new Error('Failed to load analytics');
                if (!revenueRes.ok) throw new Error('Failed to load revenue data');

                overview.value = await overviewRes.json();
                revenueData.value = await revenueRes.json();
                topProducts.value = await productsRes.json();
                vendorPerformance.value = await vendorRes.json();
                aiAccuracy.value = await aiRes.json();
            } catch (e) {
                error.value = e.message;
            } finally {
                loading.value = false;
            }
        };

        onMounted(fetchData);

        return {
            loading,
            error,
            overview,
            revenueData,
            topProducts,
            vendorPerformance,
            aiAccuracy,
            barHeight,
            formatNumber,
        };
    },
};
</script>
