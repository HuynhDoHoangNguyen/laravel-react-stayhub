<?php

namespace App\Http\Controllers\Agreement;

use App\Http\Controllers\Controller;
use App\Mappers\Agreement\PartnerAgreementMapper;
use App\Services\Agreement\PartnerAgreementService;
use App\Validators\Agreement\PartnerAgreementValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostPartnerAgreementController extends Controller
{
    public function __construct(
        protected readonly PartnerAgreementService $service,
        protected readonly PartnerAgreementValidator $validator,
        protected readonly PartnerAgreementMapper $mapper,
    ) {}

    /**
     * Lấy phiên bản hợp đồng đối tác đang có hiệu lực (Public — phục vụ màn hình Đăng ký Đối tác).
     */
    public function publicCurrent(): JsonResponse
    {
        $agreement = $this->service->getCurrentAgreement(null);
        if ($agreement === null) {
            return response()->json(['message' => 'Chưa có hợp đồng đối tác nào được kích hoạt.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapper->toPublicAgreementResponse($agreement),
        ], 200);
    }

    /**
     * Đăng ký tài khoản Đối tác (Host) đồng thời chấp thuận hợp đồng hoa hồng 10%.
     */
    public function registerHost(Request $request): JsonResponse
    {
        foreach (['name', 'email'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $request->merge([$field => trim($value)]);
            }
        }

        $this->service->registerHostWithAgreement($request->all());

        return response()->json([
            'message' => 'Đăng ký tài khoản Đối tác và chấp thuận hợp đồng thành công. Vui lòng đăng nhập để tiếp tục.',
        ], 201);
    }

    /**
     * Lấy phiên bản hợp đồng đối tác đang có hiệu lực (Dành cho Host đã đăng nhập).
     */
    public function current(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->validator->authorizeHost($actor);

        $agreement = $this->service->getCurrentAgreement($actor->id);
        if ($agreement === null) {
            return response()->json(['message' => 'Chưa có hợp đồng đối tác nào được kích hoạt.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapper->toAgreementResponse($agreement),
        ], 200);
    }

    /**
     * Lấy danh sách lịch sử các phiên bản hợp đồng mà Host đã chấp thuận.
     */
    public function acceptances(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->validator->authorizeHost($actor);

        $acceptances = $this->service->listHostAcceptances($actor->id);

        return response()->json([
            'success' => true,
            'data' => $this->mapper->toAcceptancesListResponse($acceptances),
        ], 200);
    }

    /**
     * Chấp thuận thỏa thuận đối tác (Clickwrap).
     */
    public function accept(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        $this->validator->authorizeHost($actor);

        $agreementId = $this->validator->validateId($id);
        $validated = $this->validator->validateAcceptPayload($request->all());

        $acceptance = $this->service->acceptAgreement(
            $actor->id,
            $agreementId,
            $validated['content_sha256']
        );

        return response()->json([
            'success' => true,
            'message' => 'Chấp thuận hợp đồng đối tác thành công.',
            'data' => $this->mapper->toAcceptanceResponse($acceptance),
        ], 200);
    }
}
