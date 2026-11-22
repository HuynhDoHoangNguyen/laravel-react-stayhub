<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ChatTurn extends Model
{
    public const PROCESSING = 'PROCESSING';
    public const FAILED = 'FAILED';
    public const SUCCEEDED = 'SUCCEEDED';

    protected $fillable = [
        'user_id',
        'request_id',
        'payload_fingerprint',
        'status',
        'attempts',
        'processing_token',
        'lease_expires_at',
        'chat_session_id',
        'user_message_id',
        'assistant_message_id',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['payload_fingerprint', 'processing_token'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'attempts' => 'integer',
            'chat_session_id' => 'integer',
            'user_message_id' => 'integer',
            'assistant_message_id' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
