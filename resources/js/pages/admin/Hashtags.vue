<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Hashtags
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Monitor and manage hashtags and topics
            </p>
        </div>

        <DataTable
            title="Hashtags"
            :columns="columns"
            :data="hashtags"
            :loading="loading"
            :has-previous="pagination.prev_cursor !== null"
            :has-next="pagination.next_cursor !== null"
            :has-actions="false"
            :sort-options="sortOptions"
            @search="handleSearch"
            @refresh="fetchHashtags"
            @previous="previousPage"
            @sort="handleSort"
            @next="nextPage"
        >
            <template #cell-user="{ item }">
                <div class="flex items-center">
                    <img
                        :src="item.user.avatar"
                        :alt="item.user.username"
                        class="w-8 h-8 rounded-full mr-2"
                        @error="
                            $event.target.src = '/storage/avatars/default.jpg'
                        "
                    />
                    <span>{{ item.user.username }}</span>
                </div>
            </template>

            <template #cell-video="{ item }">
                <div class="flex items-center">
                    <img
                        :src="item.video.thumbnail"
                        :alt="item.video.title"
                        class="w-12 h-8 rounded mr-2 object-cover"
                    />
                    <span class="max-w-xs truncate">{{
                        item.video.title
                    }}</span>
                </div>
            </template>

            <template #cell-count="{ value }">
                {{ formatNumber(value) }}
            </template>

            <template #cell-created_at="{ value }">
                {{ formatDate(value) }}
            </template>
        </DataTable>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import DataTable from "@/components/DataTable.vue";
import { hashtagsApi } from "@/services/adminApi";
import { useUtils } from "@/composables/useUtils";
const { formatNumber, formatDate } = useUtils();

const hashtags = ref([]);
const loading = ref(false);
const pagination = ref({
    cursor: null,
    prev: false,
    next: false,
});

const searchQuery = ref("");
const sortBy = ref("");
const DEBOUNCE_DELAY = 300;
let searchTimeout = null;

const columns = [
    { key: "id", label: "ID" },
    { key: "name", label: "Hashtag" },
    { key: "count", label: "# of uses" },
    { key: "can_search", label: "Searchable" },
    { key: "can_trend", label: "Trendable" },
    { key: "created_at", label: "Created At" },
];

const sortOptions = [
    { name: "Most Used", value: "count_desc" },
    { name: "Newest", value: "created_at_desc" },
    { name: "Oldest", value: "created_at_asc" },
];

const fetchHashtags = async (cursor = null, direction = "next") => {
    loading.value = true;
    try {
        const params = { cursor, direction };

        if (searchQuery.value) {
            params.search = searchQuery.value;
        }

        if (sortBy.value) {
            params.sort = sortBy.value;
        }

        const response = await hashtagsApi.getHashtags(params);
        hashtags.value = response.data;
        pagination.value = response.meta;
    } catch (error) {
        console.error("Error fetching likes:", error);
    } finally {
        loading.value = false;
    }
};

watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchHashtags();
    }, DEBOUNCE_DELAY);
});

const handleSearch = async (query) => {
    searchQuery.value = query;
};

watch(sortBy, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchHashtags();
    }, DEBOUNCE_DELAY);
});

const handleSort = (sortValue) => {
    sortBy.value = sortValue;
};

const nextPage = () => {
    if (pagination.value.next_cursor) {
        fetchHashtags(pagination.value.next_cursor, "next");
    }
};

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchHashtags(pagination.value.prev_cursor, "previous");
    }
};

onMounted(() => {
    fetchHashtags();
});
</script>
