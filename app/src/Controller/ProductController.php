<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateProductRequest;
use App\Dto\PaginationRequest;
use App\Dto\UpdateProductRequest;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductListProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'product_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Получить список товаров',
        description: 'Возвращает товары с пагинацией, по возрастанию ID. '
        . 'Если на странице нет товаров, возвращается пустой массив.',
        security: [],
        tags: ['Products'],
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Список товаров',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                required: [
                    'id', 'name', 'description',
                    'price', 'weight', 'category',
                ],
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Маргарита'),
                    new OA\Property(
                        property: 'description',
                        type: 'string',
                        example: 'Пицца с томатами и моцареллой',
                    ),
                    new OA\Property(property: 'price', type: 'integer', example: 500),
                    new OA\Property(property: 'weight', type: 'integer', example: 450),
                    new OA\Property(property: 'category', type: 'string', example: 'food'),
                ],
                type: 'object',
            ),
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Ошибка валидации параметров пагинации',
        content: new OA\JsonContent(
            required: ['type', 'title', 'status', 'detail', 'violations'],
            properties: [
                new OA\Property(property: 'type', type: 'string'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'status', type: 'integer'),
                new OA\Property(property: 'detail', type: 'string'),
                new OA\Property(
                    property: 'violations',
                    type: 'array',
                    items: new OA\Items(
                        required: ['propertyPath', 'title'],
                        properties: [
                            new OA\Property(property: 'propertyPath', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
                        ],
                        type: 'object',
                    ),
                ),
            ],
            type: 'object',
            example: [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => "page: Номер страницы должен быть больше 0\n"
                    . 'limit: Лимит должен быть от 1 до 20',
                'violations' => [
                    [
                        'propertyPath' => 'page',
                        'title' => 'Номер страницы должен быть больше 0',
                    ],
                    [
                        'propertyPath' => 'limit',
                        'title' => 'Лимит должен быть от 1 до 20',
                    ],
                ],
            ],
        ),
    )]
    public function list(
        #[MapQueryString]
        PaginationRequest $pagination,
        ProductListProviderInterface $productListProvider,
    ): JsonResponse {
        $data = $productListProvider->getProductList($pagination);

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
