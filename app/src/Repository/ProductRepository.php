<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Exception\TranslatableHttpException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends ServiceEntityRepository<Product>
 */
final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function getById(int $id): Product
    {
        $product = $this->find($id);

        if ($product === null) {
            throw new TranslatableHttpException(
                'product.not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $product;
    }
}
