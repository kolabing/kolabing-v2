<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Application;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator as ValidationValidator;

class AcceptApplicationRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date', 'after:today'],
            'contact_methods' => ['sometimes', 'nullable', 'array'],
            'contact_methods.whatsapp' => ['nullable', 'string'],
            'contact_methods.email' => ['nullable', 'email'],
            'contact_methods.instagram' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            if ($validator->errors()->has('scheduled_date')) {
                return;
            }

            $application = $this->route('application');

            if (! $application instanceof Application) {
                return;
            }

            $scheduledDate = $this->input('scheduled_date');
            if (! is_string($scheduledDate) || $scheduledDate === '') {
                return;
            }

            $application->loadMissing('kolab');
            $opportunity = $application->kolab;

            if ($opportunity === null) {
                return;
            }

            try {
                $selectedDate = Carbon::parse($scheduledDate)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $start = $opportunity->availability_start?->copy()->startOfDay();
            $end = $opportunity->availability_end?->copy()->startOfDay();

            if ($start !== null && $selectedDate->lt($start)) {
                $validator->errors()->add(
                    'scheduled_date',
                    __('The scheduled date must fall within the publisher availability window.')
                );

                return;
            }

            if ($end !== null && $selectedDate->gt($end)) {
                $validator->errors()->add(
                    'scheduled_date',
                    __('The scheduled date must fall within the publisher availability window.')
                );

                return;
            }

            if ($opportunity->availability_mode !== 'recurring') {
                return;
            }

            $recurringDays = collect($opportunity->recurring_days ?? [])
                ->filter(fn (mixed $day): bool => is_numeric($day))
                ->map(fn (mixed $day): int => (int) $day)
                ->values()
                ->all();

            if ($recurringDays === []) {
                return;
            }

            if (! in_array($selectedDate->dayOfWeekIso, $recurringDays, true)) {
                $validator->errors()->add(
                    'scheduled_date',
                    __('The scheduled date must match one of the publisher recurring days.')
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_date.required' => __('validation.required', ['attribute' => 'scheduled date']),
            'scheduled_date.date' => __('validation.date', ['attribute' => 'scheduled date']),
            'scheduled_date.after' => __('validation.after', ['attribute' => 'scheduled date', 'date' => 'today']),
            'contact_methods.array' => __('validation.array', ['attribute' => 'contact methods']),
            'contact_methods.email.email' => __('validation.email', ['attribute' => 'contact email']),
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
