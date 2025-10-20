<?php

namespace Soha\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => __('soha-chat::validation.message_required'),
            'message.string' => __('soha-chat::validation.message_string'),
            'message.max' => __('soha-chat::validation.message_max'),
        ];
    }
}
