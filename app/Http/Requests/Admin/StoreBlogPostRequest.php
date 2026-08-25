<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    /**
     * The /admin/blog routes are already behind the auth:admin + maintainer
     * middleware, which is the authorization gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('post')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('blog_posts', 'slug')->ignore($id)],
            'description' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_title' => ['nullable', 'string', 'max:120'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'locale' => ['required', 'string', 'max:8'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
