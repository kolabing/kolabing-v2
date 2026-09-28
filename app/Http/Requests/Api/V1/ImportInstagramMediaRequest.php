<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/me/instagram/media/import (BE-NF-75).
 */
class ImportInstagramMediaRequest extends FormRequest
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
        $max = max(1, (int) config('services.instagram.max_import_per_request', 10));

        return [
            'ids' => ['required', 'array', 'min:1', "max:{$max}"],
            // Instagram media ids are numeric strings.
            'ids.*' => ['required', 'string', 'regex:/^\d{1,30}$/'],
            'target' => ['sometimes', 'string', 'in:gallery,kolab'],
            'kolab_id' => ['required_if:target,kolab', 'nullable', 'uuid'],
        ];
    }
}
