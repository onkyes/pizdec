<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PaginationRequest;

/**
 * @phpstan-type ProductListItemData array{
 *     id: int,
 *     name: string,
 *     description: string,
 *     price: int,
 *     weight: int,
 *     category: string
 * }
 */
interface ProductListProviderInterface
{
    /**
     * @return list<ProductListItemData>
     */
    public function getProductList(PaginationRequest $pagination): array;
}
