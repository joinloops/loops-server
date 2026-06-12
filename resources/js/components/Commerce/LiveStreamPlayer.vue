<template>
    <div class="live-stream-player bg-gray-900 text-white min-h-screen">
        <!-- Stream Video Area -->
        <div class="relative w-full bg-black" :style="{ aspectRatio: '16/9', maxHeight: '70vh' }">
            <!-- Video Placeholder / RTMP Player -->
            <div class="absolute inset-0 flex items-center justify-center bg-black">
                <template v-if="stream && stream.status === 'live'">
                    <!-- RTMP video would be embedded here via a video player library -->
                    <div class="text-center">
                        <i class="bx bx-video-recording text-[#F02C56] text-[64px] animate-pulse"></i>
                        <p class="text-sm text-gray-400 mt-2">Live stream in progress</p>
                    </div>
                </template>
                <template v-else-if="stream && stream.status === 'pending'">
                    <div class="text-center">
                        <i class="bx bx-time text-gray-500 text-[64px]"></i>
                        <p class="text-sm text-gray-400 mt-2">
                            {{ stream.scheduled_at ? 'Scheduled for ' + formatDate(stream.scheduled_at) : 'Stream not started yet' }}
                        </p>
                    </div>
                </template>
                <template v-else-if="stream && stream.status === 'ended'">
                    <div class="text-center">
                        <i class="bx bx-stop-circle text-gray-500 text-[64px]"></i>
                        <p class="text-sm text-gray-400 mt-2">Stream ended</p>
                        <p v-if="stream.recording_url" class="mt-3">
                            <a
                                :href="stream.recording_url"
                                target="_blank"
                                class="inline-flex items-center gap-2 bg-[#F02C56] text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-[#d61f48] transition-colors"
                            >
                                <i class="bx bx-play-circle"></i> Watch Recording
                            </a>
                        </p>
                    </div>
                </template>
                <template v-else>
                    <div class="text-center">
                        <i class="bx bx-camera-off text-gray-500 text-[64px]"></i>
                        <p class="text-sm text-gray-400 mt-2">No stream available</p>
                    </div>
                </template>
            </div>

            <!-- Overlay: Top Bar -->
            <div class="absolute top-0 left-0 right-0 p-4 flex items-center justify-between bg-gradient-to-b from-black/60 to-transparent">
                <!-- Stream Status Badge -->
                <div class="flex items-center gap-2">
                    <span
                        v-if="stream"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold"
                        :class="statusBadgeClass"
                    >
                        <span v-if="stream.status === 'live'" class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                        <i v-else :class="statusIcon"></i>
                        {{ stream.status.toUpperCase() }}
                    </span>
                    <span v-if="stream && stream.status === 'live'" class="text-xs text-gray-300">
                        {{ stream.viewer_count || 0 }}
                        <i class="bx bx-user ml-0.5"></i>
                    </span>
                </div>

                <!-- Stream Key Copy (owner only) -->
                <button
                    v-if="stream && isOwner && stream.stream_key"
                    @click="copyStreamKey"
                    class="text-xs text-gray-400 hover:text-white bg-black/30 px-2 py-1 rounded-md transition-colors flex items-center gap-1"
                >
                    <i class="bx bx-key"></i>
                    {{ copiedKey ? 'Copied!' : 'Stream Key' }}
                </button>
            </div>
        </div>

        <!-- Stream Info + Content Area -->
        <div v-if="stream" class="max-w-6xl mx-auto p-4 lg:p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Stream Info + Product Carousel -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Stream Info -->
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-white">{{ stream.title }}</h1>
                        <p v-if="stream.description" class="text-sm text-gray-400 mt-1">{{ stream.description }}</p>
                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <i class="bx bx-user-circle"></i>
                                Streamer #{{ stream.profile_id }}
                            </span>
                            <span v-if="stream.scheduled_at" class="flex items-center gap-1">
                                <i class="bx bx-calendar"></i>
                                {{ formatDate(stream.scheduled_at) }}
                            </span>
                            <span v-if="stream.started_at" class="flex items-center gap-1">
                                <i class="bx bx-timer"></i>
                                Started {{ formatDate(stream.started_at) }}
                            </span>
                        </div>
                    </div>

                    <!-- Product Carousel -->
                    <div v-if="products.length > 0">
                        <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3 flex items-center gap-1">
                            <i class="bx bx-package text-[#F02C56]"></i>
                            Featured Products ({{ products.length }})
                        </h3>
                        <div class="relative">
                            <div
                                ref="carouselRef"
                                class="flex gap-4 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-2"
                                style="-webkit-overflow-scrolling: touch; scrollbar-width: none;"
                            >
                                <div
                                    v-for="product in products"
                                    :key="product.id"
                                    class="flex-shrink-0 w-48 bg-gray-800 rounded-xl overflow-hidden snap-start border border-gray-700 hover:border-[#F02C56]/50 transition-colors cursor-pointer"
                                    @click="selectProduct(product)"
                                >
                                    <!-- Product Image -->
                                    <div class="h-32 bg-gray-700 relative">
                                        <img
                                            v-if="product.images && product.images.length > 0"
                                            :src="product.images[0]"
                                            :alt="product.name"
                                            class="w-full h-full object-cover"
                                        />
                                        <div v-else class="flex items-center justify-center h-full text-gray-500">
                                            <i class="bx bx-package text-[32px]"></i>
                                        </div>
                                        <!-- Price Badge -->
                                        <div class="absolute bottom-1 left-1 bg-[#F02C56] text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                            ${{ formatPrice(product.price) }}
                                        </div>
                                    </div>
                                    <!-- Product Name -->
                                    <div class="p-2">
                                        <h4 class="text-sm font-semibold text-white truncate">{{ product.name }}</h4>
                                        <p v-if="product.category" class="text-xs text-gray-500 mt-0.5">{{ product.category }}</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Scroll Arrows -->
                            <button
                                v-if="products.length > 2"
                                @click="scrollCarousel(-1)"
                                class="absolute left-0 top-1/2 -translate-y-1/2 -ml-3 w-8 h-8 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-700 transition-colors shadow-lg"
                            >
                                <i class="bx bx-chevron-left"></i>
                            </button>
                            <button
                                v-if="products.length > 2"
                                @click="scrollCarousel(1)"
                                class="absolute right-0 top-1/2 -translate-y-1/2 -mr-3 w-8 h-8 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-700 transition-colors shadow-lg"
                            >
                                <i class="bx bx-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="stream.product_ids && stream.product_ids.length > 0" class="text-center py-8 text-gray-500">
                        <i class="bx bx-package text-[32px]"></i>
                        <p class="text-sm mt-1">Loading products...</p>
                    </div>
                </div>

                <!-- Right: Live Chat -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden flex flex-col" style="height: 500px;">
                        <!-- Chat Header -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                            <h3 class="text-sm font-semibold text-white flex items-center gap-1">
                                <i class="bx bx-chat text-[#F02C56]"></i>
                                Live Chat
                            </h3>
                            <span v-if="stream && !stream.chat_enabled" class="text-xs text-gray-500">Chat disabled</span>
                        </div>

                        <!-- Chat Messages -->
                        <div ref="chatRef" class="flex-1 overflow-y-auto p-3 space-y-2">
                            <div v-if="chatMessages.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500 text-sm">
                                <i class="bx bx-message-square-dots text-[32px] mb-2"></i>
                                <p>No messages yet</p>
                                <p class="text-xs text-gray-600">Be the first to chat!</p>
                            </div>
                            <div
                                v-for="(msg, idx) in chatMessages"
                                :key="idx"
                                class="flex gap-2"
                            >
                                <div class="w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center flex-shrink-0">
                                    <i class="bx bx-user text-xs text-gray-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-semibold text-[#F02C56]">{{ msg.username || 'Anonymous' }}</span>
                                    <p class="text-sm text-gray-200 break-words">{{ msg.message }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Input -->
                        <div v-if="stream && stream.chat_enabled" class="border-t border-gray-700 p-3">
                            <form @submit.prevent="sendChatMessage" class="flex gap-2">
                                <input
                                    v-model="chatInput"
                                    type="text"
                                    placeholder="Type a message..."
                                    maxlength="500"
                                    class="flex-1 bg-gray-700 text-white text-sm rounded-lg px-3 py-2 outline-none focus:ring-1 focus:ring-[#F02C56] placeholder-gray-500 border border-gray-600"
                                    :disabled="sendingChat"
                                />
                                <button
                                    type="submit"
                                    :disabled="!chatInput.trim() || sendingChat"
                                    class="bg-[#F02C56] text-white px-3 py-2 rounded-lg text-sm hover:bg-[#d61f48] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                                >
                                    <i v-if="sendingChat" class="bx bx-loader-alt bx-spin"></i>
                                    <i v-else class="bx bx-send"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="flex items-center justify-center py-20">
            <i class="bx bx-loader-alt bx-spin text-[32px] text-gray-400"></i>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex flex-col items-center justify-center py-20 text-gray-400">
            <i class="bx bx-error-circle text-[64px] mb-4"></i>
            <p class="text-lg font-medium">Failed to load stream</p>
            <p class="text-sm mt-1">{{ error }}</p>
            <button
                @click="fetchStream"
                class="mt-4 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 transition-colors"
            >
                <i class="bx bx-refresh mr-1"></i> Try Again
            </button>
        </div>

        <!-- Product Quick View Modal -->
        <Teleport to="body">
            <div
                v-if="selectedProduct"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm"
                @click.self="selectedProduct = null"
            >
                <div class="bg-gray-800 rounded-2xl max-w-sm w-full mx-4 overflow-hidden shadow-2xl border border-gray-700">
                    <!-- Product Image -->
                    <div class="relative h-48 bg-gray-700">
                        <img
                            v-if="selectedProduct.images && selectedProduct.images.length > 0"
                            :src="selectedProduct.images[0]"
                            :alt="selectedProduct.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="flex items-center justify-center h-full text-gray-500">
                            <i class="bx bx-package text-[48px]"></i>
                        </div>
                        <button
                            class="absolute top-2 right-2 bg-black/50 rounded-full p-1.5 text-white hover:bg-black/70 transition-colors"
                            @click="selectedProduct = null"
                        >
                            <i class="bx bx-x text-[20px]"></i>
                        </button>
                        <div class="absolute top-2 left-2 bg-[#F02C56] text-white text-xs font-bold px-2 py-1 rounded-full">
                            ${{ formatPrice(selectedProduct.price) }}
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="p-4">
                        <h3 class="text-lg font-bold text-white mb-1">{{ selectedProduct.name }}</h3>
                        <p v-if="selectedProduct.description" class="text-sm text-gray-400 mb-3 line-clamp-2">
                            {{ selectedProduct.description }}
                        </p>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-xs bg-gray-700 text-gray-300 px-2 py-0.5 rounded-full">
                                {{ selectedProduct.category || 'General' }}
                            </span>
                            <span v-if="selectedProduct.stock !== undefined" class="text-xs text-gray-500">
                                {{ selectedProduct.stock > 0 ? selectedProduct.stock + ' in stock' : 'Out of stock' }}
                            </span>
                        </div>

                        <!-- Buy Button -->
                        <button
                            @click="handleBuy(selectedProduct)"
                            class="w-full bg-[#F02C56] text-white py-2.5 rounded-xl font-semibold text-sm hover:bg-[#d61f48] transition-colors flex items-center justify-center gap-2"
                        >
                            <i class="bx bx-cart"></i>
                            Buy Now — ${{ formatPrice(selectedProduct.price) }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    streamId: { type: [Number, String], required: true },
})

const emit = defineEmits(['buy', 'chat-sent'])

// State
const stream = ref(null)
const loading = ref(true)
const error = ref('')
const products = ref([])
const selectedProduct = ref(null)
const chatMessages = ref([])
const chatInput = ref('')
const sendingChat = ref(false)
const copiedKey = ref(false)

const chatRef = ref(null)
const carouselRef = ref(null)

// Computed
const isOwner = computed(() => {
    if (!stream.value) return false
    // Ownership is checked via the authenticated user; on frontend we just show the key copy button
    // The actual ownership enforcement is server-side
    return true
})

const statusBadgeClass = computed(() => {
    if (!stream.value) return ''
    switch (stream.value.status) {
        case 'live': return 'bg-[#F02C56] text-white'
        case 'pending': return 'bg-yellow-600 text-yellow-100'
        case 'ended': return 'bg-gray-600 text-gray-200'
        case 'cancelled': return 'bg-red-800 text-red-200'
        default: return 'bg-gray-600 text-gray-200'
    }
})

const statusIcon = computed(() => {
    if (!stream.value) return ''
    switch (stream.value.status) {
        case 'pending': return 'bx bx-time'
        case 'ended': return 'bx bx-stop-circle'
        case 'cancelled': return 'bx bx-x-circle'
        default: return 'bx bx-question-mark'
    }
})

// Methods
async function fetchStream() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(`/api/v1/commerce/live/${props.streamId}`)
        stream.value = data
        await fetchProducts(data)
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to load stream.'
        console.warn('Failed to fetch stream:', e)
    } finally {
        loading.value = false
    }
}

async function fetchProducts(streamData) {
    const productIds = streamData?.product_ids
    if (!productIds || productIds.length === 0) {
        products.value = []
        return
    }

    try {
        // Fetch each product by ID
        const promises = productIds.map(id =>
            axios.get(`/api/v1/commerce/products/${id}`).then(r => r.data).catch(() => null)
        )
        const results = await Promise.all(promises)
        products.value = results.filter(Boolean)
    } catch (e) {
        console.warn('Failed to fetch products:', e)
    }
}

function formatPrice(price) {
    if (!price && price !== 0) return '0.00'
    return Number(price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateStr) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function selectProduct(product) {
    selectedProduct.value = product
}

function handleBuy(product) {
    emit('buy', product)
    selectedProduct.value = null
}

function scrollCarousel(direction) {
    if (!carouselRef.value) return
    const scrollAmount = 200
    carouselRef.value.scrollBy({
        left: direction * scrollAmount,
        behavior: 'smooth',
    })
}

async function sendChatMessage() {
    if (!chatInput.value.trim() || sendingChat.value) return
    sendingChat.value = true

    const msg = chatInput.value.trim()
    chatInput.value = ''

    try {
        const { data } = await axios.post(`/api/v1/commerce/live/${props.streamId}/chat`, {
            message: msg,
        })
        chatMessages.value.push(data)
        emit('chat-sent', data)
        await nextTick()
        scrollChatToBottom()
    } catch (e) {
        console.warn('Failed to send message:', e)
        // Re-add input on failure
        chatInput.value = msg
    } finally {
        sendingChat.value = false
    }
}

function scrollChatToBottom() {
    if (chatRef.value) {
        chatRef.value.scrollTop = chatRef.value.scrollHeight
    }
}

async function copyStreamKey() {
    if (!stream.value?.stream_key) return
    try {
        await navigator.clipboard.writeText(stream.value.stream_key)
        copiedKey.value = true
        setTimeout(() => { copiedKey.value = false }, 2000)
    } catch (e) {
        console.warn('Failed to copy stream key:', e)
    }
}

onMounted(() => {
    fetchStream()
})

// Auto-scroll chat on new messages
watch(chatMessages, async () => {
    await nextTick()
    scrollChatToBottom()
}, { deep: true })
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
