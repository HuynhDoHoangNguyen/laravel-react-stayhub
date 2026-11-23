<?php

namespace App\Services\HostReport;

use App\DTOs\HostReport\Responses\CustomerHostReportResponse;
use App\Enums\BookingStatus;
use App\Enums\HostReportReason;
use App\Models\Booking;
use App\Models\HostReport;
use App\Models\User;
use App\Validators\HostReport\HostReportValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class CustomerHostReportService
{
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function __construct(
        private readonly HostReportValidator $validator,
        private readonly HostReportEvidenceService $evidence,
    ) {}

    public function current(User $actor, int $bookingId): array
    {
        $booking = $this->ownedBooking($actor, $bookingId)->firstOrFail();
        $report = $this->reportFor($booking);
        $eligibility = $this->eligibility($booking);
        $blockedReason = $report ? 'ALREADY_SUBMITTED' : $eligibility['blocked_reason'];

        return [
            'can_submit' => $blockedReason === null,
            'blocked_reason' => $blockedReason,
            'deadline' => $eligibility['deadline'],
            'reasons' => $blockedReason === null ? array_map(
                static fn (HostReportReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
                $eligibility['reasons'],
            ) : [],
            'report' => $report ? CustomerHostReportResponse::fromReport($report) : null,
        ];
    }

    public function submit(User $actor, int $bookingId, array $input): CustomerHostReportResponse
    {
        $hasEvidence = ($input['evidence'] ?? null) !== null;
        if ($hasEvidence && DB::transactionLevel() !== 0) {
            throw new LogicException('Evidence upload requires a root transaction.');
        }
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $bookingId, $input, &$path): CustomerHostReportResponse {
                return $this->createReport($actor, $bookingId, $input, $path);
            }, $hasEvidence ? 1 : 3);
        } catch (Throwable $failure) {
            if ($path !== null) {
                $this->evidence->cleanupUncommitted($path, $bookingId);
            }
            throw $failure;
        }
    }

    private function createReport(User $actor, int $bookingId, array $input, ?string &$path): CustomerHostReportResponse
    {
        $booking = $this->ownedBooking($actor, $bookingId)->lockForUpdate()->firstOrFail();
        if ($this->reportFor($booking)) {
            throw new ConflictHttpException('Đơn đặt phòng này đã có báo cáo.');
        }

        $eligibility = $this->eligibility($booking);
        if ($eligibility['blocked_reason'] !== null) {
            throw new ConflictHttpException(match ($eligibility['blocked_reason']) {
                'DEADLINE_PASSED' => 'Đơn đặt phòng đã hết hạn gửi báo cáo.',
                default => 'Trạng thái đơn đặt phòng hiện tại chưa cho phép gửi báo cáo.',
            });
        }

        $command = $this->validator->validate($input);
        $this->validator->ensureAllowedReason($command->reason, $eligibility['reasons']);
        $property = $booking->room?->property;
        if ($property === null) {
            throw new ConflictHttpException('Không xác định được cơ sở của đơn đặt phòng.');
        }

        $attributes = [
            'booking_id' => $booking->id,
            'customer_id' => $actor->id,
            'property_id' => $property->id,
            'host_id' => $property->host_id,
            'reason' => $command->reason,
            'description' => $command->description,
            'status' => HostReport::SUBMITTED,
        ];
        if ($command->evidence !== null) {
            $path = $this->evidence->newPath($bookingId, $command->evidenceMime);
            $this->evidence->store($path, $command->evidence);
            $attributes += ['evidence_path' => $path, 'evidence_mime' => $command->evidenceMime, 'evidence_size' => $command->evidence->getSize()];
        }
        $report = HostReport::query()->create($attributes);
        $reportId = $report->id;
        DB::afterCommit(static fn () => Log::info('Customer host report submitted.', ['report_id' => $reportId]));

        return CustomerHostReportResponse::fromReport($report);
    }

    /** @return Builder<Booking> */
    private function ownedBooking(User $actor, int $bookingId): Builder
    {
        return Booking::query()->forCustomer($actor->id)->whereKey($bookingId);
    }

    private function reportFor(Booking $booking): ?HostReport
    {
        return HostReport::query()->where('booking_id', $booking->id)->first();
    }

    /** @return array{deadline: string, blocked_reason: ?string, reasons: list<HostReportReason>} */
    private function eligibility(Booking $booking): array
    {
        $today = now(self::TIMEZONE)->toDateString();
        $deadline = CarbonImmutable::parse($booking->check_out_date->toDateString(), self::TIMEZONE)
            ->addDays(30)->toDateString();
        $blockedReason = match (true) {
            !in_array($booking->status, [BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN, BookingStatus::COMPLETED], true) => 'BOOKING_STATUS',
            $today > $deadline => 'DEADLINE_PASSED',
            default => null,
        };

        $reasons = HostReportReason::cases();
        if ($booking->status === BookingStatus::CONFIRMED && $today < $booking->check_in_date->toDateString()) {
            $reasons = array_values(array_filter($reasons, static fn (HostReportReason $reason) => $reason !== HostReportReason::CHECK_IN_REFUSED));
        }

        return ['deadline' => $deadline, 'blocked_reason' => $blockedReason, 'reasons' => $reasons];
    }
}
