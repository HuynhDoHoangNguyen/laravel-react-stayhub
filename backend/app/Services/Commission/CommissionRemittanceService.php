<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Models\BookingCommission;
use App\Models\CommissionRemittance;
use App\Repositories\Commission\CommissionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CommissionRemittanceService
{
    public function __construct(
        protected readonly CommissionRepository $repository,
    ) {}

    /**
     * Danh sách hoa hồng của Host có phân trang và lọc theo trạng thái.
     */
    public function listForHost(int $hostId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForHost($hostId, $filters, $perPage);
    }

    /**
     * Lấy chi tiết khoản hoa hồng thuộc Host kèm relations.
     */
    public function findForHost(int $commissionId, int $hostId): BookingCommission
    {
        $commission = $this->repository->findForHostWithRelations($commissionId, $hostId);

        if ($commission === null) {
            throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng.');
        }

        return $commission;
    }

    /**
     * Host nộp khai báo chuyển khoản đối soát (Remittance).
     * Tuân thủ quy tắc Idempotency, BCMath không ép float và State Machine (REM-01, MONEY-01, AC-16).
     */
    public function submitRemittance(int $commissionId, int $hostId, array $data): CommissionRemittance
    {
        return DB::transaction(function () use ($commissionId, $hostId, $data) {
            // Khóa bản ghi cha BookingCommission trước theo thứ tự lock chuẩn: BookingCommission -> CommissionRemittance
            $commission = $this->repository->findForHostForUpdate($commissionId, $hostId);

            if ($commission === null) {
                throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng cần nộp.');
            }

            $submittedAmount = $this->normalizeDecimalString((string) ($data['amount'] ?? '0.00'));
            $submittedBankRef = isset($data['bank_transaction_reference']) && trim((string) $data['bank_transaction_reference']) !== ''
                ? trim((string) $data['bank_transaction_reference'])
                : null;
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

            // 1. Xử lý Idempotency TRƯỚC gate kiểm tra trạng thái DUE (REM-01, AC-16)
            if ($idempotencyKey !== '') {
                $existing = $this->repository->findRemittanceByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    // Kiểm tra đúng owner và đúng commission
                    if ($existing->booking_commission_id === $commission->id && $existing->host_id === $hostId) {
                        $existingAmount = $this->normalizeDecimalString((string) $existing->amount);
                        $existingBankRef = $existing->bank_transaction_reference !== null && trim((string) $existing->bank_transaction_reference) !== ''
                            ? trim((string) $existing->bank_transaction_reference)
                            : null;

                        // Kiểm tra tính nhất quán của toàn bộ payload: cả số tiền và mã giao dịch phải khớp
                        if (bccomp($existingAmount, $submittedAmount, 2) === 0 && $existingBankRef === $submittedBankRef) {
                            return $existing;
                        }

                        throw new UnprocessableEntityHttpException(
                            'Khóa idempotency đã được sử dụng với dữ liệu đối soát khác.'
                        );
                    }

                    throw new UnprocessableEntityHttpException(
                        'Khóa idempotency đã được sử dụng cho một giao dịch khác.'
                    );
                }
            }

            // 2. Kiểm tra trạng thái: Phải đang DUE (State conflict dùng 409 theo plan §5.2)
            if ($commission->status !== CommissionStatus::DUE) {
                throw new ConflictHttpException(
                    'Chỉ có thể nộp đối soát cho khoản hoa hồng đang ở trạng thái Chờ thanh toán (DUE).'
                );
            }

            // 3. Kiểm tra số tiền: phải khớp 100% với số tiền hoa hồng cần nộp bằng BCMath thuần (không ép float)
            $expectedAmount = $this->normalizeDecimalString((string) $commission->commission_amount);
            if (bccomp($expectedAmount, $submittedAmount, 2) !== 0) {
                throw new UnprocessableEntityHttpException(
                    'Số tiền nộp phải khớp 100% với số tiền hoa hồng cần nộp (' .
                    $this->formatWholeVndString($expectedAmount) . ' VND).'
                );
            }

            // 4. Kiểm tra xem đã có submission pending chưa (State conflict dùng 409)
            if ($this->repository->hasPendingRemittance((int) $commission->id)) {
                throw new ConflictHttpException(
                    'Khoản hoa hồng này đã có một yêu cầu đối soát đang chờ Admin kiểm tra.'
                );
            }

            // 5. Tạo mới submission qua Repository
            return $this->repository->createRemittance([
                'booking_commission_id' => $commission->id,
                'host_id' => $hostId,
                'amount' => $submittedAmount,
                'bank_transaction_reference' => $submittedBankRef,
                'idempotency_key' => $idempotencyKey,
                'status' => RemittanceStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);
        });
    }

    /**
     * Chuẩn hóa chuỗi thập phân 2 chữ số bằng BCMath, không ép kiểu float.
     */
    private function normalizeDecimalString(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $trimmed)) {
            return '0.00';
        }

        return bcadd($trimmed, '0.00', 2);
    }

    /**
     * Định dạng phần nguyên tiền VND với dấu chấm phân cách hàng nghìn bằng thao tác chuỗi thuần (không ép float).
     */
    private function formatWholeVndString(string $decimalAmount): string
    {
        $integerPart = explode('.', $decimalAmount, 2)[0] ?: '0';

        return strrev(implode('.', str_split(strrev($integerPart), 3)));
    }
}
