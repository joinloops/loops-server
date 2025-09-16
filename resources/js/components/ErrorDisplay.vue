<template>
    <div
        class="flex flex-col items-center justify-center w-full h-full p-4 text-center"
    >
        <div class="mb-4 text-red-500">
            <svg
                class="w-12 h-12 mx-auto mb-2"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
        </div>

        <h3
            class="mb-2 text-3xl font-semibold text-gray-800 dark:text-slate-400"
        >
            {{ title || "Something went wrong" }}
        </h3>

        <p class="text-xl mb-4 text-gray-600 dark:text-slate-500">
            {{ message || errorMessage }}
        </p>

        <button
            v-if="retryAction"
            @click="retryAction"
            class="px-4 py-2 text-white bg-[#F02C56] rounded hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
        >
            Try Again
        </button>
    </div>
</template>

<script lang="ts">
export default {
    name: "ErrorDisplay",

    props: {
        error: {
            type: [Error, Object],
            default: null,
        },
        title: {
            type: String,
            default: "",
        },
        message: {
            type: String,
            default: "",
        },
        retryAction: {
            type: Function,
            default: null,
        },
    },

    computed: {
        errorMessage() {
            if (!this.error) return "An unexpected error occurred";

            if (this.error.response) {
                const status = this.error.response.status;

                switch (status) {
                    case 401:
                        return "The content you're trying to view is not available";
                    case 404:
                        return "The content you're looking for could not be found";
                    case 403:
                        return "You don't have permission to access this content";
                    case 401:
                        return "Please log in to continue";
                    case 429:
                        return "Too many requests. Please try again later";
                    case 500:
                        return "Server error. Please try again later";
                    default:
                        return (
                            this.error.response.data?.message ||
                            "An error occurred while fetching the content"
                        );
                }
            }

            if (this.error.message?.includes("Network Error")) {
                return "Unable to connect to the server. Please check your internet connection";
            }

            return this.error.message || "An unexpected error occurred";
        },
    },
};
</script>
