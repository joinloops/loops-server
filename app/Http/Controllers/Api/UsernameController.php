<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UsernameChangeException;
use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\UsernameChangeService;
use Illuminate\Http\Request;

class UsernameController extends Controller
{
    use ApiHelpers;

    public function __construct(protected UsernameChangeService $service) {}

    public function show(Request $request)
    {
        return $this->data($this->service->status($request->user()));
    }

    public function check(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:64',
        ]);

        return $this->data($this->service->check($request->user(), $request->input('username')));
    }

    public function update(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:64',
            'password' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        try {
            $profile = $this->service->change($user, $request->input('username'), $request->input('password'));
        } catch (UsernameChangeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reason' => $e->reason,
                'errors' => [
                    $e->reason === 'password' ? 'password' : 'username' => [$e->getMessage()],
                ],
                ...$e->meta,
            ], $e->status);
        }

        $account = AccountService::get($profile->id);
        $account['is_owner'] = true;
        $account['likes_count'] = (int) AccountService::getAccountLikesCount($profile->id);

        return $this->data([
            'account' => $account,
            'status' => $this->service->status($user->fresh()),
        ]);
    }
}
