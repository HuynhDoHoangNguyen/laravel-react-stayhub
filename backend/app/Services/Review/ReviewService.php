<?php

namespace App\Services\Review;

use App\DTOs\Review\Requests\CreateReviewRequest;
use App\Models\Review;
use App\Models\User;
use App\Validators\Review\ReviewCreationValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use App\Repositories\Review\ReviewRepository;
use App\Mappers\Review\ReviewMapper;
use LogicException;

class ReviewService
{
    public function __construct(
        protected readonly ReviewRepository $repository,
        protected readonly ReviewCreationValidator $validator,
        protected readonly ReviewMapper $mapper,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * Lấy đối tượng Validator đang sử dụng.
     */
    public function getValidator(): ReviewCreationValidator
    {
        return $this->validator;
    }

    /**
     * Tạo đánh giá (Review) cho một đơn đặt phòng đã hoàn tất lưu trú (COMPLETED).
     *
     * @param int $bookingId ID của Booking cần đánh giá
     * @param User $customerActor Người dùng thực hiện (bắt buộc role CUSTOMER và sở hữu Booking)
     * @param array $data Dữ liệu đánh giá gồm: 'rating' (int 1..5) và 'comment' (string 5..1000)
     * @return Review Bản ghi Review vừa tạo kèm quan hệ booking, property, customer
     *
     * @throws ModelNotFoundException Nếu không tìm thấy Booking
     * @throws \Illuminate\Auth\Access\AuthorizationException Nếu người dùng không có quyền
     * @throws InvalidReviewConditionException Nếu Booking chưa hoàn tất hoặc thiếu cơ sở lưu trú
     * @throws DuplicateReviewException Nếu Booking đã có đánh giá
     * @throws \InvalidArgumentException Nếu rating hoặc comment không hợp lệ
     * @throws QueryException Nếu phát sinh lỗi cơ sở dữ liệu ngoài vi phạm unique
     */
    public function createReview(int $bookingId, User $customerActor, array $data): Review
    {
        return $this->persistReview($bookingId, $customerActor, $data);
    }

    public function createReviewFromRequest(CreateReviewRequest $request, User $customerActor): Review
    {
        return $this->persistReview($request->bookingId, $customerActor, [
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);
    }

    private function persistReview(int $bookingId, User $customerActor, array $data): Review
    {
        return DB::transaction(function () use ($bookingId, $customerActor, $data) {
            // 1. Khóa bản ghi Booking bằng lockForUpdate() chống concurrent requests
            $booking = $this->repository->findBookingForReviewWithLock($bookingId);

            if (!$booking) {
                throw new ModelNotFoundException("Không tìm thấy đơn đặt phòng với ID: {$bookingId}");
            }

            // 2. Xác thực toàn bộ điều kiện nghiệp vụ và trích xuất property_id an toàn
            $this->validator->validateCreationEligibility($booking, $customerActor);
            if (!$booking->relationLoaded('review')) {
                throw new LogicException('Review creation requires an eager-loaded review relation.');
            }
            $duplicateExists = $booking->getRelation('review') !== null
                || ($booking->exists && $this->repository->reviewExistsForBooking($bookingId));
            $propertyId = $this->validator->validateCreationDetails($booking, $data, $duplicateExists);

            // 3. Kiểm tra lại sự tồn tại của Review ngay trong phạm vi transaction được khóa
            if ($this->repository->reviewExistsForBooking($bookingId)) {
                throw new DuplicateReviewException("Đơn đặt phòng #{$bookingId} đã có đánh giá.");
            }

            // 4. Lưu bản ghi Review vào cơ sở dữ liệu
            $attributes = $this->mapper->toCreationAttributes($booking, $customerActor, $propertyId, $data);

            try {
                $review = $this->repository->createReview($attributes);
            } catch (QueryException $e) {
                $this->handleQueryException($e, $booking->id);
            }

            // 5. Đăng ký log nghiệp vụ chính SAU KHI transaction commit thành công (không log nhạy cảm)
            DB::afterCommit(function () use ($review) {
                $this->logInfo('Tạo đánh giá thành công', [
                    'review_id' => $review->id,
                    'booking_id' => $review->booking_id,
                    'property_id' => $review->property_id,
                    'rating' => $review->rating,
                ]);
            });

            return $this->repository->loadReviewRelations($review);
        });
    }

    /**
     * Phân biệt chính xác vi phạm UNIQUE constraint trên booking_id.
     * Không chuyển mọi QueryException thành lỗi duplicate booking.
     *
     * @throws DuplicateReviewException
     * @throws QueryException
     */
    protected function handleQueryException(QueryException $e, int $bookingId): void
    {
        $driverMessage = $e->errorInfo[2] ?? $e->getMessage();
        $pureMessage = explode('(Connection:', $driverMessage)[0];
        $pureMessage = explode('(SQL:', $pureMessage)[0];

        $isUniqueViolation = str_contains($driverMessage, 'Duplicate entry')
            || str_contains($driverMessage, 'UNIQUE constraint failed')
            || $e->getCode() === '23000'
            || (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [1062, 19], true));

        if ($isUniqueViolation) {
            if (
                str_contains($pureMessage, 'booking_id')
                || str_contains($pureMessage, 'reviews_booking_id_unique')
                || str_contains($driverMessage, 'reviews.booking_id')
            ) {
                throw new DuplicateReviewException(
                    "Đơn đặt phòng #{$bookingId} đã có đánh giá trong hệ thống (vi phạm ràng buộc duy nhất).",
                    0,
                    $e
                );
            }
        }

        // Ném lại QueryException gốc nếu là lỗi database khác (foreign key, connection, syntax...)
        throw $e;
    }

    /**
     * Ghi log thực thi nghiệp vụ an toàn.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}
