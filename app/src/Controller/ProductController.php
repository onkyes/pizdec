<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'product_list', methods: ['GET'])]
    public function list(Request $request, ProductRepository $repository): JsonResponse
    {

        // пагинация, страница и лимит записей на страницу
        $page = max(1, (int) $request->query->get('page', 1)); //страница
        $limit = (int) $request->query->get('limit', 20); // сколько записей вернуть на одну страницу
        $limit = max(1, min($limit, 20));// от 1 до 20
        $offset = ($page - 1) * $limit; // пропуск записей

        //запрос к бд, получаем продукты с сортировкой по алфавиту, лимитом, смещением
        $products = $repository->findBy([], ['id' => 'ASC'], $limit, $offset); // преобразованный sql запрос

        //подготовка структуры к json
        $data = [];
        foreach ($products as $product) { //массив объектов Product
            $data[] = $this->serializeProduct($product);
        }
        return $this->json($data, Response::HTTP_OK); //$this->json()<- превращает массив
        // в json, возвращает http ответ. HTTP_OK константа кода 200 (запрос выполнен)
    }

    #[Route('/api/products/{id}', name: 'product_show', methods: ['GET'])]
    public function show(int $id, ProductRepository $repository): JsonResponse
    // ^^автоматически внедряется (dependency injection)
    // оно делает find, findAll, findBy
    {
        $product = $repository->find($id); // получает продукт, ищет по primary key
            if (!$product) {
                throw $this->createNotFoundException('Товар не найден'); //если товара нет,
            // создаётся исключение NotFoundHttpException, код останавливается(HTTP 404 Not Found)
            }
        return $this->json(
            $this->serializeProduct($product),
            Response::HTTP_CREATED // вернуть продукт в джейсоне (201)
        );

    }

    #[Route('/api/products', name: 'product_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
    // декодирование  json из хттп запроса,json_decode делает из json->php, associative/true - ассоциативный массив
    // 512 - глубина вложенности
        } catch (\JsonException) { //<- если ошибка сюда падаем
            return $this->json( // <- вернуть json хттп ответ
                ['error' => 'Invalid JSON body'],
                Response::HTTP_BAD_REQUEST // статус 400
            );
        }

        $reqFields = ['name', 'description', 'price', 'weight', 'category']; // массив обязательных полей

        foreach ($reqFields as $field) {
            if (!array_key_exists($field, $data)) { //array_k проверяет отсутствие ключа, если нет возвращает 422
                return $this->json(  // формирует json ответ
                    ['error' => sprintf('Field "%s" is required', $field)],
                    // Функция sprintf форматирует строку по условиям
                    Response::HTTP_UNPROCESSABLE_ENTITY // статус ответа 422 (верный запрос,
                    // данные не валидны - нет поля
                ); //
            }
        }

        if (
            // данные не инт, данные не числа, проверка что прайс и вес - целые числа
            (!is_int($data['price']) && !ctype_digit((string)$data['price'])) ||
            (!is_int($data['weight']) && !ctype_digit((string)$data['weight']))
        ) {
            return $this->json(
                ['error' => 'Цена и вес должны быть целыми числами'],
                Response::HTTP_UNPROCESSABLE_ENTITY // 422 статус, данные есть, но невалидные
            );
        }
        // обрезаем пробелы у строковых полей
        $name = trim((string) $data['name']);
        $description = trim((string) $data['description']);
        $category = trim((string) $data['category']);

        if ($name === '' || $description === '' || $category === '') { // проверяем что строки не пустые
            return $this->json(
                ['error' => 'Название, описание и категория не могут быть пустыми'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        // делаем прайс и вес инт
        $price = (int) $data['price'];
        $weight = (int) $data['weight'];

        if ($price < 0 || $weight < 0) { // проверка что прайс и масса не отрицательные
            return $this->json(
                ['error' => 'Цена и вес должны быть больше или равны 0'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // создаём объект через конструктор
        $product = new Product(
            $name,
            $description,
            $price,
            $weight,
            $category
        );

        $em->persist($product);//сохранить
        $em->flush();//отправить изменения в бд

        return $this->json(
            $this->serializeProduct($product),
            Response::HTTP_OK); // вернули возданный продукт, статус 201

    }

    #[Route('/api/products/{id}', name: 'product_update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $repository
    ): JsonResponse {
        $product = $repository->find($id); // найти товар

        if (!$product) {
            throw $this->createNotFoundException('Товар не найден');
        } // ищем товар, если товар не найден возвращает 404

        try {
            $data = json_decode($request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR);// декодируем джейсон из тела запроса
        } catch (\JsonException) {
            return $this->json(
                ['error' => 'Invalid JSON body'],
                Response::HTTP_BAD_REQUEST
            ); // если тело запроса невалидное, получаем 400 (некорректный формат запроса или данные)
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']); // если пришло название - валидируем и обновляем

            if ($name === '') {
                return $this->json(
                    ['error' => 'Name must be a non-empty string'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверяем name, если он пришёл, но пришёл пустой, возвращаем 422

            $product->setName($name);
        }

        if (array_key_exists('description', $data)) {
            $description = trim((string) $data['description']); // описание - валидация и обновление

            if ($description === '') {
                return $this->json(
                    ['error' => 'Description must be a non-empty string'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверка дескрипшена, так же - пришёл, но не пустой

            $product->setDescription($description);
        }

        if (array_key_exists('price', $data)) {
            if (!is_int($data['price'])
                && !ctype_digit((string)
                $data['price'])) { // та же аналогия, если пришла цена - валидируем и обновляем
                return $this->json(
                    ['error' => 'Цена должна быть целым числом'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверка на целое число

            $price = (int) $data['price'];

            if ($price < 0) {
                return $this->json(
                    ['error' => 'Цена должна быть больше или равна 0'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверка на положительное число

            $product->setPrice($price);
        }

        if (array_key_exists('weight', $data)) {
            if (!is_int($data['weight']) && !ctype_digit((string) $data['weight'])) {
                return $this->json(
                    ['error' => 'Масса должна быть целым числом'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверка на целое число

            $weight = (int) $data['weight'];

            if ($weight < 0) {
                return $this->json(
                    ['error' => 'Масса должна быть больше или равна 0'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверка на положительно число

            $product->setWeight($weight);
        }

        if (array_key_exists('category', $data)) {
            $category = trim((string) $data['category']);

            if ($category === '') {
                return $this->json(
                    ['error' => 'Категория должна быть непустой строкой'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } // проверка категории - пришла строка, но не пустая

            $product->setCategory($category);
        }

        $em->flush(); // сохраняем изменения в бд

        // вернуть обновленный продукт, статус 200
        return $this->json($this->serializeProduct($product), Response::HTTP_OK);

    }

    #[Route('/api/products/{id}', name: 'product_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        ProductRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $product = $repository->find($id); // ищем товар

        if (!$product) {
            throw $this->createNotFoundException('Товар не найден');// если не нашли - возвращаем 404
        }

        $em->remove($product);  // удалить
        $em->flush(); // сохранить в бд

        return new Response(null, Response::HTTP_NO_CONTENT); // удалили, 204 ответ (пустое тело)
    }

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
