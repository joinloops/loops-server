<template>
    <StudioLayout>
        <StudioPosts api-base="/api/v1/studio/posts" />
    </StudioLayout>
</template>

<script setup>
import StudioPosts from '@/components/Studio/StudioPosts.vue'
</script>
