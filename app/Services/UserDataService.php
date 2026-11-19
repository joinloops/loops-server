<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Follower;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoLike;

class UserDataService
{
    public function deleteUserVideos(User $user): int
    {
        // todo: finish impl
        return 0;
    }

    public function deleteUserComments(User $user): int
    {
        // todo: finish impl
        return 0;
    }

    public function deleteUserWatchHistory(User $user): int
    {
        // todo: finish impl
        return 0;
    }

    public function deleteAllUserData(User $user): int
    {
        // todo: finish impl
        return 0;
    }

    public function generateDataExport(User $user, string $type): array
    {
        $data = [];

        switch ($type) {
            case 'complete':
                $data = $this->generateCompleteExport($user);
                break;
            case 'videos':
                $data = $this->generateVideosExport($user);
                break;
            case 'profile':
                $data = $this->generateProfileExport($user);
                break;
            case 'interactions':
                $data = $this->generateInteractionsExport($user);
                break;
            case 'followers':
                $data = $this->generateFollowersExport($user);
                break;
        }

        return $data;
    }

    private function generateCompleteExport(User $user): array
    {
        return [
            'user_profile' => $this->generateProfileExport($user),
            'videos' => $this->generateVideosExport($user),
            'interactions' => $this->generateInteractionsExport($user),
            'social_connections' => $this->generateFollowersExport($user),
            'export_metadata' => [
                'exported_at' => now()->format('F j, Y, g:i a'),
                'export_version' => '1.0',
                'platform' => 'Loops',
            ],
        ];
    }

    private function generateProfileExport(User $user): array
    {
        $settings = $user->getOrCreateDataSettings();
        $profile = $user->profile;

        return [
            'basic_info' => [
                'id' => $profile->id,
                'username' => $user->username,
                'email' => $user->email,
                'display_name' => $user->name,
                'bio' => $profile->bio,
                'created_at' => $user->created_at->format('F j, Y, g:i a'),
            ],
            'settings' => [
                'data_retention_period' => $settings->data_retention_period,
                'analytics_tracking' => $settings->analytics_tracking,
                'research_data_sharing' => $settings->research_data_sharing,
            ],
            'statistics' => [
                'videos_count' => $profile->video_count,
                'followers_count' => $profile->followers,
                'following_count' => $profile->following,
            ],
        ];
    }

    private function generateVideosExport(User $user): array
    {
        return [
            'videos' => $user->videos()->published()->get()->map(function (Video $video) {
                $cached = VideoService::getMediaData($video->id);

                return $cached ? [
                    'id' => $cached['id'],
                    'caption' => $cached['caption'],
                    'url' => $cached['url'],
                    'file_size' => $video->size_kb ? $video->size_kb * 1024 : null,
                    'likes' => $cached['likes'],
                    'comments' => $cached['comments'],
                    'media' => $cached['media']['src_url'],
                    'thumbnail' => $video->thumb(),
                    'created_at' => now()->parse($cached['created_at'])->format('F j, Y, g:i a'),
                ] : [
                    'id' => (string) $video->id,
                    'caption' => $video->caption,
                    'url' => $video->shareUrl(),
                    'file_size' => $video->size_kb ? $video->size_kb * 1024 : null,
                    'likes' => $video->likes,
                    'comments' => $video->comments,
                    'media' => $video->mediaUrl(),
                    'thumbnail' => $video->thumb(),
                    'created_at' => $video->created_at->format('F j, Y, g:i a'),
                ];
            })->filter()->toArray(),
        ];
    }

    private function generateInteractionsExport(User $user): array
    {
        return [
            'comments' => $user->comments()->get()->map(function (Comment $comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->caption,
                    'video_id' => $comment->video_id,
                    'created_at' => $comment->created_at->format('F j, Y'),
                ];
            })->toArray(),
            'likes' => $user->likes()->get()->map(function (VideoLike $like) {
                return [
                    'video_id' => $like->video_id,
                    'liked_at' => $like->created_at->format('F j, Y'),
                ];
            })->toArray(),
        ];
    }

    private function generateFollowersExport(User $user): array
    {
        return [
            'followers' => $user->followers()->get()->map(function (Follower $follower) {
                $acct = AccountService::get($follower->profile_id);
                if (! $acct) {
                    return;
                }

                return [
                    'username' => $acct['username'],
                    'display_name' => $acct['name'],
                    'url' => $acct['url'],
                    'followed_at' => $follower->created_at->format('F j, Y'),
                ];
            })->filter()->toArray(),
            'following' => $user->following()->get()->map(function (Follower $following) {
                $acct = AccountService::get($following->following_id);
                if (! $acct) {
                    return;
                }

                return [
                    'username' => $acct['username'],
                    'display_name' => $acct['name'],
                    'url' => $acct['url'],
                    'followed_at' => $following->created_at->format('F j, Y'),
                ];
            })->filter()->toArray(),
        ];
    }
}
