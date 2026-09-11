<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReorderGalleryRequest extends FormRequest
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
        // GalleryController::MAX_GALLERY_PHOTOS is 20.
        return [
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => ['required', 'uuid'],
        ];
    }
}
