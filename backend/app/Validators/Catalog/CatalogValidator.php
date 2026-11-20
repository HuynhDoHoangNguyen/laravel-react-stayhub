<?php

namespace App\Validators\Catalog;

use App\Enums\UserRole;
use App\Enums\RoomStatus;
use App\DTOs\Catalog\Requests\ChangeHostRoomStatusRequest;
use App\Enums\PropertyStatus;
use App\DTOs\Catalog\Requests\ChangeHostPropertyStatusRequest;
use App\DTOs\Catalog\Requests\CreateHostPropertyRequest;
use App\DTOs\Catalog\Requests\CreateHostRoomTypeRequest;
use App\DTOs\Catalog\Requests\CreateHostRoomRequest;
use App\DTOs\Catalog\Requests\UpdateHostRoomRequest;
use Brick\Math\BigDecimal;
use Closure;
use App\DTOs\Catalog\Requests\DeleteHostRoomTypeRequest;
use App\DTOs\Catalog\Requests\UpdateHostPropertyRequest;
use App\DTOs\Catalog\Requests\UpdateHostRoomTypeRequest;
use App\DTOs\Catalog\Requests\HostPropertyDetailQuery;
use App\DTOs\Catalog\Requests\HostRoomTypeListQuery;
use App\DTOs\Catalog\Requests\HostRoomListQuery;
use App\DTOs\Catalog\Requests\HostRoomDetailQuery;
use App\DTOs\Catalog\Requests\UploadHostPropertyImageRequest;
use App\DTOs\Catalog\Requests\HostPropertyImageRequest;
use App\Models\User;
use App\DTOs\Catalog\Requests\PublicPropertyDetailQuery;
use App\DTOs\Catalog\Requests\PublicPropertyListQuery;
use App\DTOs\Catalog\Requests\PublicPropertyListInputRequest;
use App\Validators\AttributeValidator;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Exceptions\AppException;

final class CatalogValidator
{
    public function __construct(
        private readonly Factory $validation,
        private readonly AttributeValidator $attributes,
    ) {}

    public function authorizeHost(User $actor): void
    {
        if ($actor->role !== UserRole::HOST) {
            throw new AppException('CATALOG_HOST_FORBIDDEN', 'Bạn không có quyền truy cập.', 403);
        }
    }

    public function validateHostRoomStatus(User $actor, string $room, array $body, array $query): ?ChangeHostRoomStatusRequest
    {
        $target = $this->validateHostRoomDetail($actor, $room, []);
        if ($target === null) {
            return null;
        }
        $validator = $this->validation->make($body, [
            'status' => ['bail', 'required', 'string', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
        ], ['status.*' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc MAINTENANCE.']);
        $validator->after(static function (Validator $validator) use ($body, $query): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, 'Đổi trạng thái phòng không nhận tham số truy vấn.');
            }
            foreach (array_diff(array_keys($body), ['status']) as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi đổi trạng thái phòng.');
            }
        });
        $data = $validator->validate();

        return new ChangeHostRoomStatusRequest($target->roomId, RoomStatus::from($data['status']));
    }

    public function hostRoomHasBlockingBookings(iterable $facts, string $day): bool
    {
        foreach ($facts as $fact) {
            if ($fact->status === 'CHECKED_IN'
                || (in_array($fact->status, ['PENDING', 'CONFIRMED'], true)
                    && (!$this->validRoomDateRange($fact->check_in_date, $fact->check_out_date) || $fact->check_out_date > $day))) {
                return true;
            }
        }

        return false;
    }

    public function hostRoomHasBlockingMaintenances(iterable $facts, string $day): bool
    {
        foreach ($facts as $fact) {
            if ($fact->status === 'IN_PROGRESS'
                || ($fact->status === 'SCHEDULED'
                    && (!$this->validRoomDateRange($fact->start_date, $fact->end_date)
                        || ($fact->start_date <= $day && $day < $fact->end_date)))) {
                return true;
            }
        }

        return false;
    }

    private function validRoomDateRange(string $start, string $end): bool
    {
        foreach ([$start, $end] as $date) {
            if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date) !== 1
                || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                return false;
            }
        }

        return $start < $end;
    }

    public function validateHostRoomCreate(User $actor, string $property, array $body, array $query): ?CreateHostRoomRequest
    {
        $target = $this->validateHostPropertyDetail($actor, $property);
        if ($target === null) {
            return null;
        }
        $data = $this->validateHostRoomPayload($body, $query);

        return new CreateHostRoomRequest($target->propertyId, (int) $data['room_type_id'],
            $data['room_number'], $data['name'], $data['description'] ?? null, (int) $data['capacity'],
            $data['price_per_night']);
    }

    public function validateHostRoomUpdate(User $actor, string $room, array $body, array $query): ?UpdateHostRoomRequest
    {
        $target = $this->validateHostRoomDetail($actor, $room, []);
        if ($target === null) {
            return null;
        }
        $data = $this->validateHostRoomPayload($body, $query, true);

        return new UpdateHostRoomRequest($target->roomId, (int) $data['room_type_id'],
            $data['room_number'], $data['name'], $data['description'] ?? null, (int) $data['capacity'],
            $data['price_per_night']);
    }

    private function validateHostRoomPayload(array $body, array $query, bool $updating = false): array
    {
        $allowed = ['room_type_id', 'room_number', 'name', 'description', 'capacity', 'price_per_night'];
        foreach ($allowed as $field) {
            if (is_string($body[$field] ?? null)) {
                $body[$field] = trim($body[$field]);
            }
        }
        if (($body['description'] ?? null) === '') {
            $body['description'] = null;
        }
        $canonicalInteger = static function (string $attribute, mixed $value, Closure $fail): void {
            if ((!is_int($value) && !is_string($value)) || (int) $value < 1 || (string) (int) $value !== (string) $value) {
                $fail($attribute === 'capacity' ? 'Sức chứa phải là số nguyên dương hợp lệ.' : 'Loại phòng phải là mã nguyên dương hợp lệ.');
            }
        };
        $validator = $this->validation->make($body, [
            'room_type_id' => ['bail', 'required', 'integer', $canonicalInteger],
            'room_number' => ['bail', 'required', 'string', 'max:50'],
            'name' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:10000'],
            'capacity' => ['bail', 'required', 'integer', $canonicalInteger, 'max:2147483647'],
            'price_per_night' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (BigDecimal::of($value)->isLessThanOrEqualTo('0')) {
                        $fail('Giá mỗi đêm phải lớn hơn 0.');
                    }
                }],
        ], [
            'room_type_id.*' => 'Loại phòng phải là mã nguyên dương hợp lệ.',
            'room_number.*' => 'Số phòng là chuỗi bắt buộc, tối đa 50 ký tự.',
            'name.*' => 'Tên phòng là chuỗi bắt buộc, tối đa 255 ký tự.',
            'description.*' => 'Mô tả phải là chuỗi tối đa 10000 ký tự.',
            'capacity.*' => 'Sức chứa phải là số nguyên dương, tối đa 2147483647.',
            'price_per_night.*' => 'Giá mỗi đêm phải là chuỗi decimal dương, tối đa 9999999999.99 và hai chữ số thập phân.',
        ]);
        $validator->after(static function (Validator $validator) use ($allowed, $body, $query, $updating): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, $updating ? 'Cập nhật phòng không nhận tham số truy vấn.' : 'Thêm phòng không nhận tham số truy vấn.');
            }
            foreach (array_diff(array_keys($body), $allowed) as $field) {
                $validator->errors()->add($field, $updating ? 'Trường này không được phép gửi khi cập nhật phòng.' : 'Trường này không được phép gửi khi thêm phòng.');
            }
        });
        $data = $validator->validate();
        $data['price_per_night'] = (string) BigDecimal::of($data['price_per_night'])->toScale(2);

        return $data;
    }

    public function validateHostRoomTypeCreate(User $actor, string $property, array $body, array $query): ?CreateHostRoomTypeRequest
    {
        $input = $this->validateHostPropertyDetail($actor, $property);
        if ($input === null) {
            return null;
        }

        $data = $this->validateRoomTypePayload($body, $query, false);

        return new CreateHostRoomTypeRequest($input->propertyId, $data['name'], $data['description'] ?? null);
    }

    public function validateHostRoomTypeDelete(User $actor, string $roomType, array $input): ?DeleteHostRoomTypeRequest
    {
        $this->authorizeHost($actor);
        $id = (int) $roomType;
        if ($id < 1 || (string) $id !== $roomType) {
            return null;
        }

        $validator = $this->validation->make($input, []);
        $validator->after(static function (Validator $validator) use ($input): void {
            foreach (array_keys($input) as $field) {
                $validator->errors()->add($field, 'Xóa loại phòng không nhận thêm dữ liệu.');
            }
        });
        $validator->validate();

        return new DeleteHostRoomTypeRequest($id);
    }

    public function validateHostRoomTypeUpdate(User $actor, string $roomType, array $body, array $query): ?UpdateHostRoomTypeRequest
    {
        $this->authorizeHost($actor);
        $id = (int) $roomType;
        if ($id < 1 || (string) $id !== $roomType) {
            return null;
        }

        $data = $this->validateRoomTypePayload($body, $query, true);

        return new UpdateHostRoomTypeRequest($id, $data['name'], $data['description']);
    }

    private function validateRoomTypePayload(array $body, array $query, bool $update): array
    {
        foreach (['name', 'description'] as $field) {
            if (is_string($body[$field] ?? null)) {
                $body[$field] = trim($body[$field]);
            }
        }
        if (($body['description'] ?? null) === '') {
            $body['description'] = null;
        }

        $validator = $this->validation->make($body, [
            'name' => ['required', 'string', 'max:255'],
            'description' => $update ? ['present', 'nullable', 'string', 'max:10000'] : ['nullable', 'string', 'max:10000'],
        ], [
            'name.*' => 'Tên loại phòng là chuỗi bắt buộc, tối đa 255 ký tự.',
            'description.*' => 'Mô tả phải là chuỗi tối đa 10000 ký tự.',
        ]);
        $action = $update ? 'Cập nhật' : 'Thêm';
        $validator->after(static function (Validator $validator) use ($body, $query, $action): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, $action.' loại phòng không nhận tham số truy vấn.');
            }
            foreach (array_diff(array_keys($body), ['name', 'description']) as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi '.mb_strtolower($action).' loại phòng.');
            }
        });
        return $validator->validate();
    }

    public function validateHostRoomDetail(User $actor, string $room, array $query): ?HostRoomDetailQuery
    {
        $this->authorizeHost($actor);
        $id = (int) $room;
        if ($id < 1 || (string) $id !== $room) {
            return null;
        }

        $validator = $this->validation->make($query, []);
        $validator->after(static function (Validator $validator) use ($query): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, 'Chi tiết phòng không nhận tham số truy vấn.');
            }
        });
        $validator->validate();

        return new HostRoomDetailQuery($id);
    }

    public function validateHostRoomList(User $actor, string $property, array $query): ?HostRoomListQuery
    {
        $input = $this->validateHostPropertyDetail($actor, $property);
        if ($input === null) {
            return null;
        }

        $validator = $this->validation->make($query, []);
        $validator->after(static function (Validator $validator) use ($query): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, 'Danh sách phòng không nhận tham số truy vấn.');
            }
        });
        $validator->validate();

        return new HostRoomListQuery($input->propertyId);
    }

    public function validateHostRoomTypeList(User $actor, string $property, array $query): ?HostRoomTypeListQuery
    {
        $input = $this->validateHostPropertyDetail($actor, $property);
        if ($input === null) {
            return null;
        }

        $validator = $this->validation->make($query, []);
        $validator->after(static function (Validator $validator) use ($query): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, 'Danh sách loại phòng không nhận tham số truy vấn.');
            }
        });
        $validator->validate();

        return new HostRoomTypeListQuery($input->propertyId);
    }

    public function validateHostPropertyImageRequest(User $actor, string $property, string $image, array $input): ?HostPropertyImageRequest
    {
        $this->authorizeHost($actor);
        $propertyId = (int) $property;
        $imageId = (int) $image;
        if ($propertyId < 1 || (string) $propertyId !== $property || $imageId < 1 || (string) $imageId !== $image) return null;

        $validator = $this->validation->make($input, []);
        $validator->after(static function (Validator $validator) use ($input): void {
            foreach (array_keys($input) as $field) $validator->errors()->add($field, 'Thao tác ảnh không nhận thêm dữ liệu.');
        });
        $validator->validate();

        return new HostPropertyImageRequest($propertyId, $imageId);
    }

    public function validateHostPropertyImage(User $actor, array $input): UploadHostPropertyImageRequest
    {
        $this->authorizeHost($actor);
        $validator = $this->validation->make($input, [
            'image' => ['bail', 'required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ], ['image.*' => 'Vui lòng chọn một ảnh JPG, PNG hoặc WebP hợp lệ, tối đa 5 MiB và 4096 × 4096 pixel.']);
        $validator->after(static function (Validator $validator) use ($input): void {
            foreach (array_diff(array_keys($input), ['image']) as $field) {
                $validator->errors()->add($field, 'Chỉ được gửi một tệp image.');
            }
        });
        $data = $validator->validate();

        return new UploadHostPropertyImageRequest($data['image']);
    }

    public function validateHostPropertyStatus(User $actor, array $input): ChangeHostPropertyStatusRequest
    {
        $this->authorizeHost($actor);
        $validator = $this->validation->make($input, [
            'status' => ['bail', 'required', 'string', 'in:ACTIVE,INACTIVE'],
            'expected_status' => ['bail', 'required', 'string', 'in:ACTIVE,INACTIVE,PENDING,BLOCKED'],
        ], [
            'status.*' => 'Trạng thái đích phải là ACTIVE hoặc INACTIVE.',
            'expected_status.*' => 'Vui lòng gửi trạng thái hiện tại hợp lệ của cơ sở.',
        ]);
        $validator->after(static function (Validator $validator) use ($input): void {
            foreach (array_diff(array_keys($input), ['status', 'expected_status']) as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi thay đổi trạng thái cơ sở.');
            }
        });
        $data = $validator->validate();

        return new ChangeHostPropertyStatusRequest(PropertyStatus::from($data['status']), PropertyStatus::from($data['expected_status']));
    }

    public function validateHostPropertyTransition(PropertyStatus $current, ChangeHostPropertyStatusRequest $input): void
    {
        if (!in_array($input->status, [PropertyStatus::ACTIVE, PropertyStatus::INACTIVE], true)) {
            throw new AppException('CATALOG_PROPERTY_STATUS_INVALID', 'Trạng thái đích phải là ACTIVE hoặc INACTIVE.', 422);
        }
        if ($current !== $input->expectedStatus) {
            throw new AppException('CATALOG_PROPERTY_STATUS_STALE', 'Trạng thái cơ sở đã thay đổi. Vui lòng tải lại chi tiết.', 409);
        }
        if (!in_array($current, [PropertyStatus::ACTIVE, PropertyStatus::INACTIVE], true)) {
            throw new AppException('CATALOG_PROPERTY_STATUS_CONFLICT', 'Host không thể thay đổi trạng thái cơ sở đang chờ duyệt hoặc đã bị khóa.', 409);
        }
    }

    public function validateHostPropertyCreate(User $actor, array $input): CreateHostPropertyRequest
    {
        $this->authorizeHost($actor);
        foreach (['name', 'address', 'phone'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $fields = ['name', 'type', 'address', 'description', 'phone', 'check_in_time', 'check_out_time'];
        $validator = $this->validation->make($input, [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'type' => ['bail', 'required', 'string', 'in:HOTEL,HOMESTAY'],
            'address' => ['bail', 'required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'check_in_time' => ['bail', 'sometimes', 'required', 'date_format:H:i'],
            'check_out_time' => ['bail', 'sometimes', 'required', 'date_format:H:i'],
        ], [
            'name.required' => 'Vui lòng nhập tên cơ sở.',
            'name.string' => 'Tên cơ sở phải là chuỗi.',
            'name.max' => 'Tên cơ sở không được vượt quá 255 ký tự.',
            'type.*' => 'Loại cơ sở phải là HOTEL hoặc HOMESTAY.',
            'address.required' => 'Vui lòng nhập địa chỉ cơ sở.',
            'address.string' => 'Địa chỉ phải là chuỗi.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'description.*' => 'Mô tả phải là chuỗi tối đa 10000 ký tự.',
            'phone.*' => 'Điện thoại phải là chuỗi tối đa 20 ký tự.',
            'check_in_time.*' => 'Giờ nhận phòng phải có định dạng HH:mm hợp lệ.',
            'check_out_time.*' => 'Giờ trả phòng phải có định dạng HH:mm hợp lệ.',
        ]);
        $validator->after(static function (Validator $validator) use ($input, $fields): void {
            foreach (array_diff(array_keys($input), $fields) as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi tạo cơ sở.');
            }
        });
        $data = $validator->validate();

        return new CreateHostPropertyRequest(
            $data['name'], $data['type'], $data['address'],
            $data['description'] ?? null, $data['phone'] ?? null,
            ($data['check_in_time'] ?? '14:00').':00',
            ($data['check_out_time'] ?? '12:00').':00',
        );
    }

    public function validateHostPropertyUpdate(User $actor, array $input): UpdateHostPropertyRequest
    {
        $this->authorizeHost($actor);
        foreach (['name', 'address', 'phone'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $fields = ['name', 'type', 'address', 'description', 'phone', 'check_in_time', 'check_out_time'];
        $validator = $this->validation->make($input, [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'type' => ['bail', 'required', 'string', 'in:HOTEL,HOMESTAY'],
            'address' => ['bail', 'required', 'string', 'max:500'],
            'description' => ['present', 'nullable', 'string', 'max:10000'],
            'phone' => ['present', 'nullable', 'string', 'max:20'],
            'check_in_time' => ['bail', 'required', 'date_format:H:i'],
            'check_out_time' => ['bail', 'required', 'date_format:H:i'],
        ], [
            'name.required' => 'Vui lòng nhập tên cơ sở.',
            'name.string' => 'Tên cơ sở phải là chuỗi.',
            'name.max' => 'Tên cơ sở không được vượt quá 255 ký tự.',
            'type.*' => 'Loại cơ sở phải là HOTEL hoặc HOMESTAY.',
            'address.required' => 'Vui lòng nhập địa chỉ cơ sở.',
            'address.string' => 'Địa chỉ phải là chuỗi.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'description.*' => 'Mô tả phải là chuỗi tối đa 10000 ký tự.',
            'phone.*' => 'Điện thoại phải là chuỗi tối đa 20 ký tự.',
            'check_in_time.*' => 'Giờ nhận phòng phải có định dạng HH:mm hợp lệ.',
            'check_out_time.*' => 'Giờ trả phòng phải có định dạng HH:mm hợp lệ.',
        ]);
        $validator->after(static function (Validator $validator) use ($input, $fields): void {
            foreach (array_diff(array_keys($input), $fields) as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi cập nhật cơ sở.');
            }
        });
        $data = $validator->validate();

        return new UpdateHostPropertyRequest(
            $data['name'], $data['type'], $data['address'],
            $data['description'] ?? null, $data['phone'] ?? null,
            $data['check_in_time'].':00',
            $data['check_out_time'].':00',
        );
    }

    public function validateHostPropertyDetail(User $actor, string $property): ?HostPropertyDetailQuery
    {
        $this->authorizeHost($actor);
        $id = (int) $property;

        return $id > 0 && (string) $id === $property ? new HostPropertyDetailQuery($id) : null;
    }

    public function validatePublicPropertyList(array $input, array $query): PublicPropertyListQuery
    {
        $raw = new PublicPropertyListInputRequest(...array_intersect_key($input, array_flip(['page', 'per_page'])));
        $errors = $this->attributes->errors($raw, ['catalog-public-list']);
        foreach (array_diff(array_keys($query), ['page', 'per_page']) as $field) {
            $errors[$field] = ['Danh sách cơ sở chỉ hỗ trợ page và per_page; chưa lọc phòng trống theo ngày.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new PublicPropertyListQuery(
            page: (int) $raw->page,
            perPage: (int) $raw->per_page,
        );
    }

    public function validatePublicPropertyDetail(string $property, array $query): ?PublicPropertyDetailQuery
    {
        $validator = $this->validation->make($query, []);
        $validator->after(static function (Validator $validator) use ($query): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add($field, 'Chi tiết cơ sở không nhận bộ lọc phòng trống hoặc tham số truy vấn.');
            }
        });
        $validator->validate();

        $id = (int) $property;

        return $id > 0 && (string) $id === $property ? new PublicPropertyDetailQuery($id) : null;
    }
}
