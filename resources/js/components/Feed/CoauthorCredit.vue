<template>
    <span v-if="accounts.length" class="pointer-events-auto">
        <span class="opacity-80">{{ t('studio.collab.with') }}</span>
        <template v-for="(account, idx) in accounts" :key="account.id">
            <router-link
                :to="`/@${account.username}`"
                class="font-semibold hover:underline"
                @click.stop
            >
                @{{ account.username }}</router-link
            ><span v-if="idx < accounts.length - 1">, </span>
        </template>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    coauthors: { type: Array, default: () => [] }
})

const { t } = useI18n()

const accounts = computed(() => (props.coauthors || []).filter((a) => a && a.username))
</script>
