<template>
    <div class="product-tag-container">
        <!-- Floating product tags on video -->
        <div
            v-for="tag in visibleTags"
            :key="tag.id"
            class="product-float-tag absolute z-20 cursor-pointer pointer-events-auto tag-pop-in"
            :style="tagStyle(tag)"
            @click.stop="openProductCard(tag)"
        >
            <div class="flex items-center gap-1 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-full px-2 py-1 shadow-lg border border-white/30 hover:scale-110 transition-transform">
                <i class="bx bxs-tag text-[14px] text-[#F02C56]"></i>
                <span class="text-xs font-semibold text-gray-800 dark:text-white truncate max-w-[100px]">
                    {{ tag.product_name }}
                </span>
                <span class="text-xs font-bold text-[#F02C56]">
                    ${{ formatPrice(tag.price) }}
                </span>
            </div>
        </div>

        <!-- Product Card Modal -->
        <Teleport to="body">
            <div
                v-if="selectedProduct"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                @click.self="closeProductCard"
            >
                <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full mx-4 overflow-hidden shadow-2xl transform transition-all animate-in">
                    <!-- Product Image -->
                    <div class="relative h-48 bg-gray-100 dark:bg-slate-800">
                        <img
                            v-if="selectedProduct.images && selectedProduct.images.length > 0"
                            :src="selectedProduct.images[0]"
                            :alt="selectedProduct.product_name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="flex items-center justify-center h-full text-gray-400">
                            <i class="bx bx-package text-[48px]"></i>
                        </div>
                        <button
                            class="absolute top-2 right-2 bg-black/50 rounded-full p-1.5 text-white hover:bg-black/70"
                            @click="closeProductCard"
                        >
                            <i class="bx bx-x text-[20px]"></i>
                        </button>
                        <div class="absolute top-2 left-2 bg-[#F02C56] text-white text-xs font-bold px-2 py-1 rounded-full">
                            ${{ formatPrice(selectedProduct.price) }}
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="p-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">
                            {{ selectedProduct.product_name }}
                        </h3>
                        <p v-if="selectedProduct.product_description" class="text-sm text-gray-500 dark:text-gray-400 mb-3 line-clamp-2">
                            {{ selectedProduct.product_description }}
                        </p>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-xs bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">
                                {{ selectedProduct.category || 'General' }}
                            </span>
                            <span v-if="selectedProduct.confidence" class="text-xs text-gray-400">
                                AI match {{ Math.round(selectedProduct.confidence * 100) }}%
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <a
                                v-if="selectedProduct.external_url"
                                :href="selectedProduct.external_url"
                                target="_blank"
                                class="flex-1 text-center bg-[#F02C56] text-white py-2.5 rounded-xl font-semibold text-sm hover:bg-[#d61f48] transition-colors"
                            >
                                <i class="bx bx-cart mr-1"></i> Buy Now
                            </a>
                            <button
                                @click="closeProductCard"
                                class="flex-1 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 py-2.5 rounded-xl font-semibold text-sm hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                            >
                                Close
                            </button>
                        </div>

                        <!-- Auto-detected badge -->
                        <div v-if="selectedProduct.detection_method === 'ai_auto'" class="mt-3 flex items-center justify-center gap-1 text-xs text-gray-400">
                            <i class="bx bx-brain"></i>
                            <span>AI detected product</span>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'

const props = defineProps({
    products: { type: Array, default: () => [] },
    currentTime: { type: Number, default: 0 },
    videoDuration: { type: Number, default: 0 },
})

const emit = defineEmits(['interaction'])

const selectedProduct = ref(null)
const visibleProducts = ref([])

// Show tags that should be visible at current timestamp
const visibleTags = computed(() => {
    if (!props.products || props.products.length === 0) return []

    return props.products.filter(p => {
        // If no timestamps set, show all
        if (p.timestamp_start === null && p.timestamp_end === null) return true
        // Show within time range
        const start = p.timestamp_start || 0
        const end = p.timestamp_end || props.videoDuration
        return props.currentTime >= start && props.currentTime <= end
    })
})

function tagStyle(tag) {
    const style = {}

    // Position using bounding box if available
    if (tag.bounding_box) {
        const box = tag.bounding_box
        style.left = `${box.x * 100}%`
        style.top = `${box.y * 100}%`

        // If height specified, center vertically in the box
        if (box.h) {
            style.top = `${(box.y + box.h / 2) * 100}%`
        }
    } else {
        // Default position: bottom-right quadrant of video
        style.right = '12px'
        style.bottom = '120px'
    }

    return style
}

function formatPrice(price) {
    if (!price && price !== 0) return '0'
    return price.toLocaleString ? price.toLocaleString() : price
}

function openProductCard(tag) {
    selectedProduct.value = tag
    emit('interaction', { type: 'product_click', product_id: tag.product_id })
}

function closeProductCard() {
    selectedProduct.value = null
}
</script>

<style scoped>
.product-float-tag {
    animation: tagPopIn 0.3s ease-out;
}

@keyframes tagPopIn {
    0% {
        opacity: 0;
        transform: scale(0.5);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.tag-pop-in {
    animation: tagPopIn 0.3s ease-out;
}

.animate-in {
    animation: modalIn 0.2s ease-out;
}

@keyframes modalIn {
    0% {
        opacity: 0;
        transform: scale(0.9);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
