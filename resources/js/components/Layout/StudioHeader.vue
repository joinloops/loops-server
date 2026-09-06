<template>
    <header
        class="relative z-40 flex h-16 w-full items-center border-b border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-950 sm:h-[70px]"
    >
        <div class="mx-auto flex w-full min-w-0 items-center justify-between px-3 sm:px-4 lg:px-6">
            <div class="flex min-w-0 items-center">
                <button
                    type="button"
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl text-gray-700 transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:text-slate-300 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500 lg:hidden"
                    aria-label="Open navigation"
                    @click="toggleMobileDrawer"
                >
                    <i class="bx bx-menu text-2xl"></i>
                </button>
            </div>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2 lg:gap-3">
                <ThemeToggleButton />

                <div
                    v-if="authStore.isAuthenticated"
                    ref="accountMenu"
                    class="relative hidden lg:block"
                >
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full transition-opacity hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:focus-visible:ring-slate-500"
                        aria-label="Open account menu"
                        :aria-expanded="showMenu"
                        @click="showMenu = !showMenu"
                    >
                        <img
                            class="size-[34px] rounded-full object-cover"
                            :src="authStore.user.avatar"
                            alt=""
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                    </button>

                    <div
                        v-if="showMenu"
                        class="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
                    >
                        <router-link
                            :to="`/@${authStore.user.username}`"
                            class="flex items-center gap-2.5 px-3 py-3 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="showMenu = false"
                        >
                            <i class="ph-user text-xl"></i>
                            <span>Profile</span>
                        </router-link>

                        <div class="h-px bg-gray-200 dark:bg-slate-700"></div>

                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 px-3 py-3 text-left text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="logout"
                        >
                            <i class="ic-outline-login text-xl"></i>
                            <span>Log out</span>
                        </button>
                    </div>
                </div>

                <button
                    v-if="authStore.isAuthenticated"
                    type="button"
                    class="flex size-10 shrink-0 items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:focus-visible:ring-slate-500 lg:hidden"
                    aria-label="Open account navigation"
                    @click="toggleMobileDrawer"
                >
                    <img
                        class="size-8 rounded-full object-cover sm:size-[34px]"
                        :src="authStore.user.avatar"
                        alt=""
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                    />
                </button>
            </div>
        </div>
    </header>
</template>

<script setup>
import { inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const emit = defineEmits(['toggleMobileDrawer', 'openLogin'])

const router = useRouter()
const authStore = inject('authStore')

const showMenu = ref(false)
const accountMenu = ref(null)

const toggleMobileDrawer = () => {
    showMenu.value = false
    emit('toggleMobileDrawer')
}

const handleDocumentClick = (event) => {
    if (showMenu.value && accountMenu.value && !accountMenu.value.contains(event.target)) {
        showMenu.value = false
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', handleDocumentClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick)
})

const logout = async () => {
    try {
        await authStore.logout()
        showMenu.value = false
        router.push('/')
    } catch (error) {
        console.error(error)
    }
}
</script>
