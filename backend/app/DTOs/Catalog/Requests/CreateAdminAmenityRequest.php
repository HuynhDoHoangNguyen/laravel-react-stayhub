<?php

namespace App\DTOs\Catalog\Requests;

final readonly class CreateAdminAmenityRequest
{
    public function __construct(
        public string $name,
        public ?string $icon,
        public ?string $description,
        public bool $status,
    ) {}

    /** @return array{name:string, icon:?string, description:?string, status:bool} */
    public function attributes(): array
    {
        return ['name' => $this->name, 'icon' => $this->icon, 'description' => $this->description, 'status' => $this->status];
    }
}
