import { useAuthStore } from "~/stores/auth";
import { useProfileStore } from "~/stores/profile";
import { useAppStore } from "~/stores/app";
import { useExploreStore } from "~/stores/explore";
import { useVideoStore } from "~/stores/video";
import { useEditHistoryStore } from "~/stores/editHistory";
import { useTagFeedStore } from "~/stores/tagFeed";

export default {
    install: (app) => {
        const authStore = useAuthStore();
        const profileStore = useProfileStore();
        const appStore = useAppStore();
        const exploreStore = useExploreStore();
        const videoStore = useVideoStore();
        const editHistoryStore = useEditHistoryStore();
        const tagFeedStore = useTagFeedStore();

        app.provide("authStore", authStore);
        app.provide("profileStore", profileStore);
        app.provide("appStore", appStore);
        app.provide("exploreStore", exploreStore);
        app.provide("videoStore", videoStore);
        app.provide("editHistoryStore", editHistoryStore);
        app.provide("tagFeedStore", tagFeedStore);

        app.config.globalProperties.$authStore = authStore;
        app.config.globalProperties.$profileStore = profileStore;
        app.config.globalProperties.$appStore = appStore;
        app.config.globalProperties.$exploreStore = exploreStore;
        app.config.globalProperties.$videoStore = videoStore;
        app.config.globalProperties.$editHistoryStore = editHistoryStore;
        app.config.globalProperties.$tagFeedStore = tagFeedStore;
    },
};
