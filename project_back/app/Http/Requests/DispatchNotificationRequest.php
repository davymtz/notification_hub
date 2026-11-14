<?php

namespace App\Http\Requests;

use App\Services\NotificationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DispatchNotificationRequest extends FormRequest
{
    protected $stopOnFirstFailure = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(NotificationRegistry $registry): array
    {
        $supportedChannels = $registry->getRegisteredChannels();

        return [
            'event_type' => ['required', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in($supportedChannels)],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'payload' => ['required', 'array'],
            'payload.email' => ['nullable', 'email'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        // Payload is an extensible channel-owned array. Preserve its keys after
        // parent validation; payload.email must not filter other channel fields.
        $validator->excludeUnvalidatedArrayKeys = false;

        $validator->after(function ($validator) {
            $channels = $this->input('channels', []);
            $payload = $this->input('payload', []);

            // Let the array rules report malformed top-level values.
            if (!is_array($channels) || !is_array($payload)) {
                return;
            }

            // Validate email-specific payload requirements
            if (in_array('email', $channels)) {
                if (empty($payload['email'])) {
                    $validator->errors()->add(
                        'payload.email',
                        'The email field is required when email channel is selected.'
                    );
                }
            }

            // Validate slack-specific payload requirements
            if (in_array('slack', $channels)) {
                if (empty($payload['message']) && empty($payload['title'])) {
                    $validator->errors()->add(
                        'payload.message',
                        'The message or title field is required when slack channel is selected.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'event_type.required' => 'The event_type field is required.',
            'channels.required' => 'The channels field is required.',
            'channels.min' => 'At least one channel must be specified.',
            'idempotency_key.required' => 'The idempotency_key field is required.',
            'payload.required' => 'The payload field is required.',
        ];
    }
}
