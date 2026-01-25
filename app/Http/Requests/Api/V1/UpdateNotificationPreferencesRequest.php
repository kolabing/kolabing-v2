<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_notifications' => ['sometimes', 'boolean'],
            'whatsapp_notifications' => ['sometimes', 'boolean'],
            'new_application_alerts' => ['sometimes', 'boolean'],
            'collaboration_updates' => ['sometimes', 'boolean'],
            'marketing_tips' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the notification preferences data for update.
     *
     * @return array<string, bool>
     */
    public function getPreferencesData(): array
    {
        return $this->only([
            'email_notifications',
            'whatsapp_notifications',
            'new_application_alerts',
            'collaboration_updates',
            'marketing_tips',
        ]);
    }
}
