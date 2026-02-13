import { useAuthStore } from '~/stores/auth'
import { useAdminStore } from '~/stores/admin'
import { useProfileStore } from '~/stores/profile'
import { useAppStore } from '~/stores/app'
import { useExploreStore } from '~/stores/explore'
import { useVideoStore } from '~/stores/video'
import { useSearchStore } from '~/stores/search'
import { useEditHistoryStore } from '~/stores/editHistory'
import { useTagFeedStore } from '~/stores/tagFeed'
import { useSoundsStore } from '~/stores/sounds'

export default {
    install: (app) => {
        const authStore = useAuthStore()
        const adminStore = useAdminStore()
        const profileStore = useProfileStore()
        const appStore = useAppStore()
        const exploreStore = useExploreStore()
        const videoStore = useVideoStore()
        const editHistoryStore = useEditHistoryStore()
        const tagFeedStore = useTagFeedStore()
        const searchStore = useSearchStore()
        const soundsStore = useSoundsStore()

        app.provide('authStore', authStore)
        app.provide('adminStore', adminStore)
        app.provide('profileStore', profileStore)
        app.provide('appStore', appStore)
        app.provide('exploreStore', exploreStore)
        app.provide('videoStore', videoStore)
        app.provide('editHistoryStore', editHistoryStore)
        app.provide('tagFeedStore', tagFeedStore)
        app.provide('searchStore', searchStore)
        app.provide('soundsStore', soundsStore)

        app.config.globalProperties.$authStore = authStore
        app.config.globalProperties.$adminStore = adminStore
        app.config.globalProperties.$profileStore = profileStore
        app.config.globalProperties.$appStore = appStore
        app.config.globalProperties.$exploreStore = exploreStore
        app.config.globalProperties.$videoStore = videoStore
        app.config.globalProperties.$editHistoryStore = editHistoryStore
        app.config.globalProperties.$tagFeedStore = tagFeedStore
        app.config.globalProperties.$searchStore = searchStore
        app.config.globalProperties.$soundsStore = soundsStore
    }
}
