<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MultiKolab;

/**
 * Same lenient shape as creation — every field optional/`sometimes`, since a
 * `PATCH` only sends the fields being changed.
 */
class UpdateMultiKolabEventRequest extends CreateMultiKolabEventRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'string', 'max:255'];

        return $rules;
    }
}
