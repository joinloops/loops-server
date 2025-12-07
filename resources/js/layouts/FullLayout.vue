<template>
    <Header />
    <div class="flex justify-between mx-auto w-full max-w-[1140px] lg:px-2.5 px-0">
        <slot />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import Header from '~/components/Layout/Header.vue'
</script>
