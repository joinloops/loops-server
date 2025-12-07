<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Settings</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Manage your Loops instance configuration and preferences
            </p>
        </div>

        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
        >
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors cursor-pointer',
                            activeTab === tab.id
                                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <component :is="tab.icon" class="w-5 h-5" />
                            {{ tab.name }}
                        </div>
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <div v-if="activeTab === 'general'" class="space-y-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Instance Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Instance Name</label
                                >
                                <input
                                    v-model="settings.general.instanceName"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    maxlength="15"
                                />
                                <div class="flex justify-between items-center mt-1">
                                    <div class="text-sm text-gray-600 dark:text-gray-300">
                                        Displayed in the menu, website previews and page title
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        {{ settings.general.instanceName.length || 0 }}/15
                                        characters
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Instance URL</label
                                >
                                <input
                                    v-model="settings.general.instanceUrl"
                                    type="url"
                                    disabled
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-200 dark:disabled:bg-gray-900 disabled:opacity-50"
                                />
                                <div class="flex justify-between items-center mt-1">
                                    <div class="text-xs text-gray-600 dark:text-gray-300">
                                        To change, edit the
                                        <code>APP_URL</code> in the <kbd>.env</kbd> file.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >Instance Description</label
                            >
                            <textarea
                                v-model="settings.general.instanceDescription"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                maxlength="150"
                            ></textarea>
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    Displayed in website previews and meta description
                                </div>
                                <div class="text-sm text-gray-400">
                                    {{ settings.general.instanceDescription.length || 0 }}/150
                                    characters
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Contact Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Admin Email</label
                                >
                                <input
                                    v-model="settings.general.adminEmail"
                                    type="email"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Support Email</label
                                >
                                <input
                                    v-model="settings.general.supportEmail"
                                    type="email"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Support Forum</label
                                >
                                <input
                                    v-model="settings.general.supportForum"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Support Fediverse Account</label
                                >
                                <input
                                    v-model="settings.general.supportFediverseAccount"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Add an optional fediverse support account that accepts DMs"
                                />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            User Registration
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-white"
                                        >Open Registration</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Allow new users to register without approval
                                    </p>
                                </div>
                                <button
                                    @click="
                                        settings.general.openRegistration =
                                            !settings.general.openRegistration
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                        settings.general.openRegistration
                                            ? 'bg-blue-600'
                                            : 'bg-gray-200 dark:bg-gray-600'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            settings.general.openRegistration
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-white"
                                        >Email Verification Required</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Require email verification for new accounts
                                    </p>
                                </div>
                                <button
                                    @click="
                                        settings.general.emailVerification =
                                            !settings.general.emailVerification
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                        settings.general.emailVerification
                                            ? 'bg-blue-600'
                                            : 'bg-gray-200 dark:bg-gray-600'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            settings.general.emailVerification
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="hidden">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Content Moderation
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Default Content Status</label
                                >
                                <select
                                    v-model="settings.general.defaultContentStatus"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="published">Published (Auto-approve)</option>
                                    <option value="pending">Pending Review</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-white"
                                        >Auto-Moderate NSFW Content</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Automatically flag potentially NSFW content for review
                                    </p>
                                </div>
                                <button
                                    @click="
                                        settings.general.autoModerateNSFW =
                                            !settings.general.autoModerateNSFW
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                        settings.general.autoModerateNSFW
                                            ? 'bg-blue-600'
                                            : 'bg-gray-200 dark:bg-gray-600'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            settings.general.autoModerateNSFW
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'branding'" class="space-y-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                            Logo & Brand Assets
                        </h3>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div
                                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <label
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Logo</label
                                    >
                                    <span class="text-xs text-gray-500 dark:text-gray-400"
                                        >PNG, JPG up to 2MB</span
                                    >
                                </div>

                                <div
                                    class="flex items-center justify-center mb-4 p-6 bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600"
                                >
                                    <img
                                        :src="getLogoSource()"
                                        alt="Logo"
                                        class="max-h-16 w-auto object-contain"
                                    />
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <label class="flex-1 relative cursor-pointer">
                                            <input
                                                type="file"
                                                class="sr-only"
                                                @change="handleLogoChange($event)"
                                                accept="image/*"
                                                ref="logoInput"
                                            />
                                            <div
                                                class="w-full px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors text-center"
                                            >
                                                {{ pendingLogo ? 'Change file' : 'Choose file' }}
                                            </div>
                                        </label>
                                    </div>

                                    <div v-if="pendingLogo" class="flex gap-2">
                                        <button
                                            @click="saveLogo"
                                            :disabled="logoSaving"
                                            class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm rounded-lg transition-colors"
                                        >
                                            <span v-if="logoSaving">Saving...</span>
                                            <span v-else>Save Logo</span>
                                        </button>
                                        <button
                                            @click="clearPendingLogo"
                                            class="px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    </div>

                                    <div v-else-if="settings.branding.logo" class="flex gap-2">
                                        <button
                                            @click="deleteLogo"
                                            :disabled="logoDeleting"
                                            class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white text-sm rounded-lg transition-colors"
                                        >
                                            <span v-if="logoDeleting">Removing...</span>
                                            <span v-else>Remove Logo</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="hidden bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <label
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Favicon</label
                                    >
                                    <span class="text-xs text-gray-500 dark:text-gray-400"
                                        >ICO, PNG 32x32px</span
                                    >
                                </div>

                                <div
                                    class="flex items-center justify-center mb-4 p-6 bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600"
                                >
                                    <img
                                        :src="getFaviconSource()"
                                        alt="Favicon"
                                        class="h-8 w-8 object-contain"
                                    />
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <label class="flex-1 relative cursor-pointer">
                                            <input
                                                type="file"
                                                class="sr-only"
                                                @change="handleFaviconChange($event)"
                                                accept="image/jpeg,image/png"
                                                ref="faviconInput"
                                            />
                                            <div
                                                class="w-full px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors text-center"
                                            >
                                                {{ pendingFavicon ? 'Change file' : 'Choose file' }}
                                            </div>
                                        </label>
                                    </div>

                                    <div v-if="pendingFavicon" class="flex gap-2">
                                        <button
                                            @click="saveFavicon"
                                            :disabled="faviconSaving"
                                            class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm rounded-lg transition-colors"
                                        >
                                            <span v-if="faviconSaving">Saving...</span>
                                            <span v-else>Save Favicon</span>
                                        </button>
                                        <button
                                            @click="clearPendingFavicon"
                                            class="px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    </div>

                                    <div v-else-if="settings.branding.favicon" class="flex gap-2">
                                        <button
                                            @click="deleteFavicon"
                                            :disabled="faviconDeleting"
                                            class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white text-sm rounded-lg transition-colors"
                                        >
                                            <span v-if="faviconDeleting">Removing...</span>
                                            <span v-else>Remove Favicon</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                Custom Styling
                            </h3>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Custom CSS</label
                                >
                                <textarea
                                    v-model="settings.branding.customCSS"
                                    rows="8"
                                    placeholder="/* Add your custom CSS here */"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm resize-none"
                                ></textarea>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    This CSS will be applied to your instance's frontend. Use with
                                    caution.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <button
                                @click="onSaveCSS"
                                :disabled="saving || cssDeleting"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white font-medium shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg
                                    v-if="saving && !cssDeleting"
                                    class="mr-2 h-4 w-4 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                        opacity=".25"
                                    />
                                    <path
                                        d="M22 12a10 10 0 0 1-10 10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                </svg>
                                <span>Save changes</span>
                            </button>

                            <button
                                @click="onDeleteCSS"
                                :disabled="cssDeleting || !settings.branding.customCSS"
                                class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2 text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:hover:bg-red-900/20 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg
                                    v-if="cssDeleting"
                                    class="mr-2 h-4 w-4 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                        opacity=".25"
                                    />
                                    <path
                                        d="M22 12a10 10 0 0 1-10 10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                </svg>
                                <span>Delete CSS</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'media'" class="space-y-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Upload Limits
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Max Video Size (MB)</label
                                >
                                <input
                                    v-model.number="settings.media.maxVideoSize"
                                    type="number"
                                    min="1"
                                    max="1000"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Max Image Size (MB)</label
                                >
                                <input
                                    v-model.number="settings.media.maxImageSize"
                                    type="number"
                                    min="1"
                                    max="50"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                        <div class="mt-4">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >Max Video Duration (seconds)</label
                            >
                            <input
                                v-model.number="settings.media.maxVideoDuration"
                                type="number"
                                min="10"
                                max="3600"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Allowed File Types
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Video Formats</label
                                >
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <label
                                        v-for="format in videoFormats"
                                        :key="format"
                                        class="flex items-center"
                                    >
                                        <input
                                            v-model="settings.media.allowedVideoFormats"
                                            :value="format"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500"
                                        />
                                        <span
                                            class="ml-2 text-sm text-gray-700 dark:text-gray-300"
                                            >{{ format.toUpperCase() }}</span
                                        >
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Storage Configuration
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Storage Driver</label
                                >
                                <select
                                    v-model="settings.media.storageDriver"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="local">Local Storage</option>
                                    <option value="s3">Amazon S3</option>
                                    <option value="gcs">Google Cloud Storage</option>
                                    <option value="azure">Azure Blob Storage</option>
                                </select>
                            </div>

                            <div
                                v-if="settings.media.storageDriver !== 'local'"
                                class="grid grid-cols-1 md:grid-cols-2 gap-6"
                            >
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >Bucket/Container Name</label
                                    >
                                    <input
                                        v-model="settings.media.bucketName"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >CDN URL</label
                                    >
                                    <input
                                        v-model="settings.media.cdnUrl"
                                        type="url"
                                        placeholder="https://cdn.example.com"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Video Processing
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-white"
                                        >Auto-generate Thumbnails</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Automatically create thumbnail images for uploaded videos
                                    </p>
                                </div>
                                <button
                                    @click="
                                        settings.media.autoThumbnails =
                                            !settings.media.autoThumbnails
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                        settings.media.autoThumbnails
                                            ? 'bg-blue-600'
                                            : 'bg-gray-200 dark:bg-gray-600'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            settings.media.autoThumbnails
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-white"
                                        >Video Transcoding</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Convert videos to optimized formats for better compatibility
                                    </p>
                                </div>
                                <button
                                    @click="
                                        settings.media.videoTranscoding =
                                            !settings.media.videoTranscoding
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                        settings.media.videoTranscoding
                                            ? 'bg-blue-600'
                                            : 'bg-gray-200 dark:bg-gray-600'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            settings.media.videoTranscoding
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'federation'" class="space-y-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            ActivityPub Configuration
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-white"
                                        >Enable Federation</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Allow this instance to communicate with other federated
                                        instances
                                    </p>
                                </div>
                                <button
                                    @click="
                                        settings.federation.enableFederation =
                                            !settings.federation.enableFederation
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                        settings.federation.enableFederation
                                            ? 'bg-blue-600'
                                            : 'bg-gray-200 dark:bg-gray-600'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            settings.federation.enableFederation
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>

                            <div v-if="settings.federation.enableFederation">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >Federation Mode</label
                                >
                                <select
                                    v-model="settings.federation.federationMode"
                                    class="w-auto px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="open">
                                        Open (Allow all, except servers you block)
                                    </option>
                                    <!-- <option value="blocklist">
                                        Moderated (New servers must get approval
                                        before ingesting activities)
                                    </option>
                                    <option value="allowlist">
                                        Trusted (Only joinloops.org + manually
                                        approved servers)
                                    </option> -->
                                    <option value="lockdown">
                                        Lockdown (Only explicitly allowed servers)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div v-if="settings.federation.enableFederation" class="flex gap-10">
                        <div class="flex-1" v-if="settings.federation.federationMode != 'open'">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Instance Management
                                </h3>
                                <!-- Toggle Switch -->
                                <div class="flex items-center gap-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400"
                                        >Individual</span
                                    >
                                    <button
                                        @click="toggleBulkView"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        :class="
                                            isBulkView
                                                ? 'bg-blue-600'
                                                : 'bg-gray-200 dark:bg-gray-700'
                                        "
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="isBulkView ? 'translate-x-6' : 'translate-x-1'"
                                        ></span>
                                    </button>
                                    <span class="text-sm text-gray-600 dark:text-gray-400"
                                        >Bulk CSV</span
                                    >
                                </div>
                            </div>

                            <div class="space-y-6">
                                <!-- Allowlist Mode -->
                                <div v-if="settings.federation.federationMode === 'allowlist'">
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Allowed Instances
                                    </label>

                                    <!-- Individual Input View -->
                                    <div v-if="!isBulkView" class="space-y-2">
                                        <div
                                            v-for="(instance, index) in settings.federation
                                                .allowedInstances"
                                            :key="index"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                v-model="
                                                    settings.federation.allowedInstances[index]
                                                "
                                                type="text"
                                                placeholder="example.social"
                                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                            <button
                                                @click="removeInstance('allowed', index)"
                                                class="p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                <svg
                                                    class="w-5 h-5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                    ></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <button
                                            @click="addInstance('allowed')"
                                            class="w-full px-3 py-2 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                        >
                                            + Add Instance
                                        </button>
                                    </div>

                                    <!-- Bulk CSV View -->
                                    <div v-else class="space-y-3">
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                            Enter instance domains, one per line or comma-separated:
                                        </div>
                                        <textarea
                                            v-model="allowedInstancesBulk"
                                            @input="updateInstancesFromBulk('allowed')"
                                            placeholder="example.social&#10;another.instance&#10;third.domain"
                                            rows="6"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                                        ></textarea>
                                        <div class="flex gap-2">
                                            <button
                                                @click="clearInstances('allowed')"
                                                class="px-3 py-1 text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Clear All
                                            </button>
                                            <div
                                                class="text-sm text-gray-500 dark:text-gray-400 ml-auto"
                                            >
                                                {{ settings.federation.allowedInstances.length }}
                                                instance(s)
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Blocklist Mode -->
                                <div v-if="settings.federation.federationMode === 'blocklist'">
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Blocked Instances
                                    </label>

                                    <!-- Individual Input View -->
                                    <div v-if="!isBulkView" class="space-y-2">
                                        <div
                                            v-for="(instance, index) in settings.federation
                                                .blockedInstances"
                                            :key="index"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                v-model="
                                                    settings.federation.blockedInstances[index]
                                                "
                                                type="text"
                                                placeholder="bad.example"
                                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                            <button
                                                @click="removeInstance('blocked', index)"
                                                class="p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                <svg
                                                    class="w-5 h-5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                    ></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <button
                                            @click="addInstance('blocked')"
                                            class="w-full px-3 py-2 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                        >
                                            + Add Instance
                                        </button>
                                    </div>

                                    <!-- Bulk CSV View -->
                                    <div v-else class="space-y-3">
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                            Enter instance domains to block, one per line or
                                            comma-separated:
                                        </div>
                                        <textarea
                                            v-model="blockedInstancesBulk"
                                            @input="updateInstancesFromBulk('blocked')"
                                            placeholder="bad.example&#10;spam.domain&#10;harmful.site"
                                            rows="6"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                                        ></textarea>
                                        <div class="flex gap-2">
                                            <button
                                                @click="clearInstances('blocked')"
                                                class="px-3 py-1 text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Clear All
                                            </button>
                                            <div
                                                class="text-sm text-gray-500 dark:text-gray-400 ml-auto"
                                            >
                                                {{ settings.federation.blockedInstances.length }}
                                                instance(s)
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lockdown Mode -->
                                <div v-if="settings.federation.federationMode === 'lockdown'">
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Allowed Instances
                                    </label>

                                    <!-- Individual Input View -->
                                    <div v-if="!isBulkView" class="space-y-2">
                                        <div
                                            v-for="(instance, index) in settings.federation
                                                .allowedInstances"
                                            :key="index"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                v-model="
                                                    settings.federation.allowedInstances[index]
                                                "
                                                type="text"
                                                placeholder="example.social"
                                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                            <button
                                                @click="removeInstance('allowed', index)"
                                                class="p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                <svg
                                                    class="w-5 h-5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                    ></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <button
                                            @click="addInstance('allowed')"
                                            class="w-full px-3 py-2 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                        >
                                            + Add Instance
                                        </button>
                                    </div>

                                    <!-- Bulk CSV View -->
                                    <div v-else class="space-y-3">
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                            Enter instance domains, one per line or comma-separated:
                                        </div>
                                        <textarea
                                            v-model="allowedInstancesBulk"
                                            @input="updateInstancesFromBulk('allowed')"
                                            placeholder="example.social&#10;trusted.instance&#10;partner.domain"
                                            rows="6"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                                        ></textarea>
                                        <div class="flex gap-2">
                                            <button
                                                @click="clearInstances('allowed')"
                                                class="px-3 py-1 text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Clear All
                                            </button>
                                            <div
                                                class="text-sm text-gray-500 dark:text-gray-400 ml-auto"
                                            >
                                                {{ settings.federation.allowedInstances.length }}
                                                instance(s)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                Federation Policies
                            </h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <label
                                            class="text-sm font-medium text-gray-900 dark:text-white"
                                            >Auto-Accept Follow Requests</label
                                        >
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Automatically accept follow requests from federated
                                            accounts
                                        </p>
                                    </div>
                                    <button
                                        @click="
                                            settings.federation.autoAcceptFollows =
                                                !settings.federation.autoAcceptFollows
                                        "
                                        :class="[
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none',
                                            settings.federation.autoAcceptFollows
                                                ? 'bg-blue-600'
                                                : 'bg-gray-200 dark:bg-gray-600'
                                        ]"
                                        disabled
                                    >
                                        <span
                                            :class="[
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                settings.federation.autoAcceptFollows
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0'
                                            ]"
                                        ></span>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <label
                                            class="text-sm font-medium text-gray-900 dark:text-white"
                                            >Enforce Authorized Access</label
                                        >
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Require valid Request Signatures for activities and
                                            collections
                                        </p>
                                    </div>
                                    <button
                                        @click="
                                            settings.federation.authorizedFetch =
                                                !settings.federation.authorizedFetch
                                        "
                                        :class="[
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none',
                                            settings.federation.authorizedFetch
                                                ? 'bg-blue-600'
                                                : 'bg-gray-200 dark:bg-gray-600'
                                        ]"
                                        :disabled="settings.federation.federationMode !== 'open'"
                                    >
                                        <span
                                            :class="[
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                settings.federation.authorizedFetch
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0'
                                            ]"
                                        ></span>
                                    </button>
                                </div>

                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Account Video Limit</label
                                    >
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                        Limit the number of videos per remote account
                                    </p>
                                    <input
                                        v-model.number="settings.federation.rateLimit"
                                        type="number"
                                        min="100"
                                        max="10000"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'pages'" class="space-y-8">
                    <div class="flex gap-6 h-[700px]">
                        <div
                            class="w-80 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
                        >
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        Pages
                                    </h3>
                                    <button
                                        @click="createNewPage"
                                        class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                                    >
                                        <PlusIcon class="w-4 h-4 inline mr-1" />
                                        New
                                    </button>
                                </div>

                                <div class="relative">
                                    <MagnifyingGlassIcon
                                        class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                    />
                                    <input
                                        v-model="pageSearchQuery"
                                        type="text"
                                        placeholder="Search pages..."
                                        class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                            </div>

                            <div class="relative overflow-y-auto h-full">
                                <div v-if="loadingPages" class="p-4 text-center text-gray-500">
                                    Loading pages...
                                </div>
                                <div
                                    v-else-if="filteredPages.length === 0"
                                    class="p-4 text-center text-gray-500"
                                >
                                    No pages found
                                </div>
                                <div v-else class="space-y-1 p-2">
                                    <div
                                        v-for="page in filteredPages"
                                        :key="page.id"
                                        @click="selectPage(page)"
                                        :class="[
                                            'p-3 rounded-md cursor-pointer transition-colors',
                                            selectedPage?.id === page.id
                                                ? 'bg-blue-100 dark:bg-blue-900 border-l-4 border-blue-500'
                                                : 'hover:bg-gray-100 dark:hover:bg-gray-800'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1 min-w-0">
                                                <h4
                                                    class="text-sm font-medium truncate"
                                                    :class="[
                                                        page.system_page
                                                            ? 'text-red-600 dark:text-white '
                                                            : 'text-gray-900 dark:text-white'
                                                    ]"
                                                >
                                                    {{ page.title }}
                                                </h4>
                                                <p
                                                    class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                                >
                                                    /{{ page.slug }}
                                                </p>
                                                <p
                                                    class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                                >
                                                    Updated
                                                    {{
                                                        page.updated_at
                                                            ? formatDate(page.updated_at)
                                                            : 'Never'
                                                    }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                        page.status === 'published'
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                    ]"
                                                >
                                                    {{ page.status }}
                                                </span>
                                                <button
                                                    v-if="!page.system_page"
                                                    @click.stop="deletePage(page.id)"
                                                    class="p-1 text-gray-400 hover:text-red-500 transition-colors"
                                                >
                                                    <TrashIcon class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 flex flex-col">
                            <div
                                v-if="!selectedPage"
                                class="flex-1 flex items-center justify-center text-gray-500 dark:text-gray-400"
                            >
                                <div class="text-center">
                                    <DocumentIcon class="w-16 h-16 mx-auto mb-4 opacity-50" />
                                    <p class="text-lg">Select a page to edit or create a new one</p>
                                </div>
                            </div>

                            <div v-else class="flex-1 flex flex-col">
                                <div
                                    class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700 mb-4"
                                >
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                            >
                                                Page Title
                                            </label>
                                            <input
                                                v-model="selectedPage.title"
                                                type="text"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                            >
                                                Slug
                                            </label>
                                            <input
                                                v-model="selectedPage.slug"
                                                :disabled="selectedPage.system_page"
                                                type="text"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
                                            />
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between">
                                        <div class="flex items-center gap-6">
                                            <div
                                                v-if="!selectedPage.system_page"
                                                class="flex items-center gap-4"
                                            >
                                                <label class="flex items-center">
                                                    <input
                                                        v-model="selectedPage.status"
                                                        type="radio"
                                                        :disabled="selectedPage.system_page"
                                                        value="published"
                                                        class="mr-2 text-blue-600 focus:ring-blue-500"
                                                    />
                                                    <span
                                                        class="text-sm text-gray-700 dark:text-gray-300"
                                                        >Published</span
                                                    >
                                                </label>
                                                <label class="flex items-center">
                                                    <input
                                                        v-model="selectedPage.status"
                                                        type="radio"
                                                        :disabled="selectedPage.system_page"
                                                        value="draft"
                                                        class="mr-2 text-blue-600 focus:ring-blue-500"
                                                    />
                                                    <span
                                                        class="text-sm text-gray-700 dark:text-gray-300"
                                                        >Draft</span
                                                    >
                                                </label>
                                            </div>

                                            <div
                                                v-if="!selectedPage.system_page"
                                                class="flex items-center gap-2"
                                            >
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >
                                                    Location
                                                </label>
                                                <div class="relative group">
                                                    <svg
                                                        class="w-4 h-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-help"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>

                                                    <div
                                                        class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10 w-64"
                                                    >
                                                        <div class="text-center">
                                                            Add this page to the Side menu, Footer
                                                            menu, or hide it completely. Options
                                                            include user-only, guest-only, or all
                                                            visitors.
                                                        </div>
                                                        <div
                                                            class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"
                                                        ></div>
                                                    </div>
                                                </div>
                                                <select
                                                    v-model="selectedPage.location"
                                                    ref="locationSelect"
                                                    :disabled="selectedPage.system_page"
                                                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
                                                >
                                                    <option value="none">None</option>
                                                    <option value="side_menu_guest">
                                                        Side Menu (Guest)
                                                    </option>
                                                    <option value="side_menu_user">
                                                        Side Menu (User)
                                                    </option>
                                                    <option value="side_menu_all">
                                                        Side Menu (All)
                                                    </option>
                                                    <option value="footer_guest">
                                                        Footer (Guest)
                                                    </option>
                                                    <option value="footer_user">
                                                        Footer (User)
                                                    </option>
                                                    <option value="footer_all">Footer (All)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-4">
                                            <router-link
                                                :to="`/${selectedPage.slug}`"
                                                class="border-2 border-blue-300 rounded-lg text-blue-500 p-2 text-sm cursor-pointer"
                                                >View page</router-link
                                            >
                                            <button
                                                @click="savePage"
                                                :disabled="savingPage"
                                                class="px-4 py-2 text-sm bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            >
                                                {{ savingPage ? 'Saving...' : 'Save Page' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
                                >
                                    <MdEditor
                                        v-model="selectedPage.content"
                                        :theme="editorTheme"
                                        :preview-theme="'github'"
                                        :code-theme="'github'"
                                        :toolbars="editorToolbars"
                                        :height="500"
                                        language="en-US"
                                        @save="savePage"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="['pages', 'branding'].indexOf(activeTab) == -1"
                    class="flex justify-end pt-6 mt-6 border-t border-gray-200 dark:border-gray-700"
                >
                    <button
                        type="button"
                        @click="saveSettings"
                        :disabled="saving"
                        class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ saving ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { MdEditor } from 'md-editor-v3'
import 'md-editor-v3/lib/style.css'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'
import {
    CogIcon,
    PaintBrushIcon,
    PhotoIcon,
    GlobeAltIcon,
    DocumentIcon,
    PlusIcon,
    MagnifyingGlassIcon,
    TrashIcon
} from '@heroicons/vue/24/outline'
import { settingsApi, pagesApi } from '@/services/adminApi'

const { truncateMiddle, formatNumber, formatDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const route = useRoute()
const router = useRouter()
const isDark = ref(document.documentElement.classList.contains('dark'))

const tabs = [
    { id: 'general', name: 'General', icon: CogIcon },
    { id: 'branding', name: 'Branding', icon: PaintBrushIcon },
    // { id: 'media', name: 'Media', icon: PhotoIcon },
    { id: 'pages', name: 'Pages', icon: DocumentIcon },
    { id: 'federation', name: 'Federation', icon: GlobeAltIcon }
]

const activeTab = ref('general')
const saving = ref(false)

const pages = ref([])
const selectedPage = ref(null)
const loadingPages = ref(false)
const savingPage = ref(false)
const pageSearchQuery = ref('')
const pendingLogo = ref(null)
const pendingFavicon = ref(null)
const logoSaving = ref(false)
const faviconSaving = ref(false)
const logoDeleting = ref(false)
const faviconDeleting = ref(false)
const logoInput = ref(null)
const faviconInput = ref(null)
const cssDeleting = ref(null)
const isBulkView = ref(true)
const allowedInstancesBulk = ref('')
const blockedInstancesBulk = ref('')

const editorTheme = ref(isDark.value ? 'dark' : 'light')
const editorToolbars = [
    'bold',
    'underline',
    'italic',
    'strikeThrough',
    '-',
    'title',
    'sub',
    'sup',
    'quote',
    'unorderedList',
    'orderedList',
    '-',
    'codeRow',
    'code',
    'link',
    'table',
    '-',
    'revoke',
    'next',
    '-',
    'pageFullscreen',
    'fullscreen',
    'preview',
    'htmlPreview',
    'catalog',
    'save'
]

const videoFormats = ['mp4', 'webm', 'mov', 'avi', 'mkv']

const settings = reactive({
    general: {
        instanceName: 'My Loops Instance',
        instanceUrl: 'https://loops.example.com',
        instanceDescription:
            'A creative community for sharing short videos and connecting with others.',
        adminEmail: 'admin@example.com',
        supportEmail: 'support@example.com',
        supportForum: 'https://github.com/joinloops/loops-server/discussions',
        supportFediverseAccount: '',
        openRegistration: true,
        emailVerification: true,
        defaultContentStatus: 'published',
        autoModerateNSFW: true
    },
    branding: {
        logo: null,
        favicon: null,
        primaryColor: '#3b82f6',
        secondaryColor: '#8b5cf6',
        accentColor: '#10b981',
        customCSS: ''
    },
    media: {
        maxVideoSize: 100,
        maxImageSize: 10,
        maxVideoDuration: 300,
        allowedVideoFormats: ['mp4', 'webm', 'mov'],
        storageDriver: 'local',
        bucketName: '',
        cdnUrl: '',
        autoThumbnails: true,
        videoTranscoding: false
    },
    federation: {
        enableFederation: false,
        federationMode: 'open',
        allowedInstances: [''],
        blockedInstances: [''],
        autoAcceptFollows: true,
        authorizedFetch: true,
        rateLimit: 1000
    }
})

// Deep linking functionality
const isInitializing = ref(true)

const updateUrlFromState = () => {
    // Don't update URL during initialization to prevent conflicts
    if (isInitializing.value) return

    const query = { ...route.query }

    // Update tab in URL
    if (activeTab.value !== 'general') {
        query.tab = activeTab.value
    } else {
        delete query.tab
    }

    // Update page in URL (only for pages tab)
    if (activeTab.value === 'pages' && selectedPage.value?.id) {
        query.page = selectedPage.value.id
    } else {
        delete query.page
    }

    // Only update if query actually changed
    const currentQuery = JSON.stringify(route.query)
    const newQuery = JSON.stringify(query)

    if (currentQuery !== newQuery) {
        router.replace({
            path: route.path,
            query
        })
    }
}

const setActiveTab = (tabId) => {
    if (tabs.find((tab) => tab.id === tabId)) {
        activeTab.value = tabId

        // Clear selected page if switching away from pages tab
        if (tabId !== 'pages') {
            selectedPage.value = null
        }
    }
}

const initializeFromUrl = async () => {
    // Set active tab from URL
    const urlTab = route.query.tab
    if (urlTab && tabs.find((tab) => tab.id === urlTab)) {
        activeTab.value = urlTab
    }

    // If we're on the pages tab and there's a page query param, select that page
    if (activeTab.value === 'pages' && route.query.page) {
        // Ensure pages are loaded first
        if (pages.value.length === 0) {
            await loadPages()
        }

        const pageId = parseInt(route.query.page)
        const page = pages.value.find((p) => p.id === pageId)

        if (page) {
            // Temporarily set isInitializing to false to allow selectPage to work properly
            const wasInitializing = isInitializing.value
            isInitializing.value = false
            await selectPage(page)
            isInitializing.value = wasInitializing
        }
    }
}

// Watch for external URL changes (browser back/forward)
watch(
    () => route.query,
    async (newQuery) => {
        // Don't react to URL changes during initialization
        if (isInitializing.value) return

        const urlTab = newQuery.tab || 'general'
        const urlPageId = newQuery.page ? parseInt(newQuery.page) : null

        // Update active tab if it changed
        if (urlTab !== activeTab.value) {
            activeTab.value = urlTab
        }

        // Handle page selection for pages tab
        if (activeTab.value === 'pages') {
            if (urlPageId && (!selectedPage.value || selectedPage.value.id !== urlPageId)) {
                const page = pages.value.find((p) => p.id === urlPageId)
                if (page) {
                    await selectPage(page)
                }
            } else if (!urlPageId && selectedPage.value) {
                selectedPage.value = null
            }
        }
    }
)

// Watch for state changes to update URL
watch(activeTab, () => {
    if (!isInitializing.value) {
        updateUrlFromState()
    }
})

watch(
    () => selectedPage.value?.id,
    () => {
        if (activeTab.value === 'pages' && !isInitializing.value) {
            updateUrlFromState()
        }
    }
)

// Methods
const getLogoSource = () => {
    if (pendingLogo.value) {
        return URL.createObjectURL(pendingLogo.value)
    }
    return settings.branding.logo || '/_static/logo-small.png'
}

const getFaviconSource = () => {
    if (pendingFavicon.value) {
        return URL.createObjectURL(pendingFavicon.value)
    }
    return settings.branding.favicon || '/favicon.png'
}

const handleLogoChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alertModal('Oops, thats too big!', 'Logo file size must be less than 2MB')
            return
        }
        pendingLogo.value = file
    }
}

const handleFaviconChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB')
            return
        }
        pendingFavicon.value = file
    }
}

const saveLogo = async () => {
    if (!pendingLogo.value) return

    const confirm = await confirmModal(
        'Confirm Logo Update',
        'Are you sure you want to update the current logo?',
        'Update Logo',
        'Cancel'
    )

    if (!confirm) {
        return
    }

    logoSaving.value = true
    try {
        const formData = new FormData()
        formData.append('logo', pendingLogo.value)

        const response = await settingsApi.updateLogo(formData)

        if (response) {
            const result = response.data
            settings.branding.logo = result.logo_url
            pendingLogo.value = null
            logoInput.value.value = ''
        } else {
            throw new Error('Failed to save logo')
        }
    } catch (error) {
        console.error('Error saving logo:', error)
        alert('Failed to save logo. Please try again.')
    } finally {
        logoSaving.value = false
    }
}

const saveFavicon = async () => {
    if (!pendingFavicon.value) return

    faviconSaving.value = true
    try {
        const formData = new FormData()
        formData.append('favicon', pendingFavicon.value)

        const response = await fetch('/api/settings/branding/favicon', {
            method: 'POST',
            body: formData
        })

        if (response.ok) {
            const result = await response.json()
            settings.branding.favicon = result.favicon_url
            pendingFavicon.value = null
            faviconInput.value.value = ''
        } else {
            throw new Error('Failed to save favicon')
        }
    } catch (error) {
        console.error('Error saving favicon:', error)
        alert('Failed to save favicon. Please try again.')
    } finally {
        faviconSaving.value = false
    }
}

const clearPendingLogo = () => {
    pendingLogo.value = null
    logoInput.value.value = ''
}

const clearPendingFavicon = () => {
    pendingFavicon.value = null
    faviconInput.value.value = ''
}

const deleteLogo = async () => {
    const confirm = await confirmModal(
        'Confirm Delete',
        'Are you sure you want to delete the current logo and restore the default Loops logo?',
        'Delete',
        'Cancel'
    )

    if (!confirm) {
        return
    }
    logoDeleting.value = true

    try {
        const response = await settingsApi.deleteLogo()

        if (response) {
            settings.branding.logo = null
        } else {
            throw new Error('Failed to delete logo')
        }
    } catch (error) {
        console.error('Error deleting logo:', error)
        alert('Failed to delete logo. Please try again.')
    } finally {
        logoDeleting.value = false
    }
}

const deleteFavicon = async () => {
    faviconDeleting.value = true
    try {
        // Replace with your actual API endpoint
        const response = await fetch('/api/settings/branding/favicon', {
            method: 'DELETE'
        })

        if (response.ok) {
            settings.branding.favicon = null
        } else {
            throw new Error('Failed to delete favicon')
        }
    } catch (error) {
        console.error('Error deleting favicon:', error)
        alert('Failed to delete favicon. Please try again.')
    } finally {
        faviconDeleting.value = false
    }
}

const handleFileUpload = (event, type) => {
    const file = event.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = (e) => {
            settings.branding[type] = e.target.result
            console.log(e)
        }
        reader.readAsDataURL(file)
    }
}

const addInstance = (type) => {
    if (type === 'allowed') {
        settings.federation.allowedInstances.push('')
    } else {
        settings.federation.blockedInstances.push('')
    }
}

const removeInstance = (type, index) => {
    if (type === 'allowed') {
        settings.federation.allowedInstances.splice(index, 1)
    } else {
        settings.federation.blockedInstances.splice(index, 1)
    }
}

const onSaveCSS = async () => {
    cssDeleting.value = null
    await saveSettings()
    await nextTick()
    window.location.reload()
}

const onDeleteCSS = async () => {
    cssDeleting.value = true
    await nextTick()
    settings.branding.customCSS = null
    await nextTick()
    await saveSettings()
    window.location.reload()
}

const saveSettings = async () => {
    saving.value = true

    try {
        const response = await settingsApi.updateSettings(settings)
    } catch (error) {
        alertModal('Error', error?.response?.data?.message)
    } finally {
        saving.value = false
        await nextTick()
        window.location.reload()
    }
}

const filteredPages = computed(() => {
    if (!pageSearchQuery.value) return pages.value

    const query = pageSearchQuery.value.toLowerCase()
    return pages.value.filter(
        (page) =>
            page.title.toLowerCase().includes(query) || page.slug.toLowerCase().includes(query)
    )
})

const loadPages = async () => {
    loadingPages.value = true
    try {
        const response = await pagesApi.getPages()
        pages.value = response.data
    } catch (error) {
        console.error('Error loading pages:', error)
    } finally {
        loadingPages.value = false
    }
}

const selectPage = async (page) => {
    if (selectedPage.value?.id === page.id) return

    try {
        const response = await pagesApi.getPage(page.id)
        const result = response
        selectedPage.value = { ...result.data }

        // Only update URL when not initializing
        if (!isInitializing.value) {
            updateUrlFromState()
        }
    } catch (error) {
        console.error('Error loading page content:', error)
        selectedPage.value = { ...page, content: '' }
    }
}

const createNewPage = () => {
    const newPage = {
        id: null,
        title: 'New Page',
        slug: 'pages/new-page',
        content: '# New Page\n\nStart writing your content here...',
        status: 'draft',
        location: 'none',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
    }

    selectedPage.value = newPage
    // Clear page from URL since this is a new page (only if not initializing)
    if (!isInitializing.value) {
        updateUrlFromState()
    }
}

const savePage = async () => {
    if (!selectedPage.value) return

    savingPage.value = true
    try {
        const isNew = !selectedPage.value.id
        let response
        if (isNew) {
            response = await pagesApi.createPage({
                title: selectedPage.value.title,
                slug: selectedPage.value.slug,
                content: selectedPage.value.content,
                location: selectedPage.value.location,
                status: selectedPage.value.status
            })
        } else {
            response = await pagesApi.updatePage(selectedPage.value.id, {
                title: selectedPage.value.title,
                slug: selectedPage.value.slug,
                content: selectedPage.value.content,
                location: selectedPage.value.location,
                status: selectedPage.value.status
            })
        }
        if (!response) throw new Error('Failed to save page')

        const result = response

        if (isNew) {
            selectedPage.value.id = result.data.id
            pages.value.unshift({ ...result.data })
            // Update URL with new page ID
            updateUrlFromState()
        } else {
            const index = pages.value.findIndex((p) => p.id === selectedPage.value.id)
            if (index !== -1) {
                pages.value[index] = { ...result.data.data }
            }
        }

        selectedPage.value.updated_at = result.data.updated_at
    } catch (error) {
        console.error('Error saving page:', error)
        alertModal('Error', error?.response?.data?.message)
    } finally {
        savingPage.value = false
        window.location.reload()
    }
}

const deletePage = async (pageId) => {
    const result = await confirmModal(
        'Confirm Delete',
        `Are you sure you want to delete this page?`,
        'Delete Page',
        'Cancel'
    )

    if (!result) {
        return
    }

    try {
        const response = await pagesApi.deletePage(pageId)

        if (!response) throw new Error('Failed to delete page')

        pages.value = pages.value.filter((p) => p.id !== pageId)

        if (selectedPage.value?.id === pageId) {
            selectedPage.value = null
            if (!isInitializing.value) {
                updateUrlFromState()
            }
        }
    } catch (error) {
        console.error('Error deleting page:', error)
        alert('Error deleting page. Please try again.')
    }
}

const toggleBulkView = () => {
    isBulkView.value = !isBulkView.value

    if (isBulkView.value) {
        if (
            settings.federation.federationMode === 'allowlist' ||
            settings.federation.federationMode === 'lockdown'
        ) {
            allowedInstancesBulk.value = settings.federation.allowedInstances
                .filter((instance) => instance.trim())
                .join(',')
        } else if (settings.federation.federationMode === 'blocklist') {
            blockedInstancesBulk.value = settings.federation.blockedInstances
                .filter((instance) => instance.trim())
                .join(',')
        }
    }
}

const updateInstancesFromBulk = (type) => {
    const bulkValue = type === 'allowed' ? allowedInstancesBulk.value : blockedInstancesBulk.value

    const instances = bulkValue
        .split(',')
        .map((instance) => instance.trim())
        .filter((instance) => instance.length > 0)
        .filter((instance, index, arr) => arr.indexOf(instance) === index)

    if (type === 'allowed') {
        settings.federation.allowedInstances = instances
    } else if (type === 'blocked') {
        settings.federation.blockedInstances = instances
    }
}

const clearInstances = (type) => {
    if (confirm(`Are you sure you want to clear all ${type} instances?`)) {
        if (type === 'allowed') {
            settings.federation.allowedInstances = []
            allowedInstancesBulk.value = ''
        } else if (type === 'blocked') {
            settings.federation.blockedInstances = []
            blockedInstancesBulk.value = ''
        }
    }
}

watch(
    () => settings.federation.allowedInstances,
    (newVal) => {
        if (
            isBulkView.value &&
            (settings.federation.federationMode === 'allowlist' ||
                settings.federation.federationMode === 'lockdown')
        ) {
            if (document.activeElement?.tagName !== 'TEXTAREA') {
                allowedInstancesBulk.value = newVal.filter((i) => i.trim()).join(',')
            }
        }
    },
    { deep: true }
)

watch(
    () => settings.federation.blockedInstances,
    (newVal) => {
        if (isBulkView.value && settings.federation.federationMode === 'blocklist') {
            if (document.activeElement?.tagName !== 'TEXTAREA') {
                blockedInstancesBulk.value = newVal.filter((i) => i.trim()).join(',')
            }
        }
    },
    { deep: true }
)

const loadSettings = async () => {
    try {
        const response = await settingsApi.getSettings()

        if (!response) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        const result = response.data
        const loadedSettings = result

        Object.keys(loadedSettings).forEach((key) => {
            const [section, setting] = key.split('.')
            if (
                settings[section] &&
                Object.prototype.hasOwnProperty.call(settings[section], setting)
            ) {
                settings[section][setting] = loadedSettings[key]
            }
        })
    } catch (error) {
        console.error('Error loading settings:', error)
    }
}

onMounted(async () => {
    isInitializing.value = true

    try {
        await loadSettings()
        await loadPages()

        await nextTick()
        await initializeFromUrl()
    } finally {
        isInitializing.value = false
    }
})

defineExpose({
    setActiveTab,
    selectPage,
    createNewPage,
    savePage,
    deletePage
})
</script>
