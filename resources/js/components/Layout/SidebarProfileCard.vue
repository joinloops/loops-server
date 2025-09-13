<template>
    <div
        class="flex items-center hover:bg-gray-100 dark:hover:bg-slate-900 rounded-md w-full py-1.5 px-2"
    >
        <img
            v-if="user.avatar"
            class="rounded-full lg:mx-0 mx-auto"
            width="35"
            :src="user.avatar"
            @error="$event.target.src = '/storage/avatars/default.jpg'"
        />
        <div class="lg:pl-2.5 lg:block hidden">
            <div class="flex items-center">
                <div class="font-bold text-[14px] dark:text-slate-400">
                    {{ user.name }}
                </div>
                <!-- <div class="ml-1 rounded-full bg-[#58D5EC] h-[14px] relative">
                    <i class="relative -top-[7px]" style="color: #FFFFFF; font-size: 15px;">✓</i>
                </div> -->
            </div>

            <div
                class="font-light text-[12px] text-gray-600 dark:text-slate-500"
            >
                &commat;{{ user.username }}
            </div>
        </div>
    </div>
</template>

<script lang="ts">
export default {
    props: {
        user: {
            type: Object,
            required: true,
        },
    },
    methods: {
        allLowerCaseNoCaps(str) {
            // Implement the logic that was in generalStore
            return str.toLowerCase();
        },
    },
};
</script>
