<template>
    <LoadingSpinner v-if="isLoading || !instance" />

    <div v-else class="max-w-8xl mx-auto p-6 space-y-6">
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
            <div class="bg-gradient-to-r from-purple-500 to-indigo-400 h-32"></div>
            <div class="relative px-6 pb-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:space-x-6">
                    <div class="relative -mt-18 mb-4 sm:mb-0">
                        <div
                            class="w-32 h-32 rounded-full border-4 border-white dark:border-gray-800 bg-gradient-to-br from-purple-400 to-indigo-500 shadow-lg flex items-center justify-center"
                        >
                            <SoftwareProjectLogo :software="instance.software" />
                        </div>
                    </div>

                    <div class="flex-1">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-2"
                        >
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ instance.domain }}
                                </h1>
                                <div
                                    v-if="instance.software"
                                    class="text-gray-600 dark:text-gray-400 font-light"
                                >
                                    <span class="text-gray-400 dark:text-gray-500"
                                        >Powered by
                                    </span>
                                    <span class="font-semibold capitalize">{{
                                        instance.software
                                    }}</span>
                                </div>
                                <div v-else class="text-gray-600 dark:text-gray-400 font-light">
                                    <span class="text-gray-400 dark:text-gray-500"
                                        >Powered by
                                    </span>
                                    <span class="font-semibold capitalize">Unknown</span>
                                </div>
                                <div class="flex items-center mt-2 space-x-4">
                                    <span
                                        :class="[
                                            'px-3 py-1 text-xs font-bold rounded-full uppercase',
                                            instance?.status === 'active'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : instance?.status === 'suspended'
                                                  ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                  : instance?.status === 'pending'
                                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                        ]"
                                    >
                                        {{ instance?.status }}
                                    </span>
                                    <span
                                        v-if="instance.is_local"
                                        class="px-3 py-1 text-xs font-bold rounded-full uppercase bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                    >
                                        Local
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3 mt-4 sm:mt-0">
                                <button
                                    v-if="instance.status === 'active'"
                                    @click="suspendInstance"
                                    class="px-4 py-2 text-sm font-medium text-red-700 bg-red-100 hover:bg-red-200 dark:bg-red-900 dark:text-red-300 dark:hover:bg-red-800 rounded-lg transition-colors"
                                >
                                    Suspend Instance
                                </button>
                                <button
                                    v-else-if="instance.status === 'suspended'"
                                    @click="activateInstance"
                                    class="px-4 py-2 text-sm font-medium text-green-700 bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:text-green-300 dark:hover:bg-green-800 rounded-lg transition-colors"
                                >
                                    Activate Instance
                                </button>
                                <button
                                    @click="refreshInstanceData"
                                    :disabled="isRefreshing"
                                    class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-300 dark:hover:bg-blue-800 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {{ isRefreshing ? 'Refreshing...' : 'Refresh Data' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="instance.status === 'active'" class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(instance.user_count) }}
                </div>
                <div class="card-info-label">Users</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(instance.video_count) }}
                </div>
                <div class="card-info-label">Videos</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(instance.comment_count) }}
                </div>
                <div class="card-info-label">Comments</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(instance.reply_count) }}
                </div>
                <div class="card-info-label">Replies</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(instance.report_count) }}
                </div>
                <div class="card-info-label">Reports</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(instance.follower_count) }}
                </div>
                <div class="card-info-label">Total Followers</div>
            </div>
        </div>

        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm cursor-pointer',
                            activeTab === tab.id
                                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:border-gray-300'
                        ]"
                    >
                        {{ tab.name }}
                        <span
                            v-if="tab.count !== undefined"
                            :class="[
                                'ml-2 py-0.5 px-2 rounded-full text-xs',
                                activeTab === tab.id
                                    ? 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
                            ]"
                        >
                            {{ formatNumber(tab.count) }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <div v-show="activeTab === 'about'">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                        >
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                Information
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Domain
                                    </label>
                                    <div class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ instance.domain }}
                                    </div>
                                </div>
                                <div v-if="instance.description">
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Description
                                    </label>
                                    <div class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ instance.description }}
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Status
                                    </label>
                                    <div
                                        v-if="instance.status == 'active'"
                                        class="mt-1 text-sm text-gray-900 dark:text-white"
                                    >
                                        <span class="text-green-600 font-bold">Active</span>
                                        <span class="text-xs"
                                            >- Found no moderation conflicts or delivery
                                            issues</span
                                        >
                                    </div>
                                    <div
                                        v-else-if="instance.status == 'suspended'"
                                        class="mt-1 text-sm text-gray-900 dark:text-white"
                                    >
                                        <span class="text-red-600 font-bold">Suspended</span>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Software
                                    </label>
                                    <div
                                        class="mt-1 text-sm text-gray-900 dark:text-white capitalize"
                                    >
                                        {{ instance.software || 'Unknown' }}
                                    </div>
                                </div>
                                <div v-if="instance.version">
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Software Version
                                    </label>
                                    <div class="mt-1 text-sm text-gray-900 dark:text-white">
                                        v{{ instance.version }}
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        First Seen
                                    </label>
                                    <div class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ formatDate(instance.created_at) }}
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Last Updated
                                    </label>
                                    <div class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ formatDate(instance.updated_at) }}
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        Version last checked
                                    </label>
                                    <div class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ formatDate(instance.version_last_checked_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                        >
                            <h3 class="text-lg text-gray-900 dark:text-white mb-4">
                                <span class="font-semibold">Settings</span>
                            </h3>
                            <div class="space-y-6">
                                <div v-if="instance.status === 'active'">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                Federation Enabled
                                            </h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                Open federation with this instance
                                            </p>
                                        </div>
                                        <button
                                            @click="toggleFederation"
                                            :class="[
                                                'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                                instance.federation_state == 5
                                                    ? 'bg-blue-600'
                                                    : 'bg-gray-200 dark:bg-gray-600'
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                    instance.federation_state == 5
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'
                                                ]"
                                            ></span>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="instance.status === 'active'">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                Allow Incoming Video Posts
                                            </h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                Accept and process incoming top-level video posts
                                                that can appear in feeds
                                            </p>
                                        </div>
                                        <button
                                            @click="toggleAllowVideoPosts"
                                            :disabled="instance.federation_state !== 5"
                                            :class="[
                                                'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                                instance.federation_state === 5 &&
                                                instance.allow_video_posts
                                                    ? 'bg-blue-600'
                                                    : 'bg-gray-200 dark:bg-gray-600',
                                                instance.federation_state !== 5 ? 'opacity-60' : ''
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                    instance.federation_state === 5 &&
                                                    instance.allow_video_posts
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'
                                                ]"
                                            ></span>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="instance.status === 'active'">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                Allow Videos in FYF Algorithm
                                            </h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                Process videos for use in the For You Feed algorithm
                                            </p>
                                        </div>
                                        <button
                                            @click="toggleAllowVideosInFyf"
                                            :disabled="instance.federation_state !== 5"
                                            :class="[
                                                'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                                instance.federation_state === 5 &&
                                                instance.allow_videos_in_fyf
                                                    ? 'bg-blue-600'
                                                    : 'bg-gray-200 dark:bg-gray-600',
                                                instance.federation_state !== 5 ? 'opacity-60' : ''
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                    instance.federation_state === 5 &&
                                                    instance.allow_videos_in_fyf
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'
                                                ]"
                                            ></span>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        Admin Notes
                                    </label>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        Only visible to admins
                                    </p>
                                    <textarea
                                        v-model="adminNotes"
                                        rows="7"
                                        placeholder="Add notes about this instance..."
                                        maxlength="1000"
                                        :disabled="savingAdminNotes"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                    ></textarea>
                                    <div class="flex justify-between px-3">
                                        <div class="text-xs text-gray-400">
                                            {{ adminNotes?.length ?? 0 }}/1000
                                        </div>

                                        <div class="flex space-x-5 text-xs">
                                            <button
                                                :disabled="savingAdminNotes"
                                                class="text-red-500 cursor-pointer font-light disabled:opacity-50"
                                            >
                                                Clear
                                            </button>
                                            <div
                                                v-if="savingAdminNotes"
                                                class="h-5 w-5 rounded-full border-4 border-gray-200 border-t-blue-500 animate-spin"
                                            ></div>
                                            <button
                                                v-else
                                                class="text-blue-500 cursor-pointer font-bold"
                                                @click="saveAdminNotes"
                                            >
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="activeTab === 'users'">
                    <DataTable
                        title="Users from this Instance"
                        :columns="userColumns"
                        :data="users"
                        :loading="usersLoading"
                        :has-previous="usersPagination.prev_cursor"
                        :has-next="usersPagination.next_cursor"
                        :has-actions="false"
                        :sort-options="userSortOptions"
                        @sort="handleUsersSort"
                        @search="handleUsersSearch"
                        @refresh="fetchUsers"
                        @previous="previousUsersPage"
                        @next="nextUsersPage"
                    >
                        <template #cell-id="{ value }">
                            <router-link
                                :to="`/admin/profiles/${value}`"
                                class="text-blue-500 hover:text-blue-300 font-medium"
                            >
                                {{ truncateMiddle(value, 12) }}
                            </router-link>
                        </template>

                        <template #cell-status="{ value }">
                            <span
                                :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full',
                                    value === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ]"
                            >
                                {{ value }}
                            </span>
                        </template>

                        <template #cell-created_at="{ value }">
                            {{ formatDate(value) }}
                        </template>
                    </DataTable>
                </div>

                <div v-show="activeTab === 'videos'">
                    <DataTable
                        title="Videos from this Instance"
                        :columns="videoColumns"
                        :data="videos"
                        :loading="videosLoading"
                        :has-previous="videosPagination.prev_cursor"
                        :has-next="videosPagination.next_cursor"
                        :has-actions="false"
                        :sort-options="videoSortOptions"
                        @sort="handleVideosSort"
                        @search="handleVideosSearch"
                        @refresh="fetchVideos"
                        @previous="previousVideosPage"
                        @next="nextVideosPage"
                    >
                        <template #cell-id="{ value }">
                            <router-link
                                :to="`/admin/videos/${value}`"
                                class="text-blue-500 hover:text-blue-300 font-medium truncate"
                            >
                                {{ value }}
                            </router-link>
                        </template>

                        <template #cell-caption="{ value }">
                            <div
                                class="max-w-[300px] whitespace-break-spaces break-all"
                                :title="value"
                            >
                                {{ textTruncate(value) }}
                            </div>
                        </template>

                        <template #cell-username="{ item }">
                            <router-link
                                :to="`/admin/profiles/${item.account.id}`"
                                class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-gray-200"
                            >
                                @{{ item.account.username }}
                            </router-link>
                        </template>

                        <template #cell-created_at="{ value }">
                            {{ formatDate(value) }}
                        </template>
                    </DataTable>
                </div>

                <div v-show="activeTab === 'comments'">
                    <DataTable
                        title="Comments from this Instance"
                        :columns="commentColumns"
                        :data="comments"
                        :loading="commentsLoading"
                        :has-previous="commentsPagination.prev_cursor"
                        :has-next="commentsPagination.next_cursor"
                        :has-actions="false"
                        :sort-options="commentSortOptions"
                        @sort="handleCommentsSort"
                        @search="handleCommentsSearch"
                        @refresh="fetchComments"
                        @previous="previousCommentsPage"
                        @next="nextCommentsPage"
                    >
                        <template #cell-id="{ value, item }">
                            <router-link
                                :to="`/admin/videos/${item.v_id}`"
                                class="text-blue-500 hover:text-blue-300 font-medium truncate"
                            >
                                {{ truncateMiddle(value, 10) }}
                            </router-link>
                        </template>

                        <template #cell-v_id="{ value }">
                            <router-link
                                :to="`/admin/videos/${value}`"
                                class="text-blue-500 hover:text-blue-300 font-medium truncate"
                            >
                                {{ truncateMiddle(value, 10) }}
                            </router-link>
                        </template>

                        <template #cell-caption="{ value }">
                            <div
                                class="max-w-[300px] whitespace-break-spaces break-all"
                                :title="value"
                            >
                                {{ textTruncate(value) }}
                            </div>
                        </template>

                        <template #cell-username="{ item }">
                            <router-link
                                :to="`/admin/profiles/${item.account.id}`"
                                class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-gray-200"
                            >
                                @{{ textTruncate(item.account.username, 10) }}
                            </router-link>
                        </template>

                        <template #cell-created_at="{ value }">
                            {{ formatDate(value) }}
                        </template>
                    </DataTable>
                </div>

                <div v-show="activeTab === 'reports'">
                    <DataTable
                        title="Reports from this Instance"
                        :columns="reportColumns"
                        :data="reports"
                        :loading="reportsLoading"
                        :has-previous="reportsPagination.prev_cursor"
                        :has-next="reportsPagination.next_cursor"
                        :has-actions="false"
                        :sort-options="reportSortOptions"
                        @sort="handleReportsSort"
                        @search="handleReportsSearch"
                        @refresh="fetchReports"
                        @previous="previousReportsPage"
                        @next="nextReportsPage"
                    >
                        <template #cell-id="{ value }">
                            <router-link
                                :to="`/admin/reports/${value}`"
                                class="text-blue-500 hover:text-blue-300 font-medium truncate"
                            >
                                {{ value }}
                            </router-link>
                        </template>

                        <template #cell-v_id="{ value }">
                            <router-link
                                :to="`/admin/videos/${value}`"
                                class="text-blue-500 hover:text-blue-300 font-medium truncate"
                            >
                                {{ truncateMiddle(value, 10) }}
                            </router-link>
                        </template>

                        <template #cell-type="{ value }">
                            <span
                                :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full',
                                    value === 'spam'
                                        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        : value === 'harassment'
                                          ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
                                          : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                ]"
                            >
                                {{ value }}
                            </span>
                        </template>

                        <template #cell-reporter="{ item }">
                            <router-link
                                :to="`/admin/profiles/${item.reporter.id}`"
                                class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-gray-200 flex items-center gap-2"
                            >
                                <img :src="item.reporter.avatar" class="w-8 h-8 rounded-full" />
                                @{{ textTruncate(item.reporter.username, 10) }}
                            </router-link>
                        </template>

                        <template #cell-status="{ value }">
                            <span
                                :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full',
                                    value === 'resolved'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : value === 'pending'
                                          ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                          : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                ]"
                            >
                                {{ value }}
                            </span>
                        </template>

                        <template #cell-created_at="{ value }">
                            {{ formatDate(value) }}
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import { instancesApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'

const { formatNumber, formatDate, truncateMiddle, textTruncate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const router = useRouter()
const route = useRoute()
const instance = ref(null)
const adminNotes = ref('')
const isLoading = ref(false)
const isRefreshing = ref(false)
const activeTab = ref('users')
const savingAdminNotes = ref(false)

// Tab configuration
const tabs = computed(() =>
    instance?.value.status === 'active'
        ? [
              { id: 'about', name: 'About' },
              { id: 'users', name: 'Users', count: instance.value?.user_count },
              {
                  id: 'videos',
                  name: 'Videos',
                  count: instance.value?.video_count
              },
              {
                  id: 'comments',
                  name: 'Comments',
                  count: instance.value?.comment_count
              },
              { id: 'reports', name: 'Reports' }
          ]
        : [{ id: 'about', name: 'About' }]
)

// About

const saveAdminNotes = async () => {
    savingAdminNotes.value = true
    try {
        await instancesApi.updateInstanceNotes(instance.value.id, {
            admin_notes: adminNotes.value
        })
        instance.value.admin_note = adminNotes.value
        console.log('Admin notes saved for', instance.value.domain)
    } catch (error) {
        console.error('Error saving admin notes:', error)
    } finally {
        savingAdminNotes.value = false
    }
}

// Users data
const users = ref([])
const usersLoading = ref(false)
const usersPagination = ref({})
const usersSearchQuery = ref('')
const usersSortBy = ref('')

const userColumns = [
    { key: 'id', label: 'ID' },
    { key: 'username', label: 'Username' },
    { key: 'name', label: 'Display Name' },
    { key: 'follower_count', label: 'Followers' },
    { key: 'post_count', label: 'Videos' },
    { key: 'created_at', label: 'Joined' }
]

const userSortOptions = [
    { name: 'Username A-Z', value: 'username_asc' },
    { name: 'Username Z-A', value: 'username_desc' },
    { name: 'Most Followers', value: 'followers_desc' },
    { name: 'Most Videos', value: 'video_count_desc' },
    { name: 'Newest', value: 'created_at_desc' },
    { name: 'Oldest', value: 'created_at_asc' }
]

// Videos data
const videos = ref([])
const videosLoading = ref(false)
const videosPagination = ref({})
const videosSearchQuery = ref('')
const videosSortBy = ref('')

const videoColumns = [
    { key: 'id', label: 'ID' },
    { key: 'caption', label: 'Caption' },
    { key: 'username', label: 'Creator' },
    { key: 'likes', label: 'Likes' },
    { key: 'comments', label: 'Comments' },
    { key: 'shares', label: 'Shares' },
    { key: 'created_at', label: 'Posted' }
]

const videoSortOptions = [
    { name: 'Most Recent', value: 'created_at_desc' },
    { name: 'Most Likes', value: 'like_count_desc' },
    { name: 'Most Views', value: 'view_count_desc' },
    { name: 'Most Comments', value: 'comment_count_desc' }
]

// Comments data
const comments = ref([])
const commentsLoading = ref(false)
const commentsPagination = ref({})
const commentsSearchQuery = ref('')
const commentsSortBy = ref('')

const commentColumns = [
    { key: 'id', label: 'ID' },
    { key: 'v_id', label: 'Video' },
    { key: 'username', label: 'Author' },
    { key: 'caption', label: 'Content' },
    { key: 'likes', label: 'Likes' },
    { key: 'replies', label: 'Replies' },
    { key: 'created_at', label: 'Posted' }
]

const commentSortOptions = [
    { name: 'Most Recent', value: 'created_at_desc' },
    { name: 'Most Likes', value: 'like_count_desc' },
    { name: 'Most Replies', value: 'reply_count_desc' }
]

// Reports data
const reports = ref([])
const reportsLoading = ref(false)
const reportsPagination = ref({})
const reportsSearchQuery = ref('')
const reportsSortBy = ref('')

const reportColumns = [
    { key: 'id', label: 'ID' },
    { key: 'content_type', label: 'Type' },
    { key: 'reason', label: 'Report Reason' },
    { key: 'reporter', label: 'Reporter' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Reported' }
]

const reportSortOptions = [
    { name: 'Most Recent', value: 'created_at_desc' },
    { name: 'Type', value: 'type_asc' },
    { name: 'Status', value: 'status_asc' }
]

// Fetch functions
const fetchInstance = async (id) => {
    isLoading.value = true
    try {
        const response = await instancesApi.getInstance(id)
        instance.value = response.data
        adminNotes.value = response.data?.admin_notes || ''

        if (response.data.status != 'active') {
            activeTab.value = 'about'
        }
    } catch (error) {
        console.error('Error fetching instance:', error)
    } finally {
        isLoading.value = false
    }
}

const fetchUsers = async (cursor = null, direction = 'next') => {
    if (instance.value.status != 'active') {
        return
    }
    usersLoading.value = true
    try {
        const params = {
            cursor,
            direction
        }

        if (usersSearchQuery.value) {
            params.q = usersSearchQuery.value
        }

        if (usersSortBy.value) {
            params.sort = usersSortBy.value
        }

        const response = await instancesApi.getInstanceUsers(instance.value.id, params)
        users.value = response.data
        usersPagination.value = response.meta
    } catch (error) {
        console.error('Error fetching users:', error)
    } finally {
        usersLoading.value = false
    }
}

const fetchVideos = async (cursor = null, direction = 'next') => {
    videosLoading.value = true
    try {
        const params = {
            cursor,
            direction
        }

        if (videosSearchQuery.value) {
            params.search = videosSearchQuery.value
        }

        if (videosSortBy.value) {
            params.sort = videosSortBy.value
        }

        const response = await instancesApi.getInstanceVideos(instance.value.id, params)
        videos.value = response.data
        videosPagination.value = response.meta
    } catch (error) {
        console.error('Error fetching videos:', error)
    } finally {
        videosLoading.value = false
    }
}

const fetchComments = async (cursor = null, direction = 'next') => {
    commentsLoading.value = true
    try {
        const params = {
            cursor,
            direction
        }

        if (commentsSearchQuery.value) {
            params.q = commentsSearchQuery.value
        }

        if (commentsSortBy.value) {
            params.sort = commentsSortBy.value
        }

        const response = await instancesApi.getInstanceComments(instance.value.id, params)
        comments.value = response.data
        commentsPagination.value = response.meta
    } catch (error) {
        console.error('Error fetching comments:', error)
    } finally {
        commentsLoading.value = false
    }
}

const fetchReports = async (cursor = null, direction = 'next') => {
    reportsLoading.value = true
    try {
        const params = {
            cursor,
            direction
        }

        if (reportsSearchQuery.value) {
            params.search = reportsSearchQuery.value
        }

        if (reportsSortBy.value) {
            params.sort = reportsSortBy.value
        }

        const response = await instancesApi.getInstanceReports(instance.value.id, params)
        reports.value = response.data
        reportsPagination.value = response.meta
    } catch (error) {
        console.error('Error fetching reports:', error)
    } finally {
        reportsLoading.value = false
    }
}

const suspendInstance = async () => {
    const title = 'Suspend Federation'

    const message = `<div class="space-y-4">
            <p class="text-gray-800">Are you sure you want to suspend federation with <span class="font-semibold text-gray-900">${instance.value.domain}</span>?</p>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm text-amber-800">This will delete existing connections between users.</p>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    <span class="font-medium">Note:</span> Existing profiles, videos, and interactions from this instance will be permanently deleted.
                    You can re-enable federation at any time.
                </p>
            </div>
        </div>`

    const confirmText = 'Suspend'
    const cancelText = 'Cancel'

    const result = await confirmModal(title, message, confirmText, cancelText)

    if (result) {
        try {
            await instancesApi.suspendInstance(instance.value.id)
            instance.value.status = 'suspended'
            instance.value.federation_state = 2
            activeTab.value = 'about'
            console.log(`Instance ${instance.value.domain} suspended`)
        } catch (error) {
            console.error('Error suspending instance:', error)
        }
    }
}

const activateInstance = async () => {
    const title = 'Activate Federation'

    const message = `<div class="space-y-4">
            <p class="text-gray-800">Are you sure you want to reactivate federation with <span class="font-semibold text-gray-900">${instance.value.domain}</span>?</p>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm text-amber-800">Previous connections and interactions will not be restored as they were permanently deleted upon suspension.</p>
                </div>
            </div>
        </div>`

    const confirmText = 'Activate'
    const cancelText = 'Cancel'

    const result = await confirmModal(title, message, confirmText, cancelText)

    if (result) {
        try {
            await instancesApi.activateInstance(instance.value.id)
            instance.value.status = 'active'
            await fetchInstance(instance.value.id)
            console.log(`Instance ${instance.value.domain} activated`)
        } catch (error) {
            console.error('Error activating instance:', error)
        }
    }
}

const refreshInstanceData = async () => {
    isRefreshing.value = true
    try {
        await instancesApi.refreshInstanceData(instance.value.id)
        await fetchInstance(route.params.id)
        console.log(`Instance ${instance.value.domain} data refreshed`)
    } catch (error) {
        console.error('Error refreshing instance data:', error)
    } finally {
        isRefreshing.value = false
    }
}

const toggleFederation = async () => {
    const isDisabling = instance.value.federation_state == 5
    const title = isDisabling ? 'Disable Federation' : 'Re-enable Federation'

    const message = isDisabling
        ? `<div class="space-y-4">
            <p class="text-gray-800">Are you sure you want to disable federation with <span class="font-semibold text-gray-900">${instance.value.domain}</span>?</p>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm text-amber-800">This may disrupt existing connections between users. Use with caution.</p>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    <span class="font-medium">Note:</span> Existing profiles, videos, and interactions will remain intact.
                    To remove all content from this instance, use the Suspend option instead.
                    You can re-enable federation at any time.
                </p>
            </div>
        </div>`
        : `<div class="space-y-3">
            <p class="text-gray-800">Are you sure you want to re-enable federation with <span class="font-semibold text-gray-900">${instance.value.domain}</span>?</p>
            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                <p class="text-sm text-green-800">This will restore federation activities and allow new interactions with users from this instance.</p>
            </div>
        </div>`

    const confirmText = isDisabling ? 'Disable Federation' : 'Re-enable Federation'
    const cancelText = 'Cancel'

    const result = await confirmModal(title, message, confirmText, cancelText)

    if (result) {
        try {
            await instancesApi.updateInstanceSettings(instance.value.id, {
                federation_state: instance.value.federation_state == 5 ? 2 : 5
            })
            instance.value.federation_state = instance.value.federation_state == 5 ? 2 : 5
        } catch (error) {
            console.error('Error updating federation setting:', error)
        }
    }
}

const toggleAllowVideoPosts = async () => {
    try {
        await instancesApi.updateInstanceSettings(instance.value.id, {
            allow_video_posts: !instance.value.allow_video_posts
        })
        instance.value.allow_video_posts = !instance.value.allow_video_posts
    } catch (error) {
        console.error('Error updating federation setting:', error)
    }
}

const toggleAllowVideosInFyf = async () => {
    try {
        await instancesApi.updateInstanceSettings(instance.value.id, {
            allow_videos_in_fyf: !instance.value.allow_videos_in_fyf
        })
        instance.value.allow_videos_in_fyf = !instance.value.allow_videos_in_fyf
    } catch (error) {
        console.error('Error updating federation setting:', error)
    }
}

// Search and pagination handlers
const handleUsersSort = (sortValue) => {
    usersSortBy.value = sortValue
    fetchUsers()
}

const handleUsersSearch = (query) => {
    usersSearchQuery.value = query
    fetchUsers()
}

const nextUsersPage = () => {
    if (usersPagination.value.next_cursor) {
        fetchUsers(usersPagination.value.next_cursor, 'next')
    }
}

const previousUsersPage = () => {
    if (usersPagination.value.prev_cursor) {
        fetchUsers(usersPagination.value.prev_cursor, 'previous')
    }
}

const handleVideosSort = (sortValue) => {
    videosSortBy.value = sortValue
    fetchVideos()
}

const handleVideosSearch = (query) => {
    videosSearchQuery.value = query
    fetchVideos()
}

const nextVideosPage = () => {
    if (videosPagination.value.next_cursor) {
        fetchVideos(videosPagination.value.next_cursor, 'next')
    }
}

const previousVideosPage = () => {
    if (videosPagination.value.prev_cursor) {
        fetchVideos(videosPagination.value.prev_cursor, 'previous')
    }
}

const handleCommentsSort = (sortValue) => {
    commentsSortBy.value = sortValue
    fetchComments()
}

const handleCommentsSearch = (query) => {
    commentsSearchQuery.value = query
    fetchComments()
}

const nextCommentsPage = () => {
    if (commentsPagination.value.next_cursor) {
        fetchComments(commentsPagination.value.next_cursor, 'next')
    }
}

const previousCommentsPage = () => {
    if (commentsPagination.value.prev_cursor) {
        fetchComments(commentsPagination.value.prev_cursor, 'previous')
    }
}

const handleReportsSort = (sortValue) => {
    reportsSortBy.value = sortValue
    fetchReports()
}

const handleReportsSearch = (query) => {
    reportsSearchQuery.value = query
    fetchReports()
}

const nextReportsPage = () => {
    if (reportsPagination.value.next_cursor) {
        fetchReports(reportsPagination.value.next_cursor, 'next')
    }
}

const previousReportsPage = () => {
    if (reportsPagination.value.prev_cursor) {
        fetchReports(reportsPagination.value.prev_cursor, 'previous')
    }
}

const nextActivitiesPage = () => {
    if (activitiesPagination.value.next_cursor) {
        fetchActivities(activitiesPagination.value.next_cursor, 'next')
    }
}

const previousActivitiesPage = () => {
    if (activitiesPagination.value.prev_cursor) {
        fetchActivities(activitiesPagination.value.prev_cursor, 'previous')
    }
}

// Watchers
watch(activeTab, (newTab) => {
    switch (newTab) {
        case 'users':
            if (users.value.length === 0) fetchUsers()
            break
        case 'videos':
            if (videos.value.length === 0) fetchVideos()
            break
        case 'comments':
            if (comments.value.length === 0) fetchComments()
            break
        case 'reports':
            if (reports.value.length === 0) fetchReports()
            break
        case 'activity':
            if (activities.value.length === 0) fetchActivities()
            break
    }
})

watch(
    () => route.params.id,
    (newId) => {
        if (newId) {
            fetchInstance(newId)
        }
    }
)

onMounted(async () => {
    await fetchInstance(route.params.id)
    // Load initial tab data
    fetchUsers()
})
</script>

<style scoped>
@reference "../../../sass/next.css";

.card-info {
    @apply bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6;
}

.card-info-value {
    @apply text-2xl font-bold text-gray-900 dark:text-white;
}

.card-info-label {
    @apply text-sm text-gray-600 dark:text-gray-400;
}
</style>
