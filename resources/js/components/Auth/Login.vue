<template>
    <div class="text-center text-[28px] mb-4 font-bold dark:text-slate-300">Log in</div>

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

    <div class="px-6 pb-2 mt-6">
        <button
            :disabled="!email || !password"
            :class="!email || !password ? 'bg-gray-200 dark:bg-slate-800' : 'bg-[#F02C56]'"
            @click="login"
            class="w-full text-[17px] font-semibold text-white py-3 rounded-lg cursor-pointer"
        >
            Log in
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue'

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
    } catch (error) {
        errors.value = error.response.data.errors
    }
}
</script>
