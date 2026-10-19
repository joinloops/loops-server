<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\UsernameHistory;
use App\Models\UsernameReservation;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\Request;

#[ExcludeAllRoutesFromDocs]
class AdminUsernameController extends Controller
{
    use ApiHelpers;

    public function profileHistory(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);

        $history = UsernameHistory::where('profile_id', $profile->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (UsernameHistory $row) => $this->historyRow($row))
            ->values();

        $reservation = UsernameReservation::where('profile_id', $profile->id)->first();

        return $this->data($history, [
            'meta' => [
                'reservation' => $reservation ? [
                    'username' => $reservation->username,
                    'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                ] : null,
                'changes_last_year' => UsernameHistory::where('profile_id', $profile->id)
                    ->where('source', UsernameHistory::SOURCE_LOCAL_CHANGE)
                    ->where('is_revert', false)
                    ->where('started_at', '>=', now()->subDays((int) config('loops.usernames.changes.window_days', 365)))
                    ->count(),
            ],
        ]);
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
        ]);

        $username = ltrim(trim($request->input('q')), '@');

        $rows = UsernameHistory::with('profile:id,username,name,avatar,local,domain,status')
            ->where('username', $username)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (UsernameHistory $row) => [
                ...$this->historyRow($row),
                'profile' => $row->profile ? [
                    'id' => (string) $row->profile->id,
                    'username' => $row->profile->username,
                    'name' => $row->profile->name,
                    'avatar' => $row->profile->avatar,
                    'local' => (bool) $row->profile->local,
                    'status' => $row->profile->status,
                ] : null,
            ])
            ->values();

        $current = Profile::where('username', $username)->first(['id', 'username', 'name', 'local']);
        $reservation = UsernameReservation::where('username', $username)->first();

        return $this->data($rows, [
            'meta' => [
                'query' => $username,
                'current_holder_id' => $current ? (string) $current->id : null,
                'reserved_by_id' => $reservation ? (string) $reservation->profile_id : null,
                'reserved_at' => $reservation?->reserved_at?->toIso8601String(),
            ],
        ]);
    }

    protected function historyRow(UsernameHistory $row): array
    {
        return [
            'id' => (string) $row->id,
            'profile_id' => (string) $row->profile_id,
            'username' => $row->username,
            'domain' => $row->domain,
            'local' => $row->local,
            'source' => $row->source,
            'is_revert' => $row->is_revert,
            'started_at' => $row->started_at?->toIso8601String(),
            'ended_at' => $row->ended_at?->toIso8601String(),
        ];
    }
}
