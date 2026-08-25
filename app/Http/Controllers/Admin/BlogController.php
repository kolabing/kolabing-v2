<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Http\Requests\Admin\UpdateBlogPostRequest;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.index', [
            'posts' => BlogPost::query()
                ->orderByRaw('published_at is null desc')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.edit', [
            'post' => new BlogPost(['locale' => 'en', 'author_name' => 'Kolabing Team']),
        ]);
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.edit', ['post' => $post]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        BlogPost::query()->create($request->validated());

        return redirect()->route('admin.blog.index')->with('status', 'Post created.');
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $post->update($request->validated());

        return redirect()->route('admin.blog.index')->with('status', 'Post updated.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.blog.index')->with('status', 'Post deleted.');
    }
}
