<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Matches profile_gallery_photos.caption (string 500, nullable). `present`
        // so an omitted key is a validation error rather than a silent no-op.
        return [
            'caption' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }
}
