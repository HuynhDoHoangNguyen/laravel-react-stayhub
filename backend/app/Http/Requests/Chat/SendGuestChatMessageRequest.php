<?php

namespace App\Http\Requests\Chat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

final class SendGuestChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(config('chatbot.guest.enabled') === true, 404);
        $actor = $this->user('web');

        return $this->hasSession() && ! $this->bearerToken()
            && ($actor === null || ($actor->role === UserRole::CUSTOMER && $actor->status === UserStatus::ACTIVE));
    }

    public function validationData(): array
    {
        $input = $this->isJson() ? json_decode($this->getContent(), true) : null;
        if (! is_array($input)) {
            return [];
        }
        if (is_string($input['message'] ?? null)) {
            $input['message'] = trim($input['message']);
        }

        return $input;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'string', 'uuid'],
            'message' => ['required', 'string', 'max:500'],
        ];
    }
}
