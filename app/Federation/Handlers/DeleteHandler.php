<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\StarterKitAccount;
use App\Models\Video;
use App\Services\DmInboundService;
use App\Services\StarterKitService;
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
                    'FeatureAuthorization' => $this->handleFeatureAuthorizationDelete($activity, $actor, $objectUrl),
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

        if ($actor && (string) $account->id !== (string) $actor->id) {
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
            if ($actor && (string) $account->id !== (string) $actor->id) {
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
            if ($actor && (string) $comment->profile_id !== (string) $actor->id) {
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
            if ($actor && (string) $video->profile_id !== (string) $actor->id) {
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
            if ($actor && (string) $commentReply->profile_id !== (string) $actor->id) {
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

        if ($actor && app(DmInboundService::class)->handleDelete($activity, $actor)) {
            return true;
        }

        if (config('logging.dev_log')) {
            Log::info('Delete received for unknown object', ['object_url' => $objectUrl]);
        }

        return true;
    }

    private function handleFeatureAuthorizationDelete(array $activity, ?Profile $actor, $objectUrl)
    {
        if (! $actor) {
            if (config('logging.dev_log')) {
                Log::warning('Rejecting FeatureAuthorization delete — no resolved actor');
            }

            return true;
        }

        $account = StarterKitAccount::where('attestation_url', $objectUrl)->first();

        if (! $account) {
            if (config('logging.dev_log')) {
                Log::info('Delete FeatureAuthorization received for unknown stamp', [
                    'object_url' => $objectUrl,
                ]);
            }

            return true;
        }

        if ((string) $account->profile_id !== (string) $actor->id) {
            if (config('logging.dev_log')) {
                Log::warning('Rejecting FeatureAuthorization delete by non-owner', [
                    'actor' => $actor->id,
                    'account_profile' => $account->profile_id,
                ]);
            }

            return true;
        }

        $kit = $account->starterKit;

        $interactingObject = data_get($activity, 'object.interactingObject');
        if ($kit->getPermalink() !== $interactingObject) {
            if (config('logging.dev_log')) {
                Log::warning('FeatureAuthorization delete interactingObject mismatch', [
                    'expected' => $kit->getPermalink(),
                    'received' => $interactingObject,
                ]);
            }

            return true;
        }

        $account->delete();

        $kit->syncAccountCount();
        app(StarterKitService::class)->forget($kit->id);

        if (config('logging.dev_log')) {
            Log::info('Successfully handled Delete FeatureAuthorization activity', [
                'object_url' => $objectUrl,
                'activity_id' => $activity['id'] ?? 'unknown',
            ]);
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
