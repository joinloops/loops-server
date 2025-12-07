<template>
    <div class="fixed flex items-center justify-center z-50 top-0 left-0 w-full h-full bg-black/80">
        <div
            class="relative bg-white dark:bg-slate-900 w-full overflow-y max-w-[470px] h-[70%] p-4 rounded-lg"
        >
            <div class="w-full flex justify-end">
                <button
                    @click="appStore.toggleLoginForm()"
                    class="w-10 h-10 rounded-full bg-gray-100 dark:bg-slate-700 cursor-pointer dark:text-white"
                >
                    <i class="mdi mdi-close" style="font-size: 26px"></i>
                </button>
            </div>

            <Login v-if="isRegister" />
            <Register v-else />

            <div
                class="absolute flex items-center justify-center py-5 left-0 bottom-0 border-t border-gray-100 dark:border-slate-800 w-full"
            >
                <span class="text-[14px] text-gray-600 dark:text-slate-400"
                    >Don't have an account?</span
                >
                <button
                    @click="isRegister = !isRegister"
                    class="text-[14px] text-[#F02C56] font-semibold pl-1"
                >
                    <span v-if="isRegister">Sign up</span>
                    <span v-else>Log in</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, inject } from 'vue'
import Login from '@/components/Login.vue'
import Register from '@/components/Register.vue'
const appStore = inject('appStore')
const isRegister = ref(true)
</script>
