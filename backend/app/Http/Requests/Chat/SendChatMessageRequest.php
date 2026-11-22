<?php

namespace App\Http\Requests\Chat;

use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class SendChatMessageRequest extends FormRequest
{
    public function validationData(): array
    {
        // Read the original JSON types before TrimStrings and ConvertEmptyStringsToNull.
        $data = $this->isJson() ? json_decode($this->getContent(), true) : null;
        if (! is_array($data)) {
            return [];
        }
        if (is_string($data['message'] ?? null)) {
            $data['message'] = trim($data['message']);
        }

        return $data;
    }

    public function after(): array
    {
        return [static function (Validator $validator): void {
            $sessionId = $validator->getData()['session_id'] ?? null;
            if ($sessionId !== null && (! is_int($sessionId) || $sessionId < 1)) {
                $validator->errors()->add('session_id', 'Phiên chat phải là số nguyên dương.');
            }
        }];
    }

    public function rules(): array
    {
        return [
            'request_id' => ['bail', 'required', 'string', 'uuid'],
            'session_id' => ['nullable'],
            'message' => ['bail', 'required', 'string', 'max:2000'],
        ];
    }
}
