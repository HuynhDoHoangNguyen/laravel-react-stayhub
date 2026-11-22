<?php

namespace App\Services\Chat;

use App\Models\Faq;
use Illuminate\Support\Str;

final class PublicFaqQueryService
{
    public const APPROVED = 'approved';

    public const UNSUPPORTED = 'unsupported';

    private const BOOKING_QUESTION = 'Làm thế nào để đặt phòng?';

    // Publication is limited to the exact seed answer approved for UC61-FAQ-01.
    private const APPROVED_ANSWER_SHA256 = 'b825aefe67f7dc40bb96642b55ba9ef223e947d3308dc62d4cfcc32947658832';

    private const MISSING = 'Chưa tìm thấy câu trả lời trong FAQ công khai.';

    public function classify(#[\SensitiveParameter] string $question): ?string
    {
        $question = $this->normalize($question);
        $explicit = str_starts_with($question, 'faq:');
        if ($explicit) {
            $question = ltrim(substr($question, strlen('faq:')));
        }
        if ($question === $this->normalize(self::BOOKING_QUESTION)) {
            return self::APPROVED;
        }

        return $explicit ? self::UNSUPPORTED : null;
    }

    public function reply(#[\SensitiveParameter] string $question): ?string
    {
        return match ($this->classify($question)) {
            self::APPROVED => $this->bookingReply(),
            self::UNSUPPORTED => self::MISSING,
            default => null,
        };
    }

    public function bookingReply(): string
    {
        $matches = Faq::query()->where('question', self::BOOKING_QUESTION)
            ->where('status', true)->limit(2)->get(['question', 'answer']);
        if ($matches->count() !== 1 || $matches[0]->question !== self::BOOKING_QUESTION) {
            return self::MISSING;
        }

        $answer = $matches[0]->answer;
        if (trim($answer) === '' || mb_strlen($answer) > 1000
            || hash('sha256', $answer) !== self::APPROVED_ANSWER_SHA256) {
            return self::MISSING;
        }

        return $answer;
    }

    private function normalize(string $question): string
    {
        $text = Str::lower(preg_replace('/\s+/u', ' ', $question));

        return rtrim(trim($text), ' ?!.');
    }
}
