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
     * The admin form always posts the full FAQ list (blank rows included), so
     * drop rows with neither a question nor an answer and store "no FAQ" as
     * null. A missing `faq` key therefore means "cleared", not "unchanged".
     */
    protected function prepareForValidation(): void
    {
        $rows = collect(is_array($this->input('faq')) ? $this->input('faq') : [])
            ->filter(fn ($row) => is_array($row)
                && (filled($row['question'] ?? null) || filled($row['answer'] ?? null)))
            ->map(fn (array $row) => [
                'question' => trim((string) ($row['question'] ?? '')),
                'answer' => trim((string) ($row['answer'] ?? '')),
            ])
            ->values()
            ->all();

        $this->merge(['faq' => $rows === [] ? null : $rows]);
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
            'faq' => ['nullable', 'array', 'max:20'],
            'faq.*.question' => ['required', 'string', 'max:300'],
            'faq.*.answer' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'faq.*.question' => 'FAQ question',
            'faq.*.answer' => 'FAQ answer',
        ];
    }
}
