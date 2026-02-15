<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Hashtag;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Video;

class ReportController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(StoreReportRequest $request)
    {
        $user = $request->user();
        $pid = $user->profile_id;

        if ($user->is_admin === true && config('loops.reports.rate_limits.admin_exempt')) {
            // skip for exempt admins
        } else {
            $this->checkRateLimit($pid);
        }

        $type = $request->input('type');
        $id = $request->input('id');
        $key = $request->input('key');
        $extra = [
            'report_type' => $key,
        ];

        if ($request->filled('comment')) {
            $extra['user_message'] = $this->purifyText($request->input('comment'));
        }

        if ($type === 'video') {
            Video::published()->where('profile_id', '!=', $pid)->findOrFail($id);
            Report::firstOrCreate([
                'reporter_profile_id' => $pid,
                'reported_video_id' => $id,
            ], $extra);
        } elseif ($type === 'profile') {
            Profile::where('id', '!=', $pid)->findOrFail($id);
            Report::firstOrCreate([
                'reporter_profile_id' => $pid,
                'reported_profile_id' => $id,
            ], $extra);
        } elseif ($type === 'comment') {
            Comment::where('profile_id', '!=', $pid)->findOrFail($id);
            Report::firstOrCreate([
                'reporter_profile_id' => $pid,
                'reported_comment_id' => $id,
            ], $extra);
        } elseif ($type === 'reply') {
            CommentReply::where('profile_id', '!=', $pid)->findOrFail($id);
            Report::firstOrCreate([
                'reporter_profile_id' => $pid,
                'reported_comment_reply_id' => $id,
            ], $extra);
        } elseif ($type === 'hashtag') {
            $tag = Hashtag::where('name', $id)->firstOrFail();
            Report::firstOrCreate([
                'reporter_profile_id' => $pid,
                'reported_hashtag_id' => $tag->id,
            ], $extra);
        }

        return $this->success();
    }

    private function checkRateLimit(int $profileId): void
    {
        $dailyLimit = config('loops.reports.rate_limits.daily');
        $monthlyLimit = config('loops.reports.rate_limits.monthly');

        $dailyCount = Report::where('reporter_profile_id', $profileId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($dailyCount >= $dailyLimit) {
            abort(429, 'You have reached the daily limit of '.$dailyLimit.' reports. Please try again tomorrow.');
        }

        $monthlyCount = Report::where('reporter_profile_id', $profileId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($monthlyCount >= $monthlyLimit) {
            abort(429, 'You have reached the monthly limit of '.$monthlyLimit.' reports. Please try again next month.');
        }
    }
}
