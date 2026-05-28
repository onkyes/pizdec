<?php

namespace App\Tests;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait TestHelper // вихууу мой первый трайт
{
    private function createUser(string $email, string $plainPassword, array $roles): User
    {
        $user = new User($email, '', $roles);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);

        $user->setPassword($hashedPassword);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function decodeResponse(KernelBrowser $client): array
    {
        return json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function getToken(KernelBrowser $client, string $email, string $password): string
    {
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'email' => $email,
                'password' => $password,
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeResponse($client);

        return $data['token'];
    }

    private function createProduct(): Product
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $product = new Product(
            'Тестовый продукт',
            'Тестовое описание',
            100,
            500,
            'test'
        );

        $em->persist($product);
        $em->flush();

        return $product;
    }

    private function authHeaders(KernelBrowser $client, string $role): array
    {
        $email = strtolower(str_replace('ROLE_', '', $role)) . '_' . \uniqid() . '@example.com';
        $password = 'password';

        $this->createUser($email, $password, [$role]);

        $token = $this->getToken($client, $email, $password);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];
    }
}
