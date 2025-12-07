<template>
    <div class="text-center text-[28px] mb-4 font-bold dark:text-slate-300">Log in</div>

    <!-- <div class="px-6 pb-4 space-y-2">
        <button 
            @click="loginWithPixelfed" 
            class="w-full text-[17px] font-semibold text-white py-3 rounded-lg bg-[#000000] hover:bg-gray-950 flex items-center justify-center gap-2"
        >
            <svg class="w-5 h-5" viewBox="0 0 50 50" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                <defs></defs>
                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                    <g id="icon-copy-12" fill="#FFFFFF">
                        <g id="Group-12">
                            <path d="M25,50 C11.1928813,50 0,38.8071187 0,25 C0,11.1928813 11.1928813,0 25,0 C38.8071187,0 50,11.1928813 50,25 C50,38.8071187 38.8071187,50 25,50 Z M23.0153159,30.4580153 L27.6014641,30.4580153 C31.9217989,30.4580153 35.4241222,27.0484974 35.4241222,22.842644 C35.4241222,18.6367906 31.9217989,15.2272727 27.6014641,15.2272727 L20.9822918,15.2272727 C18.4897909,15.2272727 16.4692198,17.1943023 16.4692198,19.6207562 L16.4692198,36.7207782 L23.0153159,30.4580153 Z" id="Combined-Shape"></path>
                        </g>
                    </g>
                </g>
            </svg>
            Login with Pixelfed
        </button>
        <button 
            @click="loginWithMastodon" 
            class="w-full text-[17px] font-semibold text-white py-3 rounded-lg bg-[#595aff] hover:bg-[#4a4bff] flex items-center justify-center gap-2"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21.327 8.566c0-4.339-2.843-5.61-2.843-5.61-1.433-.658-3.894-.935-6.451-.956h-.063c-2.557.021-5.018.298-6.451.956 0 0-2.843 1.271-2.843 5.61 0 .993-.019 2.181.012 3.441.103 4.243.778 8.425 4.701 9.463 1.809.479 3.362.579 4.612.51 2.268-.126 3.541-.809 3.541-.809l-.075-1.646s-1.621.511-3.441.449c-1.804-.062-3.707-.194-3.999-2.409a4.523 4.523 0 0 1-.04-.621s1.77.433 4.014.536c1.372.063 2.658-.08 3.965-.236 2.506-.299 4.688-1.843 4.962-3.254.434-2.223.398-5.424.398-5.424zm-3.353 5.59h-2.081V9.057c0-1.075-.452-1.62-1.357-1.62-1 0-1.501.647-1.501 1.927v2.791h-2.069V9.364c0-1.28-.501-1.927-1.501-1.927-.905 0-1.357.546-1.357 1.62v5.099H6.027V8.902c0-1.074.273-1.927.823-2.558.566-.631 1.307-.955 2.228-.955 1.065 0 1.872.409 2.405 1.228l.518.869.519-.869c.533-.819 1.34-1.228 2.405-1.228.921 0 1.662.324 2.228.955.549.631.822 1.484.822 2.558v5.254z"/>
            </svg>
            Login with Mastodon
        </button>
    </div>

    <div class="px-6 pb-4 flex items-center">
        <div class="flex-grow border-t border-gray-200 dark:border-slate-700"></div>
        <span class="px-4 text-sm text-gray-500 dark:text-slate-500">or</span>
        <div class="flex-grow border-t border-gray-200 dark:border-slate-700"></div>
    </div> -->

    <div>
        <div class="px-6 pb-1.5 text-[15px] dark:text-slate-500">Email address</div>

        <div class="px-6 pb-2">
            <TextInput
                placeholder="Email address"
                v-model="email"
                input-type="email"
                :auto-focus="true"
                :error="errors?.email?.[0]"
            />
        </div>

        <div class="px-6 pb-1.5 text-[15px] dark:text-slate-500">Password</div>

        <div class="px-6 pb-2">
            <TextInput placeholder="Password" v-model="password" input-type="password" />
        </div>
        <div class="px-6 text-[12px] text-gray-600">Forgot password?</div>
    </div>

    <div class="px-6 py-2">
        <button
            :disabled="!email || !password"
            :class="!email || !password ? 'bg-gray-200 dark:bg-slate-800' : 'bg-[#F02C56]'"
            @click="login"
            class="w-full text-[17px] font-semibold text-white py-3 rounded-lg"
        >
            Login with email
        </button>
    </div>
</template>

<script setup>
import { ref, inject } from 'vue'

const authStore = inject('authStore')
const appStore = inject('appStore')

const email = ref(null)
const password = ref(null)
const errors = ref(null)

const login = async () => {
    errors.value = null

    try {
        await authStore.login({ email: email.value, password: password.value })
        appStore.isLoginOpen = false
        window.location.reload()
    } catch (error) {
        errors.value = error.response.data?.errors
    }
}

const loginWithPixelfed = async () => {
    try {
        await authStore.loginWithPixelfed()
        appStore.isLoginOpen = false
    } catch (error) {
        errors.value = { social: ['Failed to login with Pixelfed'] }
    }
}

const loginWithMastodon = async () => {
    try {
        await authStore.loginWithMastodon()
        appStore.isLoginOpen = false
    } catch (error) {
        errors.value = { social: ['Failed to login with Mastodon'] }
    }
}
</script>
