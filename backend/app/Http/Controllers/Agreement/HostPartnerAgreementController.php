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
     * Lấy phiên bản hợp đồng đối tác đang có hiệu lực.
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
     * Chấp thuận thỏa thuận đối tác (Clickwrap).
     */
    public function accept(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        $this->validator->authorizeHost($actor);

        $validated = $this->validator->validateAcceptPayload($request->all());

        $acceptance = $this->service->acceptAgreement(
            $actor->id,
            (int) $id,
            $validated['content_sha256']
        );

        return response()->json([
            'success' => true,
            'message' => 'Chấp thuận hợp đồng đối tác thành công.',
            'data' => $this->mapper->toAcceptanceResponse($acceptance),
        ], 200);
    }
}
