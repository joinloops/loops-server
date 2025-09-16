<template>
    <Header />
    <div class="pt-[80px] flex mx-auto h-screen max-w-[1140px]">
        <Sidebar />
        <main class="flex-1 overflow-y-auto no-scrollbar pb-10">
            <div class="bg-gray-100 dark:bg-gray-900 rounded-xl">
                <slot></slot>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import Sidebar from "@/components/Settings/Sidebar.vue";
</script>
