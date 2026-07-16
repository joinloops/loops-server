<template>
    <LoadingSpinner v-if="isLoading" />

    <div v-else-if="notFound" class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <div
            class="rounded-3xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"
            >
                <UserIcon class="h-10 w-10 text-gray-400" />
            </div>
            <h1 class="mt-6 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                Profile Not Found
            </h1>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600 dark:text-gray-400">
                The profile you are looking for does not exist, or it may have already been removed.
            </p>
            <button
                @click="goBack"
                class="mt-6 inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Go Back
            </button>
        </div>
    </div>

    <div v-else-if="profile" class="mx-auto max-w-8xl px-4 py-4 sm:px-6 lg:px-4 lg:py-6">
        <div class="space-y-6 lg:space-y-8">
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <section
                    :class="[
                        'relative overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900',
                        isDeleted ? 'xl:col-span-12' : 'xl:col-span-7'
                    ]"
                >
                    <div
                        class="absolute inset-x-0 top-0 h-25 bg-gradient-to-r from-[#F02C56]/10 via-transparent to-[#F02C56]/5 dark:from-[#F02C56]/15 dark:via-transparent dark:to-[#F02C56]/10"
                    />

                    <div class="flex flex-col h-full relative px-4 py-5 sm:px-6 sm:py-6 lg:px-7">
                        <div class="flex items-center justify-between gap-3 lg:gap-5">
                            <AnimatedButton size="sm" variant="outline" pill @click="goBack">
                                <div class="flex items-center gap-4">
                                    <ArrowLeftIcon class="h-4 w-4" />
                                    <span class="hidden sm:inline">Back to Profiles</span>
                                </div>
                            </AnimatedButton>

                            <h2
                                class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white"
                            >
                                Profile
                            </h2>

                            <p
                                class="text-xs max-w-[60px] lg:max-w-[300px] lg:text-sm truncate font-semibold tracking-tight text-gray-400 dark:text-gray-500"
                            >
                                #{{ route.params.id }}
                            </p>
                        </div>

                        <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-center">
                            <div class="mx-auto shrink-0 lg:mx-0">
                                <img
                                    :src="profile.avatar"
                                    :alt="profile.username"
                                    class="h-24 w-24 rounded-full border-4 border-white bg-white object-cover shadow-sm dark:border-gray-900 sm:h-28 sm:w-28"
                                    @error="handleAvatarError"
                                />
                            </div>

                            <div class="min-w-0 flex-1 text-center lg:text-left">
                                <div
                                    class="flex flex-wrap items-center justify-center gap-2 lg:justify-start"
                                >
                                    <h1
                                        class="break-words text-2xl font-semibold tracking-tight text-gray-950 dark:text-white lg:text-3xl"
                                    >
                                        {{ profile.name || profile.username }}
                                    </h1>
                                    <ShieldCheckIcon
                                        v-if="profile.is_admin"
                                        class="h-5 w-5 shrink-0 text-red-500"
                                    />
                                </div>

                                <router-link
                                    :to="`/@${profile.username}`"
                                    class="mt-1 inline-flex text-sm text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    @{{ profile.username }}
                                </router-link>

                                <div
                                    class="mt-3 flex flex-wrap items-center justify-center gap-2 lg:justify-start"
                                >
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                            statusBadge
                                        ]"
                                    >
                                        {{ profile.status || 'unknown' }}
                                    </span>
                                    <span
                                        v-if="profile.is_admin"
                                        class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                    >
                                        Admin
                                    </span>
                                    <span
                                        v-if="!profile.local"
                                        class="inline-flex items-center rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                                    >
                                        Remote
                                    </span>
                                    <span
                                        v-if="profile.local && profile.email_verified"
                                        class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                    >
                                        Verified email
                                    </span>
                                    <span
                                        v-if="profile.local && profile.has_2fa"
                                        class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                    >
                                        2FA
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="!isDeleted && !profile.is_admin"
                                class="flex flex-wrap items-center justify-center gap-2 lg:w-full lg:max-w-[240px] lg:shrink-0 lg:justify-end"
                            >
                                <Dropdown align="right">
                                    <template #trigger>
                                        <AnimatedButton variant="primaryGradient" pill>
                                            <div class="flex items-center gap-3">
                                                Actions
                                                <ChevronDownIcon class="h-5 w-5" />
                                            </div>
                                        </AnimatedButton>
                                    </template>

                                    <template v-if="!profile.is_admin && profile.local">
                                        <DropdownItem @click="showSendEmailModal = true">
                                            <EnvelopeIcon class="h-4 w-4 mr-1.5" />
                                            Send Email
                                        </DropdownItem>

                                        <DropdownDivider class="my-1" />
                                    </template>

                                    <template v-if="!profile.is_admin && profile.local">
                                        <DropdownItem @click="showResetPasswordModal = true">
                                            <LockClosedIcon class="h-4 w-4 mr-1.5" />
                                            Reset password
                                        </DropdownItem>

                                        <DropdownItem
                                            v-if="profile?.has_2fa"
                                            @click="handleDisable2fa"
                                        >
                                            <KeyIcon class="h-4 w-4 mr-1.5" />
                                            Disable Two Factor
                                        </DropdownItem>

                                        <DropdownItem @click="handleRevokeAllSessions">
                                            <KeyIcon class="h-4 w-4 mr-1.5" />
                                            Revoke API sessions
                                        </DropdownItem>

                                        <DropdownDivider class="my-1" />
                                    </template>

                                    <DropdownItem
                                        v-if="!profile.is_admin && profile.local"
                                        @click="toggleVerification"
                                    >
                                        <CheckBadgeIcon class="h-4 w-4 mr-1.5" />
                                        {{
                                            profile.email_verified
                                                ? 'Unverify email'
                                                : 'Verify email'
                                        }}
                                    </DropdownItem>

                                    <DropdownItem
                                        v-if="!profile.is_admin"
                                        destructive
                                        @click="handleDeleteAllComments"
                                    >
                                        <ChatBubbleBottomCenterIcon class="h-4 w-4 mr-1.5" />
                                        Delete All Comments
                                    </DropdownItem>

                                    <DropdownItem destructive @click="handleDeleteAvatar">
                                        <UserCircleIcon class="h-4 w-4 mr-1.5" />
                                        Delete Avatar
                                    </DropdownItem>

                                    <DropdownDivider class="my-1" v-if="!profile.is_admin" />

                                    <DropdownItem
                                        v-if="!profile.is_admin && profile.status === 'active'"
                                        destructive
                                        @click="handleSuspend"
                                    >
                                        <NoSymbolIcon class="mr-1.5 inline h-4 w-4" />
                                        Suspend
                                    </DropdownItem>

                                    <DropdownItem
                                        v-else-if="profile.status === 'suspended'"
                                        @click="handleUnsuspend"
                                    >
                                        <CheckIcon class="mr-1.5 inline h-4 w-4" />
                                        Unsuspend
                                    </DropdownItem>

                                    <DropdownItem
                                        v-if="!profile.is_admin && profile.status === 'active'"
                                        destructive
                                        @click="showDeleteConfirmation = true"
                                    >
                                        <TrashIcon class="h-4 w-4 mr-1.5" />
                                        Delete Account
                                    </DropdownItem>
                                </Dropdown>
                            </div>
                        </div>

                        <div v-if="profile.bio" class="mt-4 min-w-0 max-w-none text-left flex-grow">
                            <div
                                ref="bioRef"
                                :class="[
                                    'text-sm leading-6 text-gray-600 dark:text-gray-400',
                                    !bioExpanded && bioNeedsClamp ? 'line-clamp-3' : ''
                                ]"
                            >
                                {{ profile.bio }}
                            </div>

                            <button
                                v-if="bioNeedsClamp"
                                type="button"
                                :aria-expanded="bioExpanded"
                                class="mt-2 inline-flex text-sm font-medium text-[#F02C56] transition hover:opacity-80"
                                @click="bioExpanded = !bioExpanded"
                            >
                                {{ bioExpanded ? 'Show less' : 'Read more' }}
                            </button>
                        </div>

                        <div
                            class="flex-shrink mt-6 border-t border-gray-100 pt-4 dark:border-gray-800"
                        >
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <QuickInfoCard
                                    label="Joined"
                                    :value="formatDate(profile.created_at)"
                                />
                                <QuickInfoCard
                                    :label="profile.local ? 'Last active' : 'Updated'"
                                    :value="relativeActivity"
                                />
                                <QuickInfoCard
                                    v-if="profile.local"
                                    label="User ID"
                                    :value="profile.user_id || '—'"
                                    mono
                                />
                                <QuickInfoCard v-else label="Server" :value="profile.domain" />
                                <QuickInfoCard
                                    label="Account"
                                    :value="profile.local ? 'Local' : 'Remote'"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="!isDeleted" class="xl:col-span-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-2">
                        <component
                            :is="stat.to ? 'router-link' : 'div'"
                            v-for="stat in statCards"
                            :key="stat.label"
                            :to="stat.to"
                            class="block h-full"
                        >
                            <MetricCard :icon="stat.icon" :label="stat.label" :value="stat.value" />
                        </component>
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <section v-if="profile.local" class="xl:col-span-5">
                    <PanelCard>
                        <template #header>
                            <PanelHeader
                                title="Account Information"
                                description="Primary profile and account metadata"
                                :icon="IdentificationIcon"
                            />
                        </template>

                        <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                            <DetailRow
                                v-if="profile.email"
                                label="Email"
                                :value="profile.email"
                                :icon="EnvelopeIcon"
                            />
                            <DetailRow
                                label="Joined"
                                :value="formatDate(profile.created_at)"
                                :icon="CalendarIcon"
                            />
                            <DetailRow
                                label="Bio"
                                :value="profile.bio || 'No bio set'"
                                :icon="DocumentTextIcon"
                            />
                            <DetailRow
                                v-if="profile.user_id"
                                label="User ID"
                                :value="profile.user_id"
                                :icon="HashtagIcon"
                                mono
                            />
                            <DetailRow
                                v-if="profile.register_ip"
                                label="Register IP"
                                :value="profile.register_ip"
                                :icon="MapPinIcon"
                                mono
                                :url="`/admin/profiles?q=register_ip:${profile.register_ip}`"
                            />
                            <DetailRow
                                v-if="profile.last_ip"
                                label="Last IP"
                                :value="profile.last_ip"
                                :icon="MapPinIcon"
                                mono
                                :url="`/admin/profiles?q=ip:${profile.last_ip}`"
                            />
                            <DetailRow
                                v-if="profile.last_active_at"
                                label="Last Active"
                                :value="formatTimeAgo(profile.last_active_at)"
                                :icon="ClockIcon"
                            />
                            <DetailRow
                                v-if="profile.has_push"
                                label="Push Notifications"
                                :value="profile.push_platform || 'Unknown'"
                                :icon="BellIcon"
                            />

                            <div
                                v-if="profile.has_atom"
                                class="flex items-start gap-3 px-5 py-4 sm:px-6"
                            >
                                <RssIcon class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div class="min-w-0 flex-1">
                                    <dt
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Atom Feed
                                    </dt>
                                    <dd class="mt-1">
                                        <a
                                            :href="profile.atom_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block truncate text-sm text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            {{ profile.atom_url }}
                                        </a>
                                    </dd>
                                </div>
                            </div>
                        </dl>
                    </PanelCard>
                </section>

                <section :class="profile.local ? 'xl:col-span-7' : 'xl:col-span-12'">
                    <PanelCard>
                        <template #header>
                            <PanelHeader
                                title="Permissions & Controls"
                                description="Operational permissions and internal moderation notes"
                                :icon="LockClosedIcon"
                            />
                        </template>

                        <div
                            v-if="!isDeleted"
                            :class="[
                                'grid grid-cols-1 gap-3 p-4 sm:p-6',
                                profile.local ? 'lg:grid-cols-2' : 'lg:grid-cols-2 xl:grid-cols-3'
                            ]"
                        >
                            <PermissionTile
                                v-for="perm in modConfig"
                                :key="perm.key"
                                :icon="perm.icon"
                                :label="perm.label"
                                :description="perm.description"
                                :enabled="!!profile[perm.key]"
                                :disabled="
                                    (perm?.localOnly && !profile.local) ||
                                    profile.is_admin ||
                                    isModPermissionSaving(perm.key)
                                "
                                @toggle="requestModPermission(perm.key)"
                            />

                            <PermissionTile
                                v-for="perm in permissionConfig"
                                :key="perm.key"
                                :icon="perm.icon"
                                :label="perm.label"
                                :description="perm.description"
                                :enabled="!!profile[perm.key]"
                                :disabled="
                                    (perm?.localOnly && !profile.local) ||
                                    profile.is_admin ||
                                    isPermissionSaving(perm.key)
                                "
                                @toggle="togglePermission(perm.key)"
                            />
                        </div>

                        <div
                            class="border-t border-gray-200 px-4 py-4 dark:border-gray-800 sm:px-6"
                        >
                            <label
                                for="admin-notes"
                                class="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white"
                            >
                                <PencilSquareIcon class="h-4 w-4 text-gray-400" />
                                Admin Notes
                                <span
                                    v-if="adminNotesSaving"
                                    class="ml-auto inline-flex items-center gap-1 text-xs font-normal text-gray-400"
                                >
                                    <ArrowPathIcon class="h-3 w-3 animate-spin" />
                                    Saving…
                                </span>
                                <span
                                    v-else-if="adminNotesSavedRecently"
                                    class="ml-auto inline-flex items-center gap-1 text-xs font-normal text-emerald-500"
                                >
                                    <CheckIcon class="h-3 w-3" />
                                    Saved
                                </span>
                            </label>
                            <textarea
                                id="admin-notes"
                                v-model="adminNotes"
                                rows="4"
                                placeholder="Add internal notes about this profile..."
                                class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder-gray-400 outline-none transition focus:border-transparent focus:ring-2 focus:ring-[#F02C56]/60 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                                @blur="saveAdminNotes"
                            />
                        </div>
                    </PanelCard>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-6" :class="profile.local ? '2xl:grid-cols-2' : ''">
                <section>
                    <PanelCard>
                        <template #header>
                            <PanelHeader
                                title="Audit Log"
                                description="Administrative actions taken on this profile"
                                :icon="ClockIcon"
                            />
                        </template>

                        <div class="p-4 sm:p-6">
                            <TimelineEmpty
                                v-if="!auditLogs.length && !isLoadingAudits"
                                title="No audit entries yet"
                                description="Admin actions on this profile will show up here."
                            />

                            <div v-else class="relative">
                                <div
                                    class="absolute bottom-2 left-5 top-2 w-px bg-gray-200 dark:bg-gray-800"
                                />

                                <ul class="space-y-6">
                                    <li
                                        v-for="entry in auditLogs"
                                        :key="entry.id"
                                        class="relative flex gap-4"
                                    >
                                        <img
                                            :src="entry.auditor.avatar"
                                            :alt="entry.auditor.username"
                                            class="relative z-10 h-10 w-10 shrink-0 rounded-full border-2 border-white bg-gray-100 object-cover dark:border-gray-900"
                                            @error="handleAvatarError"
                                        />

                                        <div
                                            class="min-w-0 flex-1 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40"
                                        >
                                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                                <span
                                                    class="font-semibold text-gray-900 dark:text-white"
                                                >
                                                    {{
                                                        entry.auditor.name || entry.auditor.username
                                                    }}
                                                </span>
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    @{{ entry.auditor.username }}
                                                </span>
                                                <span class="text-gray-300 dark:text-gray-700"
                                                    >·</span
                                                >
                                                <span
                                                    v-if="entry.created_at"
                                                    class="text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    {{ formatTimeAgo(entry.created_at) }}
                                                </span>
                                            </div>

                                            <div class="mt-2">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium',
                                                        auditTypeStyle(entry.type)
                                                    ]"
                                                >
                                                    <component
                                                        :is="auditTypeIcon(entry.type)"
                                                        class="h-3.5 w-3.5"
                                                    />
                                                    {{ formatAuditType(entry.type) }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="
                                                    entry.type === 'profile:send_email' &&
                                                    entry.value
                                                "
                                                class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/60"
                                            >
                                                <div class="space-y-2.5 px-3.5 py-3">
                                                    <div class="flex items-start gap-2">
                                                        <span
                                                            class="mt-0.5 shrink-0 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                                        >
                                                            Subject
                                                        </span>
                                                        <span
                                                            class="break-words text-sm font-medium text-gray-900 dark:text-white"
                                                        >
                                                            {{ entry.value.subject }}
                                                        </span>
                                                    </div>

                                                    <p
                                                        class="line-clamp-2 break-words text-xs leading-5 text-gray-600 dark:text-gray-400"
                                                    >
                                                        {{
                                                            messageSnippet(entry.value.message_body)
                                                        }}
                                                    </p>

                                                    <div
                                                        class="flex flex-wrap items-center gap-1.5 pt-0.5"
                                                    >
                                                        <span
                                                            v-if="entry.value.template_id"
                                                            class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                                        >
                                                            <DocumentTextIcon class="h-3 w-3" />
                                                            {{ entry.value.template_id }}
                                                        </span>
                                                        <span
                                                            v-if="entry.value.cc_admin"
                                                            class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                                        >
                                                            BCC Admin
                                                        </span>
                                                        <span
                                                            v-if="entry.value.message_length"
                                                            class="text-[11px] text-gray-400 dark:text-gray-500"
                                                        >
                                                            {{
                                                                formatNumber(
                                                                    entry.value.message_length
                                                                )
                                                            }}
                                                            chars
                                                        </span>
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    @click="openEmailModal(entry)"
                                                    class="flex w-full items-center justify-center gap-1.5 border-t border-gray-100 bg-gray-50/50 py-2 text-xs font-medium text-[#F02C56] transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800/30 dark:hover:bg-gray-800/60"
                                                >
                                                    <EyeIcon class="h-3.5 w-3.5" />
                                                    View full message
                                                </button>
                                            </div>

                                            <div
                                                v-if="getAuditDiff(entry).length"
                                                class="mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60"
                                            >
                                                <div
                                                    v-for="change in getAuditDiff(entry)"
                                                    :key="change.key"
                                                    class="flex flex-wrap items-center gap-2 py-1 text-xs"
                                                >
                                                    <span
                                                        class="font-mono text-gray-500 dark:text-gray-400"
                                                    >
                                                        {{ change.key }}:
                                                    </span>
                                                    <span :class="valueBadge(change.old)">
                                                        {{ formatValue(change.old) }}
                                                    </span>
                                                    <ArrowRightIcon class="h-3 w-3 text-gray-400" />
                                                    <span :class="valueBadge(change.new)">
                                                        {{ formatValue(change.new) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>

                                <LoadMoreButton
                                    v-if="hasMoreAudits"
                                    :loading="isLoadingMoreAudits"
                                    @click="loadMoreAudits"
                                />
                            </div>
                        </div>
                    </PanelCard>
                </section>

                <section v-if="profile.local">
                    <PanelCard>
                        <template #header>
                            <PanelHeader
                                title="User Audit Log"
                                description="User-originated account changes and activity"
                                :icon="ClockIcon"
                            />
                        </template>

                        <div class="p-4 sm:p-6">
                            <TimelineEmpty
                                v-if="!userAuditLogs.length && !isLoadingUserAudits"
                                title="No user audit entries yet"
                                description="User changes will appear here when available."
                            />

                            <div v-else class="relative">
                                <div
                                    class="absolute bottom-2 left-5 top-2 w-px bg-gray-200 dark:bg-gray-800"
                                />

                                <ul class="space-y-6">
                                    <li
                                        v-for="entry in userAuditLogs"
                                        :key="entry.id"
                                        class="relative flex gap-4"
                                    >
                                        <img
                                            :src="profile.avatar"
                                            :alt="profile.username"
                                            class="relative z-10 h-10 w-10 shrink-0 rounded-full border-2 border-white bg-gray-100 object-cover dark:border-gray-900"
                                            @error="handleAvatarError"
                                        />

                                        <div
                                            class="min-w-0 flex-1 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40"
                                        >
                                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                                <span
                                                    class="font-semibold text-gray-900 dark:text-white"
                                                >
                                                    {{ profile.name || profile.username }}
                                                </span>
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    @{{ profile.username }}
                                                </span>
                                                <span class="text-gray-300 dark:text-gray-700"
                                                    >·</span
                                                >
                                                <span
                                                    v-if="entry.created_at"
                                                    class="text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    {{ formatTimeAgo(entry.created_at) }}
                                                </span>
                                            </div>

                                            <div class="mt-2">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium',
                                                        userAuditTypeStyle(entry.type)
                                                    ]"
                                                >
                                                    <component
                                                        :is="userAuditTypeIcon(entry.type)"
                                                        class="h-3.5 w-3.5"
                                                    />
                                                    {{ formatUserAuditType(entry.type) }}
                                                </span>
                                            </div>

                                            <UserAuditDiff :entry="entry" />
                                        </div>
                                    </li>
                                </ul>

                                <LoadMoreButton
                                    v-if="hasMoreUserAudits"
                                    :loading="isLoadingMoreUserAudits"
                                    @click="loadMoreUserAudits"
                                />
                            </div>
                        </div>
                    </PanelCard>
                </section>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showDeleteConfirmation"
                class="fixed inset-0 z-50 overflow-y-auto"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-modal-title"
            >
                <div class="flex min-h-screen items-center justify-center px-4 py-8 text-center">
                    <div
                        class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm dark:bg-black/70"
                        @click="closeDeleteModal"
                    />

                    <div
                        class="relative w-full max-w-md rounded-3xl border border-gray-200 bg-white p-6 text-left shadow-2xl dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div
                            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                        >
                            <ExclamationTriangleIcon
                                class="h-6 w-6 text-red-600 dark:text-red-400"
                            />
                        </div>

                        <h3
                            id="delete-modal-title"
                            class="text-center text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Delete Account
                        </h3>
                        <p
                            class="mt-2 text-center text-sm leading-6 text-gray-600 dark:text-gray-400"
                        >
                            Permanently delete <strong>@{{ profile.username }}</strong
                            >? This action cannot be undone and will remove all associated data.
                        </p>

                        <label
                            for="delete-confirm"
                            class="mt-6 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Type "DELETE" to confirm:
                        </label>
                        <input
                            id="delete-confirm"
                            ref="deleteInputRef"
                            v-model="deleteConfirmationText"
                            type="text"
                            placeholder="DELETE"
                            autocomplete="off"
                            class="mt-2 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder-gray-400 outline-none transition focus:border-transparent focus:ring-2 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            @keydown.enter="deleteAccount"
                        />

                        <div class="mt-5 flex gap-3">
                            <button
                                type="button"
                                class="flex-1 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                @click="closeDeleteModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                :disabled="!canDelete"
                                :class="[
                                    'inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium text-white transition',
                                    canDelete
                                        ? 'bg-red-600 hover:bg-red-700'
                                        : 'cursor-not-allowed bg-gray-300 dark:bg-gray-700'
                                ]"
                                @click="deleteAccount"
                            >
                                <TrashIcon v-if="!isDeleting" class="h-4 w-4" />
                                <ArrowPathIcon v-else class="h-4 w-4 animate-spin" />
                                {{ isDeleting ? 'Deleting...' : 'Delete' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showEmailModal && selectedEmailEntry"
                class="fixed inset-0 z-50 overflow-y-auto"
                role="dialog"
                aria-modal="true"
                aria-labelledby="email-modal-title"
            >
                <div class="flex min-h-screen items-center justify-center px-4 py-8">
                    <div
                        class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm dark:bg-black/70"
                        @click="closeEmailModal"
                    />

                    <div
                        class="relative w-full max-w-2xl overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div
                            class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/30"
                                >
                                    <EnvelopeIcon
                                        class="h-5 w-5 text-blue-600 dark:text-blue-400"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <h3
                                        id="email-modal-title"
                                        class="text-base font-semibold text-gray-900 dark:text-white"
                                    >
                                        Email Sent
                                    </h3>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        Sent by @{{ selectedEmailEntry.auditor.username }}
                                        <span class="mx-1 text-gray-300 dark:text-gray-700">·</span>
                                        {{ formatTimeAgo(selectedEmailEntry.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="closeEmailModal"
                                class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                            >
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4 px-6 py-5">
                            <div
                                v-if="selectedEmailEntry.resource"
                                class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-800 dark:bg-gray-800/40"
                            >
                                <img
                                    :src="selectedEmailEntry.resource.avatar"
                                    :alt="selectedEmailEntry.resource.username"
                                    class="h-10 w-10 shrink-0 rounded-full bg-gray-100 object-cover"
                                    @error="handleAvatarError"
                                />
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        To
                                    </div>
                                    <div
                                        class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            selectedEmailEntry.resource.name ||
                                            selectedEmailEntry.resource.username
                                        }}
                                    </div>
                                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        @{{ selectedEmailEntry.resource.username }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-if="selectedEmailEntry.value.template_id"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <DocumentTextIcon class="h-3.5 w-3.5" />
                                    {{ selectedEmailEntry.value.template_id }}
                                </span>
                                <span
                                    v-if="selectedEmailEntry.value.cc_admin"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                >
                                    <CheckIcon class="h-3.5 w-3.5" />
                                    BCC'd Admins
                                </span>
                                <span
                                    v-if="selectedEmailEntry.value.message_length"
                                    class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ formatNumber(selectedEmailEntry.value.message_length) }}
                                    characters
                                </span>
                            </div>

                            <div>
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Subject
                                </div>
                                <div
                                    class="mt-1 break-words text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ selectedEmailEntry.value.subject }}
                                </div>
                            </div>

                            <div>
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Message
                                </div>
                                <div
                                    class="mt-2 max-h-[50vh] overflow-y-auto rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40"
                                >
                                    <pre
                                        class="whitespace-pre-wrap break-words font-sans text-sm leading-relaxed text-gray-800 dark:text-gray-200"
                                    >
                                {{ selectedEmailEntry.value.message_body }}</pre>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-800"
                        >
                            <button
                                type="button"
                                @click="copyMessageBody"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <component
                                    :is="copiedMessage ? CheckIcon : ClipboardIcon"
                                    class="h-4 w-4"
                                />
                                {{ copiedMessage ? 'Copied' : 'Copy' }}
                            </button>
                            <button
                                type="button"
                                @click="closeEmailModal"
                                class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <AdminSendEmailModal
            :show="showSendEmailModal"
            :profile="profile"
            @close="showSendEmailModal = false"
            @send="handleSendEmail"
        />

        <AdminResetPasswordModal
            :show="showResetPasswordModal"
            :profile="profile"
            @close="showResetPasswordModal = false"
            @reset="handleResetPassword"
        />

        <AdminModPermissionModal
            :show="modPermissionModal.show"
            :permission="modPermissionModal.perm"
            :profile="profile"
            :enabling="modPermissionModal.enabling"
            :saving="modPermissionModal.saving"
            @close="closeModPermissionModal"
            @confirm="confirmModPermission"
        />
    </div>
</template>

<script setup>
import { computed, h, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { profilesApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'
import Dropdown from '@/components/Dropdown.vue'
import DropdownItem from '@/components/DropdownItem.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import {
    ArchiveBoxIcon,
    ArrowLeftIcon,
    ArrowPathIcon,
    ArrowRightIcon,
    BellAlertIcon,
    BellIcon,
    BellSlashIcon,
    CalendarIcon,
    ChatBubbleLeftIcon,
    ChatBubbleLeftRightIcon,
    CheckBadgeIcon,
    CheckCircleIcon,
    CheckIcon,
    ChevronDownIcon,
    ClockIcon,
    ClipboardIcon,
    EyeIcon,
    XMarkIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    EnvelopeOpenIcon,
    ExclamationTriangleIcon,
    FlagIcon,
    HashtagIcon,
    HeartIcon,
    IdentificationIcon,
    KeyIcon,
    LinkIcon,
    LockClosedIcon,
    LockOpenIcon,
    MagnifyingGlassCircleIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    NoSymbolIcon,
    PencilSquareIcon,
    RocketLaunchIcon,
    RssIcon,
    ShieldCheckIcon,
    SparklesIcon,
    TrashIcon,
    UserCircleIcon,
    UserIcon,
    UserPlusIcon,
    UsersIcon,
    VideoCameraIcon,
    Cog6ToothIcon,
    ChatBubbleBottomCenterIcon,
    CodeBracketIcon,
    QueueListIcon
} from '@heroicons/vue/24/outline'
import DropdownDivider from '@/components/DropdownDivider.vue'
import AdminSendEmailModal from '@/components/Admin/AdminSendEmailModal.vue'
import AdminResetPasswordModal from '@/components/Admin/AdminResetPasswordModal.vue'
import AdminModPermissionModal from '@/components/Admin/AdminModPermissionModal.vue'

const { formatDate, formatNumber, formatTimeAgo } = useUtils()
const { confirmModal } = useAlertModal()
const route = useRoute()
const router = useRouter()

const DEFAULT_AVATAR = '/storage/avatars/default.jpg'
const BIO_CLAMP_LINES = 3

const profile = ref(null)
const adminNotes = ref('')
const adminNotesSaving = ref(false)
const adminNotesSavedRecently = ref(false)
const isLoading = ref(true)
const notFound = ref(false)
const showDeleteConfirmation = ref(false)
const deleteConfirmationText = ref('')
const isDeleting = ref(false)
const deleteInputRef = ref(null)
const showSendEmailModal = ref(false)
const showResetPasswordModal = ref(false)

const auditLogs = ref([])
const auditCursor = ref(null)
const hasMoreAudits = ref(false)
const isLoadingAudits = ref(false)
const isLoadingMoreAudits = ref(false)

const userAuditLogs = ref([])
const userAuditCursor = ref(null)
const hasMoreUserAudits = ref(false)
const isLoadingUserAudits = ref(false)
const isLoadingMoreUserAudits = ref(false)

const showEmailModal = ref(false)
const selectedEmailEntry = ref(null)
const copiedMessage = ref(false)
let copiedTimer = null

const bioRef = ref(null)
const bioExpanded = ref(false)
const bioNeedsClamp = ref(false)

const savingPermissions = ref(new Set())
const savingModPermissions = ref(new Set())
let fetchToken = 0
let bioResizeObserver = null
let savedNotesTimer = null

const modPermissionModal = ref({
    show: false,
    perm: null,
    enabling: false,
    saving: false
})

const statusBadgeStyles = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    disabled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    deleted: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
}
const defaultBadge = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'

const permissionConfig = [
    { key: 'can_comment', label: 'Comments', description: 'Can comment', icon: ChatBubbleLeftIcon },
    {
        key: 'can_upload',
        label: 'Video Uploads',
        description: 'Can upload videos',
        icon: VideoCameraIcon
    },
    { key: 'can_follow', label: 'Follow', description: 'Can follow', icon: UserPlusIcon },
    { key: 'can_like', label: 'Likes', description: 'Can like', icon: HeartIcon },
    { key: 'can_report', label: 'Reports', description: 'Can create reports', icon: FlagIcon },
    {
        key: 'can_playlist',
        label: 'Playlists',
        description: 'Can create playlists',
        icon: QueueListIcon
    },
    {
        key: 'can_create_starter_kits',
        label: 'Starter Kits',
        description: 'Create Starter Kits',
        icon: SparklesIcon
    },
    {
        key: 'can_use_starter_kits',
        label: 'Use Starter Kits',
        description: 'Use Starter Kits',
        icon: RocketLaunchIcon
    },
    {
        key: 'can_embed',
        label: 'Embeds',
        localOnly: true,
        description: 'Allow video embeds',
        icon: CodeBracketIcon
    }
]

const modConfig = [
    {
        key: 'enforce_ai_label',
        label: 'AI Auto-Label',
        description: 'Enforce AI label on posts',
        icon: ExclamationTriangleIcon
    },
    {
        key: 'enforce_ad_label',
        label: 'Ad Auto-Label',
        description: 'Enforce Ad label on posts',
        icon: ExclamationTriangleIcon
    },
    {
        key: 'enforce_nsfw_label',
        label: 'NSFW Auto-Label',
        description: 'Enforce NSFW on posts',
        icon: ExclamationTriangleIcon
    }
]

const auditTypeLabels = {
    'profile:update_notes': 'Admin notes updated',
    'profile:permissions': 'Permissions Updated',
    'profile:revoke_sessions': 'Revoke mobile sessions',
    'profile:suspend': 'Suspended',
    'profile:unsuspend': 'Unsuspended',
    'profile:delete': 'Deleted',
    'profile:notes': 'Notes Updated',
    'profile:verify': 'Email Verified',
    'profile:unverify': 'Email Unverified',
    'profile:email_verify': 'Email Verified by admin',
    'profile:email_unverify': 'Email Unverified by admin',
    'profile:2fa_disable': 'Two factor auth disabled by admin',
    'profile:send_email': 'Email sent',
    'profile:password_reset': 'Password reset',
    'profile:avatar_delete': 'Avatar deleted by admin',
    'profile:delete_all_comments': 'Delete all comments/replies by this user'
}

const auditTypeStyles = {
    'profile:permissions': 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'profile:suspend': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:revoke_sessions': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:avatar_delete': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:unsuspend': 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    'profile:delete': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:notes': 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    'profile:verify': 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'profile:update_notes':
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'profile:unverify': 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    'profile:email_verify':
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'profile:email_unverify': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:delete_all_comments': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:2fa_disable': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:send_email': 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'profile:password_reset': 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
}

const auditTypeIcons = {
    'profile:update_notes': PencilSquareIcon,
    'profile:permissions': LockClosedIcon,
    'profile:revoke_sessions': LockClosedIcon,
    'profile:suspend': NoSymbolIcon,
    'profile:unsuspend': CheckIcon,
    'profile:delete': TrashIcon,
    'profile:avatar_delete': TrashIcon,
    'profile:delete_all_comments': TrashIcon,
    'profile:notes': PencilSquareIcon,
    'profile:verify': CheckBadgeIcon,
    'profile:email_verify': CheckBadgeIcon,
    'profile:email_unverify': NoSymbolIcon,
    'profile:2fa_disable': NoSymbolIcon,
    'profile:unverify': CheckBadgeIcon,
    'profile:send_email': EnvelopeIcon,
    'profile:password_reset': KeyIcon
}

const userAuditTypeLabels = {
    atom_enabled: 'Atom enabled',
    atom_disabled: 'Atom disabled',
    avatar_updated: 'Avatar updated',
    avatar_deleted: 'Avatar deleted',
    password_changed: 'Password changed',
    two_factor_enabled: 'MFA enabled',
    two_factor_disabled: 'MFA disabled',
    email_changed: 'Email updated',
    account_data_settings_updated: 'Data settings changed',
    email_verified: 'Email verified',
    profile_updated: 'Profile updated',
    starter_kit_state: 'Starter Kit Permissions',
    profile_links_add: 'New Profile Link',
    profile_links_delete: 'Profile Link Deleted',
    push_notifications_enabled: 'Push Notifications enabled',
    push_notifications_disabled: 'Push Notifications disabled',
    content_settings: 'Content settings updated'
}

const userAuditTypeIcons = {
    atom_enabled: RssIcon,
    atom_disabled: RssIcon,
    avatar_updated: UserCircleIcon,
    avatar_deleted: UserCircleIcon,
    password_changed: LockClosedIcon,
    two_factor_enabled: ShieldCheckIcon,
    two_factor_disabled: LockOpenIcon,
    email_changed: EnvelopeOpenIcon,
    account_data_settings_updated: ArchiveBoxIcon,
    email_verified: CheckCircleIcon,
    profile_updated: UserPlusIcon,
    starter_kit_state: UserPlusIcon,
    profile_links_add: LinkIcon,
    profile_links_delete: LinkIcon,
    push_notifications_enabled: BellAlertIcon,
    push_notifications_disabled: BellSlashIcon,
    content_settings: Cog6ToothIcon
}

const userAuditTypeStyles = {
    atom_enabled: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    atom_disabled: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    avatar_updated: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    content_settings: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    avatar_deleted: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    profile_links_delete: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    password_changed: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    two_factor_enabled: 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    two_factor_disabled: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    email_changed: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    push_notifications_disabled:
        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    account_data_settings_updated:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    starter_kit_state: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    email_verified: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    push_notifications_enabled:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    profile_links_add:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    profile_updated: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
}

const starterKitTypes = {
    0: 'Disabled',
    1: 'Accounts they follow only',
    2: 'Accounts they follow with approval',
    3: 'Local accounts only',
    4: 'Local accounts with approval only',
    5: 'Permission required',
    6: 'Auto-allow everyone'
}

const messageSnippet = (body) => {
    if (!body) return ''
    return body.replace(/\s+/g, ' ').trim()
}

const openEmailModal = (entry) => {
    selectedEmailEntry.value = entry
    showEmailModal.value = true
}

const closeEmailModal = () => {
    if (copiedTimer) clearTimeout(copiedTimer)
    showEmailModal.value = false
    selectedEmailEntry.value = null
    copiedMessage.value = false
}

const copyMessageBody = async () => {
    const body = selectedEmailEntry.value?.value?.message_body
    if (!body) return
    try {
        await navigator.clipboard.writeText(body)
        copiedMessage.value = true
        if (copiedTimer) clearTimeout(copiedTimer)
        copiedTimer = setTimeout(() => {
            copiedMessage.value = false
        }, 2000)
    } catch (error) {
        console.error('Failed to copy message body:', error)
    }
}

const isDeleted = computed(() => profile.value?.status === 'deleted')

const statusBadge = computed(() => statusBadgeStyles[profile.value?.status] || defaultBadge)

const canDelete = computed(() => deleteConfirmationText.value === 'DELETE' && !isDeleting.value)

const relativeActivity = computed(() => {
    if (!profile.value) return '—'
    const ts = profile.value.local ? profile.value.last_active_at : profile.value.updated_at
    return ts ? formatTimeAgo(ts) : '—'
})

const statCards = computed(() => {
    if (!profile.value) return []
    const p = profile.value
    return [
        {
            icon: VideoCameraIcon,
            label: 'Videos',
            value: formatNumber(p.post_count || 0),
            to: `/admin/videos?q=username:${p.username}`
        },
        { icon: UsersIcon, label: 'Followers', value: formatNumber(p.follower_count || 0) },
        { icon: UserPlusIcon, label: 'Following', value: formatNumber(p.following_count || 0) },
        { icon: HeartIcon, label: 'Total Likes', value: formatNumber(p.likes_count || 0) },
        {
            icon: ChatBubbleLeftIcon,
            label: 'Comments',
            value: formatNumber(p.comments_count || 0),
            to: `/admin/comments?q=user:${p.username}`
        },
        {
            icon: ChatBubbleLeftRightIcon,
            label: 'Replies',
            value: formatNumber(p.comment_replies_count || 0)
        },
        {
            icon: FlagIcon,
            label: 'Reports Created',
            value: formatNumber(p.reports_created_count || 0),
            to: `/admin/reports?q=reported_by:${p.id}`
        },
        {
            icon: ExclamationTriangleIcon,
            label: 'Times Reported',
            value: formatNumber(p.reported_count || 0),
            to: `/admin/reports?q=reported_profile_id:${p.id}`
        },
        {
            icon: SparklesIcon,
            label: 'In Starter Kit(s)',
            value: formatNumber(p.starter_kits_included_in_count || 0),
            to: `/admin/reports?q=reported_profile_id:${p.id}`
        },
        {
            icon: RocketLaunchIcon,
            label: 'Starter Kit(s) Made',
            value: formatNumber(p.starter_kits_created_count || 0),
            to: `/admin/reports?q=reported_profile_id:${p.id}`
        }
    ]
})

const PanelCard = {
    setup(_, { slots }) {
        return () =>
            h(
                'div',
                {
                    class: 'overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900'
                },
                [slots.header?.(), slots.default?.()]
            )
    }
}

const PanelHeader = {
    props: ['title', 'description', 'icon'],
    setup(props) {
        return () =>
            h('div', { class: 'border-b border-gray-200 px-4 py-4 dark:border-gray-800 sm:px-6' }, [
                h('div', { class: 'flex items-start gap-3' }, [
                    h(
                        'div',
                        {
                            class: 'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800'
                        },
                        [h(props.icon, { class: 'h-5 w-5 text-gray-500 dark:text-gray-400' })]
                    ),
                    h('div', { class: 'min-w-0' }, [
                        h(
                            'h3',
                            {
                                class: 'text-base font-semibold tracking-tight text-gray-950 dark:text-white'
                            },
                            props.title
                        ),
                        props.description
                            ? h(
                                  'p',
                                  { class: 'mt-1 text-sm text-gray-500 dark:text-gray-400' },
                                  props.description
                              )
                            : null
                    ])
                ])
            ])
    }
}

const MetricCard = {
    props: ['icon', 'label', 'value'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    class: 'flex h-full min-h-[88px] items-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700'
                },
                [
                    h(
                        'div',
                        {
                            class: 'flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800'
                        },
                        [h(props.icon, { class: 'h-4.5 w-4.5 text-gray-500 dark:text-gray-400' })]
                    ),
                    h('div', { class: 'min-w-0 flex-1' }, [
                        h(
                            'div',
                            {
                                class: 'text-[11px] font-semibold uppercase leading-4 text-gray-500 dark:text-gray-400'
                            },
                            props.label
                        ),
                        h(
                            'div',
                            {
                                class: 'mt-1 truncate text-xl font-semibold tracking-tight text-gray-950 dark:text-white'
                            },
                            props.value
                        )
                    ])
                ]
            )
    }
}

const QuickInfoCard = {
    props: ['label', 'value', 'mono'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    class: 'rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/50'
                },
                [
                    h(
                        'div',
                        {
                            class: 'text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400'
                        },
                        props.label
                    ),
                    h(
                        'div',
                        {
                            class: [
                                'mt-1 truncate text-sm font-medium text-gray-900 dark:text-white',
                                props.mono ? 'font-mono' : ''
                            ]
                        },
                        props.value
                    )
                ]
            )
    }
}

const DetailRow = {
    props: ['icon', 'label', 'value', 'mono', 'url'],
    setup(props) {
        return () =>
            h('div', { class: 'flex items-start gap-3 px-5 py-4 sm:px-6' }, [
                h(props.icon, { class: 'mt-0.5 h-4 w-4 shrink-0 text-gray-400' }),
                h('div', { class: 'min-w-0 flex-1' }, [
                    h(
                        'dt',
                        {
                            class: 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400'
                        },
                        props.label
                    ),
                    h(
                        'dd',
                        {
                            class: [
                                'mt-1 break-words text-sm text-gray-900 dark:text-white',
                                props.mono ? 'font-mono' : ''
                            ]
                        },
                        props.url
                            ? h(
                                  'a',
                                  {
                                      href: props.url,
                                      class: 'text-blue-600 hover:underline dark:text-blue-400'
                                  },
                                  props.value
                              )
                            : props.value
                    )
                ])
            ])
    }
}

const PermissionTile = {
    emits: ['toggle'],
    props: ['icon', 'label', 'description', 'enabled', 'disabled'],
    setup(props, { emit }) {
        return () =>
            h(
                'div',
                {
                    class: 'rounded-2xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40'
                },
                [
                    h('div', { class: 'flex items-start justify-between gap-4' }, [
                        h('div', { class: 'flex min-w-0 gap-3' }, [
                            h(
                                'div',
                                {
                                    class: 'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white dark:bg-gray-900'
                                },
                                [
                                    h(props.icon, {
                                        class: 'h-4 w-4 text-gray-500 dark:text-gray-400'
                                    })
                                ]
                            ),
                            h('div', { class: 'min-w-0' }, [
                                h(
                                    'h4',
                                    {
                                        class: 'text-sm font-semibold text-gray-900 dark:text-white'
                                    },
                                    props.label
                                ),
                                h(
                                    'p',
                                    {
                                        class: 'mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400'
                                    },
                                    props.description
                                )
                            ])
                        ]),
                        h(
                            'button',
                            {
                                type: 'button',
                                role: 'switch',
                                'aria-checked': props.enabled,
                                'aria-label': `${props.enabled ? 'Disable' : 'Enable'} ${props.label}`,
                                onClick: () => emit('toggle'),
                                disabled: props.disabled,
                                class: [
                                    'relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#F02C56]/60 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:cursor-not-allowed disabled:opacity-40',
                                    props.enabled ? 'bg-[#F02C56]' : 'bg-gray-300 dark:bg-gray-700'
                                ]
                            },
                            [
                                h('span', {
                                    'aria-hidden': 'true',
                                    class: [
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 ease-in-out',
                                        props.enabled ? 'translate-x-5' : 'translate-x-0'
                                    ]
                                })
                            ]
                        )
                    ])
                ]
            )
    }
}

const TimelineEmpty = {
    props: ['title', 'description'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    class: 'rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700'
                },
                [
                    h(ClockIcon, { class: 'mx-auto h-12 w-12 text-gray-300 dark:text-gray-700' }),
                    h(
                        'p',
                        { class: 'mt-3 text-sm font-medium text-gray-700 dark:text-gray-300' },
                        props.title
                    ),
                    props.description
                        ? h(
                              'p',
                              { class: 'mt-1 text-sm text-gray-500 dark:text-gray-400' },
                              props.description
                          )
                        : null
                ]
            )
    }
}

const LoadMoreButton = {
    emits: ['click'],
    props: ['loading'],
    setup(props, { emit }) {
        return () =>
            h('div', { class: 'mt-6 text-center' }, [
                h(
                    'button',
                    {
                        type: 'button',
                        disabled: props.loading,
                        onClick: () => emit('click'),
                        class: 'inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    },
                    [
                        props.loading
                            ? h(ArrowPathIcon, { class: 'h-4 w-4 animate-spin' })
                            : h(ChevronDownIcon, { class: 'h-4 w-4' }),
                        props.loading ? 'Loading...' : 'Load more'
                    ]
                )
            ])
    }
}

const diffRowClass =
    'mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60'

const UserAuditDiff = {
    props: ['entry'],
    setup(props) {
        const entry = () => props.entry
        const rowWrap = (children) => h('div', { class: diffRowClass }, [children])
        const inlineRow = (children) =>
            h('div', { class: 'flex flex-wrap items-center gap-2 text-xs' }, children)
        const monoLabel = (txt) =>
            h('span', { class: 'font-mono text-gray-500 dark:text-gray-400' }, txt)
        const arrow = () => h(ArrowRightIcon, { class: 'h-3 w-3 text-gray-400' })
        const badge = (val, text) => h('span', { class: valueBadge(val) }, text)

        return () => {
            const e = entry()
            if (!e?.value) return null

            if (e.type === 'profile_updated') {
                return rowWrap(
                    h('div', { class: 'flex flex-wrap items-center gap-2 text-xs' }, [
                        h('span', { class: 'text-gray-600 dark:text-gray-400' }, 'Changed:'),
                        ...(e.value.changed_fields || []).map((field) =>
                            h('span', { key: `${e.id}-${field}`, class: valueBadge(true) }, field)
                        )
                    ])
                )
            }

            if (e.type === 'account_data_settings_updated') {
                return h(
                    'div',
                    { class: diffRowClass },
                    (e.value.changed_fields || []).map((change, index) =>
                        h(
                            'div',
                            {
                                key: `${e.id}-${index}`,
                                class: 'flex flex-wrap items-center gap-2 py-1 text-xs'
                            },
                            [
                                monoLabel('Changed:'),
                                h('span', null, Object.keys(change)[0]),
                                arrow(),
                                badge(Object.values(change)[0], Object.values(change)[0])
                            ]
                        )
                    )
                )
            }

            if (e.type === 'email_verified') {
                return rowWrap(inlineRow([badge(true, e.value.email)]))
            }

            if (e.type === 'email_changed') {
                return rowWrap(
                    inlineRow([
                        monoLabel('Email:'),
                        badge(false, e.value.old_email),
                        arrow(),
                        badge(true, e.value.new_email)
                    ])
                )
            }

            if (e.type === 'content_settings') {
                const contentSettingsType = Object.hasOwn(e.value.new, 'hide_sensitive')
                    ? 'nsfw'
                    : 'ai'
                if (contentSettingsType === 'nsfw') {
                    return rowWrap(
                        inlineRow([
                            monoLabel('Hide Sensitive:'),
                            badge(false, e.value.old.hide_sensitive),
                            arrow(),
                            badge(true, e.value.new.hide_sensitive)
                        ])
                    )
                }
                return rowWrap(
                    inlineRow([
                        monoLabel('Hide AI:'),
                        badge(false, e.value.old.hide_ai),
                        arrow(),
                        badge(true, e.value.new.hide_ai)
                    ])
                )
            }

            if (e.type === 'starter_kit_state') {
                return rowWrap(
                    inlineRow([
                        badge(false, starterKitTypes[e.value.old]),
                        arrow(),
                        badge(true, starterKitTypes[e.value.new])
                    ])
                )
            }

            if (e.type === 'profile_links_add' || e.type === 'profile_links_delete') {
                const oldLinks = Array.isArray(e.value.old) ? e.value.old : []
                const newLinks = Array.isArray(e.value.new) ? e.value.new : []
                const children = []
                if (oldLinks.length) {
                    oldLinks.forEach((old, i) => {
                        children.push(
                            h(
                                'span',
                                { key: `old-${e.id}-${i}`, class: valueBadge(false) },
                                old.url
                            )
                        )
                    })
                } else {
                    children.push(badge(false, '—'))
                }
                children.push(arrow())
                newLinks.forEach((link, i) => {
                    children.push(
                        h('span', { key: `new-${e.id}-${i}`, class: valueBadge(true) }, link.url)
                    )
                })
                return rowWrap(inlineRow(children))
            }

            if (
                e.type === 'push_notifications_enabled' ||
                e.type === 'push_notifications_disabled'
            ) {
                return rowWrap(
                    inlineRow([monoLabel('Platform:'), arrow(), badge(true, e.value.platform)])
                )
            }

            return null
        }
    }
}

const handleAvatarError = (event) => {
    const img = event.target
    if (img.dataset.fallbackApplied) return
    img.dataset.fallbackApplied = '1'
    img.src = DEFAULT_AVATAR
}

const getBioThreshold = (el) => {
    const styles = getComputedStyle(el)
    const parsed = parseFloat(styles.lineHeight)
    if (Number.isFinite(parsed) && parsed > 0) return parsed * BIO_CLAMP_LINES
    const fontSize = parseFloat(styles.fontSize) || 14
    return fontSize * 1.5 * BIO_CLAMP_LINES
}

const checkBioOverflow = () => {
    const el = bioRef.value
    if (!el) {
        bioNeedsClamp.value = false
        return
    }
    bioNeedsClamp.value = el.scrollHeight > getBioThreshold(el) + 1
}

const observeBio = () => {
    bioResizeObserver?.disconnect()
    if (!bioRef.value || typeof ResizeObserver === 'undefined') return
    bioResizeObserver = new ResizeObserver(() => checkBioOverflow())
    bioResizeObserver.observe(bioRef.value)
    checkBioOverflow()
}

watch(bioRef, async () => {
    await nextTick()
    observeBio()
})

const fetchProfile = async (id) => {
    const token = ++fetchToken
    isLoading.value = true
    notFound.value = false

    try {
        const response = await profilesApi.getProfile(id)
        if (token !== fetchToken) return

        profile.value = response.data
        adminNotes.value = response.data?.admin_notes || ''
        bioExpanded.value = false

        if (response.data?.local) {
            await fetchUserAuditLog(id)
        } else {
            userAuditLogs.value = []
            userAuditCursor.value = null
            hasMoreUserAudits.value = false
        }

        await nextTick()
        checkBioOverflow()
    } catch (error) {
        if (token !== fetchToken) return
        notFound.value = true
        console.error('Error fetching profile:', error)
    } finally {
        if (token === fetchToken) isLoading.value = false
    }
}

const fetchAuditLog = async (id, cursor = null) => {
    try {
        if (!cursor) isLoadingAudits.value = true
        const res = await profilesApi.getProfileAdminAuditLogs(id, cursor)
        const data = res.data || []
        auditLogs.value = cursor ? [...auditLogs.value, ...data] : data
        auditCursor.value = res.meta?.next_cursor || null
        hasMoreAudits.value = !!res.meta?.next_cursor
    } catch (error) {
        console.error('Error fetching audit logs:', error)
    } finally {
        isLoadingAudits.value = false
    }
}

const fetchUserAuditLog = async (id, cursor = null) => {
    try {
        if (!cursor) isLoadingUserAudits.value = true
        const res = await profilesApi.getProfileUserAuditLogs(id, cursor)
        const data = res.data || []
        userAuditLogs.value = cursor ? [...userAuditLogs.value, ...data] : data
        userAuditCursor.value = res.meta?.next_cursor || null
        hasMoreUserAudits.value = !!res.meta?.next_cursor
    } catch (error) {
        console.error('Error fetching user audit logs:', error)
    } finally {
        isLoadingUserAudits.value = false
    }
}

const loadMoreAudits = async () => {
    if (!hasMoreAudits.value || isLoadingMoreAudits.value) return
    isLoadingMoreAudits.value = true
    try {
        await fetchAuditLog(profile.value.id, auditCursor.value)
    } finally {
        isLoadingMoreAudits.value = false
    }
}

const loadMoreUserAudits = async () => {
    if (!hasMoreUserAudits.value || isLoadingMoreUserAudits.value) return
    isLoadingMoreUserAudits.value = true
    try {
        await fetchUserAuditLog(profile.value.id, userAuditCursor.value)
    } finally {
        isLoadingMoreUserAudits.value = false
    }
}

const formatAuditType = (type) => auditTypeLabels[type] || type
const auditTypeStyle = (type) => auditTypeStyles[type] || defaultBadge
const auditTypeIcon = (type) => auditTypeIcons[type] || ClockIcon

const formatUserAuditType = (type) => userAuditTypeLabels[type] || type
const userAuditTypeStyle = (type) => userAuditTypeStyles[type] || defaultBadge
const userAuditTypeIcon = (type) => userAuditTypeIcons[type] || ClockIcon

const getAuditDiff = (entry) => {
    if (!entry.value || typeof entry.value !== 'object') return []
    const newVals = entry.value.new || {}
    const oldVals = entry.value.old || {}
    return Object.keys(newVals).map((key) => ({
        key: key.replace(/_/g, ' '),
        old: oldVals[key],
        new: newVals[key]
    }))
}

const formatValue = (val) => {
    if (val === true) return 'Enabled'
    if (val === false) return 'Disabled'
    if (val === null || val === undefined || val === '') return '—'
    return String(val)
}

function valueBadge(val) {
    if (val === true)
        return 'rounded-lg bg-green-100 px-2 py-1 font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300'
    if (val === false)
        return 'rounded-lg bg-red-100 px-2 py-1 font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300'
    return 'rounded-lg bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300'
}

const goBack = () => router.replace('/admin/profiles')

const handleSuspend = async () => {
    const userState = profile.value.local
        ? `This will not delete this account, or existing connections and associated profile data. This will prevent <strong>${profile.value.username}</strong> from logging in and hide all activity, interactions, profile and videos. You can safely unsuspend to restore their account.`
        : `This will not delete this account, or existing connections and associated profile data. This will hide all activity, interactions, profile and videos from <strong>${profile.value.username}</strong>. You can safely unsuspend to restore their account.`

    const message = `<div class="space-y-4">
        <p class="text-gray-800 dark:text-gray-300">Are you sure you want to suspend <span class="font-semibold">${profile.value.username}</span>?</p>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <p class="text-sm text-amber-800">${userState}</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <p class="text-sm text-blue-800"><span class="font-medium">Note:</span> To permanently delete, use Delete Account in the overflow menu.</p>
        </div>
    </div>`

    const result = await confirmModal('Confirm Suspend', message)
    if (!result) return

    try {
        await profilesApi.updateProfileSuspend(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error suspending profile:', error)
    }
}

const handleUnsuspend = async () => {
    const result = await confirmModal(
        'Confirm Unsuspend',
        `Are you sure you want to unsuspend <strong>${profile.value.username}</strong>?`
    )
    if (!result) return

    try {
        await profilesApi.updateProfileUnsuspend(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error unsuspending profile:', error)
    }
}

const handleDisable2fa = async () => {
    const result = await confirmModal(
        'Confirm Disable Two Factor Auth',
        `Are you sure you want to disable two factor auth for <strong>${profile.value.username}</strong>'s account?`
    )
    if (!result) return

    try {
        await profilesApi.updateProfileDisableTwoFactorAuth(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error unsuspending profile:', error)
    }
}

const handleDeleteAllComments = async () => {
    const result = await confirmModal(
        'Confirm Delete All Comments',
        `Are you sure you want to delete all comments and replies authored by <strong>${profile.value.username}</strong>'s account?`
    )
    if (!result) return

    try {
        await profilesApi.deleteAllProfileComments(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error deleting all profile comments:', error)
    }
}

const handleDeleteAvatar = async () => {
    const result = await confirmModal(
        'Confirm Avatar Delete',
        `Are you sure you want to delete <strong>${profile.value.username}</strong>'s avatar?`
    )
    if (!result) return

    try {
        await profilesApi.updateProfileDeleteAvatar(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error unsuspending profile:', error)
    }
}

const handleRevokeAllSessions = async () => {
    const result = await confirmModal(
        'Confirm Revoke All Sessions',
        `Are you sure you want to revoke all of <strong>${profile.value.username}</strong>'s sessions?`
    )
    if (!result) return

    try {
        await profilesApi.updateProfileRevokeAllSessions(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error revoking profile sessions:', error)
    }
}

const toggleVerification = async () => {
    const dir = profile.value.email_verified
    const ver = dir ? 'unverify' : 'verify'
    const result = await confirmModal(
        dir ? 'Confirm Unverify Email' : 'Confirm Verify Email',
        `Are you sure you want to ${ver} <strong>${profile.value.username}</strong>'s email address?`
    )
    if (!result) return

    try {
        await profilesApi.updateProfileToggleEmailVerify(profile.value.id)
        await fetchProfile(profile.value.id)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error updating verification:', error)
    }
}

const handleSendEmail = async (payload) => {
    try {
        await profilesApi.sendProfileEmail(profile.value.id, {
            subject: payload.subject,
            message: payload.message,
            cc_admin: payload.ccAdmin,
            log_as_audit: payload.logAsAudit,
            template_id: payload.templateId
        })
        showSendEmailModal.value = false
        if (payload.logAsAudit) {
            await fetchAuditLog(profile.value.id)
        }
    } catch (error) {
        console.error('Error sending email:', error)
    } finally {
        payload.done()
    }
}

const handleResetPassword = async (payload) => {
    try {
        await profilesApi.updateProfileResetPassword(profile.value.id, {
            password: payload.password,
            send_email: payload.sendEmail,
            force_change: payload.forceChange,
            revoke_sessions: payload.revokeSessions
        })
        showResetPasswordModal.value = false
        await fetchAuditLog(profile.value.id)
        // Optionally refresh profile to pick up 2FA / session flag changes
        // await fetchProfile(profile.value.id)
    } catch (error) {
        console.error('Error resetting password:', error)
    } finally {
        payload.done()
    }
}

const isPermissionSaving = (key) => savingPermissions.value.has(key)
const isModPermissionSaving = (key) => savingModPermissions.value.has(key)

const togglePermission = async (key) => {
    if (isPermissionSaving(key)) return
    const previous = !!profile.value[key]
    const next = !previous

    profile.value[key] = next
    savingPermissions.value = new Set([...savingPermissions.value, key])

    try {
        await profilesApi.updateProfilePermissions(profile.value.id, { [key]: next })
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        profile.value[key] = previous
        console.error('Error updating permission:', error)
    } finally {
        const updated = new Set(savingPermissions.value)
        updated.delete(key)
        savingPermissions.value = updated
    }
}

const requestModPermission = (key) => {
    if (isModPermissionSaving(key)) return
    const perm = modConfig.find((p) => p.key === key)
    if (!perm) return

    modPermissionModal.value = {
        show: true,
        perm,
        enabling: !profile.value[key],
        saving: false
    }
}

const closeModPermissionModal = () => {
    if (modPermissionModal.value.saving) return
    modPermissionModal.value = { show: false, perm: null, enabling: false, saving: false }
}

const confirmModPermission = async ({ key, value, applyToExisting }) => {
    if (!key || isModPermissionSaving(key)) return
    const previous = !!profile.value[key]

    modPermissionModal.value.saving = true
    profile.value[key] = value
    savingModPermissions.value = new Set([...savingModPermissions.value, key])

    const payload = { [key]: value }
    if (applyToExisting) {
        payload.apply_to_existing = true
    }

    try {
        await profilesApi.updateProfileModPermissions(profile.value.id, payload)
        await fetchAuditLog(profile.value.id)
        modPermissionModal.value = { show: false, perm: null, enabling: false, saving: false }
    } catch (error) {
        profile.value[key] = previous
        modPermissionModal.value.saving = false
        console.error('Error updating mod permission:', error)
    } finally {
        const updated = new Set(savingModPermissions.value)
        updated.delete(key)
        savingModPermissions.value = updated
    }
}

const saveAdminNotes = async () => {
    if (adminNotes.value === (profile.value?.admin_notes || '')) return
    adminNotesSaving.value = true
    adminNotesSavedRecently.value = false

    try {
        await profilesApi.updateProfileNotes(profile.value.id, { admin_notes: adminNotes.value })
        profile.value.admin_notes = adminNotes.value
        adminNotesSavedRecently.value = true
        if (savedNotesTimer) clearTimeout(savedNotesTimer)
        savedNotesTimer = setTimeout(() => {
            adminNotesSavedRecently.value = false
        }, 2500)
        await fetchAuditLog(profile.value.id)
    } catch (error) {
        console.error('Error saving admin notes:', error)
    } finally {
        adminNotesSaving.value = false
    }
}

const closeDeleteModal = () => {
    if (isDeleting.value) return
    showDeleteConfirmation.value = false
    deleteConfirmationText.value = ''
}

const deleteAccount = async () => {
    if (!canDelete.value) return
    isDeleting.value = true

    try {
        await new Promise((resolve) => setTimeout(resolve, 2000))
        showDeleteConfirmation.value = false
        router.replace('/admin/profiles')
    } catch (error) {
        console.error('Error deleting account:', error)
    } finally {
        isDeleting.value = false
        deleteConfirmationText.value = ''
    }
}

const handleKeydown = (event) => {
    if (event.key !== 'Escape') return
    if (showDeleteConfirmation.value) closeDeleteModal()
    else if (showEmailModal.value) closeEmailModal()
}

watch(showDeleteConfirmation, async (open) => {
    if (open) {
        document.body.style.overflow = 'hidden'
        await nextTick()
        deleteInputRef.value?.focus()
    } else {
        document.body.style.overflow = ''
    }
})

onMounted(async () => {
    window.addEventListener('keydown', handleKeydown)
    await fetchProfile(route.params.id)
    await fetchAuditLog(route.params.id)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    bioResizeObserver?.disconnect()
    if (savedNotesTimer) clearTimeout(savedNotesTimer)
    if (copiedTimer) clearTimeout(copiedTimer)
    document.body.style.overflow = ''
})

watch(
    () => route.params.id,
    (newId, oldId) => {
        if (!newId || newId === oldId) return

        auditLogs.value = []
        auditCursor.value = null
        hasMoreAudits.value = false
        userAuditLogs.value = []
        userAuditCursor.value = null
        hasMoreUserAudits.value = false
        bioNeedsClamp.value = false
        bioExpanded.value = false

        fetchProfile(newId)
        fetchAuditLog(newId)
    }
)

watch(
    () => profile.value?.bio,
    async () => {
        bioExpanded.value = false
        await nextTick()
        checkBioOverflow()
    }
)

watch(showEmailModal, (open) => {
    if (open) {
        document.body.style.overflow = 'hidden'
    } else if (!showDeleteConfirmation.value) {
        document.body.style.overflow = ''
    }
})
</script>
