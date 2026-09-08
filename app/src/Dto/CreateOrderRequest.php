<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\DeliveryType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateOrderRequest
{
    #[Assert\NotBlank(message: 'order.delivery_type.required')]
    #[Assert\Type('string')]
    #[Assert\Choice(
        callback: [DeliveryType::class, 'values'],
        message: 'order.delivery_type.choice',
    )]
    public string $deliveryType;

    #[Assert\Type('string')]
    public ?string $deliveryRegion;

    #[Assert\Type('string')]
    public ?string $deliveryCity;

    #[Assert\Type('string')]
    public ?string $deliveryStreet;

    #[Assert\Type('string')]
    public ?string $deliveryHouse;

    #[Assert\Type('string')]
    public ?string $deliveryEntrance;

    #[Assert\Type('string')]
    public ?string $deliveryApartment;

    #[Assert\Type('string')]
    #[Assert\Regex(
        pattern: '/^\d{6}$/',
        message: 'order.postal_code.regex',
    )]
    public ?string $deliveryPostalCode;

    public function __construct(
        string $deliveryType,
        ?string $deliveryRegion = null,
        ?string $deliveryCity = null,
        ?string $deliveryStreet = null,
        ?string $deliveryHouse = null,
        ?string $deliveryPostalCode = null,
        ?string $deliveryEntrance = null,
        ?string $deliveryApartment = null,
    ) {
        $this->deliveryType = trim($deliveryType); // убираем лишние пробелы у типа доставки

        $this->deliveryRegion = $this->trimNullable($deliveryRegion); // чистим область
        $this->deliveryCity = $this->trimNullable($deliveryCity); // чистим город
        $this->deliveryStreet = $this->trimNullable($deliveryStreet); // чистим улицу
        $this->deliveryHouse = $this->trimNullable($deliveryHouse); // чистим дом
        $this->deliveryPostalCode = $this->trimNullable($deliveryPostalCode); // чистим индекс
        $this->deliveryEntrance = $this->trimNullable($deliveryEntrance); // чистим подъезд
        $this->deliveryApartment = $this->trimNullable($deliveryApartment); // чистим квартиру
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // если это не доставка курьером, адрес не обязателен
        if ($this->deliveryType !== DeliveryType::Courier->value) {
            return;
        }

        // для курьерской доставки проверяем обязательные поля адреса
        $this->requireCourierField($context, 'deliveryRegion', $this->deliveryRegion, 'order.delivery_region.required');
        $this->requireCourierField($context, 'deliveryCity', $this->deliveryCity, 'order.delivery_city.required');
        $this->requireCourierField($context, 'deliveryStreet', $this->deliveryStreet, 'order.delivery_street.required');
        $this->requireCourierField($context, 'deliveryHouse', $this->deliveryHouse, 'order.delivery_house.required');
        $this->requireCourierField($context, 'deliveryPostalCode', $this->deliveryPostalCode, 'order.delivery_postal_code.required');
    }

    private function trimNullable(?string $value): ?string
    {
        // если поля вообще нет, оставляем null
        if ($value === null) {
            return null;
        }

        $value = trim($value); // убираем пробелы по краям строки

        // если после очистки строка пустая, считаем что значения нет
        return $value === '' ? null : $value;
    }

    private function requireCourierField(
        ExecutionContextInterface $context,
        string $fieldName,
        ?string $value,
        string $message,
    ): void {
        // если значение есть, ошибка не нужна
        if ($value !== null) {
            return;
        }

        // добавляем ошибку именно к нужному полю
        $context
            ->buildViolation($message)
            ->atPath($fieldName)
            ->addViolation();
    }
}
