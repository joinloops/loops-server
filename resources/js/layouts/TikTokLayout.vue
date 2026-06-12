<template>
    <div class="tiktok-layout w-full h-full">
        <div class="w-full h-full">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.tiktok-layout {
    width: 100%;
    height: 100%;
    position: relative;
    background: #000;
}
</style>
