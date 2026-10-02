<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\KolabInviteService;
use Illuminate\Foundation\Http\FormRequest;

class SendKolabInvitesRequest extends FormRequest
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
        return [
            'kolab' => ['required', 'uuid', 'exists:kolabs,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.KolabInviteService::MAX_LIMIT],
            'weekly_cap' => ['nullable', 'integer', 'min:1', 'max:'.KolabInviteService::MAX_WEEKLY_CAP],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kolab.required' => 'Pick a Kolab.',
            'kolab.exists' => 'That Kolab no longer exists.',
        ];
    }
}
