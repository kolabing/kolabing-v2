<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'posts' => BlogPost::query()->published()->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(BlogPost $post): View
    {
        abort_unless($post->isPublished(), 404);

        return view('blog.show', [
            'post' => $post,
            'related' => BlogPost::query()->published()
                ->whereKeyNot($post->getKey())
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
