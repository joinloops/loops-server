import { createRouter, createWebHistory } from 'vue-router'
import { authMiddleware } from '@/middleware/auth'
import { useAuthStore } from '@/stores/auth'
import { initializeAuth } from '@/plugins/auth'
import DynamicPage from '@/pages/DynamicPage.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: () => import('~/pages/index.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/feed/for-you',
            name: 'feedForYouPage',
            component: () => import('~/pages/feed/for-you.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/feed/following',
            name: 'feedFollowingPage',
            component: () => import('~/pages/feed/following.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/explore',
            name: 'explorePage',
            component: () => import('~/pages/explore.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/intents/follow',
            name: 'followIntent',
            component: () => import('~/pages/intents/follow.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/notifications/system/:id/permalink',
            name: 'notificationSystemPermalink',
            component: () => import('~/pages/notifications/system-permalink.vue'),
            meta: { requiresAuth: false, params: true }
        },
        {
            path: '/notifications/system/:id',
            name: 'notificationSystem',
            component: () => import('~/pages/notifications/system.vue'),
            meta: { requiresAuth: true, params: true }
        },
        {
            path: '/notifications',
            name: 'notifications',
            component: () => import('~/pages/notifications/index.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/search',
            name: 'search',
            component: () => import('~/pages/search.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/invite/:id',
            name: 'invitePage',
            component: () => import('~/pages/invite/index.vue'),
            meta: { requiresAuth: false, params: true }
        },
        {
            path: '/v/:id',
            name: 'videoPage',
            component: () => import('~/pages/v/index.vue'),
            meta: { requiresAuth: false, params: true }
        },
        {
            path: '/tag/:id',
            name: 'tagPage',
            component: () => import('~/pages/tag/index.vue'),
            meta: { requiresAuth: false, params: true }
        },
        {
            path: '/sounds/:id',
            name: 'soundsPage',
            component: () => import('~/pages/sounds/index.vue'),
            meta: { requiresAuth: true, params: true }
        },
        {
            path: '/@:id',
            name: 'profilePage',
            component: () => import('~/pages/profile/index.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/about',
            name: 'About',
            component: DynamicPage,
            meta: {
                isDynamicPage: true,
                title: 'About',
                requiresAuth: false
            }
        },
        {
            path: '/legal/privacy-policy',
            redirect: '/privacy'
        },
        {
            path: '/terms',
            name: 'Terms of Service',
            component: DynamicPage,
            meta: {
                isDynamicPage: true,
                title: 'Terms of Service',
                requiresAuth: false
            }
        },
        {
            path: '/privacy',
            name: 'Privacy Policy',
            component: DynamicPage,
            meta: {
                isDynamicPage: true,
                title: 'Privacy Policy',
                requiresAuth: false
            }
        },
        {
            path: '/community-guidelines',
            name: 'Community Guidelines',
            component: DynamicPage,
            meta: {
                isDynamicPage: true,
                title: 'Community Guidelines',
                requiresAuth: false
            }
        },
        {
            path: '/pages/:slug',
            name: 'CustomPage',
            component: DynamicPage,
            meta: {
                isDynamicPage: true,
                requiresAuth: false
            },
            beforeEnter: async (to, from) => {
                return true
            }
        },
        // {
        //   path: '/platform/developers',
        //   name: 'developers',
        //   component: () => import('~/pages/platform/developer/index.vue'),
        //   meta: { requiresAuth: false }
        // },
        // {
        //   path: '/api/docs/v1/video/create',
        //   name: 'api.docs.v1.video.create',
        //   component: () => import('~/pages/platform/developer/api/v1/video/create.vue'),
        //   meta: { requiresAuth: false }
        // },
        {
            path: '/contact',
            name: 'contact',
            component: () => import('~/pages/platform/contact.vue'),
            meta: { requiresAuth: false }
        },

        // AUTH
        {
            path: '/auth/verify-email',
            name: 'manuallyVerifyEmail',
            component: () => import('~/pages/auth/manuallyVerifyEmail.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/auth/forgot-password',
            name: 'forgotPassword',
            component: () => import('~/pages/auth/ForgotPassword.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/password/reset/:token',
            name: 'passwordResetConfirm',
            component: () => import('~/pages/auth/PasswordReset.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/auth/verify-email/:userId/:token',
            name: 'verifyEmail',
            component: () => import('~/pages/auth/VerifyEmail.vue'),
            meta: { requiresAuth: true }
        },
        // {
        //   path: '/help-center',
        //   name: 'help-center',
        //   component: () => import('~/pages/platform/help/index.vue'),
        //   meta: { requiresAuth: false }
        // },
        // {
        //   path: '/help-center/kb/:id',
        //   name: 'help-center-article',
        //   component: () => import('~/pages/platform/help/show.vue'),
        //   meta: { requiresAuth: false }
        // },
        {
            path: '/studio',
            name: 'Studio',
            component: () => import('~/pages/studio/index.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/studio/playlists',
            name: 'playlists',
            component: () => import('~/pages/studio/playlists/index.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/studio/playlists/:id',
            name: 'playlistDetails',
            component: () => import('~/pages/studio/playlists/details.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/studio/upload',
            name: 'upload',
            component: () => import('~/pages/studio/upload.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/studio/posts',
            name: 'StudioPosts',
            component: () => import('~/pages/studio/posts.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('~/pages/auth/login.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('~/pages/auth/register.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/auth/app/register',
            name: 'appRegister',
            component: () => import('~/pages/auth/app-register.vue'),
            meta: { requiresAuth: false }
        },
        {
            path: '/dashboard',
            redirect: '/dashboard/account'
        },
        {
            path: '/dashboard/account',
            name: 'dashboard',
            component: () => import('~/pages/dashboard/index.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/appearance',
            name: 'dashboardAppearance',
            component: () => import('~/pages/dashboard/appearance.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/account/data',
            name: 'dashboardAccountData',
            component: () => import('~/pages/dashboard/account/AccountData.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/account/security',
            name: 'dashboardAccountSecurity',
            component: () => import('~/pages/dashboard/account/AccountSecurity.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/account/status',
            name: 'dashboardAccountStatus',
            component: () => import('~/pages/dashboard/account/AccountStatus.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/account/email',
            name: 'dashboardAccountEmail',
            component: () => import('~/pages/dashboard/account/AccountEmail.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/account/deactivate',
            name: 'dashboardAccountDeactivate',
            component: () => import('~/pages/dashboard/account/AccountDeactivate.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/account/delete',
            name: 'dashboardAccountDelete',
            component: () => import('~/pages/dashboard/account/AccountDelete.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/safety',
            name: 'dashboardSafety',
            component: () => import('~/pages/dashboard/safety.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/dashboard/safety/blocked-accounts',
            name: 'dashboardSafetyBlockedAccounts',
            component: () => import('~/pages/dashboard/safety/SafetyBlockedAccounts.vue'),
            meta: { requiresAuth: true }
        },
        // {
        //   path: '/dashboard/federation',
        //   name: 'dashboardFederation',
        //   component: () => import('~/pages/dashboard/federation.vue'),
        //   meta: { requiresAuth: true }
        // },
        // {
        //   path: '/dashboard/notifications',
        //   name: 'dashboardPushNotify',
        //   component: () => import('~/pages/dashboard/notifications.vue'),
        //   meta: { requiresAuth: true }
        // },
        // {
        //   path: '/dashboard/business',
        //   name: 'dashboardBusiness',
        //   component: () => import('~/pages/dashboard/business.vue'),
        //   meta: { requiresAuth: true }
        // },
        // {
        //     path: '/dashboard/screen-time',
        //     name: 'dashboardScreenTime',
        //     component: () => import('~/pages/dashboard/screen-time.vue'),
        //     meta: { requiresAuth: true }
        // },
        // {
        //     path: "/dashboard/preferences",
        //     name: "dashboardPref",
        //     component: () => import("~/pages/dashboard/preferences.vue"),
        //     meta: { requiresAuth: true },
        // },
        {
            path: '/admin',
            component: () => import('~/layouts/AdminLayout.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
            children: [
                {
                    path: '',
                    redirect: '/admin/dashboard'
                },
                {
                    path: 'dashboard',
                    name: 'Dashboard',
                    component: () => import('~/pages/admin/Dashboard.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'comments',
                    name: 'Comments',
                    component: () => import('~/pages/admin/Comments.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'replies',
                    name: 'Replies',
                    component: () => import('~/pages/admin/Replies.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'hashtags',
                    name: 'Hashtags',
                    component: () => import('~/pages/admin/Hashtags.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'reports/:id',
                    name: 'ReportView',
                    component: () => import('~/pages/admin/ReportShow.vue'),
                    params: true,
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'reports',
                    name: 'Reports',
                    component: () => import('~/pages/admin/Reports.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'profiles',
                    name: 'Profiles',
                    component: () => import('~/pages/admin/Profiles.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'invites',
                    name: 'adminInvites',
                    component: () => import('~/pages/admin/AdminInvites.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'invites/create',
                    name: 'adminInvitesCreate',
                    component: () => import('~/pages/admin/AdminInvitesCreate.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'invites/:id',
                    name: 'adminInvitesShow',
                    component: () => import('~/pages/admin/AdminInvitesShow.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'profiles/:id',
                    name: 'ProfileShow',
                    component: () => import('~/pages/admin/ProfileShow.vue'),
                    params: true,
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'videos',
                    name: 'Videos',
                    component: () => import('~/pages/admin/Videos.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'videos/:id',
                    name: 'VideoShow',
                    component: () => import('~/pages/admin/VideoShow.vue'),
                    params: true,
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'instances',
                    name: 'instances',
                    component: () => import('~/pages/admin/Instances.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'instances/manage',
                    name: 'instancesManage',
                    component: () => import('~/pages/admin/InstanceManage.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'instances/:id',
                    name: 'InstancesShow',
                    component: () => import('~/pages/admin/InstanceShow.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'settings',
                    name: 'Settings',
                    component: () => import('~/pages/admin/Settings.vue'),
                    meta: { requiresAdmin: true }
                },
                {
                    path: 'relays',
                    name: 'Relays',
                    component: () => import('~/pages/admin/Relays.vue'),
                    meta: { requiresAdmin: true }
                }
            ]
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'NotFound',
            component: () => import('~/pages/notFound.vue')
        }
    ]
})

let authInitialized = false

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore()

    if (!authInitialized) {
        await initializeAuth()
        authInitialized = true
    }

    // Check if route requires admin access
    if (to.matched.some((record) => record.meta.requiresAdmin)) {
        if (!authStore.user || !authStore.user.is_admin) {
            next('/')
            return
        }
    }

    authMiddleware(to, from, next)
})

export default router
