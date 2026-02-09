<?php

namespace App\Services;

use App\Models\Page;
use Cache;
use Storage;

class PageService
{
    const CACHE_KEY = 'api:s:page:';

    public static function getActiveSideLinks($refresh = false)
    {
        $key = self::CACHE_KEY.'activeSideLinks';

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::rememberForever($key, function () {
            $links = ['side_menu_guest', 'side_menu_user', 'side_menu_all', 'footer_guest', 'footer_user', 'footer_all'];

            return Page::whereIn('location', $links)
                ->where('system_page', false)
                ->published()
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->title,
                        'slug' => $item->slug,
                        'location' => $item->location,
                    ];
                })
                ->toArray();
        });
    }

    public static function systemTemplates()
    {
        return [
            'terms.md',
            'privacy.md',
            'about.md',
            'community-guidelines.md',
        ];
    }

    public static function getTemplate($name)
    {
        if (! in_array($name, self::systemTemplates())) {
            return;
        }

        $file = 'page-templates/'.$name;

        if (Storage::disk('app')->exists($file)) {
            return Storage::disk('app')->get($file);
        }

    }

    public static function getTemplateAndReplaceAppUrl($name)
    {
        $tmpl = self::getTemplate($name);

        if (! $tmpl) {
            return;
        }

        $appUrl = config('app.url');
        $baseDomain = parse_url($appUrl, PHP_URL_HOST);

        return str_replace('example.org', $baseDomain, $tmpl);
    }
}
