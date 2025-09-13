<template>
    <Header
        @toggleMobileDrawer="toggleMobileDrawer"
        @openLogin="openLoginModal"
    />
    <div
        class="flex justify-between mx-auto w-full bg-white dark:bg-slate-950 px-0 loops-feed-layout"
    >
        <div class="pt-[80px] lg:px-2.5">
            <Sidebar
                :isOpen="isMobileDrawerOpen"
                @close="closeMobileDrawer"
                @openLogin="openLoginModal"
            />
        </div>
        <div class="w-full lg:w-[calc(100%-260px)]">
            <slot />
        </div>
    </div>

    <ReportModal />

    <Teleport to="body">
        <LoginModal v-if="showLoginModal" @close="closeLoginModal" />
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import Header from "~/components/Layout/Header.vue";
import Sidebar from "~/components/Layout/Sidebar.vue";
import LoginModal from "~/components/Layout/LoginModal.vue";

const isMobileDrawerOpen = ref(false);
const showLoginModal = ref(false);
const windowWidth = ref(window.innerWidth);

const isMobile = computed(() => windowWidth.value < 1024);

const toggleMobileDrawer = () => {
    isMobileDrawerOpen.value = !isMobileDrawerOpen.value;
};

const closeMobileDrawer = () => {
    isMobileDrawerOpen.value = false;
};

const openLoginModal = () => {
    showLoginModal.value = true;
};

const closeLoginModal = () => {
    showLoginModal.value = false;
};

const handleResize = () => {
    windowWidth.value = window.innerWidth;
    if (windowWidth.value >= 1024) {
        isMobileDrawerOpen.value = false;
    }
};

onMounted(() => {
    window.addEventListener("resize", handleResize);
});

onUnmounted(() => {
    window.removeEventListener("resize", handleResize);
});
</script>
