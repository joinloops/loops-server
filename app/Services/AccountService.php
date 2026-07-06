<?php

namespace App\Services;

use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AccountService
{
    const CACHE_KEY = 'api:s:account:v2:';

    const ACCOUNT_LIKES_CACHE_KEY = 'api:s:account:likes_count:';

    public static function get($id, $grace = true)
    {
        $res = Cache::remember(self::CACHE_KEY.$id, now()->addDays(7), function () use ($id) {
            $req = new Request;
            $profile = Profile::active()->find($id);
            if (! $profile) {
                return false;
            }

            return (new ProfileResource($profile))->toArray($req);
        });

        if ($res) {
            return $res;
        }

        return $grace ? [] : false;
    }

    public static function getByUserId($uid, $grace = true, $compact = false)
    {
        $id = Cache::remember(self::CACHE_KEY.'userid2pid:'.$uid, now()->addDays(7), function () use ($uid) {
            $user = User::find($uid);

            return $user->profile_id;
        });

        $res = self::get($id, $grace);

        if (! $res) {
            return $grace ? [] : false;
        }

        if ($compact) {
            return [
                'id' => (string) $res['id'],
                'name' => $res['name'],
                'username' => $res['username'],
                'avatar' => $res['avatar'],
            ];
        }

        return $res;

    }

    public static function getActorId($id)
    {
        return Cache::remember(self::CACHE_KEY.'actorid:'.$id, now()->addHours(12), function () use ($id) {
            $profile = Profile::active()->find($id);
            if (! $profile) {
                return;
            }

            return $profile->getActorId();
        });
    }

    public static function compact($id, $grace = true)
    {
        $res = self::get($id);
        if (! $res) {
            return $grace ? [] : false;
        }

        return [
            'id' => (string) $res['id'],
            'name' => $res['name'],
            'display_name' => $res['name'],
            'username' => $res['username'],
            'avatar' => $res['avatar'],
        ];
    }

    public static function deletedAccount()
    {
        return [
            'id' => (string) '0',
            'name' => 'User',
            'username' => 'deleted',
            'avatar' => '/storage/avatars/default.jpg',
        ];
    }

    public static function del($id)
    {
        return Cache::forget(self::CACHE_KEY.$id);
    }

    public static function getDefaultDisplayName($id)
    {
        return 'User';
    }

    public static function validUsername($username)
    {
        $valid = self::isValidUsernameFormat($username);

        if (! $valid) {
            return false;
        }

        return (bool) (strlen($username) <= 24);
    }

    public static function validDisplayName($displayName)
    {
        $valid = self::isValidDisplayNameFormat($displayName);

        if (! $valid) {
            return false;
        }

        return (bool) (strlen($displayName) <= 30);
    }

    public static function isValidUsernameFormat($displayName)
    {
        $pattern = '/^[a-zA-Z0-9][a-zA-Z0-9._-]*[a-zA-Z0-9]$/';

        return (bool) preg_match($pattern, $displayName);
    }

    public static function isValidDisplayNameFormat($displayName)
    {
        $pattern = '/^[\p{L}\p{N}\p{Z}\p{S}\p{P}]+$/u';

        return (bool) preg_match($pattern, $displayName);
    }

    public static function getAccountLikesCount($id)
    {
        return Cache::remember(self::ACCOUNT_LIKES_CACHE_KEY.$id, now()->addHours(36), function () use ($id) {
            return Video::whereProfileId($id)->whereStatus(2)->sum('likes');
        });
    }
}
