<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="authStore.isOpen" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="fixed inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm"></div>

                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="authStore.isOpen"
                            class="relative w-full max-w-md transform rounded-2xl bg-white dark:bg-gray-900 px-8 pb-8 pt-12 shadow-2xl transition-all"
                            @click.stop
                        >
                            <button
                                @click="closeModal"
                                class="absolute right-4 top-4 rounded-full p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>

                            <div class="text-center mb-8">
                                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ getTitle() }}
                                </h2>
                                <p class="mt-2 text-gray-600 dark:text-gray-400">
                                    {{ getSubtitle() }}
                                </p>
                            </div>

                            <Transition
                                enter-active-class="duration-300 ease-out"
                                enter-from-class="opacity-0 transform -translate-y-2"
                                enter-to-class="opacity-100 transform translate-y-0"
                                leave-active-class="duration-200 ease-in"
                                leave-from-class="opacity-100 transform translate-y-0"
                                leave-to-class="opacity-0 transform -translate-y-2"
                            >
                                <div
                                    v-if="error"
                                    class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4"
                                >
                                    <p
                                        class="text-sm text-red-700 dark:text-red-400"
                                        v-html="error"
                                    ></p>
                                </div>
                            </Transition>

                            <Transition
                                enter-active-class="duration-300 ease-out"
                                enter-from-class="opacity-0 transform -translate-y-2"
                                enter-to-class="opacity-100 transform translate-y-0"
                                leave-active-class="duration-200 ease-in"
                                leave-from-class="opacity-100 transform translate-y-0"
                                leave-to-class="opacity-0 transform -translate-y-2"
                            >
                                <div
                                    v-if="success"
                                    class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4"
                                >
                                    <p class="text-sm text-green-700 dark:text-green-400">
                                        {{ success }}
                                    </p>
                                </div>
                            </Transition>

                            <form
                                v-if="currentMode === 'login'"
                                @submit.prevent="handleLogin"
                                class="space-y-6"
                            >
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        {{ t('common.email') }}
                                    </label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        :placeholder="t('common.enterYourEmail')"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        {{ t('common.password') }}
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model="form.password"
                                            :type="showPassword ? 'text' : 'password'"
                                            required
                                            class="w-full px-4 py-3 pr-12 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            :placeholder="t('common.enterYourPassword')"
                                        />
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                        >
                                            <EyeIcon v-if="showPassword" class="h-5 w-5" />
                                            <EyeSlashIcon v-else class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.remember"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-800"
                                        />
                                        <span
                                            class="ml-2 text-sm text-gray-700 dark:text-gray-300"
                                            >{{ t('common.rememberMe') }}</span
                                        >
                                    </label>
                                    <button
                                        type="button"
                                        @click="currentMode = 'password-reset'"
                                        class="text-sm font-medium text-[#F02C56] hover:text-[#D7284A] dark:hover:text-[#D7284A] transition-colors cursor-pointer"
                                    >
                                        {{ t('common.forgotPassword') }}
                                    </button>
                                </div>

                                <template v-if="appCaptcha.enabled">
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
                                    <template v-else-if="appCaptcha.provider === 'hcaptcha'">
                                        <HCaptcha
                                            ref="hcaptchaRef"
                                            :sitekey="appCaptcha.siteKey"
                                            theme="light"
                                            @success="onCaptchaSuccess"
                                            @error="onCaptchaError"
                                            @expired="onCaptchaExpired"
                                        />
                                    </template>
                                </template>

                                <AnimatedButton
                                    type="submit"
                                    :loading="loading"
                                    class="w-full"
                                    variant="primary"
                                >
                                    {{ t('common.signIn') }}
                                </AnimatedButton>
                            </form>

                            <div v-if="currentMode === 'register'">
                                <div class="mb-8">
                                    <div
                                        class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 mb-2"
                                    >
                                        <span>Step {{ registrationStep }} of 5</span>
                                        <span
                                            >{{ Math.round(((registrationStep - 1) / 5) * 100) }}%
                                            complete</span
                                        >
                                    </div>
                                    <div
                                        class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2"
                                    >
                                        <div
                                            class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all duration-500 ease-out"
                                            :style="{
                                                width: `${((registrationStep - 1) / 4) * 100}%`
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <form
                                    v-if="registrationStep === 1"
                                    @submit.prevent="handleSendCode"
                                    class="space-y-6"
                                >
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{ t('common.email') }}
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            required
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            :placeholder="t('common.enterYourEmail')"
                                        />
                                    </div>

                                    <template v-if="appCaptcha.enabled">
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
                                        <template v-else-if="appCaptcha.provider === 'hcaptcha'">
                                            <HCaptcha
                                                ref="hcaptchaRef"
                                                :sitekey="appCaptcha.siteKey"
                                                theme="light"
                                                @success="onCaptchaSuccess"
                                                @error="onCaptchaError"
                                                @expired="onCaptchaExpired"
                                            />
                                        </template>
                                    </template>

                                    <div>
                                        <p class="text-xs text-gray-500 text-center">
                                            {{ $t('auth.byCreatingANewAccount') }}
                                            <a
                                                href="/terms"
                                                target="_blank"
                                                class="font-medium text-blue-500"
                                                >{{ $t('auth.termsOfService') }}</a
                                            >,
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
                                                >{{ $t('auth.communityGuidelines') }}</a
                                            >.
                                        </p>
                                    </div>

                                    <AnimatedButton
                                        type="submit"
                                        :loading="loading"
                                        class="w-full"
                                        variant="primary"
                                    >
                                        {{ t('common.continue') }}
                                    </AnimatedButton>

                                    <button
                                        v-if="error"
                                        type="button"
                                        @click="handleOtpAlreadyHaveCode"
                                        class="mt-5 w-full text-center text-sm font-bold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 transition-colors cursor-pointer"
                                    >
                                        {{ t('common.iAlreadyHaveTheCode') }}
                                    </button>
                                </form>

                                <form
                                    v-if="registrationStep === 2"
                                    @submit.prevent="handleVerifyCode"
                                    class="space-y-6"
                                >
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{ t('common.verificationCode') }}
                                        </label>
                                        <input
                                            v-model="form.verificationCode"
                                            type="text"
                                            required
                                            maxlength="6"
                                            class="w-full px-4 py-3 text-center text-2xl tracking-widest rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            placeholder="000000"
                                        />
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            {{ t('common.enterTheSixDigitCodeSentTo') }}
                                            {{ registrationData.email }}
                                        </p>
                                    </div>

                                    <div class="flex justify-between items-center">
                                        <button
                                            type="button"
                                            @click="handleResendCode"
                                            :disabled="resendCooldown > 0 || loading"
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 disabled:text-gray-400 dark:disabled:text-gray-600 transition-colors"
                                        >
                                            {{
                                                resendCooldown > 0
                                                    ? `${t('common.resendIn')} ${resendCooldown}s`
                                                    : t('common.resendCode')
                                            }}
                                        </button>
                                    </div>

                                    <div class="flex gap-3">
                                        <AnimatedButton
                                            type="button"
                                            @click="goBackStep"
                                            variant="outline"
                                            class="flex-1"
                                        >
                                            {{ t('settings.back') }}
                                        </AnimatedButton>
                                        <AnimatedButton
                                            type="submit"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            {{ t('common.verify') }}
                                        </AnimatedButton>
                                    </div>
                                </form>

                                <form
                                    v-if="registrationStep === 3"
                                    @submit.prevent="handleBirthdateNext"
                                    class="space-y-6"
                                >
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{ t('common.birthdate') || 'Birth date' }}
                                        </label>

                                        <div class="grid grid-cols-3 gap-3">
                                            <div>
                                                <select
                                                    v-model="birth.month"
                                                    required
                                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                                >
                                                    <option value="" disabled>
                                                        {{ t('common.month') || 'Month' }}
                                                    </option>
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
                                                <select
                                                    v-model="birth.day"
                                                    required
                                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                                >
                                                    <option value="" disabled>
                                                        {{ t('common.day') || 'Day' }}
                                                    </option>
                                                    <option v-for="d in days" :key="d" :value="d">
                                                        {{ d }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <select
                                                    v-model="birth.year"
                                                    required
                                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                                >
                                                    <option value="" disabled>
                                                        {{ t('common.year') || 'Year' }}
                                                    </option>
                                                    <option v-for="y in years" :key="y" :value="y">
                                                        {{ y }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{
                                                t('common.weUseThisToVerifyAge') ||
                                                'We use this to verify your age. It won’t be public.'
                                            }}
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <AnimatedButton
                                            type="button"
                                            @click="goBackStep"
                                            variant="outline"
                                            class="flex-1"
                                        >
                                            {{ t('settings.back') }}
                                        </AnimatedButton>
                                        <AnimatedButton
                                            type="submit"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            {{ t('common.continue') }}
                                        </AnimatedButton>
                                    </div>
                                </form>

                                <form
                                    v-if="registrationStep === 4"
                                    @submit.prevent="handleSetProfile"
                                    class="space-y-6"
                                >
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{ t('common.username') }}
                                        </label>
                                        <input
                                            v-model="form.username"
                                            type="text"
                                            required
                                            maxlength="24"
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            :placeholder="t('common.chooseAUsername')"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{ t('common.password') }}
                                        </label>
                                        <div class="relative">
                                            <input
                                                v-model="form.password"
                                                :type="showPassword ? 'text' : 'password'"
                                                required
                                                class="w-full px-4 py-3 pr-12 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                                :placeholder="t('common.createAStrongPassword')"
                                            />
                                            <button
                                                type="button"
                                                @click="showPassword = !showPassword"
                                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                            >
                                                <svg
                                                    v-if="showPassword"
                                                    class="h-5 w-5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.17 8.17m1.708 1.708a3 3 0 004.243 4.243m2.12-2.12L18.536 8.17m-1.415 1.708a3 3 0 00-4.243-4.243m-2.12 2.12L8.17 8.17"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else
                                                    class="h-5 w-5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                    />
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                    />
                                                </svg>
                                            </button>
                                        </div>

                                        <div v-if="form.password" class="mt-2">
                                            <div
                                                class="flex items-center justify-between text-sm mb-1"
                                            >
                                                <span class="text-gray-600 dark:text-gray-400">{{
                                                    t('common.passwordStrength')
                                                }}</span>
                                                <span
                                                    :class="`text-${passwordStrength.color}-600 dark:text-${passwordStrength.color}-400 font-medium`"
                                                >
                                                    {{ passwordStrength.text }}
                                                </span>
                                            </div>
                                            <div
                                                class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1"
                                            >
                                                <div
                                                    class="h-1 rounded-full transition-all duration-300"
                                                    :class="`bg-${passwordStrength.color}-500`"
                                                    :style="{
                                                        width: `${(passwordStrength.score / 5) * 100}%`
                                                    }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{ t('common.confirmPassword') }}
                                        </label>
                                        <input
                                            v-model="form.confirmPassword"
                                            type="password"
                                            required
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            :placeholder="t('settings.confirmYourPassword')"
                                        />
                                    </div>

                                    <div class="flex gap-3">
                                        <AnimatedButton
                                            type="button"
                                            @click="goBackStep"
                                            variant="outline"
                                            class="flex-1"
                                        >
                                            {{ t('settings.back') }}
                                        </AnimatedButton>
                                        <AnimatedButton
                                            type="submit"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            {{ t('common.continue') }}
                                        </AnimatedButton>
                                    </div>
                                </form>

                                <div v-if="registrationStep === 5" class="space-y-6">
                                    <div v-if="form.selectedAvatarUrl" class="text-center">
                                        <div
                                            class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 text-white text-2xl font-bold mb-4"
                                        >
                                            <img
                                                :src="form.selectedAvatarUrl"
                                                class="w-20 h-20 rounded-full"
                                                @error="
                                                    $event.target.src =
                                                        '/storage/avatars/default.jpg'
                                                "
                                            />
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                                        >
                                            {{ t('common.uploadYourAvatar') }}
                                        </label>
                                        <input
                                            type="file"
                                            accept="image/jpg,image/png"
                                            @change="handleAvatarUpload"
                                            class="hidden"
                                            ref="avatarInput"
                                        />
                                        <button
                                            type="button"
                                            @click="$refs.avatarInput.click()"
                                            class="w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-blue-500 dark:hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                        >
                                            {{ t('common.clickToUploadImage') }}
                                        </button>
                                    </div>

                                    <div class="flex gap-3">
                                        <AnimatedButton
                                            @click="handleCompleteRegistration"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            {{ t('common.finishSignUp') }}
                                        </AnimatedButton>
                                    </div>
                                </div>
                            </div>

                            <form
                                v-if="currentMode === 'password-reset'"
                                @submit.prevent="handlePasswordReset"
                                class="space-y-6"
                            >
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        {{ t('common.email') }}
                                    </label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        :placeholder="t('common.enterYourEmail')"
                                    />
                                </div>
                                <template v-if="appCaptcha.enabled">
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
                                    <template v-else-if="appCaptcha.provider === 'hcaptcha'">
                                        <HCaptcha
                                            ref="hcaptchaRef"
                                            :sitekey="appCaptcha.siteKey"
                                            theme="light"
                                            @success="onCaptchaSuccess"
                                            @error="onCaptchaError"
                                            @expired="onCaptchaExpired"
                                        />
                                    </template>
                                </template>
                                <AnimatedButton
                                    type="submit"
                                    :loading="loading"
                                    class="w-full"
                                    variant="primary"
                                >
                                    {{ t('common.sendResetLink') }}
                                </AnimatedButton>
                                <button
                                    type="button"
                                    @click="currentMode = 'login'"
                                    class="mt-5 w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                >
                                    {{ t('common.backToSignIn') }}
                                </button>
                            </form>

                            <form
                                v-if="currentMode === 'two-factor'"
                                @submit.prevent="handleTwoFactor"
                                class="space-y-6"
                            >
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        {{ t('common.authenticationCode') }}
                                    </label>
                                    <TwoFactorInput
                                        v-model="form.twoFactorCode"
                                        :auto-submit="true"
                                        :has-error="hasOtpError"
                                        :error-message="otpErrorMessage"
                                        @complete="handleCodeComplete"
                                    />
                                </div>
                                <AnimatedButton
                                    type="submit"
                                    :loading="loading"
                                    class="w-full"
                                    variant="primary"
                                >
                                    {{ t('common.verifyCode') }}
                                </AnimatedButton>
                                <button
                                    v-if="otpAttempts > 1"
                                    type="button"
                                    @click="handleOtpBackup"
                                    class="mt-5 w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                >
                                    {{ t('common.useADifferentMethod') }}
                                </button>
                            </form>

                            <form
                                v-if="currentMode === 'two-factor-backup'"
                                @submit.prevent="handleEmailTwoFactor"
                                class="space-y-6"
                            >
                                <div class="text-center mb-6">
                                    <div
                                        class="inline-flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full mb-4"
                                    >
                                        <svg
                                            class="w-6 h-6 text-blue-600 dark:text-blue-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                            ></path>
                                        </svg>
                                    </div>
                                    <h3
                                        class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2"
                                    >
                                        {{ t('common.emailVerification') }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                        {{ t('common.wellSendAVerificationCodeTo') }}:
                                    </p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ form.email || t('common.yourRegisteredEmailAddress') }}
                                    </p>
                                </div>

                                <div v-if="emailCodeSent">
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        {{ t('common.verifyEmailCode') }}
                                    </label>
                                    <TwoFactorInput
                                        v-model="form.emailCode"
                                        :auto-submit="true"
                                        :has-error="hasEmailCodeError"
                                        :error-message="emailCodeErrorMessage"
                                        @complete="handleEmailCodeComplete"
                                    />
                                </div>

                                <div class="flex gap-3">
                                    <AnimatedButton
                                        v-if="emailCodeSent"
                                        type="submit"
                                        :loading="loading"
                                        class="flex-1"
                                        variant="primary"
                                    >
                                        Verify Email Code
                                    </AnimatedButton>

                                    <button
                                        v-if="!emailCodeSent"
                                        type="button"
                                        @click="sendEmailCode"
                                        :disabled="emailCodeLoading"
                                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {{
                                            emailCodeLoading
                                                ? t('settings.sendingDotDotDot')
                                                : t('common.sendCode')
                                        }}
                                    </button>
                                </div>

                                <div v-if="emailCodeSent" class="text-center">
                                    <p class="text-sm text-green-600 dark:text-green-400 mb-2">
                                        ✓
                                        {{ t('common.verificationCodeSentToYourEmail') }}
                                    </p>
                                    <button
                                        type="button"
                                        @click="resendEmailCode"
                                        :disabled="resendCooldown > 0"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                    >
                                        {{
                                            resendCooldown > 0
                                                ? `${t('common.resendIn')} ${resendCooldown}s`
                                                : t('common.resendCode')
                                        }}
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    @click="currentMode = 'two-factor'"
                                    class="w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                >
                                    ← {{ t('common.backToAuthenticatorApp') }}
                                </button>
                            </form>

                            <div
                                v-if="
                                    (openRegistration && currentMode === 'login') ||
                                    (currentMode === 'register' && registrationStep === 1)
                                "
                                class="mt-8 text-center"
                            >
                                <p class="text-gray-600 dark:text-gray-400">
                                    {{
                                        currentMode === 'login'
                                            ? t('common.dontHaveAnAccount')
                                            : t('common.alreadyHaveAnAccount')
                                    }}
                                    <button
                                        @click="toggleMode"
                                        class="ml-1 text-[#F02C56] hover:text-[#D7284A] dark:hover:text-[#D7284A] font-medium transition-colors cursor-pointer"
                                    >
                                        {{
                                            currentMode === 'login'
                                                ? t('common.signUp')
                                                : t('common.signIn')
                                        }}
                                    </button>
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, inject } from 'vue'
import { useAuthStore } from '~/stores/auth'
import CloudflareTurnstile from '@/components/Captcha/CloudflareTurnstile.vue'
import HCaptcha from '@/components/Captcha/HCaptcha.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    mode: {
        type: String,
        default: 'login',
        validator: (value) => ['login', 'register', 'password-reset', 'two-factor'].includes(value)
    }
})

import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const axios = inject('axios')
const appConfig = inject('appConfig')
const appCaptcha = inject('appCaptcha')
const { t } = useI18n()

const currentMode = ref(props.mode)
const loading = ref(false)
const error = ref('')
const otpAttempts = ref(0)
const hasOtpError = ref(false)
const otpErrorMessage = ref('')
const success = ref('')
const showPassword = ref(false)
const minimumAge = ref(13)

const registrationStep = ref(1)
const resendCooldown = ref(0)
const resendTimer = ref(null)
const emailCodeSent = ref(false)
const emailCodeLoading = ref(false)
const hasEmailCodeError = ref(false)
const emailCodeErrorMessage = ref('')
const errorPreventsRegistration = ref(false)
const captchaType = ref(appCaptcha.provider)
const captchaToken = ref('')
const turnstileRef = ref(null)
const hcaptchaRef = ref(null)
const birth = ref({ day: '', month: '', year: '' })
const registrationBirthdate = ref('')

const months = [
    { value: 1, label: t('common.months.jan') || 'January' },
    { value: 2, label: t('common.months.feb') || 'February' },
    { value: 3, label: t('common.months.mar') || 'March' },
    { value: 4, label: t('common.months.apr') || 'April' },
    { value: 5, label: t('common.months.may') || 'May' },
    { value: 6, label: t('common.months.jun') || 'June' },
    { value: 7, label: t('common.months.jul') || 'July' },
    { value: 8, label: t('common.months.aug') || 'August' },
    { value: 9, label: t('common.months.sep') || 'September' },
    { value: 10, label: t('common.months.oct') || 'October' },
    { value: 11, label: t('common.months.nov') || 'November' },
    { value: 12, label: t('common.months.dec') || 'December' }
]

const onCaptchaSuccess = (token) => {
    captchaToken.value = token
    form.value.captcha_token = token
    form.value.captcha_type = captchaType.value
}

const onCaptchaError = (error) => {
    console.error('Captcha error:', error)
    captchaToken.value = ''
    form.value.captcha_token = ''
    if (appCaptcha.provider === 'turnstile' && turnstileRef.value) {
        turnstileRef.value.reset()
    } else if (appCaptcha.provider === 'hcaptcha' && hcaptchaRef.value) {
        hcaptchaRef.value.reset()
    }
}

const onCaptchaExpired = () => {
    captchaToken.value = ''
    form.value.captcha_token = ''

    if (appCaptcha.provider === 'turnstile' && turnstileRef.value) {
        turnstileRef.value.reset()
    } else if (appCaptcha.provider === 'hcaptcha' && hcaptchaRef.value) {
        hcaptchaRef.value.reset()
    }
}

const form = ref({
    email: '',
    emailCode: '',
    password: '',
    confirmPassword: '',
    username: '',
    firstName: '',
    lastName: '',
    remember: false,
    acceptTerms: false,
    twoFactorCode: '',
    verificationCode: '',
    avatar: null,
    selectedAvatarUrl: '',
    birthdate: ''
})

const registrationData = ref({
    email: '',
    username: '',
    password: '',
    avatar: null,
    avatarUrl: ''
})

const openRegistration = computed(() => {
    return appConfig.registration
})

const getTitle = () => {
    switch (currentMode.value) {
        case 'login':
            return t('common.welcomeBack')
        case 'register':
            switch (registrationStep.value) {
                case 1:
                    return t('common.createAccount')
                case 2:
                    return t('common.verifyYourEmail')
                case 3:
                    return t('common.confirmYourBirthdate')
                case 4:
                    return t('common.setUpYourProfile')
                case 5:
                    return t('common.chooseYourAvatar')
                default:
                    return t('common.createAccount')
            }
        case 'password-reset':
            return t('common.resetPassword')
        case 'two-factor':
            return t('common.verifyTwoFactor')
        case 'two-factor-backup':
            return t('common.verifyAnotherWay')
        default:
            return t('common.welcome')
    }
}

const getSubtitle = () => {
    switch (currentMode.value) {
        case 'login':
            return t('common.signIntoYourAccountToContinue')
        case 'register':
            switch (registrationStep.value) {
                case 1:
                    return t('common.enterYourEmailToGetStarted')
                case 2:
                    return t('common.weSentAVerificationCodeToYourEmail')
                case 3:
                    return t('common.weNeedYourBirthdateToVerifyAge')
                case 4:
                    return t('common.chooseAUsernameAndSecurePassword')
                case 5:
                    return t('common.uploadAnAvatarToPersonalizeYourProfile')
                default:
                    return t('common.createANewAccountToGetStarted')
            }
        case 'password-reset':
            return t('common.wellSendYouALinkToResetYourPassword')
        case 'two-factor':
            return ''
        case 'two-factor-backup':
            return ''
        default:
            return ''
    }
}

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

function isOldEnough(ymd, minYears) {
    const [y, m, d] = ymd.split('-').map(Number)
    const today = new Date()
    let age = today.getFullYear() - y
    const mdiff = today.getMonth() + 1 - m
    if (mdiff < 0 || (mdiff === 0 && today.getDate() < d)) age--
    return age >= minYears
}

const passwordStrength = computed(() => {
    const password = form.value.password
    if (!password) return { score: 0, text: '', color: '' }

    let score = 0
    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /\d/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    }

    score = Object.values(checks).filter(Boolean).length

    if (score <= 2) return { score, text: 'Weak', color: 'red' }
    if (score <= 3) return { score, text: 'Fair', color: 'yellow' }
    if (score <= 4) return { score, text: 'Good', color: 'blue' }
    return { score, text: 'Strong', color: 'green' }
})

async function verifyAgeApi(ymd) {
    try {
        const res = await axios.post('/api/v1/auth/register/verify-age', {
            birthdate: ymd,
            minAge: minimumAge.value
        })
        if (typeof res?.data?.data?.allowed === 'boolean') {
            minimumAge.value = res.data.data.minAge
            await nextTick()
            return res.data.data.allowed
        }
        return isOldEnough(ymd, minimumAge.value)
    } catch (e) {
        return isOldEnough(ymd, minimumAge.value)
    }
}

const closeModal = () => {
    authStore.closeAuthModal()
    clearForm()
    clearMessages()
    registrationStep.value = 1
}

const clearForm = () => {
    form.value = {
        email: '',
        password: '',
        confirmPassword: '',
        username: '',
        firstName: '',
        lastName: '',
        remember: false,
        acceptTerms: false,
        twoFactorCode: '',
        verificationCode: '',
        avatar: null,
        selectedAvatarUrl: '',
        birthdate: ''
    }
    birth.value = { day: '', month: '', year: '' }
    registrationBirthdate.value = ''
}

const clearMessages = () => {
    error.value = ''
    success.value = ''
}

const toggleMode = () => {
    currentMode.value = currentMode.value === 'login' ? 'register' : 'login'
    registrationStep.value = 1
    clearMessages()
}

const setError = (message) => {
    error.value = message
    success.value = ''
}

const setSuccess = (message) => {
    success.value = message
    error.value = ''
}

const startResendCooldown = () => {
    resendCooldown.value = 60
    resendTimer.value = setInterval(() => {
        resendCooldown.value--
        if (resendCooldown.value <= 0) {
            clearInterval(resendTimer.value)
        }
    }, 1000)
}

const handleOtpAlreadyHaveCode = async () => {
    clearMessages()
    registrationData.value.email = form.value.email
    registrationStep.value = 2
}

const handleSendCode = async () => {
    clearMessages()
    loading.value = true

    try {
        const data = {
            email: form.value.email,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token
        }
        await authStore.registerEmailVerification(data).then((res) => {
            registrationData.value.email = form.value.email
            registrationStep.value = 2
            setSuccess(t('common.verificationCodeSentToYourEmail'))
            startResendCooldown()
        })
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                err.response?.data?.message ||
                t('common.failedToSendVerificationCode')
        )
        throw err
    } finally {
        loading.value = false
    }
}

const handleVerifyCode = async () => {
    clearMessages()
    loading.value = true

    // Strip spaces and non-numeric characters from the code
    const cleanCode = form.value.verificationCode?.replace(/[^0-9]/g, '') || ''
    form.value.verificationCode = cleanCode

    if (cleanCode.length != 6) {
        setError(t('common.invalidCodeLength'))
        setTimeout(() => {
            clearMessages()
            loading.value = false
        }, 1500)
        return
    }

    try {
        await authStore.verifyEmailVerification(form.value.email, cleanCode).then((res) => {
            registrationStep.value = 3
            setSuccess(t('common.emailVerifiedSuccessfully'))
        })
    } catch (err) {
        console.error('Verification error:', err)

        const errorMessage =
            err.response?.data?.message ||
            err.response?.data?.error?.message ||
            err.message ||
            t('common.invalidVerificationCode')

        setError(errorMessage)

        if (err.response?.status === 404) {
            setTimeout(() => {
                registrationStep.value = 1
                clearMessages()
            }, 2000)
        }
    } finally {
        loading.value = false
    }
}

const handleResendCode = async () => {
    if (resendCooldown.value > 0) return

    loading.value = true
    try {
        await authStore.registerResendEmailVerification({ email: form.value.email }).then((res) => {
            setSuccess(t('common.newVerificationCodeSent'))
            startResendCooldown()
        })
    } catch (err) {
        if (err?.response?.data?.message) {
            console.log(err?.response?.data?.message)
            setError(err?.response?.data?.message)
        } else {
            setError(t('common.failedToResendVerificationCode'))
        }
    } finally {
        loading.value = false
    }
}

const handleBirthdateNext = async () => {
    clearMessages()
    loading.value = true

    try {
        if (!birthdateFormatted.value) {
            setError(t('common.pleaseSelectYourBirthdate'))
            return
        }

        const ok = await verifyAgeApi(birthdateFormatted.value)
        if (!ok) {
            errorPreventsRegistration.value = true
            setError(
                t('common.youMustBeAtLeastXYearsOld', {
                    years: minimumAge.value
                })
            )
            return
        }

        registrationBirthdate.value = birthdateFormatted.value
        registrationData.value.birthdate = registrationBirthdate.value
        form.value.birthdate = registrationBirthdate.value

        setSuccess(t('common.birthdateVerified') || 'Birthdate verified!')
        registrationStep.value = 4
    } catch (err) {
        setError(err?.message || t('common.failedToVerifyAgePleaseTryAgain'))
    } finally {
        loading.value = false
    }
}

const handleSetProfile = async () => {
    clearMessages()
    loading.value = true

    if (form.value.password !== form.value.confirmPassword) {
        setError(t('common.passwordsDoNotMatch'))
        loading.value = false
        return
    }

    if (passwordStrength.value.score < 3) {
        setError(t('common.pleaseChooseAStrongerPassword'))
        loading.value = false
        return
    }

    try {
        await authStore
            .registerUsername(
                form.value.username,
                form.value.password,
                form.value.confirmPassword,
                birthdateFormatted.value
            )
            .then((res) => {
                registrationData.value.username = form.value.username
                registrationData.value.password = form.value.password
                registrationStep.value = 5
                setSuccess(t('common.profileInformationSaved'))
            })
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                err.response?.data?.message ||
                t('common.anUnexpectedErrorOccuredPleaseTryAgain')
        )
        throw err
    } finally {
        await authStore.forceRegisterLogin()
        loading.value = false
    }
}

const handleCompleteRegistration = async () => {
    clearMessages()
    loading.value = true

    try {
        if (form.value.avatar) {
            const data = new FormData()
            data.append('avatar', form.value.avatar || '')
            await authStore.updateAvatar(data).catch((err) => {
                console.log(err)
            })
        }
        setSuccess(t('common.accountCreatedSuccessfullyWelcomeAboard'))
        setTimeout(() => {
            window.location.reload()
            closeModal()
        }, 2000)
    } catch (err) {
        setError(err.message || t('common.registrationFailedPleaseTryAgain'))
    } finally {
        loading.value = false
    }
}

const selectAvatar = (avatar) => {
    form.value.selectedAvatarUrl = avatar
    registrationData.value.avatarUrl = avatar
}

const handleAvatarUpload = (event) => {
    const file = event.target.files[0]
    if (file) {
        const reader = new FileReader()
        form.value.avatar = file
        reader.onload = (e) => {
            form.value.selectedAvatarUrl = e.target.result
            registrationData.value.avatarUrl = e.target.result
        }
        reader.readAsDataURL(file)
    }
}

const goBackStep = () => {
    if (registrationStep.value > 1) {
        registrationStep.value--
        clearMessages()
    }
}

const handleLogin = async () => {
    clearMessages()
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(form.value.email)) {
        setError('Please enter a valid email address.')
        return
    }
    loading.value = true

    try {
        const res = await authStore.login({
            email: form.value.email,
            password: form.value.password,
            remember: form.value.remember,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token
        })

        if (res.data.has_2fa) {
            currentMode.value = 'two-factor'
            setSuccess(t('common.pleaseEnterYour2FACode'))
        } else if (res.data.redirect) {
            window.location.href = res.data.redirect
        } else {
            closeModal()
        }
    } catch (err) {
        setError(err?.response?.data?.message || t('common.loginFailedPleaseTryAgain'))
        if (err.response?.data?.redirect) {
            window.location.href = err.response?.data?.redirect
        }
        onCaptchaExpired()
    } finally {
        loading.value = false
    }
}

const handlePasswordReset = async () => {
    clearMessages()
    loading.value = true

    try {
        const res = await authStore.resetMyPassword({
            email: form.value.email,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token
        })

        setSuccess(t('common.passwordResetLinkHasBeenSentToYourEmail'))
        setTimeout(() => {
            currentMode.value = 'login'
            clearMessages()
        }, 2000)
    } catch (err) {
        setError(err.message || t('common.failedToSendResetLinkPleaseTryAgain'))
    } finally {
        loading.value = false
    }
}

const handleOtpBackup = async () => {
    clearMessages()
    currentMode.value = 'two-factor-backup'
}

const sendEmailCode = async () => {
    loading.value = true
    emailCodeSent.value = true
    startResendCooldown()
    setTimeout(() => {
        loading.value = false
    }, 2000)
}

const handleTwoFactor = async () => {
    clearMessages()
    loading.value = true

    if (form.value.twoFactorCode?.length != 6) {
        setError(t('common.invalidCodeLength'))
        setTimeout(() => {
            clearMessages()
            loading.value = false
        }, 1500)
        return
    }

    try {
        const res = await authStore.validateOTP(form.value.twoFactorCode)
        otpAttempts.value++
        if (res.data.success) {
            setSuccess(t('common.authenticationSuccessful'))
            if (res.data.redirect) {
                window.location.href = res.data.redirect
            } else {
                setTimeout(() => {
                    window.location.reload()
                }, 500)
            }
        } else if (res.data.force_relogin) {
            setError(t('common.tooManyFailedAttemptsPleaseTryAgainLater'))
            clearForm()
            currentMode.value = 'login'
        } else {
            const msg =
                res.data?.error || res.data?.message || t('common.loginFailedPleaseTryAgain')
            //  hasOtpError.value = true;
            //  otpErrorMessage.value = msg
            throw new Error(msg)
        }
    } catch (err) {
        // hasOtpError.value = true;
        // otpErrorMessage.value = err
        setError(err)
    } finally {
        loading.value = false
    }
}

const handleCodeComplete = (code) => {
    form.value.twoFactorCode = code

    nextTick(() => {
        handleTwoFactor()
    })
}

watch(
    () => props.mode,
    (newMode) => {
        currentMode.value = newMode
        registrationStep.value = 1
        if (resendTimer.value) {
            clearInterval(resendTimer.value)
            resendCooldown.value = 0
        }
    }
)
</script>
