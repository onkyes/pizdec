<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PaginationRequest;
use App\Entity\Product;
use App\Repository\ProductRepository;

/**
 * @phpstan-import-type ProductListItemData from ProductListProviderInterface
 */
final readonly class ProductListProvider implements ProductListProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    /**
     * @return list<ProductListItemData>
     */
    public function getProductList(PaginationRequest $pagination): array
    {
        $products = $this->productRepository->findBy(
            [],
            ['id' => 'ASC'],
            $pagination->limit,
            $pagination->getOffset(),
        );

        $data = [];

        foreach ($products as $product) {
            $data[] = $this->serializeProduct($product);
        }

        return $data;
    }

    /**
     * @return ProductListItemData
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'weight' => $product->getWeight(),
            'category' => $product->getCategory(),
        ];
    }
}
