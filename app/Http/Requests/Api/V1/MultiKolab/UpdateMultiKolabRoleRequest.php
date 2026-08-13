<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MultiKolab;

class UpdateMultiKolabRoleRequest extends AddMultiKolabRoleRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'string', 'max:255'];
        $rules['eligible_account_type'] = ['sometimes', 'string', 'in:business,community,either'];

        return $rules;
    }
}
