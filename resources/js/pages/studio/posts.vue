<template>
    <StudioLayout>
        <StudioPosts api-base="/api/v1/studio/posts" />
    </StudioLayout>
</template>

<script setup lang="ts">
import StudioPosts from "@/components/Studio/StudioPosts.vue";
</script>
