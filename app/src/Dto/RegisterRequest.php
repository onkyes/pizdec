<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequest // dto запроса регистрации
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email, // email пользователя

        #[Assert\NotBlank]
        #[Assert\Length(min: 6)]
        public string $password, // пароль пользователя
    ) {}
}
