<?php

namespace App\Validators\Invoice;

use App\DTOs\Invoice\Requests\AdminInvoiceQueryInputRequest;
use App\Validators\AttributeValidator;
use App\DTOs\Invoice\Requests\AdminInvoiceFilterOptionsInputRequest;
use Illuminate\Validation\ValidationException;

final class AdminInvoiceValidator
{
    public function __construct(private readonly AttributeValidator $attributes) {}

    public function validateFilterOptions(array $query): AdminInvoiceFilterOptionsInputRequest
    {
        $data = array_intersect_key($query, array_flip(['type', 'search', 'page', 'per_page', 'host_id']));
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
                if (in_array($key, ['search', 'host_id'], true) && $data[$key] === '') $data[$key] = null;
            }
        }
        $input = new AdminInvoiceFilterOptionsInputRequest(...$data);
        $errors = $this->attributes->errors($input);
        if ($input->type === 'hosts' && array_key_exists('host_id', $query)) {
            $errors['host_id'] = ['Không được truyền host_id khi lấy danh sách Host.'];
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
        return $input;
    }

    public function validateQuery(array $query): AdminInvoiceQueryInputRequest
    {
        $data = array_intersect_key($query, array_flip(['host_id', 'property_id', 'status', 'search', 'page', 'per_page']));
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
                if (!in_array($key, ['page', 'per_page'], true) && $data[$key] === '') {
                    $data[$key] = null;
                }
            }
        }
        $input = new AdminInvoiceQueryInputRequest(...$data);
        $this->attributes->validateHttp($input);
        return $input;
    }
}
