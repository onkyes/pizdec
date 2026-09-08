<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Logger\InMemoryLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationControllerTest extends WebTestCase
{
    use TestHelper;

    public function testRegisterCallsWelcomeEmailStub(): void // проверяет регистрацию
    {
        $client = self::createClient();

        $logger = self::getContainer()->get(InMemoryLogger::class);
        self::assertInstanceOf(InMemoryLogger::class, $logger);
        $logger->reset();

        $email = 'registered_' . uniqid() . '@example.com';

        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeResponse($client);
        self::assertSame($email, $data['email']);

        $matchedRecords = array_values(array_filter(
            $logger->getRecords(),
            static fn(array $record): bool => $record['message'] === 'welcome_email.sent'
                && $record['context']['email'] === $email
                && $record['context']['userId'] === $data['id'],
        ));

        self::assertCount(1, $matchedRecords);
    }

    public function testDuplicateEmailReturnsRussianMessage(): void
    {
        $client = self::createClient();
        $email = 'duplicate_ru_' . uniqid() . '@example.com';

        $this->createUser($email, 'password', ['ROLE_USER']);

        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'ru',
            ],
            json_encode([
                'email' => $email,
                'password' => 'password',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $data = $this->decodeResponse($client);
        self::assertSame('Пользователь с таким email уже существует', $data['message']);
    }

    public function testDuplicateEmailReturnsEnglishMessage(): void
    {
        $client = self::createClient();
        $email = 'duplicate_en_' . uniqid() . '@example.com';

        $this->createUser($email, 'password', ['ROLE_USER']);

        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ],
            json_encode([
                'email' => $email,
                'password' => 'password',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $data = $this->decodeResponse($client);
        self::assertSame('User with this email already exists', $data['message']);
    }
}
