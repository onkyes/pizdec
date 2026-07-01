<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderRequest
{
    #[Assert\NotBlank(message: 'Необходимо указать область')]
    #[Assert\Type('string')]
    public string $deliveryRegion;

    #[Assert\NotBlank(message: 'Необходимо указать город')]
    #[Assert\Type('string')]
    public string $deliveryCity;

    #[Assert\NotBlank(message: 'Необходимо указать улицу')]
    #[Assert\Type('string')]
    public string $deliveryStreet;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'Необходимо указать дом или строение')]
    public string $deliveryHouse;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'Необходимо указать парадную', allowNull: true)]
    public ?string $deliveryEntrance;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'Необходимо указать квартиру', allowNull: true)]
    public ?string $deliveryApartment;


    #[Assert\NotBlank(message: 'Необходимо указать почтовый индекс')]
    #[Assert\Type('string')]
    #[Assert\Regex(
        pattern: '/^\d{6}$/',
        message: 'Индекс должен состоять из 6 цифр'
    )]
    public string $deliveryPostalCode;

    public function __construct(
        string $deliveryRegion,
        string $deliveryCity,
        string $deliveryStreet,
        string $deliveryHouse,
        string $deliveryPostalCode,
        ?string $deliveryEntrance = null,
        ?string $deliveryApartment = null,
    ) {
        $this->deliveryRegion = trim($deliveryRegion);
        $this->deliveryCity = trim($deliveryCity);
        $this->deliveryStreet = trim($deliveryStreet);
        $this->deliveryHouse = trim($deliveryHouse);
        $this->deliveryPostalCode = trim($deliveryPostalCode);
        $this->deliveryEntrance = $deliveryEntrance !== null ? trim($deliveryEntrance) : null;
        $this->deliveryApartment = $deliveryApartment !== null ? trim($deliveryApartment) : null;
    }

}
