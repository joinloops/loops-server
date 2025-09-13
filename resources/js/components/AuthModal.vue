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
            <div
                v-if="authStore.isOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                @click="closeModal"
            >
                <div
                    class="fixed inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm"
                ></div>

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
                                <h2
                                    class="text-3xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ getTitle() }}
                                </h2>
                                <p
                                    class="mt-2 text-gray-600 dark:text-gray-400"
                                >
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
                                    >
                                        {{ error }}
                                    </p>
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
                                    <p
                                        class="text-sm text-green-700 dark:text-green-400"
                                    >
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
                                        Email
                                    </label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        placeholder="Enter your email"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Password
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model="form.password"
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            class="w-full px-4 py-3 pr-12 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            placeholder="Enter your password"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                showPassword = !showPassword
                                            "
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                        >
                                            <EyeIcon
                                                v-if="showPassword"
                                                class="h-5 w-5"
                                            />
                                            <EyeSlashIcon
                                                v-else
                                                class="h-5 w-5"
                                            />
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
                                            >Remember me</span
                                        >
                                    </label>
                                    <button
                                        type="button"
                                        @click="currentMode = 'password-reset'"
                                        class="text-sm font-medium text-[#F02C56] hover:text-[#D7284A] dark:hover:text-[#D7284A] transition-colors cursor-pointer"
                                    >
                                        Forgot password?
                                    </button>
                                </div>

                                <template v-if="appCaptcha.enabled">
                                    <template
                                        v-if="
                                            appCaptcha.provider === 'turnstile'
                                        "
                                    >
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
                                        v-else-if="
                                            appCaptcha.provider === 'hcaptcha'
                                        "
                                    >
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
                                    Sign In
                                </AnimatedButton>
                            </form>

                            <div v-if="currentMode === 'register'">
                                <div class="mb-8">
                                    <div
                                        class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 mb-2"
                                    >
                                        <span
                                            >Step {{ registrationStep }} of
                                            4</span
                                        >
                                        <span
                                            >{{
                                                Math.round(
                                                    ((registrationStep - 1) /
                                                        4) *
                                                        100,
                                                )
                                            }}% complete</span
                                        >
                                    </div>
                                    <div
                                        class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2"
                                    >
                                        <div
                                            class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all duration-500 ease-out"
                                            :style="{
                                                width: `${((registrationStep - 1) / 4) * 100}%`,
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
                                            Email Address
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            required
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            placeholder="Enter your email address"
                                        />
                                    </div>

                                    <template v-if="appCaptcha.enabled">
                                        <template
                                            v-if="
                                                appCaptcha.provider ===
                                                'turnstile'
                                            "
                                        >
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
                                            v-else-if="
                                                appCaptcha.provider ===
                                                'hcaptcha'
                                            "
                                        >
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
                                        Continue
                                    </AnimatedButton>

                                    <button
                                        v-if="error"
                                        type="button"
                                        @click="handleOtpAlreadyHaveCode"
                                        class="mt-5 w-full text-center text-sm font-bold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 transition-colors cursor-pointer"
                                    >
                                        I already have the code
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
                                            Verification Code
                                        </label>
                                        <input
                                            v-model="form.verificationCode"
                                            type="text"
                                            required
                                            maxlength="6"
                                            class="w-full px-4 py-3 text-center text-2xl tracking-widest rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            placeholder="000000"
                                        />
                                        <p
                                            class="mt-2 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            Enter the 6-digit code sent to
                                            {{ registrationData.email }}
                                        </p>
                                    </div>

                                    <div
                                        class="flex justify-between items-center"
                                    >
                                        <button
                                            type="button"
                                            @click="handleResendCode"
                                            :disabled="
                                                resendCooldown > 0 || loading
                                            "
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 disabled:text-gray-400 dark:disabled:text-gray-600 transition-colors"
                                        >
                                            {{
                                                resendCooldown > 0
                                                    ? `Resend in ${resendCooldown}s`
                                                    : "Resend code"
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
                                            Back
                                        </AnimatedButton>
                                        <AnimatedButton
                                            type="submit"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            Verify
                                        </AnimatedButton>
                                    </div>
                                </form>

                                <form
                                    v-if="registrationStep === 3"
                                    @submit.prevent="handleSetProfile"
                                    class="space-y-6"
                                >
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            Username
                                        </label>
                                        <input
                                            v-model="form.username"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            placeholder="Choose a username"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            Password
                                        </label>
                                        <div class="relative">
                                            <input
                                                v-model="form.password"
                                                :type="
                                                    showPassword
                                                        ? 'text'
                                                        : 'password'
                                                "
                                                required
                                                class="w-full px-4 py-3 pr-12 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                                placeholder="Create a strong password"
                                            />
                                            <button
                                                type="button"
                                                @click="
                                                    showPassword = !showPassword
                                                "
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
                                                <span
                                                    class="text-gray-600 dark:text-gray-400"
                                                    >Password strength</span
                                                >
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
                                                        width: `${(passwordStrength.score / 5) * 100}%`,
                                                    }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            Confirm Password
                                        </label>
                                        <input
                                            v-model="form.confirmPassword"
                                            type="password"
                                            required
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                            placeholder="Confirm your password"
                                        />
                                    </div>

                                    <div class="flex gap-3">
                                        <AnimatedButton
                                            type="button"
                                            @click="goBackStep"
                                            variant="outline"
                                            class="flex-1"
                                        >
                                            Back
                                        </AnimatedButton>
                                        <AnimatedButton
                                            type="submit"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            Continue
                                        </AnimatedButton>
                                    </div>
                                </form>

                                <div
                                    v-if="registrationStep === 4"
                                    class="space-y-6"
                                >
                                    <div
                                        v-if="form.selectedAvatarUrl"
                                        class="text-center"
                                    >
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

                                    <div
                                        class="border-t border-gray-200 dark:border-gray-700 pt-6"
                                    >
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                                        >
                                            Upload your avatar
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
                                            Click to upload image
                                        </button>
                                    </div>

                                    <div class="flex gap-3">
                                        <AnimatedButton
                                            @click="handleCompleteRegistration"
                                            :loading="loading"
                                            class="flex-1"
                                            variant="primary"
                                        >
                                            Finish sign-up
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
                                        Email
                                    </label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        placeholder="Enter your email"
                                    />
                                </div>
                                <template v-if="appCaptcha.enabled">
                                    <template
                                        v-if="
                                            appCaptcha.provider === 'turnstile'
                                        "
                                    >
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
                                        v-else-if="
                                            appCaptcha.provider === 'hcaptcha'
                                        "
                                    >
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
                                    Send Reset Link
                                </AnimatedButton>
                                <button
                                    type="button"
                                    @click="currentMode = 'login'"
                                    class="mt-5 w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                >
                                    Back to Sign In
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
                                        Authentication Code
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
                                    Verify Code
                                </AnimatedButton>
                                <button
                                    v-if="otpAttempts > 1"
                                    type="button"
                                    @click="handleOtpBackup"
                                    class="mt-5 w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                >
                                    Use a different method
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
                                        Email Verification
                                    </h3>
                                    <p
                                        class="text-sm text-gray-600 dark:text-gray-400 mb-1"
                                    >
                                        We'll send a verification code to:
                                    </p>
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{
                                            form.email ||
                                            "your registered email address"
                                        }}
                                    </p>
                                </div>

                                <div v-if="emailCodeSent">
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Email Verification Code
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
                                                ? "Sending..."
                                                : "Send Code"
                                        }}
                                    </button>
                                </div>

                                <div v-if="emailCodeSent" class="text-center">
                                    <p
                                        class="text-sm text-green-600 dark:text-green-400 mb-2"
                                    >
                                        ✓ Verification code sent to your email
                                    </p>
                                    <button
                                        type="button"
                                        @click="resendEmailCode"
                                        :disabled="resendCooldown > 0"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{
                                            resendCooldown > 0
                                                ? `Resend in ${resendCooldown}s`
                                                : "Resend code"
                                        }}
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    @click="currentMode = 'two-factor'"
                                    class="w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                >
                                    ← Back to authenticator app
                                </button>
                            </form>

                            <div
                                v-if="
                                    (openRegistration &&
                                        currentMode === 'login') ||
                                    (currentMode === 'register' &&
                                        registrationStep === 1)
                                "
                                class="mt-8 text-center"
                            >
                                <p class="text-gray-600 dark:text-gray-400">
                                    {{
                                        currentMode === "login"
                                            ? "Don't have an account?"
                                            : "Already have an account?"
                                    }}
                                    <button
                                        @click="toggleMode"
                                        class="ml-1 text-[#F02C56] hover:text-[#D7284A] dark:hover:text-[#D7284A] font-medium transition-colors cursor-pointer"
                                    >
                                        {{
                                            currentMode === "login"
                                                ? "Sign up"
                                                : "Sign in"
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

<script setup lang="ts">
import { ref, computed, watch, nextTick, inject } from "vue";
import { useAuthStore } from "~/stores/auth";
import CloudflareTurnstile from "@/components/Captcha/CloudflareTurnstile.vue";
import HCaptcha from "@/components/Captcha/HCaptcha.vue";

const props = defineProps({
    mode: {
        type: String,
        default: "login",
        validator: (value) =>
            ["login", "register", "password-reset", "two-factor"].includes(
                value,
            ),
    },
});

import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";

const authStore = useAuthStore();
const axios = inject("axios");
const appConfig = inject("appConfig");
const appCaptcha = inject("appCaptcha");

const currentMode = ref(props.mode);
const loading = ref(false);
const error = ref("");
const otpAttempts = ref(0);
const hasOtpError = ref(false);
const otpErrorMessage = ref("");
const success = ref("");
const showPassword = ref(false);

const registrationStep = ref(1);
const resendCooldown = ref(0);
const resendTimer = ref(null);
const emailCodeSent = ref(false);
const emailCodeLoading = ref(false);
const hasEmailCodeError = ref(false);
const emailCodeErrorMessage = ref("");
const errorPreventsRegistration = ref(false);
const captchaType = ref(appCaptcha.provider);
const captchaToken = ref("");
const turnstileRef = ref(null);
const hcaptchaRef = ref(null);

const onCaptchaSuccess = (token) => {
    captchaToken.value = token;
    form.value.captcha_token = token;
    form.value.captcha_type = captchaType.value;
};

const onCaptchaError = (error) => {
    console.error("Captcha error:", error);
    captchaToken.value = "";
    form.value.captcha_token = "";
};

const onCaptchaExpired = () => {
    captchaToken.value = "";
    form.value.captcha_token = "";
};

const form = ref({
    email: "",
    emailCode: "",
    password: "",
    confirmPassword: "",
    username: "",
    firstName: "",
    lastName: "",
    remember: false,
    acceptTerms: false,
    twoFactorCode: "",
    verificationCode: "",
    avatar: null,
    selectedAvatarUrl: "",
});

const registrationData = ref({
    email: "",
    username: "",
    password: "",
    avatar: null,
    avatarUrl: "",
});

const openRegistration = computed(() => {
    return appConfig.registration;
});

const getTitle = () => {
    switch (currentMode.value) {
        case "login":
            return "Welcome back";
        case "register":
            switch (registrationStep.value) {
                case 1:
                    return "Create account";
                case 2:
                    return "Verify your email";
                case 3:
                    return "Set up your profile";
                case 4:
                    return "Choose your avatar";
                default:
                    return "Create account";
            }
        case "password-reset":
            return "Reset password";
        case "two-factor":
            return "Verify 2FA";
        case "two-factor-backup":
            return "Verify another way";
        default:
            return "Welcome";
    }
};

const getSubtitle = () => {
    switch (currentMode.value) {
        case "login":
            return "Sign in to your account to continue";
        case "register":
            switch (registrationStep.value) {
                case 1:
                    return "Enter your email to get started";
                case 2:
                    return "We sent a verification code to your email";
                case 3:
                    return "Choose a username and secure password";
                case 4:
                    return "Pick an avatar to personalize your profile";
                default:
                    return "Create a new account to get started";
            }
        case "password-reset":
            return "We'll send you a link to reset your password";
        case "two-factor":
            return "";
        case "two-factor-backup":
            return "";
        default:
            return "";
    }
};

const passwordStrength = computed(() => {
    const password = form.value.password;
    if (!password) return { score: 0, text: "", color: "" };

    let score = 0;
    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /\d/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password),
    };

    score = Object.values(checks).filter(Boolean).length;

    if (score <= 2) return { score, text: "Weak", color: "red" };
    if (score <= 3) return { score, text: "Fair", color: "yellow" };
    if (score <= 4) return { score, text: "Good", color: "blue" };
    return { score, text: "Strong", color: "green" };
});

const closeModal = () => {
    authStore.closeAuthModal();
    clearForm();
    clearMessages();
    registrationStep.value = 1;
};

const clearForm = () => {
    form.value = {
        email: "",
        password: "",
        confirmPassword: "",
        username: "",
        firstName: "",
        lastName: "",
        remember: false,
        acceptTerms: false,
        twoFactorCode: "",
        verificationCode: "",
        avatar: null,
        selectedAvatarUrl: "",
    };
};

const clearMessages = () => {
    error.value = "";
    success.value = "";
};

const toggleMode = () => {
    currentMode.value = currentMode.value === "login" ? "register" : "login";
    registrationStep.value = 1;
    clearMessages();
};

const setError = (message) => {
    error.value = message;
    success.value = "";
};

const setSuccess = (message) => {
    success.value = message;
    error.value = "";
};

const startResendCooldown = () => {
    resendCooldown.value = 60;
    resendTimer.value = setInterval(() => {
        resendCooldown.value--;
        if (resendCooldown.value <= 0) {
            clearInterval(resendTimer.value);
        }
    }, 1000);
};

const handleOtpAlreadyHaveCode = async () => {
    clearMessages();
    registrationData.value.email = form.value.email;
    registrationStep.value = 2;
};

const handleSendCode = async () => {
    clearMessages();
    loading.value = true;

    try {
        let data = {
            email: form.value.email,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token,
        };
        await authStore.registerEmailVerification(data).then((res) => {
            registrationData.value.email = form.value.email;
            registrationStep.value = 2;
            setSuccess("Verification code sent to your email!");
            startResendCooldown();
        });
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                err.response?.data?.message ||
                "Failed to send verification code. Please try again.",
        );
        throw err;
    } finally {
        loading.value = false;
    }
};

const handleVerifyCode = async () => {
    clearMessages();
    loading.value = true;

    if (form.value.verificationCode?.length != 6) {
        setError("Invalid code length");
        setTimeout(() => {
            clearMessages();
            loading.value = false;
        }, 1500);
        return;
    }

    try {
        await authStore
            .verifyEmailVerification(
                form.value.email,
                form.value.verificationCode,
            )
            .then((res) => {
                registrationStep.value = 3;
                setSuccess("Email verified successfully!");
            });
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                "Invalid verification code. Please try again.",
        );
        throw err;
    } finally {
        loading.value = false;
    }
};

const handleResendCode = async () => {
    if (resendCooldown.value > 0) return;

    loading.value = true;
    try {
        await new Promise((resolve) => setTimeout(resolve, 1000));

        setSuccess("New verification code sent!");
        startResendCooldown();
    } catch (err) {
        setError("Failed to resend code. Please try again.");
    } finally {
        loading.value = false;
    }
};

const handleSetProfile = async () => {
    clearMessages();
    loading.value = true;

    if (form.value.password !== form.value.confirmPassword) {
        setError("Passwords do not match");
        loading.value = false;
        return;
    }

    if (passwordStrength.value.score < 3) {
        setError("Please choose a stronger password");
        loading.value = false;
        return;
    }

    try {
        await authStore
            .registerUsername(
                form.value.username,
                form.value.password,
                form.value.confirmPassword,
            )
            .then((res) => {
                registrationData.value.username = form.value.username;
                registrationData.value.password = form.value.password;
                registrationStep.value = 4;
                setSuccess("Profile information saved!");
            });
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                err.response?.data?.message ||
                "An unexpected error occurred. Please try again.",
        );
        throw err;
    } finally {
        await authStore.forceRegisterLogin();
        loading.value = false;
    }
};

const handleCompleteRegistration = async () => {
    clearMessages();
    loading.value = true;

    try {
        if (form.value.avatar) {
            const data = new FormData();
            data.append("avatar", form.value.avatar || "");
            await authStore.updateAvatar(data).catch((err) => {
                console.log(err);
            });
        }
        setSuccess("Account created successfully! Welcome aboard!");
        setTimeout(() => {
            window.location.reload();
            closeModal();
        }, 2000);
    } catch (err) {
        setError(err.message || "Registration failed. Please try again.");
    } finally {
        loading.value = false;
    }
};

const selectAvatar = (avatar) => {
    form.value.selectedAvatarUrl = avatar;
    registrationData.value.avatarUrl = avatar;
};

const handleAvatarUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        form.value.avatar = file;
        reader.onload = (e) => {
            form.value.selectedAvatarUrl = e.target.result;
            registrationData.value.avatarUrl = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const goBackStep = () => {
    if (registrationStep.value > 1) {
        registrationStep.value--;
        clearMessages();
    }
};

const handleLogin = async () => {
    clearMessages();
    loading.value = true;

    try {
        const res = await authStore.login({
            email: form.value.email,
            password: form.value.password,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token,
        });

        if (res.data.has_2fa) {
            currentMode.value = "two-factor";
            setSuccess("Please enter your two-factor authentication code");
        } else {
            closeModal();
        }
    } catch (err) {
        setError(
            err?.response?.data?.message || "Login failed. Please try again.",
        );
    } finally {
        loading.value = false;
    }
};

const handlePasswordReset = async () => {
    clearMessages();
    loading.value = true;

    try {
        const res = await authStore.resetMyPassword({
            email: form.value.email,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token,
        });

        setSuccess("Password reset link has been sent to your email.");
        setTimeout(() => {
            currentMode.value = "login";
            clearMessages();
        }, 2000);
    } catch (err) {
        setError(err.message || "Failed to send reset link. Please try again.");
    } finally {
        loading.value = false;
    }
};

const handleOtpBackup = async () => {
    clearMessages();
    currentMode.value = "two-factor-backup";
};

const sendEmailCode = async () => {
    loading.value = true;
    emailCodeSent.value = true;
    startResendCooldown();
    setTimeout(() => {
        loading.value = false;
    }, 2000);
};

const handleTwoFactor = async () => {
    clearMessages();
    loading.value = true;

    if (form.value.twoFactorCode?.length != 6) {
        setError("Invalid code length");
        setTimeout(() => {
            clearMessages();
            loading.value = false;
        }, 1500);
        return;
    }

    try {
        const res = await authStore.validateOTP(form.value.twoFactorCode);
        otpAttempts.value++;
        if (res.data.success) {
            setSuccess("Authentication successful!");
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else if (res.data.force_relogin) {
            setError("Too many failed attempts, please try again later");
            clearForm();
            currentMode.value = "login";
        } else {
            const msg =
                res.data?.error ||
                res.data?.message ||
                "Authentication failed. Please try again.";
            //  hasOtpError.value = true;
            //  otpErrorMessage.value = msg
            throw new Error(msg);
        }
    } catch (err) {
        // hasOtpError.value = true;
        // otpErrorMessage.value = err
        setError(err);
    } finally {
        loading.value = false;
    }
};

const handleCodeComplete = (code) => {
    form.value.twoFactorCode = code;

    nextTick(() => {
        handleTwoFactor();
    });
};

watch(
    () => props.mode,
    (newMode) => {
        currentMode.value = newMode;
        registrationStep.value = 1;
        if (resendTimer.value) {
            clearInterval(resendTimer.value);
            resendCooldown.value = 0;
        }
    },
);
</script>
