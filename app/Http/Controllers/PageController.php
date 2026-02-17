<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AdminOnlyAccess;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(AdminOnlyAccess::class);
    }

    public function index(Request $request)
    {
        $query = Page::query();

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && in_array($request->status, ['draft', 'published'])) {
            $query->where('status', $request->status);
        }

        $query->orderBy('updated_at', 'desc');

        $perPage = $request->input('per_page', 50);
        $pages = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pages->items(),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $page = Page::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $page,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:pages,slug',
            'content' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'meta' => 'nullable|array',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        } else {
            $validated['slug'] = $validated['slug'];
        }

        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Page::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug.'-'.$counter;
            $counter++;
        }

        $page = Page::create($validated);

        PageService::getActiveSideLinks(true);

        return response()->json([
            'success' => true,
            'message' => 'Page created successfully',
            'data' => $page,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'location' => 'sometimes|nullable|in:none,side_menu_guest,side_menu_user,side_menu_all,footer_guest,footer_user,footer_all',
            'meta' => 'nullable|array',
        ]);

        $page = Page::findOrFail($id);

        abort_if($page->system_page && $validated['status'] != 'published', 422, 'System pages cannot be unpublished');

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        } else {
            $validated['slug'] = $validated['slug'];
        }

        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Page::where('slug', $validated['slug'])->where('id', '!=', $page->id)->exists()) {
            $validated['slug'] = $originalSlug.'-'.$counter;
            $counter++;
        }

        $page->update($validated);

        PageService::getActiveSideLinks(true);

        return response()->json([
            'success' => true,
            'message' => 'Page updated successfully',
            'data' => $page->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $page = Page::whereSystemPage(false)->findOrFail($id);
        $page->delete();

        PageService::getActiveSideLinks(true);

        return response()->json([
            'success' => true,
            'message' => 'Page deleted successfully',
        ]);
    }
}
