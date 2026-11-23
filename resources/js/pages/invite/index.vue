<template>
    <BlankLayout>
        <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <div
                class="pointer-events-none absolute inset-0 opacity-60 dark:opacity-40"
                aria-hidden="true"
            >
                <div
                    class="absolute -top-24 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full blur-3xl"
                    style="
                        background: radial-gradient(
                            circle at 50% 50%,
                            rgba(240, 44, 86, 0.35),
                            transparent 60%
                        );
                    "
                ></div>
                <div
                    class="absolute -bottom-24 left-1/3 h-72 w-[40rem] -translate-x-1/2 rounded-full blur-3xl"
                    style="
                        background: radial-gradient(
                            circle at 50% 50%,
                            rgba(240, 44, 86, 0.22),
                            transparent 60%
                        );
                    "
                ></div>
            </div>

            <div
                class="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10 lg:px-8"
            >
                <div class="grid w-full grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-12">
                    <section class="flex flex-col justify-center">
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                            {{ $t('auth.joinLoopsWithYourInvite') }}
                        </h1>

                        <p
                            class="mt-4 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-300"
                        >
                            {{ $t('auth.youveBeenInvitedToCreateALoopsAccount') }}
                        </p>

                        <div
                            v-if="invitedBy && currentStep >= 2"
                            class="mt-8 rounded-2xl border border-slate-200 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <p
                                class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3"
                            >
                                {{ $t('auth.invitedBy') }}
                            </p>
                            <div class="flex items-center gap-3">
                                <img
                                    :src="invitedBy.avatar"
                                    :alt="invitedBy.name"
                                    class="h-12 w-12 rounded-full border-2 border-slate-200 dark:border-slate-700 object-cover"
                                />
                                <div>
                                    <p
                                        class="text-base font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{ invitedBy.name }}
                                    </p>
                                    <p class="text-sm text-slate-600 dark:text-slate-400">
                                        @{{ invitedBy.username }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="inviteMessage && currentStep >= 2"
                            class="mt-4 rounded-2xl border border-slate-200 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <div class="flex items-start gap-3">
                                <div>
                                    <p
                                        class="my-1 text-lg leading-relaxed text-slate-700 dark:text-slate-300"
                                    >
                                        {{ inviteMessage }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-if="currentStep > 0 && currentStep < 5" class="mt-8">
                            <div class="flex items-center gap-2">
                                <div
                                    v-for="i in 4"
                                    :key="i"
                                    class="h-1 flex-1 rounded-full transition-colors"
                                    :class="
                                        i <= currentStep
                                            ? 'bg-[#F02C56]'
                                            : 'bg-slate-200 dark:bg-slate-800'
                                    "
                                ></div>
                            </div>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('common.step') }} {{ currentStep }} {{ $t('common.of') }} 4
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="authStore.isAuthenticated"
                        class="flex items-center justify-center"
                    >
                        <div class="w-full max-w-md">
                            <div
                                class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-xl shadow-slate-900/5 backdrop-blur dark:border-slate-800 dark:bg-slate-900/70 dark:shadow-black/30 overflow-hidden"
                            >
                                <transition name="slide">
                                    <div v-if="authStore.isAuthenticated" class="space-y-4">
                                        <div class="flex justify-center items-center gap-3">
                                            <div
                                                class="flex flex-col space-y-3 justify-center items-center"
                                            >
                                                <p class="text-base font-semibold">
                                                    You are already logged in.
                                                </p>
                                                <p
                                                    class="text-sm text-slate-600 dark:text-slate-300"
                                                >
                                                    Please logout before attempting to use this
                                                    invite.
                                                </p>
                                                <AnimatedButton @click="router.push('/')">
                                                    Go back home
                                                </AnimatedButton>
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </section>
                    <section v-else class="flex items-center justify-center">
                        <div class="w-full max-w-md">
                            <div
                                class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-xl shadow-slate-900/5 backdrop-blur dark:border-slate-800 dark:bg-slate-900/70 dark:shadow-black/30 overflow-hidden"
                            >
                                <transition name="slide">
                                    <div v-if="currentStep === 0" class="space-y-4">
                                        <div class="flex items-center gap-3">
                                            <Spinner />

                                            <div>
                                                <p class="text-base font-semibold">
                                                    {{ $t('auth.verifyingInviteDotDotDot') }}
                                                </p>
                                                <p
                                                    class="text-sm text-slate-600 dark:text-slate-300"
                                                >
                                                    {{ $t('auth.thisUsuallyTakesAMoment') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <div
                                                class="h-10 w-full animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/70"
                                            ></div>
                                            <div
                                                class="h-10 w-full animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/70"
                                            ></div>
                                            <div
                                                class="h-10 w-full animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/70"
                                            ></div>
                                        </div>
                                    </div>
                                </transition>

                                <transition name="slide">
                                    <div v-if="currentStep === -1" class="space-y-4">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="mt-0.5 p-4 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950 flex items-center justify-center"
                                            >
                                                <ExclamationTriangleIcon
                                                    class="h-10 w-10 text-red-500"
                                                />
                                            </div>
                                            <div>
                                                <p class="text-base font-semibold">
                                                    {{ $t('auth.inviteLinkNotValid') }}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                                >
                                                    {{ $t('auth.thisInviteMayBeExpired') }}
                                                </p>
                                                <p
                                                    v-if="inviteError"
                                                    class="mt-2 text-sm text-rose-600 dark:text-rose-400"
                                                >
                                                    {{ inviteError }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4"
                                                :style="primaryButtonStyle"
                                                @click="retryVerify"
                                            >
                                                {{ $t('common.tryAgain') }}
                                            </button>

                                            <button
                                                type="button"
                                                class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900 dark:focus:ring-slate-800"
                                                @click="goHome"
                                            >
                                                {{ $t('post.goBack') }}
                                            </button>
                                        </div>
                                    </div>
                                </transition>

                                <transition name="slide">
                                    <div
                                        v-if="currentStep === 1 && appCaptcha.enabled"
                                        class="space-y-4"
                                    >
                                        <div>
                                            <p class="text-lg font-semibold">
                                                {{ $t('auth.verifyYoureHuman') }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                {{ $t('auth.completeTheVerificationToContinue') }}
                                            </p>
                                        </div>

                                        <div class="flex justify-center">
                                            <template v-if="appCaptcha.provider === 'turnstile'">
                                                <CloudflareTurnstile
                                                    ref="turnstileRef"
                                                    :sitekey="appCaptcha.siteKey"
                                                    theme="auto"
                                                    @success="onCaptchaSuccess"
                                                    @error="onCaptchaError"
                                                    @expired="onCaptchaExpired"
                                                />
                                            </template>
                                            <template
                                                v-else-if="appCaptcha.provider === 'hcaptcha'"
                                            >
                                                <HCaptcha
                                                    ref="hcaptchaRef"
                                                    :sitekey="appCaptcha.siteKey"
                                                    theme="auto"
                                                    @success="onCaptchaSuccess"
                                                    @error="onCaptchaError"
                                                    @expired="onCaptchaExpired"
                                                />
                                            </template>
                                        </div>

                                        <button
                                            type="button"
                                            :disabled="!captchaToken || verifying"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60"
                                            :style="primaryButtonStyle"
                                            @click="verifyInvite"
                                        >
                                            <span
                                                v-if="verifying"
                                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/70 border-t-transparent"
                                            ></span>
                                            <span>{{
                                                verifying
                                                    ? $t('auth.verifyingDotDotDot')
                                                    : $t('common.continue')
                                            }}</span>
                                        </button>
                                    </div>
                                </transition>

                                <transition name="slide">
                                    <div v-if="currentStep === 2" class="space-y-4">
                                        <div>
                                            <p class="text-lg font-semibold">
                                                {{ $t('auth.whatsYourBirthdate') }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                {{ $t('auth.weNeedToVerifyYouMeetTheMinAgeReq') }}
                                            </p>
                                        </div>

                                        <div class="grid grid-cols-3 gap-3">
                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                >
                                                    {{ $t('common.month') }}
                                                </label>
                                                <select
                                                    v-model="birth.month"
                                                    required
                                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 text-sm focus:border-[#F02C56] focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] transition-colors outline-none"
                                                >
                                                    <option value="" disabled>Month</option>
                                                    <option
                                                        v-for="m in months"
                                                        :key="m.value"
                                                        :value="m.value"
                                                    >
                                                        {{ m.label }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                >
                                                    {{ $t('common.day') }}
                                                </label>
                                                <select
                                                    v-model="birth.day"
                                                    required
                                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 text-sm focus:border-[#F02C56] focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] transition-colors outline-none"
                                                >
                                                    <option value="" disabled>Day</option>
                                                    <option v-for="d in days" :key="d" :value="d">
                                                        {{ d }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                >
                                                    {{ $t('common.year') }}
                                                </label>
                                                <select
                                                    v-model="birth.year"
                                                    required
                                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 text-sm focus:border-[#F02C56] focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] transition-colors outline-none"
                                                >
                                                    <option value="" disabled>Year</option>
                                                    <option v-for="y in years" :key="y" :value="y">
                                                        {{ y }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div
                                            v-if="ageError"
                                            class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-200"
                                        >
                                            {{ ageError }}
                                        </div>

                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $t('auth.yourBirthdateWontBePublic') }}
                                        </div>

                                        <div class="flex gap-2">
                                            <button
                                                v-if="appCaptcha.enabled"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900 dark:focus:ring-slate-800"
                                                @click="goToPreviousStep"
                                            >
                                                {{ $t('settings.back') }}
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="!birthdateFormatted || verifying"
                                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60"
                                                :style="primaryButtonStyle"
                                                @click="verifyAge"
                                            >
                                                <span
                                                    v-if="verifying"
                                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/70 border-t-transparent"
                                                ></span>
                                                <span>{{
                                                    verifying
                                                        ? $t('auth.verifyingDotDotDot')
                                                        : $t('common.continue')
                                                }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </transition>

                                <transition name="slide">
                                    <div v-if="currentStep === 3" class="space-y-4">
                                        <div>
                                            <p class="text-lg font-semibold">
                                                {{ $t('auth.createYourProfile') }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                {{
                                                    $t('auth.chooseYourDisplayNameUsernameAndEmail')
                                                }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                                            >
                                                {{ $t('profile.displayName') }}
                                            </label>
                                            <input
                                                v-model.trim="form.name"
                                                type="text"
                                                autocomplete="name"
                                                class="mt-1 w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition border-slate-200 text-slate-900 placeholder:text-slate-400 focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] focus:border-[rgba(240,44,86,0.55)] dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                placeholder="Your name"
                                            />
                                            <p
                                                v-if="errors.name"
                                                class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                                            >
                                                {{ errors.name }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                                            >
                                                {{ $t('common.username') }}
                                            </label>

                                            <div class="mt-1 flex items-center gap-2">
                                                <div
                                                    class="flex w-full items-center rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm transition border-slate-200 focus-within:ring-4 focus-within:ring-[rgba(240,44,86,0.18)] focus-within:border-[rgba(240,44,86,0.55)] dark:border-slate-800 dark:bg-slate-950"
                                                >
                                                    <span
                                                        class="select-none text-slate-400 dark:text-slate-500"
                                                        >@</span
                                                    >
                                                    <input
                                                        v-model.trim="form.username"
                                                        type="text"
                                                        autocomplete="username"
                                                        inputmode="text"
                                                        spellcheck="false"
                                                        autocapitalize="none"
                                                        @input="handleUsernameInput"
                                                        :maxlength="24"
                                                        class="ml-2 w-full bg-transparent text-slate-900 placeholder:text-slate-400 outline-none dark:text-slate-100 dark:placeholder:text-slate-500"
                                                        placeholder="yourUsername123"
                                                    />
                                                </div>

                                                <div class="min-w-[2.25rem] flex justify-center">
                                                    <span
                                                        v-if="usernameStatus === 'checking'"
                                                        class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-transparent dark:border-slate-600 dark:border-t-transparent"
                                                    ></span>

                                                    <svg
                                                        v-else-if="usernameStatus === 'available'"
                                                        viewBox="0 0 24 24"
                                                        class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path d="M20 6 9 17l-5-5" />
                                                    </svg>

                                                    <svg
                                                        v-else-if="
                                                            usernameStatus === 'taken' ||
                                                            usernameStatus === 'invalid'
                                                        "
                                                        viewBox="0 0 24 24"
                                                        class="h-5 w-5 text-rose-600 dark:text-rose-400"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path d="M18 6 6 18" />
                                                        <path d="M6 6l12 12" />
                                                    </svg>
                                                </div>
                                            </div>

                                            <p
                                                v-if="usernameHelp"
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{ usernameHelp }}
                                            </p>
                                            <p
                                                v-if="errors.username"
                                                class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                                            >
                                                {{ errors.username }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                                            >
                                                {{ $t('common.email') }}
                                            </label>
                                            <input
                                                v-model.trim="form.email"
                                                type="email"
                                                autocomplete="email"
                                                class="mt-1 w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition border-slate-200 text-slate-900 placeholder:text-slate-400 focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] focus:border-[rgba(240,44,86,0.55)] dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                placeholder="you@example.com"
                                            />
                                            <p
                                                v-if="errors.email"
                                                class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                                            >
                                                {{ errors.email }}
                                            </p>
                                        </div>

                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900 dark:focus:ring-slate-800"
                                                @click="goToPreviousStep"
                                            >
                                                {{ $t('settings.back') }}
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="!canProceedFromStep3"
                                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60"
                                                :style="primaryButtonStyle"
                                                @click="goToNextStep"
                                            >
                                                {{ $t('common.continue') }}
                                            </button>
                                        </div>
                                    </div>
                                </transition>

                                <transition name="slide">
                                    <div v-if="currentStep === 4" class="space-y-4">
                                        <div>
                                            <p class="text-lg font-semibold">
                                                {{ $t('auth.setYourPassword') }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                {{ $t('auth.chooseASecurePasswordForYourAccount') }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                                            >
                                                {{ $t('common.password') }}
                                            </label>
                                            <input
                                                v-model="form.password"
                                                type="password"
                                                autocomplete="new-password"
                                                class="mt-1 w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition border-slate-200 text-slate-900 placeholder:text-slate-400 focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] focus:border-[rgba(240,44,86,0.55)] dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                placeholder="••••••••"
                                            />
                                            <p
                                                v-if="errors.password"
                                                class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                                            >
                                                {{ errors.password }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                                            >
                                                {{ $t('common.confirmPassword') }}
                                            </label>
                                            <input
                                                v-model="form.password_confirmation"
                                                type="password"
                                                autocomplete="new-password"
                                                class="mt-1 w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition border-slate-200 text-slate-900 placeholder:text-slate-400 focus:ring-4 focus:ring-[rgba(240,44,86,0.18)] focus:border-[rgba(240,44,86,0.55)] dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                placeholder="••••••••"
                                            />
                                            <p
                                                v-if="passwordMismatch"
                                                class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                                            >
                                                {{ $t('auth.passwordsDoNotMatch') }}
                                            </p>
                                            <p
                                                v-if="errors.password_confirmation"
                                                class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                                            >
                                                {{ errors.password_confirmation }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="formError"
                                            class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-200"
                                        >
                                            {{ formError }}
                                        </div>

                                        <div>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400 text-center"
                                            >
                                                {{ $t('auth.byCreatingANewAccount') }}
                                                <a
                                                    href="/terms"
                                                    target="_blank"
                                                    class="font-medium text-blue-500"
                                                    >{{ $t('auth.termsOfService') }}</a>,
                                                <a
                                                    href="/privacy"
                                                    target="_blank"
                                                    class="font-medium text-blue-500"
                                                    >{{ $t('auth.privacyPolicy') }}</a
                                                >
                                                {{ $t('common.and') }}
                                                <a
                                                    href="/community-guidelines"
                                                    target="_blank"
                                                    class="font-medium text-blue-500"
                                                    >{{ $t('auth.communityGuidelines') }}</a>.
                                            </p>
                                        </div>

                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900 dark:focus:ring-slate-800"
                                                @click="goToPreviousStep"
                                            >
                                                {{ $t('settings.back') }}
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="!canSubmit || submitting"
                                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60"
                                                :style="primaryButtonStyle"
                                                @click="handleSubmit"
                                            >
                                                <span
                                                    v-if="submitting"
                                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/70 border-t-transparent"
                                                ></span>
                                                <span>{{
                                                    submitting
                                                        ? $t('auth.creatingAccountDotDotDot')
                                                        : $t('common.createAccount')
                                                }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </transition>

                                <transition name="slide">
                                    <div v-if="currentStep === 5" class="space-y-4">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="mt-0.5 h-10 w-10 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950 flex items-center justify-center"
                                            >
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    class="h-5 w-5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path d="M9 12l2 2 4-4" />
                                                    <path
                                                        d="M12 22s8-4 8-10V6l-8-4-8 4v6c0 6 8 10 8 10Z"
                                                    />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-base font-semibold">
                                                    {{ $t('auth.accountCreated') }}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                                >
                                                    {{
                                                        $t('auth.yourAccountIsReadyYouCanSignInNow')
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-4"
                                            :style="primaryButtonStyle"
                                            @click="goToLogin"
                                        >
                                            {{ $t('auth.continueToSignIn') }}
                                        </button>
                                    </div>
                                </transition>
                            </div>

                            <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('auth.needHelpAskTheAdminWhoSentYouThisInvite') }}
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </BlankLayout>
</template>

<script setup>
import Spinner from '@/components/Spinner.vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { computed, onMounted, onUnmounted, inject, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BlankLayout from '~/layouts/BlankLayout.vue'
import axios from '~/plugins/axios'
import { useI18n } from 'vue-i18n'
import AnimatedButton from '@/components/AnimatedButton.vue'

const authStore = inject('authStore')
const appCaptcha = inject('appCaptcha')
const route = useRoute()
const router = useRouter()
const axiosInstance = axios.getAxiosInstance()
const { t } = useI18n()

const { id } = route.params

const currentStep = ref(0)
const inviteKey = ref(id)
const sessionToken = ref('')
const inviteError = ref('')
const inviteMessage = ref('')
const invitedBy = ref(null)
const verifying = ref(false)
const submitting = ref(false)
const formError = ref('')
const ageError = ref('')
const hasStartedForm = ref(false)
const captchaToken = ref('')
const turnstileRef = ref(null)
const hcaptchaRef = ref(null)
const birth = ref({ day: '', month: '', year: '' })
const minimumAge = ref(13)

const form = ref({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: ''
})

const errors = ref({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: ''
})

const usernameStatus = ref('idle')
const USERNAME_ALLOWED = /^[a-zA-Z0-9_.-]+$/

let usernameTimer = null
let usernameAbort = null

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' }
]

const years = computed(() => {
    const now = new Date().getFullYear()
    const span = 120
    return Array.from({ length: span }, (_, i) => now - i)
})

function daysInMonth(y, m) {
    if (!y || !m) return 31
    return new Date(y, m, 0).getDate()
}

const days = computed(() => {
    const y = Number(birth.value.year) || 2000
    const m = Number(birth.value.month) || 1
    const max = daysInMonth(y, m)
    return Array.from({ length: max }, (_, i) => i + 1)
})

const birthdateFormatted = computed(() => {
    const y = birth.value.year
    const m = birth.value.month
    const d = birth.value.day
    if (!y || !m || !d) return ''
    const mm = String(m).padStart(2, '0')
    const dd = String(d).padStart(2, '0')
    return `${y}-${mm}-${dd}`
})

function validateUsername(value) {
    const v = (value ?? '').toString()

    if (!v.length) return { ok: false, message: 'Username is required.' }

    if (!USERNAME_ALLOWED.test(v)) {
        return {
            ok: false,
            message:
                'Username can only contain letters, numbers, underscores, hyphens, and periods.'
        }
    }

    if (v.startsWith('.')) return { ok: false, message: 'Username cannot start with a period.' }
    if (v.startsWith('_'))
        return { ok: false, message: 'Username cannot start with an underscore.' }
    if (v.startsWith('-')) return { ok: false, message: 'Username cannot start with a hyphen.' }

    if (v.endsWith('.')) return { ok: false, message: 'Username cannot end with a period.' }
    if (v.endsWith('-')) return { ok: false, message: 'Username cannot end with a hyphen.' }

    if (v.includes('--'))
        return { ok: false, message: 'Username cannot contain consecutive hyphens.' }
    if (v.includes('..'))
        return { ok: false, message: 'Username cannot contain consecutive periods.' }

    return { ok: true, message: null }
}

const usernameNormalized = computed(() => (form.value.username || '').trim().toLowerCase())

const usernameValidFormat = computed(() => validateUsername(usernameNormalized.value))

const passwordMismatch = computed(() => {
    if (!form.value.password || !form.value.password_confirmation) return false
    return form.value.password !== form.value.password_confirmation
})

const usernameHelp = computed(() => {
    if (!form.value.username) return '3–24 chars, lowercase letters, numbers, underscore.'
    if (!usernameValidFormat.value) return 'Use 3–24 chars: lowercase letters, numbers, underscore.'
    if (usernameStatus.value === 'checking') return 'Checking availability…'
    if (usernameStatus.value === 'available') return 'Username is available.'
    if (usernameStatus.value === 'taken') return 'That username is already taken.'
    if (usernameStatus.value === 'invalid') return 'Username is not valid.'
    return '3–24 chars, lowercase letters, numbers, underscore.'
})

const canProceedFromStep3 = computed(() => {
    if (!form.value.name || !form.value.username || !form.value.email) return false
    if (!usernameValidFormat.value) return false
    if (
        usernameStatus.value === 'taken' ||
        usernameStatus.value === 'checking' ||
        usernameStatus.value === 'invalid'
    )
        return false
    return true
})

const canSubmit = computed(() => {
    if (!form.value.password || !form.value.password_confirmation) return false
    if (passwordMismatch.value) return false
    return true
})

const primaryButtonStyle = computed(() => ({
    backgroundColor: '#F02C56',
    boxShadow: '0 10px 25px rgba(240,44,86,0.25)'
}))

function goHome() {
    router.push({ path: '/' })
}

function goToLogin() {
    hasStartedForm.value = false
    router.push({ path: '/login' })
}

function goToNextStep() {
    if (currentStep.value < 5) {
        currentStep.value++
        hasStartedForm.value = true
    }
}

function goToPreviousStep() {
    if (currentStep.value > 1) {
        currentStep.value--
    }
}

function resetCaptcha() {
    captchaToken.value = ''
    if (appCaptcha.enabled) {
        if (appCaptcha.provider === 'turnstile' && turnstileRef.value) {
            turnstileRef.value.reset()
        } else if (appCaptcha.provider === 'hcaptcha' && hcaptchaRef.value) {
            hcaptchaRef.value.reset()
        }
    }
}

const onCaptchaSuccess = (token) => {
    captchaToken.value = token
}

const onCaptchaError = (error) => {
    console.error('Captcha error:', error)
    captchaToken.value = ''
}

const onCaptchaExpired = () => {
    captchaToken.value = ''
    resetCaptcha()
}

async function retryVerify() {
    resetCaptcha()
    currentStep.value = appCaptcha.enabled ? 1 : 2
}

async function verifyInvite() {
    verifying.value = true
    inviteError.value = ''

    try {
        const res = await axiosInstance.post('/api/v1/invite/verify', {
            token: inviteKey.value,
            captcha_token: captchaToken.value || '',
            captcha_type: appCaptcha.provider || ''
        })

        const data = res.data

        if (res.status !== 200 || data?.valid === false) {
            currentStep.value = -1
            inviteError.value = data?.message || t('auth.inviteIsNotValid')
            return
        }

        sessionToken.value = data.session_token
        inviteMessage.value = data?.message || ''
        invitedBy.value = data?.invited_by || null

        currentStep.value = 2
        hasStartedForm.value = true
    } catch (e) {
        currentStep.value = -1
        inviteError.value = e?.response?.data?.message || 'Unable to verify invite right now.'
    } finally {
        verifying.value = false
    }
}

async function verifyAge() {
    if (!birthdateFormatted.value) return

    verifying.value = true
    ageError.value = ''

    try {
        const res = await axiosInstance.post('/api/v1/invite/verify-age', {
            birthdate: birthdateFormatted.value,
            minAge: minimumAge.value,
            captcha_token: captchaToken.value || '',
            captcha_type: appCaptcha.provider || '',
            session_token: sessionToken.value
        })

        const data = res.data

        if (res.status !== 200 || !data?.allowed) {
            ageError.value = data?.message || 'You must meet the minimum age requirement.'
            return
        }

        minimumAge.value = data?.minAge || 13

        currentStep.value = 3
    } catch (e) {
        ageError.value = e?.response?.data?.message || 'Unable to verify age right now.'
    } finally {
        verifying.value = false
    }
}

async function checkUsernameAvailability(username) {
    if (!username) {
        usernameStatus.value = 'idle'
        return
    }

    const normalized = username.trim().toLowerCase()
    if (!usernameValidFormat.value.ok) {
        usernameStatus.value = 'invalid'
        return
    }

    try {
        usernameStatus.value = 'checking'

        const res = await axiosInstance.post(`/api/v1/invite/check-username`, {
            username: normalized,
            captcha_token: captchaToken.value || '',
            captcha_type: appCaptcha.provider || '',
            session_token: sessionToken.value
        })

        const data = res.data

        if (res.status !== 200) {
            usernameStatus.value = 'invalid'
            return
        }

        usernameStatus.value = data?.valid ? 'available' : 'taken'
    } catch (e) {
        usernameStatus.value = 'invalid'
    }
}

function handleUsernameInput() {
    if (errors.value.username) {
        errors.value.username = ''
    }

    if (!form.value.username) {
        usernameStatus.value = 'idle'
        if (usernameAbort) usernameAbort.abort()
        if (usernameTimer) clearTimeout(usernameTimer)
        return
    }

    if (!usernameValidFormat.value) {
        usernameStatus.value = 'invalid'
        if (usernameAbort) usernameAbort.abort()
        if (usernameTimer) clearTimeout(usernameTimer)
        return
    }

    if (usernameTimer) clearTimeout(usernameTimer)
    usernameTimer = setTimeout(async () => {
        if (usernameAbort) usernameAbort.abort()
        usernameAbort = new AbortController()

        await checkUsernameAvailability(form.value.username)
    }, 450)
}

function resetErrors() {
    formError.value = ''
    errors.value = {
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: ''
    }
}

async function handleSubmit() {
    resetErrors()

    if (!form.value.name) errors.value.name = 'Display name is required.'
    if (!form.value.username) errors.value.username = 'Username is required.'
    if (form.value.username && !usernameValidFormat.value) {
        errors.value.username = 'Username format is not valid.'
    }
    if (usernameStatus.value === 'taken') {
        errors.value.username = 'That username is already taken.'
    }
    if (!form.value.email) errors.value.email = 'Email is required.'
    if (!form.value.password) errors.value.password = 'Password is required.'
    if (passwordMismatch.value) {
        errors.value.password_confirmation = 'Passwords do not match.'
    }

    const hasFieldErrors = Object.values(errors.value).some(Boolean)
    if (hasFieldErrors) return false

    submitting.value = true
    try {
        const payload = {
            invite_key: inviteKey.value,
            birthdate: birthdateFormatted.value,
            name: form.value.name,
            username: usernameNormalized.value,
            email: form.value.email,
            password: form.value.password,
            password_confirmation: form.value.password_confirmation,
            captcha_token: captchaToken.value || '',
            captcha_type: appCaptcha.provider || '',
            session_token: sessionToken.value
        }

        const res = await axiosInstance.post('/api/v1/invite/register', payload)

        const data = res.data

        if (res.status !== 200) {
            if (res.status === 422 && data?.errors) {
                const fieldErrors = data.errors
                errors.value.name = fieldErrors.name?.[0] || ''
                errors.value.username = fieldErrors.username?.[0] || ''
                errors.value.email = fieldErrors.email?.[0] || ''
                errors.value.password = fieldErrors.password?.[0] || ''
                errors.value.password_confirmation = fieldErrors.password_confirmation?.[0] || ''
                formError.value = data?.message || 'Please check the form for errors.'
                return false
            }

            formError.value =
                data?.message || 'Unable to create account right now. Please try again.'
            return false
        }

        currentStep.value = 5
        hasStartedForm.value = false
        return true
    } catch (e) {
        if (e.response?.status === 422) {
            const fieldErrors = e?.response?.data?.errors
            errors.value.name = fieldErrors?.name?.[0] || ''
            errors.value.username = fieldErrors?.username?.[0] || ''
            errors.value.email = fieldErrors?.email?.[0] || ''
            errors.value.password = fieldErrors?.password?.[0] || ''
            errors.value.password_confirmation = fieldErrors?.password_confirmation?.[0] || ''
            formError.value = e.response?.data?.message || 'Please check the form for errors.'
        } else {
            formError.value =
                e?.response?.data?.message ||
                'Unable to create account right now. Please try again.'
        }
        return false
    } finally {
        submitting.value = false
    }
}

function handleBeforeUnload(e) {
    if (hasStartedForm.value && currentStep.value > 0 && currentStep.value < 5) {
        e.preventDefault()
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?'
        return e.returnValue
    }
}

onMounted(async () => {
    window.addEventListener('beforeunload', handleBeforeUnload)

    if (appCaptcha.enabled) {
        await new Promise((resolve) => setTimeout(resolve, 1000))
        currentStep.value = 1
    } else {
        await new Promise((resolve) => setTimeout(resolve, 1000))
        verifyInvite()
    }
})

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    if (usernameTimer) clearTimeout(usernameTimer)
    if (usernameAbort) usernameAbort.abort()
})
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease;
}

.slide-enter-from {
    opacity: 0;
    transform: translateX(20px);
}

.slide-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}
</style>
