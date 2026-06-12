<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div
            v-if="show"
            class="fixed inset-0 bg-black/50 z-40 transition-opacity"
            @click="$emit('close')"
        ></div>

        <!-- Drawer -->
        <div
            v-if="show"
            class="fixed top-0 right-0 h-full w-full sm:w-[400px] bg-white dark:bg-slate-900 z-50 shadow-2xl transform transition-transform duration-300 flex flex-col"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="bx bx-cart mr-1"></i> Cart ({{ cartCount }})
                </h2>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1"
                >
                    <i class="bx bx-x text-[24px]"></i>
                </button>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div v-if="loading" class="flex items-center justify-center h-full">
                    <i class="bx bx-loader-alt bx-spin text-[32px] text-gray-400"></i>
                </div>

                <div v-else-if="cartItems.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                    <i class="bx bx-cart text-[64px] mb-4"></i>
                    <p class="text-lg font-medium">Your cart is empty</p>
                    <p class="text-sm mt-1">Tap "Buy Now" on a product to add items</p>
                </div>

                <div
                    v-for="item in cartItems"
                    :key="item.cart_id"
                    class="flex gap-3 p-3 bg-gray-50 dark:bg-slate-800 rounded-xl"
                >
                    <!-- Product Image -->
                    <div class="w-20 h-20 rounded-lg bg-gray-200 dark:bg-slate-700 flex-shrink-0 overflow-hidden">
                        <img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.product_name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="flex items-center justify-center h-full">
                            <i class="bx bx-package text-[24px] text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ item.product_name }}
                        </h4>
                        <p class="text-sm font-bold text-[#F02C56] mt-1">
                            ${{ formatPrice(item.price) }}
                        </p>
                        <div class="flex items-center gap-2 mt-2">
                            <button
                                @click="updateQuantity(item.cart_id, item.quantity - 1)"
                                class="w-7 h-7 rounded-full border border-gray-300 dark:border-slate-600 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700"
                            >
                                <i class="bx bx-minus"></i>
                            </button>
                            <span class="text-sm font-medium w-6 text-center">{{ item.quantity }}</span>
                            <button
                                @click="updateQuantity(item.cart_id, item.quantity + 1)"
                                class="w-7 h-7 rounded-full border border-gray-300 dark:border-slate-600 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700"
                            >
                                <i class="bx bx-plus"></i>
                            </button>
                            <button
                                @click="removeItem(item.cart_id)"
                                class="ml-auto text-gray-400 hover:text-red-500 p-1"
                            >
                                <i class="bx bx-trash text-[18px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div v-if="cartItems.length > 0" class="border-t border-gray-200 dark:border-slate-700 p-4 space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                    <span class="font-semibold text-gray-900 dark:text-white">
                        ${{ formatPrice(cartTotal) }}
                    </span>
                </div>
                <button
                    @click="handleCheckout"
                    :disabled="checkingOut"
                    class="w-full bg-[#F02C56] text-white py-3 rounded-xl font-semibold text-sm hover:bg-[#d61f48] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                    <i v-if="checkingOut" class="bx bx-loader-alt bx-spin"></i>
                    <i v-else class="bx bx-lock-alt"></i>
                    Checkout · ${{ formatPrice(cartTotal) }}
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'checkout-success'])

const cartItems = ref([])
const cartTotal = ref(0)
const cartCount = ref(0)
const loading = ref(false)
const checkingOut = ref(false)

async function fetchCart() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/v1/commerce/cart')
        cartItems.value = data.items || []
        cartTotal.value = data.total || 0
        cartCount.value = data.count || 0
    } catch (e) {
        if (e.response?.status !== 401) {
            console.warn('Failed to fetch cart:', e)
        }
    } finally {
        loading.value = false
    }
}

async function updateQuantity(cartId, qty) {
    try {
        await axios.put(`/api/v1/commerce/cart/${cartId}`, { quantity: qty })
        await fetchCart()
    } catch (e) {
        console.warn('Failed to update cart:', e)
    }
}

async function removeItem(cartId) {
    try {
        await axios.delete(`/api/v1/commerce/cart/${cartId}`)
        await fetchCart()
    } catch (e) {
        console.warn('Failed to remove item:', e)
    }
}

async function handleCheckout() {
    checkingOut.value = true
    try {
        const { data } = await axios.post('/api/v1/commerce/checkout')
        emit('checkout-success', data)
        cartItems.value = []
        cartTotal.value = 0
        cartCount.value = 0
    } catch (e) {
        console.error('Checkout failed:', e)
        alert(e.response?.data?.message || 'Checkout failed. Please try again.')
    } finally {
        checkingOut.value = false
    }
}

function formatPrice(price) {
    if (!price && price !== 0) return '0.00'
    return Number(price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

watch(() => props.show, (newVal) => {
    if (newVal) fetchCart()
})
</script>
