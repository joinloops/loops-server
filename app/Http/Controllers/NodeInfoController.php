<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Services\Migration\MigrationSettings;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NodeInfoController extends Controller
{
    /**
     * Display NodeInfo discovery document
     *
     * GET /.well-known/nodeinfo
     */
    public function discovery(Request $request)
    {
        return response()->json([
            'links' => [
                [
                    'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                    'href' => route('nodeinfo.2.0'),
                ],
                [
                    'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
                    'href' => route('nodeinfo.2.1'),
                ],
            ],
        ]);
    }

    /**
     * Display NodeInfo 2.0
     */
    public function nodeInfo20(Request $request)
    {
        return $this->nodeInfo('2.0');
    }

    /**
     * Display NodeInfo 2.1
     */
    public function nodeInfo21(Request $request)
    {
        return $this->nodeInfo('2.1');
    }

    /**
     * Generate NodeInfo response
     */
    protected function nodeInfo(string $version)
    {
        $settings = Cache::get('settings:public');

        $data = [
            'version' => $version,
            'software' => [
                'name' => 'loops',
                'version' => app('app_version'),
            ],
            'protocols' => ['activitypub'],
            'services' => [
                'outbound' => [],
                'inbound' => [],
            ],
            'usage' => [
                'users' => [
                    'total' => $this->getUserCount(),
                    'activeMonth' => $this->getActiveUserCount(30),
                    'activeHalfyear' => $this->getActiveUserCount(180),
                ],
                'localPosts' => $this->getPostCount(),
            ],
            'openRegistrations' => data_get($settings, 'registration', false),
            'metadata' => [
                'nodeName' => data_get($settings, 'app.name', 'Loops'),
                'nodeDescription' => data_get($settings, 'app.description', 'A Loops instance'),
                'loops' => [
                    'migration' => app(MigrationSettings::class)->publicSummary(),
                ],
            ],
        ];

        if ($version === '2.1') {
            $data['software']['repository'] = 'https://github.com/joinloops/loops-server';
            $data['software']['homepage'] = 'https://joinloops.org';
        }

        return response()->json($data)
            ->header('Content-Type', 'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/'.$version.'#"');
    }

    /**
     * Get total user count
     */
    protected function getUserCount(): int
    {
        return Cache::remember('nodeinfo:getUserCount', now()->addMinutes(30), function () {
            return User::count();
        });
    }

    /**
     * Get active user count within days
     */
    protected function getActiveUserCount(int $days): int
    {
        $key = 'nodeinfo:getActiveUserCount:days-'.$days;

        return Cache::remember($key, now()->addHours(6), function () use ($days) {
            return DB::table('user_daily_actives')
                ->where('day', '>=', now('UTC')->subDays($days)->toDateString())
                ->distinct('user_id')->count('user_id');
        });
    }

    /**
     * Get total post count
     */
    protected function getPostCount(): int
    {
        return Cache::remember('nodeinfo:getPostCount', now()->addMinutes(30), function () {
            return Video::whereStatus(2)->count();
        });
    }
}
