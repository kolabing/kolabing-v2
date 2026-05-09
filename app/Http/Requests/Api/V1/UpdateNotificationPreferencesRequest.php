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
            'messages_enabled' => ['sometimes', 'boolean'],
            'applications_enabled' => ['sometimes', 'boolean'],
            'collaborations_enabled' => ['sometimes', 'boolean'],
            'rewards_enabled' => ['sometimes', 'boolean'],
            'marketing_enabled' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
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
            'messages_enabled',
            'applications_enabled',
            'collaborations_enabled',
            'rewards_enabled',
            'marketing_enabled',
            'quiet_hours_start',
            'quiet_hours_end',
            'timezone',
        ]);
    }
}
