<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Role;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserRoleRequest
{
    #[Assert\NotBlank(message: 'user.role.required')]
    #[Assert\Type('string')]
    #[Assert\Choice(
        callback: [Role::class, 'values'],
        message: 'user.role.choice',
    )]
    public string $role;

    public function __construct(string $role)
    {
        $this->role = trim($role);
    }
}
