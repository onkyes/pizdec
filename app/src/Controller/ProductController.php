<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateProductRequest;
use App\Dto\PaginationRequest;
use App\Dto\UpdateProductRequest;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'product_list', methods: ['GET'])]
    public function list(
        #[MapQueryString]
        PaginationRequest $pagination,
        ProductRepository $repository,
    ): JsonResponse {
        $products = $repository->findBy(
            [],
            ['id' => 'ASC'],
            $pagination->limit,
            $pagination->getOffset(),
        );

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->serializeProduct($product);
        }

        return $this->json($data, Response::HTTP_OK);

    }

    #[Route('/api/products/{id}', name: 'product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ProductRepository $repository): JsonResponse
    // ^^автоматически внедряется (dependency injection)
    // оно делает find, findAll, findBy
    {
        $product = $repository->getById($id);

        return $this->json(
            $this->serializeProduct($product),
            Response::HTTP_OK, // вернуть продукт в джейсоне (200)
        );

    }

    #[Route('/api/products', name: 'product_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateProductRequest $dto,
        EntityManagerInterface $em,
    ): JsonResponse {
        $product = new Product(
            $dto->name,
            $dto->description,
            $dto->price,
            $dto->weight,
            $dto->category,
        );

        $em->persist($product);
        $em->flush();

        return $this->json(
            $this->serializeProduct($product),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/products/{id}', name: 'product_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(
        int $id,
        #[MapRequestPayload]
        UpdateProductRequest $dto,
        EntityManagerInterface $em,
        ProductRepository $repository,
    ): JsonResponse {

        $product = $repository->getById($id);

        $hasChanges = false;

        if ($dto->name !== null) {
            $product->setName($dto->name);
            $hasChanges = true;
        }

        if ($dto->description !== null) {
            $product->setDescription($dto->description);
            $hasChanges = true;
        }

        if ($dto->price !== null) {
            $product->setPrice($dto->price);
            $hasChanges = true;
        }

        if ($dto->weight !== null) {
            $product->setWeight($dto->weight);
            $hasChanges = true;
        }

        if ($dto->category !== null) {
            $product->setCategory($dto->category);
            $hasChanges = true;
        }


        if ($hasChanges) {
            $em->flush();
        }

        return $this->json($this->serializeProduct($product), Response::HTTP_OK);

    }

    #[Route('/api/products/{id}', name: 'product_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        int $id,
        ProductRepository $repository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $product = $repository->getById($id);

        $em->remove($product);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        // удалили, 204 ответ (пустое тело)
    }

    /**
     * @return array{id: int, name: string, description: string, price: int, weight: int, category: string}
     */
    private function serializeProduct(Product $product): array
    {

        // совет от кодекса, что бы сократить код, преобразовать Product в массив json
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
