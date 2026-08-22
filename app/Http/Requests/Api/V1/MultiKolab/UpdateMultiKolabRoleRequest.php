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
        // `filled` is derived by the acceptance service and is deliberately
        // NOT client-settable — the organizer may only stop (`closed`) and
        // resume (`open`) recruiting for a role.
        $rules['status'] = ['sometimes', 'string', 'in:open,closed'];

        return $rules;
    }
}
