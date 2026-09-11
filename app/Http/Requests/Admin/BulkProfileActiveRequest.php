<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The selection behind a bulk activate / deactivate on /admin/users (#256).
 *
 * Every id must exist. A stale or tampered one fails the whole request rather
 * than being quietly skipped — an admin who thinks they switched twenty accounts
 * off must not have switched nineteen.
 */
class BulkProfileActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile_ids' => ['required', 'array', 'min:1'],
            'profile_ids.*' => ['required', 'uuid', 'exists:profiles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_ids.required' => __('Select at least one account first.'),
            'profile_ids.*.exists' => __('One of the selected accounts no longer exists. Reload the page and try again.'),
        ];
    }

    /**
     * @return list<string>
     */
    public function profileIds(): array
    {
        /** @var list<string> $ids */
        $ids = array_values(array_unique($this->validated()['profile_ids']));

        return $ids;
    }
}
