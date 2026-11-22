<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Repositories\Commission\CommissionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AdminCommissionService
{
    public function __construct(
        protected readonly CommissionRepository $repository,
    ) {}

    /**
     * Danh sách toàn bộ hoa hồng hệ thống dành cho Admin.
     */
    public function listCommissions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForAdmin($filters, $perPage);
    }

    /**
     * Hàng chờ khai báo đối soát (Remittance Queue) dành cho Admin.
     */
    public function listRemittances(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateRemittancesForAdmin($filters, $perPage);
    }

    /**
     * Admin xác minh giao dịch đối soát (Verify).
     * Tuân thủ thứ tự khóa thống nhất (BookingCommission -> CommissionRemittance),
     * kiểm tra trạng thái và settlement atomic (REM-01, AC-17).
     */
    public function verifyRemittance(int $remittanceId, int $adminId, array $data): array
    {
        $commissionId = $this->repository->findRemittanceCommissionId($remittanceId);
        if ($commissionId === null) {
            throw new NotFoundHttpException('Không tìm thấy yêu cầu nộp đối soát.');
        }

        return DB::transaction(function () use ($remittanceId, $commissionId, $adminId, $data) {
            // 1. Khóa bản ghi cha BookingCommission TRƯỚC, sau đó mới khóa CommissionRemittance (REM-01 lock order)
            $commission = $this->repository->findCommissionForUpdate($commissionId);
            $remittance = $this->repository->findRemittanceForUpdate($remittanceId);

            if ($remittance === null) {
                throw new NotFoundHttpException('Không tìm thấy yêu cầu nộp đối soát.');
            }

            if ($commission === null) {
                throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng liên kết.');
            }

            // 2. Idempotency: nếu đã VERIFIED trước đó thì trả về thành công no-op (AC-17)
            if ($remittance->status === RemittanceStatus::VERIFIED) {
                return [
                    'remittance' => $remittance,
                    'message' => 'Yêu cầu đối soát này đã được xác minh trước đó.',
                ];
            }

            // 3. Kiểm tra trạng thái remittance: bắt buộc phải đang SUBMITTED (State conflict dùng 409)
            if ($remittance->status !== RemittanceStatus::SUBMITTED) {
                throw new ConflictHttpException(
                    'Không thể xác minh yêu cầu nộp tiền không ở trạng thái chờ duyệt (SUBMITTED).'
                );
            }

            $verifiedBankRef = trim((string) ($data['verified_bank_reference'] ?? ''));
            if ($verifiedBankRef === '') {
                throw new UnprocessableEntityHttpException('Mã giao dịch ngân hàng trích từ sao kê là bắt buộc.');
            }

            // 4. Chống tái sử dụng verified_bank_reference (AC-19)
            if ($this->repository->verifiedBankReferenceExists($verifiedBankRef, (int) $remittance->id)) {
                throw new UnprocessableEntityHttpException(
                    'Mã giao dịch ngân hàng này đã được xác minh cho một khoản đối soát khác. Vui lòng kiểm tra lại sao kê.'
                );
            }

            // 5. Kiểm tra trạng thái commission: bắt buộc phải đang DUE (State conflict dùng 409)
            if ($commission->status !== CommissionStatus::DUE) {
                throw new ConflictHttpException(
                    'Khoản hoa hồng liên kết không ở trạng thái Chờ thanh toán (DUE) để xác minh.'
                );
            }

            // 6. Kiểm tra số tiền bằng BCMath: số tiền đối soát phải khớp 100% với hoa hồng
            if (bccomp((string) $remittance->amount, (string) $commission->commission_amount, 2) !== 0) {
                throw new UnprocessableEntityHttpException(
                    'Số tiền đối soát không khớp với số tiền hoa hồng cần nộp.'
                );
            }

            $now = now();

            // Cập nhật Remittance -> VERIFIED
            $this->repository->updateRemittance($remittance, [
                'status' => RemittanceStatus::VERIFIED,
                'verified_bank_reference' => $verifiedBankRef,
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => $now,
            ]);

            // Cập nhật Commission -> SETTLED atomically (AC-17)
            $this->repository->updateCharge($commission, [
                'status' => CommissionStatus::SETTLED,
                'settled_at' => $now,
            ]);

            return [
                'remittance' => $remittance,
                'commission' => $commission,
                'message' => 'Xác minh giao dịch đối soát thành công. Khoản hoa hồng đã được ghi nhận thực thu (SETTLED).',
            ];
        });
    }

    /**
     * Admin từ chối giao dịch đối soát (Reject) với lý do bắt buộc.
     * Tuân thủ thứ tự khóa thống nhất: BookingCommission -> CommissionRemittance.
     */
    public function rejectRemittance(int $remittanceId, int $adminId, array $data): array
    {
        $commissionId = $this->repository->findRemittanceCommissionId($remittanceId);
        if ($commissionId === null) {
            throw new NotFoundHttpException('Không tìm thấy yêu cầu nộp đối soát.');
        }

        return DB::transaction(function () use ($remittanceId, $commissionId, $adminId, $data) {
            // Khóa bản ghi cha BookingCommission TRƯỚC, sau đó mới khóa CommissionRemittance (REM-01 lock order)
            $commission = $this->repository->findCommissionForUpdate($commissionId);
            $remittance = $this->repository->findRemittanceForUpdate($remittanceId);

            if ($remittance === null) {
                throw new NotFoundHttpException('Không tìm thấy yêu cầu nộp đối soát.');
            }

            if ($commission === null) {
                throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng liên kết.');
            }

            if ($remittance->status === RemittanceStatus::VERIFIED) {
                throw new ConflictHttpException(
                    'Không thể từ chối giao dịch đã được xác minh thành công.'
                );
            }

            if ($remittance->status !== RemittanceStatus::SUBMITTED) {
                throw new ConflictHttpException(
                    'Yêu cầu nộp đối soát không ở trạng thái chờ duyệt (SUBMITTED) để từ chối.'
                );
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw new UnprocessableEntityHttpException('Lý do từ chối là bắt buộc.');
            }

            $now = now();

            // Cập nhật Remittance -> REJECTED, giữ lịch sử (AC-18)
            $this->repository->updateRemittance($remittance, [
                'status' => RemittanceStatus::REJECTED,
                'review_note' => $reason,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => $now,
            ]);

            // Commission giữ nguyên trạng thái DUE (AC-18)
            if ($commission->status !== CommissionStatus::DUE && $commission->status !== CommissionStatus::SETTLED) {
                $this->repository->updateCharge($commission, [
                    'status' => CommissionStatus::DUE,
                ]);
            }

            return [
                'remittance' => $remittance,
                'commission' => $commission,
                'message' => 'Đã từ chối giao dịch đối soát. Khoản hoa hồng vẫn ở trạng thái Chờ thanh toán (DUE).',
            ];
        });
    }
}
