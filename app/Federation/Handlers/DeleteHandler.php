<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteHandler extends BaseHandler
{
    public function handle(array $activity, ?Profile $actor = null, ?Profile $target = null)
    {
        $objectUrl = is_array($activity['object']) ? $activity['object']['id'] : $activity['object'];

        try {
            DB::beginTransaction();

            if (is_array($activity['object']) && isset($activity['object']['type'])) {
                $result = match (data_get($activity, 'object.type')) {
                    'Person' => $this->handleProfileDelete($activity, $actor, $objectUrl),
                    'Tombstone' => $this->handleTombstoneDelete($activity, $actor, $objectUrl),
                    default => $this->handleGenericDelete($activity, $actor, $objectUrl)
                };
            } else {
                $result = $this->handleTombstoneDelete($activity, $actor, $objectUrl);
            }

            DB::commit();

            return;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Delete activity', [
                    'object' => $objectUrl,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    private function handleProfileDelete(array $activity, ?Profile $actor, $objectUrl)
    {
        $account = Profile::whereUri($objectUrl)->whereLocal(false)->first();

        if (! $account) {
            return true;
        }

        if ($actor && $account->id !== $actor->id) {
            if (config('logging.dev_log')) {
                Log::warning('Rejecting profile delete by non-owner', ['actor' => $actor->id, 'target' => $account->id]);
            }

            return true;
        }

        $account->delete();

        if (config('logging.dev_log')) {
            Log::info('Successfully handled Delete account activity', [
                'object_url' => $objectUrl,
                'activity_id' => $activity['id'] ?? 'unknown',
            ]);
        }

        return true;
    }

    private function handleTombstoneDelete(array $activity, ?Profile $actor, $objectUrl)
    {
        $account = Profile::whereUri($objectUrl)->first();
        if ($account) {
            if ($account->local) {
                if (config('logging.dev_log')) {
                    Log::warning('Rejecting profile delete of local account', ['target' => $account->id]);
                }

                return true;
            }
            if ($actor && $account->id !== $actor->id) {
                if (config('logging.dev_log')) {
                    Log::warning('Rejecting profile delete by non-owner', ['target' => $account->id]);
                }

                return true;
            }
            $account->delete();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Delete account activity', [
                    'object_url' => $objectUrl,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return true;
        }

        $comment = Comment::where('ap_id', $objectUrl)->first();
        if ($comment) {
            if ($actor && $comment->profile_id !== $actor->id) {
                if (config('logging.dev_log')) {
                    Log::warning('Rejecting comment delete by non-owner', ['actor' => $actor->id, 'target' => $comment->profile_id]);
                }

                return true;
            }
            if (CommentReply::whereCommentId($comment->id)->exists()) {
                $comment->update(['caption' => null, 'status' => 'deleted_by_user']);
                $comment->delete();
            } else {
                $comment->forceDelete();
            }

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Delete comment activity', [
                    'object_url' => $objectUrl,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return true;
        }

        $video = Video::where('ap_id', $objectUrl)->first();
        if ($video && ! $video->is_local) {
            if ($actor && $video->profile_id !== $actor->id) {
                if (config('logging.dev_log')) {
                    Log::warning('Rejecting video delete by non-owner', ['actor' => $actor->id, 'target' => $video->profile_id]);
                }

                return true;
            }

            $s3Path = 'videos/'.$video->profile_id.'/'.$video->id.'/';
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->deleteDirectory($s3Path);
            }
            $video->delete();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Delete video activity', [
                    'object_url' => $objectUrl,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return true;
        }

        $commentReply = CommentReply::where('ap_id', $objectUrl)->first();
        if ($commentReply) {
            if ($actor && $commentReply->profile_id !== $actor->id) {
                if (config('logging.dev_log')) {
                    Log::warning('Rejecting comment reply delete by non-owner', ['actor' => $actor->id, 'target' => $commentReply->profile_id]);
                }

                return true;
            }
            $commentReply->update(['caption' => null, 'status' => 'deleted_by_user']);
            $commentReply->delete();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Delete comment_reply activity', [
                    'object_url' => $objectUrl,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return true;
        }

        if (config('logging.dev_log')) {
            Log::info('Delete received for unknown object', ['object_url' => $objectUrl]);
        }

        return true;
    }

    private function handleGenericDelete(array $activity, Profile $actor, $objectUrl)
    {
        if (config('logging.dev_log')) {
            Log::error('Failed to handle Delete activity', [
                'actor' => $actor->username,
                'object' => $objectUrl,
            ]);
        }

        return true;
    }
}
