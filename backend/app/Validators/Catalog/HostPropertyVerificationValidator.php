<?php

declare(strict_types=1);

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\SubmitPropertyVerificationRequest;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;

final class HostPropertyVerificationValidator
{
    private const EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public function __construct(private readonly Factory $validation) {}

    public function validate(array $input): SubmitPropertyVerificationRequest
    {
        $validated = $this->validation->make($input, [
            'document' => ['bail', 'required', 'file', 'max:2048'],
        ], [
            'document.required' => 'Chọn tệp hồ sơ để gửi.',
            'document.file' => 'Tệp hồ sơ không hợp lệ.',
            'document.uploaded' => 'Không thể tải tệp lên. Hãy chọn tệp tối đa 2 MiB.',
            'document.max' => 'Tệp hồ sơ phải có dung lượng tối đa 2 MiB.',
        ])->validate();

        $document = $validated['document'];
        $size = $document->getSize();
        if ($size === 0) {
            throw ValidationException::withMessages(['document' => ['Tệp hồ sơ đang trống.']]);
        }
        $mime = $document->getMimeType();
        $extensions = self::EXTENSIONS[$mime] ?? [];
        if (!in_array(strtolower($document->getClientOriginalExtension()), $extensions, true)) {
            throw ValidationException::withMessages(['document' => ['Chọn tệp PDF, JPG hoặc PNG có nội dung đúng định dạng.']]);
        }

        return new SubmitPropertyVerificationRequest($document, $mime, $extensions[0], $size);
    }
}
