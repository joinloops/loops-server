<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Profiles
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Manage user profiles
            </p>
        </div>

        <DataTable
            title="Profiles"
            :columns="columns"
            :data="profiles"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :has-actions="false"
            :sort-options="sortOptions"
            @sort="handleSort"
            @search="handleSearch"
            @refresh="fetchProfiles"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-id="{ value }">
                <router-link
                    :to="`/admin/profiles/${value}`"
                    :title="value"
                    class="text-xs text-blue-500 hover:text-blue-300"
                >
                    {{ value }}
                </router-link>
            </template>

            <template #cell-user="{ item }">
                <div class="flex items-center">
                    <img
                        :src="item.avatar"
                        :alt="item.username"
                        class="w-10 h-10 rounded-full mr-3"
                        @error="
                            $event.target.src = '/storage/avatars/default.jpg'
                        "
                    />
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-3">
                            <div
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                &commat;{{ item.username }}
                            </div>
                            <div
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ item.name }}
                            </div>
                        </div>
                        <div
                            v-if="item.bio"
                            class="truncate text-xs text-gray-400"
                        >
                            {{ item.bio }}
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-created_at="{ value }">
                {{ formatDate(value) }}
            </template>

            <template #actions="{ item }">
                <button
                    @click="viewProfile(item)"
                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3 cursor-pointer"
                >
                    View
                </button>
                <button
                    @click="manageProfile(item)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 mr-3 cursor-pointer"
                >
                    Manage
                </button>
            </template>
        </DataTable>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import DataTable from "@/components/DataTable.vue";
import { profilesApi } from "@/services/adminApi";
import { useUtils } from "@/composables/useUtils";
const { truncateMiddle, formatDate } = useUtils();

const router = useRouter();
const profiles = ref([]);
const loading = ref(false);
const pagination = ref({
    cursor: null,
    hasPrevious: false,
    hasNext: false,
});

const searchQuery = ref("");
const sortBy = ref("");
const DEBOUNCE_DELAY = 300;
let searchTimeout = null;

const columns = [
    { key: "id", label: "ID" },
    { key: "user", label: "User" },
    { key: "post_count", label: "Videos" },
    { key: "follower_count", label: "Followers" },
    { key: "following_count", label: "Following" },
    { key: "created_at", label: "Joined" },
];

const sortOptions = [
    { name: "Username A-Z", value: "username_asc" },
    { name: "Username Z-A", value: "username_desc" },
    { name: "Most Followers", value: "followers_desc" },
    { name: "Most Videos", value: "video_count_desc" },
    { name: "Newest", value: "created_at_desc" },
    { name: "Oldest", value: "created_at_asc" },
    { name: "Last Updated", value: "updated_at_desc" },
];

const fetchProfiles = async (cursor = null, direction = "next") => {
    loading.value = true;
    try {
        const params = { cursor, direction };

        if (searchQuery.value) {
            params.search = searchQuery.value;
        }

        if (sortBy.value) {
            params.sort = sortBy.value;
        }

        const response = await profilesApi.getProfiles(params);
        profiles.value = response.data;
        pagination.value = response.meta;
    } catch (error) {
        console.error("Error fetching profiles:", error);
    } finally {
        loading.value = false;
    }
};

const viewProfile = (profile) => {
    router.push(`/@${profile.username}`);
};

const manageProfile = (profile) => {
    router.push(`/admin/profiles/${profile.id}`);
};

watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchProfiles();
    }, DEBOUNCE_DELAY);
});

watch(sortBy, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchProfiles();
    }, DEBOUNCE_DELAY);
});

const handleSort = (sortValue) => {
    sortBy.value = sortValue;
};

const handleSearch = (query) => {
    searchQuery.value = query;
};

const nextPage = () => {
    if (pagination.value.next_cursor) {
        fetchProfiles(pagination.value.next_cursor, "next");
    }
};

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchProfiles(pagination.value.prev_cursor, "previous");
    }
};

onUnmounted(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});

onMounted(() => {
    fetchProfiles();
});
</script>
