<?php

namespace App\Services\Agreement;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\HostAgreementAcceptance;
use App\Repositories\Agreement\PartnerAgreementRepository;
use App\Repositories\Auth\AuthRepository;
use App\Services\Auth\EmailUniqueConflict;
use App\Validators\Agreement\PartnerAgreementValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PartnerAgreementService
{
    public function __construct(
        protected readonly PartnerAgreementRepository $repository,
        protected readonly AuthRepository $authRepository,
        protected readonly PartnerAgreementValidator $validator,
    ) {}

    /**
     * Lấy phiên bản hợp đồng đối tác đang có hiệu lực.
     */
    public function getCurrentAgreement(?int $hostId = null): ?array
    {
        $agreement = $this->repository->findActivePublishedAgreement();

        if ($agreement === null) {
            return null;
        }

        $acceptance = null;
        if ($hostId !== null) {
            $acceptance = $this->repository->findHostAcceptance($hostId, (int) $agreement->id);
        }

        $isPendingApproval = $acceptance !== null && $acceptance->method === 'REGISTRATION_PENDING';

        return [
            'id' => $agreement->id,
            'version' => $agreement->version,
            'title' => $agreement->title,
            'terms_body' => $agreement->terms_body,
            'content_sha256' => $agreement->content_sha256,
            'commission_rate_bps' => $agreement->commission_rate_bps,
            'is_accepted' => $acceptance !== null,
            'accepted_at' => $acceptance?->accepted_at?->toIso8601String(),
            'is_approved' => ! $isPendingApproval,
            'is_pending_approval' => $isPendingApproval,
        ];
    }

    /**
     * Kiểm tra xem Host đã chấp thuận phiên bản hợp đồng hiện hành hay chưa.
     */
    public function hasAcceptedCurrentAgreement(int $hostId): bool
    {
        $current = $this->getCurrentAgreement($hostId);
        if ($current === null) {
            return true;
        }

        return (bool) $current['is_accepted'];
    }

    /**
     * Lấy bản ghi chấp thuận hợp đồng đang có hiệu lực của Host (nếu có).
     */
    public function getHostActiveAcceptance(int $hostId): ?HostAgreementAcceptance
    {
        $agreement = $this->repository->findActivePublishedAgreement();

        if ($agreement === null) {
            return null;
        }

        return $this->repository->findHostAcceptanceWithAgreement($hostId, (int) $agreement->id);
    }

    /**
     * Lấy danh sách lịch sử các phiên bản hợp đồng mà Host đã chấp thuận.
     *
     * @return Collection<int, HostAgreementAcceptance>
     */
    public function listHostAcceptances(int $hostId): Collection
    {
        return $this->repository->listHostAcceptances($hostId);
    }

    /**
     * Ghi nhận việc Host chấp thuận hợp đồng (Clickwrap).
     */
    public function acceptAgreement(int $hostId, int $agreementId, string $contentSha256): HostAgreementAcceptance
    {
        $agreement = $this->repository->findById($agreementId);
        if ($agreement === null || $agreement->status !== 'PUBLISHED') {
            throw new NotFoundHttpException('Phiên bản hợp đồng không tồn tại hoặc chưa được phát hành.');
        }

        if (strtolower($agreement->content_sha256) !== strtolower($contentSha256)) {
            throw new ConflictHttpException('Nội dung hợp đồng đã được cập nhật phiên bản mới. Vui lòng tải lại và xem nội dung mới nhất.');
        }

        return DB::transaction(function () use ($hostId, $agreement): HostAgreementAcceptance {
            $acceptance = $this->repository->firstOrCreateAcceptance(
                $hostId,
                (int) $agreement->id,
                (string) $agreement->content_sha256,
                'CHECKBOX_CLICKWRAP'
            );

            DB::afterCommit(fn () => Log::info('Host đã chấp thuận hợp đồng đối tác StayHub.', [
                'host_id' => $hostId,
                'agreement_id' => $agreement->id,
                'version' => $agreement->version,
            ]));

            return $acceptance;
        });
    }

    /**
     * Đăng ký tài khoản Đối tác (Host) đồng thời ghi nhận chấp thuận hợp đồng hoa hồng 10% trong cùng transaction.
     */
    public function registerHostWithAgreement(#[SensitiveParameter] array $input): void
    {
        $email = $this->validator->registrationEmailForLookup($input);
        $emailExists = $email !== null && $this->authRepository->emailExists($email);

        $validated = $this->validator->validateHostRegistrationPayload($input, $emailExists);

        $agreement = $this->repository->findActivePublishedAgreement();
        if ($agreement === null) {
            throw new NotFoundHttpException('Chưa có hợp đồng đối tác nào được kích hoạt.');
        }

        if (strtolower((string) $agreement->content_sha256) !== strtolower((string) $validated['content_sha256'])) {
            throw new ConflictHttpException('Nội dung hợp đồng đã được cập nhật phiên bản mới. Vui lòng tải lại và xem nội dung mới nhất.');
        }

        try {
            DB::transaction(function () use ($validated, $agreement): void {
                $user = $this->authRepository->createUser([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => UserRole::HOST,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => null,
                ]);

                $this->repository->firstOrCreateAcceptance(
                    (int) $user->id,
                    (int) $agreement->id,
                    (string) $agreement->content_sha256,
                    'REGISTRATION_PENDING'
                );

                DB::afterCommit(fn () => Log::info('Đăng ký tài khoản đối tác (Host) và chấp thuận hợp đồng thành công.', [
                    'host_id' => $user->id,
                    'agreement_id' => $agreement->id,
                    'version' => $agreement->version,
                ]));
            });
        } catch (UniqueConstraintViolationException $exception) {
            EmailUniqueConflict::rethrow($exception);
        }
    }
}
