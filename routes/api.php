<?php

use App\Http\Controllers\AccountDataController;
use App\Http\Controllers\ActorController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ExploreController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudioController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\WebPublicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InstanceActorController;
use App\Http\Controllers\NodeInfoController;
use App\Http\Controllers\ObjectController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SharedInboxController;
use App\Http\Controllers\UserRegisterVerifyController;
use App\Http\Controllers\WebfingerController;
use App\Http\Middleware\AdminOnlyAccess;
use App\Http\Middleware\AuthorizedFetch;
use App\Http\Middleware\FederationEnabled;
use Illuminate\Support\Facades\Route;

// NodeInfo endpoints
Route::group(['prefix' => 'nodeinfo'], function () {
    Route::get('2.0', [NodeInfoController::class, 'nodeInfo20'])
        ->name('nodeinfo.2.0')->middleware([FederationEnabled::class]);

    Route::get('2.1', [NodeInfoController::class, 'nodeInfo21'])
        ->name('nodeinfo.2.1')->middleware([FederationEnabled::class]);

    Route::any('{any}', function () {
        return response()->json(['message' => 'Not Found.'], 404);
    })->where('any', '.*');
});

Route::group(['prefix' => '.well-known'], function () {
    Route::get('host-meta', function () {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">
    <Link rel="lrdd" type="application/xrd+xml" template="'.url('/.well-known/webfinger').'?resource={uri}"/>
</XRD>';

        return response($xml, 200)->header('Content-Type', 'application/xrd+xml');
    })->name('host-meta')->middleware([FederationEnabled::class]);
    Route::get('nodeinfo', [NodeInfoController::class, 'discovery'])->middleware([FederationEnabled::class]);
    Route::get('webfinger', [WebfingerController::class, 'handle'])
        ->name('webfinger')->middleware([FederationEnabled::class]);

    Route::any('{any}', function () {
        return response()->json(['message' => 'Not Found.'], 404);
    })->where('any', '.*');
});

Route::prefix('api')->group(function () {
    Route::post('/v1/apps', [AuthController::class, 'registerApp']);

    Route::get('/v1/web/report-rules', [WebPublicController::class, 'reportTypes']);

    Route::get('/v1/platform/contact', [WebPublicController::class, 'getContactInfo']);
    Route::get('/v1/page/content', [WebPublicController::class, 'getPageContent']);

    Route::get('/v1/accounts/suggested', [AccountController::class, 'getSuggestedAccounts']);

    // Auth
    Route::post('/v1/auth/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    Route::post('/v1/auth/register/email', [UserRegisterVerifyController::class, 'sendEmailVerification']);
    Route::post('/v1/auth/register/email/resend', [UserRegisterVerifyController::class, 'resendEmailVerification']);
    Route::post('/v1/auth/register/email/verify', [UserRegisterVerifyController::class, 'verifyEmailVerification']);
    Route::post('/v1/auth/register/username', [UserRegisterVerifyController::class, 'claimUsername']);
    Route::post('/v1/auth/register/verify-age', [UserRegisterVerifyController::class, 'verifyAge']);

    // Studio
    Route::get('/v1/studio/posts', [StudioController::class, 'getPosts'])->middleware('auth:web,api');
    Route::post('/v1/studio/upload', [VideoController::class, 'store'])->middleware('auth:web,api');

    // Search
    Route::get('/v1/search', [SearchController::class, 'search'])->middleware(['auth:web,api', 'throttle:searchV1']);
    Route::post('/v1/search/users', [SearchController::class, 'getUsers'])->middleware(['auth:web,api', 'throttle:autocomplete']);

    // Feeds
    Route::get('/v0/user/self', [AccountController::class, 'selfAccountInfo'])->middleware('auth:web,api');
    Route::get('/v0/feed/for-you', [FeedController::class, 'getForYouFeed'])->middleware('auth:web,api');
    Route::get('/web/feed', [WebPublicController::class, 'getFeed'])->middleware('throttle:api');

    // Web Accounts
    Route::get('/v1/web/account/followers/{id}', [WebPublicController::class, 'accountFollowers'])->middleware('throttle:api');
    Route::get('/v1/web/account/following/{id}', [WebPublicController::class, 'accountFollowing'])->middleware('throttle:api');

    // Accounts
    Route::get('/v1/account/info/self', [AccountController::class, 'selfAccountInfo'])->middleware('auth:web,api');
    Route::get('/v1/account/info/{id}', [AccountController::class, 'getAccountInfo'])->middleware(['auth:web,api', 'throttle:api']);
    Route::get('/v1/account/state/{id}', [AccountController::class, 'getRelationshipState'])->middleware(['auth:web,api', 'throttle:api']);
    Route::get('/v1/account/username/{id}', [WebPublicController::class, 'getAccountInfoByUsername'])->middleware('throttle:api');
    Route::post('/v1/account/block/{id}', [AccountController::class, 'accountBlock'])->middleware('auth:web,api');
    Route::post('/v1/account/unblock/{id}', [AccountController::class, 'accountUnblock'])->middleware('auth:web,api');
    Route::get('/v1/account/followers/{id}', [AccountController::class, 'accountFollowers'])->middleware('auth:web,api');
    Route::get('/v1/account/following/{id}', [AccountController::class, 'accountFollowing'])->middleware('auth:web,api');
    Route::get('/v1/account/friends/{id}', [AccountController::class, 'accountFriends'])->middleware('auth:web,api');
    Route::get('/v1/account/suggested/{id}', [AccountController::class, 'accountSuggestedFollows'])->middleware('auth:web,api');
    Route::post('/v1/account/follow/{id}', [AccountController::class, 'follow'])->middleware('auth:web,api');
    Route::post('/v1/account/unfollow/{id}', [AccountController::class, 'unfollow'])->middleware('auth:web,api');
    Route::post('/v1/account/undo-follow-request/{id}', [AccountController::class, 'undoFollowRequest'])->middleware('auth:web,api');

    // Notifications
    Route::get('/v1/account/notifications', [AccountController::class, 'notifications'])->middleware('auth:web,api');
    Route::get('/v1/account/notifications/count', [AccountController::class, 'notificationUnreadCount'])->middleware('auth:web,api');
    Route::post('/v1/account/notifications/mark-all-read', [AccountController::class, 'markAllNotificationsAsRead'])->middleware('auth:web,api');
    Route::post('/v1/account/notifications/{id}/read', [AccountController::class, 'markNotificationAsRead'])->middleware('auth:web,api');

    // Settings
    Route::get('/v1/account/settings/privacy', [SettingsController::class, 'getPrivacy'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/privacy', [SettingsController::class, 'updatePrivacy'])->middleware('auth:web,api');
    Route::get('/v1/account/settings/birthdate', [SettingsController::class, 'checkBirthdate'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/birthdate', [SettingsController::class, 'setBirthdate'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/bio', [SettingsController::class, 'storeBio'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/update-avatar', [SettingsController::class, 'updateAvatar'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/delete-avatar', [SettingsController::class, 'deleteAvatar'])->middleware('auth:web,api');
    Route::get('/v1/account/settings/security-config', [SettingsController::class, 'securityConfig'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/update-password', [SettingsController::class, 'updatePassword'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/disable-2fa', [SettingsController::class, 'disableTwoFactor'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/setup-2fa', [SettingsController::class, 'setupTwoFactor'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/confirm-2fa', [SettingsController::class, 'confirmTwoFactor'])->middleware('auth:web,api');
    Route::get('/v1/account/settings/total-blocked-accounts', [SettingsController::class, 'totalBlockedAccounts'])->middleware('auth:web,api');
    Route::get('/v1/account/settings/blocked-accounts', [SettingsController::class, 'blockedAccounts'])->middleware('auth:web,api');
    Route::post('/v1/account/settings/blocked-account-search', [SettingsController::class, 'blockedAccountSearch'])->middleware(['auth:web,api', 'throttle:autocomplete']);
    Route::get('/v1/account/settings/email', [EmailChangeController::class, 'getEmailSettings'])->middleware(['auth:web,api']);
    Route::post('/v1/account/settings/email/update', [EmailChangeController::class, 'changeEmail'])->middleware(['auth:web,api']);
    Route::post('/v1/account/settings/email/cancel', [EmailChangeController::class, 'cancelEmailChange'])->middleware(['auth:web,api']);
    Route::post('/v1/account/settings/email/verify', [EmailChangeController::class, 'verifyEmailChange'])->middleware(['auth:web,api']);
    Route::post('/v1/account/settings/email/resend', [EmailChangeController::class, 'resendEmailChange'])->middleware(['auth:web,api']);

    // Account Data
    Route::get('/v1/account/data/insights', [AccountDataController::class, 'getDataInsights'])->middleware(['auth:web,api']);
    Route::get('/v1/account/data/settings', [AccountDataController::class, 'getDataSettings'])->middleware(['auth:web,api']);
    Route::put('/v1/account/data/settings', [AccountDataController::class, 'updateDataSettings'])->middleware(['auth:web,api']);
    Route::post('/v1/account/data/export/full', [AccountDataController::class, 'requestFullExport'])->name('export.full')->middleware(['auth:web,api']);
    Route::post('/v1/account/data/export/selective', [AccountDataController::class, 'requestSelectiveExport'])->name('export.selective')->middleware(['auth:web,api']);
    Route::get('/v1/account/data/export/history', [AccountDataController::class, 'getExportHistory'])->name('export.history')->middleware(['auth:web,api']);
    Route::get('/v1/account/data/export/{id}/download', [AccountDataController::class, 'downloadExport'])->name('export.download')->middleware(['auth:web,api']);

    // Account Feeds
    Route::get('/v1/feed/account/self', [FeedController::class, 'selfAccountFeed'])->middleware('auth:web,api');
    Route::get('/v1/feed/account/{id}/cursor', [FeedController::class, 'getAccountFeedWithCursor'])->middleware('throttle:api');
    Route::get('/v1/feed/account/{id}', [WebPublicController::class, 'getAccountFeed'])->middleware('throttle:api');

    // Explore Feed
    Route::get('/v1/explore/tags', [ExploreController::class, 'getTrendingTags'])->middleware('throttle:api');
    Route::get('/v1/explore/tag-feed/{id}', [ExploreController::class, 'getTagFeed'])->middleware('throttle:api');

    Route::get('/v1/tags/video/{id}', [WebPublicController::class, 'getVideoTags'])->middleware('throttle:api');

    // Global Feeds
    Route::get('/v1/feed/for-you', [FeedController::class, 'getForYouFeed'])->middleware('auth:web,api');
    Route::get('/v1/feed/following', [FeedController::class, 'getFollowingFeed'])->middleware('auth:web,api');

    // Video Comments
    Route::post('/v1/comments/like/{vid}/{id}', [VideoController::class, 'storeCommentLike'])->middleware('auth:web,api');
    Route::post('/v1/comments/like/{vid}/{pid}/{id}', [VideoController::class, 'storeCommentReplyLike'])->middleware('auth:web,api');
    Route::post('/v1/comments/unlike/{vid}/{pid}/{id}', [VideoController::class, 'storeCommentReplyUnlike'])->middleware('auth:web,api');
    Route::post('/v1/comments/unlike/{vid}/{id}', [VideoController::class, 'storeCommentUnlike'])->middleware('auth:web,api');
    Route::post('/v1/comments/delete/{vid}/{id}', [VideoController::class, 'deleteComment'])->middleware('auth:web,api');
    Route::post('/v1/comments/delete/{vid}/{pid}/{id}', [VideoController::class, 'deleteCommentReply'])->middleware('auth:web,api');
    Route::post('/v1/video/comments/reply/edit/{id}', [VideoController::class, 'storeCommentReplyUpdate'])->middleware('auth:web,api');
    Route::post('/v1/video/comments/edit/{id}', [VideoController::class, 'storeCommentUpdate'])->middleware('auth:web,api');
    Route::post('/v1/video/comments/{id}', [VideoController::class, 'storeComment'])->middleware('auth:web,api');
    Route::get('/v1/video/comments/{id}', [WebPublicController::class, 'comments'])->middleware('throttle:api');
    Route::get('/v1/video/comments/{vid}/replies', [WebPublicController::class, 'commentsThread'])->middleware('throttle:api');
    Route::get('/v1/comments/history/{vid}/{cid}', [VideoController::class, 'showCommentsHistory']);
    Route::get('/v1/comments/history/{vid}/{cid}/{id}', [VideoController::class, 'showCommentReplyHistory']);
    Route::get('/v1/video/comments/{videoId}/comment/{commentId}', [WebPublicController::class, 'getCommentById'])->middleware('throttle:api');
    Route::get('/v1/video/comments/{videoId}/reply/{replyId}', [WebPublicController::class, 'getReplyById'])->middleware('throttle:api');

    // Videos
    Route::post('/v1/video/edit/{id}', [VideoController::class, 'update'])->middleware('auth:web,api');
    Route::post('/v1/video/delete/{id}', [VideoController::class, 'destroy'])->middleware('auth:web,api');
    Route::post('/v1/video/like/{id}', [VideoController::class, 'like'])->middleware('auth:web,api');
    Route::post('/v1/video/unlike/{id}', [VideoController::class, 'unlike'])->middleware('auth:web,api');
    Route::get('/v1/video/history/{id}', [VideoController::class, 'showVideoHistory'])->middleware('throttle:api');
    Route::get('/v1/video/{id}', [WebPublicController::class, 'showVideo'])->middleware('throttle:api');

    // Autocomplete
    Route::get('/v1/autocomplete/tags', [VideoController::class, 'showAutocompleteTags'])->middleware(['auth:web,api', 'throttle:autocomplete']);
    Route::get('/v1/autocomplete/accounts', [VideoController::class, 'showAutocompleteAccounts'])->middleware(['auth:web,api', 'throttle:autocomplete']);

    // Languages
    Route::get('/v1/i18n/list', [WebPublicController::class, 'getLanguagesList']);

    // Reports
    Route::post('/v1/report', [ReportController::class, 'store'])->middleware(['auth:web,api', 'throttle:api']);

    // Admin
    Route::prefix('/v1/admin')->middleware(['auth:web,api', AdminOnlyAccess::class])->group(function () {

        Route::middleware(['auth:web,api'])->group(function () {
            Route::get('pages', [PageController::class, 'index']);
            Route::post('pages', [PageController::class, 'store']);
            Route::get('pages/{id}', [PageController::class, 'show']);
            Route::put('pages/{id}', [PageController::class, 'update']);
            Route::delete('pages/{id}', [PageController::class, 'destroy']);
        });

        Route::get('/videos', [AdminController::class, 'videos'])->middleware('auth:web,api');
        Route::get('/comments', [AdminController::class, 'comments'])->middleware('auth:web,api');
        Route::get('/videos/{id}/comments', [AdminController::class, 'videoCommentsShow'])->middleware('auth:web,api');
        Route::post('/comments/{id}/delete', [AdminController::class, 'videoCommentsDelete'])->middleware('auth:web,api');
        Route::post('/videos/{id}/moderate', [AdminController::class, 'videoModerate'])->middleware('auth:web,api');
        Route::get('/video/{id}', [AdminController::class, 'videoShow'])->middleware('auth:web,api');
        Route::get('/profiles', [AdminController::class, 'profiles'])->middleware('auth:web,api');
        Route::get('/hashtags', [AdminController::class, 'hashtags'])->middleware('auth:web,api');
        Route::post('/hashtags/{id}/update', [AdminController::class, 'hashtagsUpdate'])->middleware('auth:web,api');
        Route::get('/reports', [AdminController::class, 'reports'])->middleware('auth:web,api');
        Route::get('/reports/{id}', [AdminController::class, 'reportShow'])->middleware('auth:web,api');
        Route::post('/reports/{id}/update-admin-notes', [AdminController::class, 'reportUpdateAdminNotes'])->middleware('auth:web,api');
        Route::post('/reports/{id}/mark-nsfw', [AdminController::class, 'reportUpdateMarkAsNsfw'])->middleware('auth:web,api');
        Route::post('/reports/{id}/comment-delete', [AdminController::class, 'reportDeleteComment'])->middleware('auth:web,api');
        Route::post('/reports/{id}/comment-reply-delete', [AdminController::class, 'reportDeleteCommentReply'])->middleware('auth:web,api');
        Route::post('/reports/{id}/dismiss', [AdminController::class, 'reportDismiss'])->middleware('auth:web,api');
        Route::post('/reports/{id}/dismiss-all-by-account', [AdminController::class, 'reportDismissAllByAccount'])->middleware('auth:web,api');
        Route::post('/reports/{id}/video-delete', [AdminController::class, 'reportDeleteVideo'])->middleware('auth:web,api');
        Route::get('/profiles/{id}', [AdminController::class, 'profileShow'])->middleware('auth:web,api');
        Route::post('/profiles/{id}/permissions', [AdminController::class, 'profilePermissionUpdate'])->middleware('auth:web,api');
        Route::post('/profiles/{id}/admin_note', [AdminController::class, 'profileAdminNoteUpdate'])->middleware('auth:web,api');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('auth:web,api');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('auth:web,api');
        Route::post('/settings/update-logo', [AdminSettingsController::class, 'updateLogo'])->middleware('auth:web,api');
        Route::post('/settings/delete-logo', [AdminSettingsController::class, 'deleteLogo'])->middleware('auth:web,api');
        Route::get('/instances', [AdminController::class, 'instances'])->middleware('auth:web,api');
        Route::get('/instances/stats', [AdminController::class, 'instanceStats'])->middleware('auth:web,api');
        Route::post('/instances/create', [AdminController::class, 'instanceCreate'])->middleware('auth:web,api');
        Route::post('/instances/bulk-create', [AdminController::class, 'instanceBulkCreate'])->middleware('auth:web,api');
        Route::get('/instances/{id}', [AdminController::class, 'instanceShow'])->middleware('auth:web,api');
        Route::get('/instances/{id}/users', [AdminController::class, 'instanceShowUsers'])->middleware('auth:web,api');
        Route::get('/instances/{id}/videos', [AdminController::class, 'instanceShowVideos'])->middleware('auth:web,api');
        Route::get('/instances/{id}/comments', [AdminController::class, 'instanceShowComments'])->middleware('auth:web,api');
        Route::post('/instances/{id}/update-admin-notes', [AdminController::class, 'updateInstanceAdminNotes'])->middleware('auth:web,api');
        Route::post('/instances/{id}/settings', [AdminController::class, 'updateInstanceSettings'])->middleware('auth:web,api');
        Route::get('/instances/{id}/reports', [AdminController::class, 'showInstanceReports'])->middleware('auth:web,api');
        Route::post('/instances/{id}/refresh', [AdminController::class, 'updateInstanceRefreshData'])->middleware('auth:web,api');
        Route::post('/instances/{id}/suspend', [AdminController::class, 'instanceSuspend'])->middleware('auth:web,api');
        Route::post('/instances/{id}/activate', [AdminController::class, 'instanceActivate'])->middleware('auth:web,api');
    });

    Route::any('{any}', function () {
        return response()->json(['message' => 'Not Found.'], 404);
    })->where('any', '.*');
});

Route::prefix('/ap')->middleware([AuthorizedFetch::class])->group(function () {
    Route::get('actor', [InstanceActorController::class, 'show'])->middleware(FederationEnabled::class);
    Route::post('inbox', [SharedInboxController::class, 'sharedInbox']);
    Route::post('actor/inbox', [SharedInboxController::class, 'sharedInbox']);
    Route::get('actor/outbox', [InstanceActorController::class, 'outbox'])->middleware(FederationEnabled::class);
    Route::get('users/{actor}', [ActorController::class, 'show'])->middleware(AuthorizedFetch::class);
    Route::post('users/{actor}/inbox', [InboxController::class, 'userInbox']);
    Route::get('users/{actor}/outbox', [ActorController::class, 'outbox'])->middleware(AuthorizedFetch::class);
    Route::get('users/{actor}/followers', [ActorController::class, 'followers'])->middleware(AuthorizedFetch::class);
    Route::get('users/{actor}/following', [ActorController::class, 'following'])->middleware(AuthorizedFetch::class);
    Route::get('users/{actor}/video/{id}', [ObjectController::class, 'showVideoObject'])->middleware(AuthorizedFetch::class);
    Route::get('users/{actor}/comment/{id}', [ObjectController::class, 'showCommentObject'])->middleware(AuthorizedFetch::class);
    Route::get('users/{actor}/reply/{id}', [ObjectController::class, 'showReplyObject'])->middleware(AuthorizedFetch::class);
});
