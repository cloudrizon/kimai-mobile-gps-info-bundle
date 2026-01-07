<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\Controller;

use App\Entity\User;
use KimaiPlugin\KimaiMobileGPSInfoBundle\Controller\GpsConfigController;
use KimaiPlugin\KimaiMobileGPSInfoBundle\Service\GpsConfigService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Unit tests for GpsConfigController.
 *
 * Tests the GPS configuration API endpoint to ensure proper
 * response format, authentication handling, and error handling.
 */
class GpsConfigControllerTest extends TestCase
{
    private MockObject&GpsConfigService $configService;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->configService = $this->createMock(GpsConfigService::class);
    }

    /**
     * Create a controller with mocked user.
     *
     * @param User|null $user The user to return from getUser()
     *
     * @return GpsConfigController The configured controller
     */
    private function createControllerWithUser(?User $user): GpsConfigController
    {
        $controller = new GpsConfigController($this->configService);

        // Create token mock
        $token = null;
        if ($user !== null) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
        }

        // Create token storage mock
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        // Create container mock
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function ($id) {
            return $id === 'security.token_storage';
        });
        $container->method('get')->willReturnCallback(function ($id) use ($tokenStorage) {
            if ($id === 'security.token_storage') {
                return $tokenStorage;
            }
            return null;
        });

        $controller->setContainer($container);

        return $controller;
    }

    // ========================================
    // Tests for successful responses
    // ========================================

    /**
     * Test getConfig returns successful response.
     *
     * Verifies that the endpoint returns a 200 OK response
     * with the correct JSON structure for authenticated users.
     */
    public function testGetConfigReturnsSuccessResponse(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')->willReturn(true);
        $this->configService->method('isUserTrackingEnabled')->willReturn(true);
        $this->configService->method('isTrackingEffective')->willReturn(true);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test response contains correct snake_case fields.
     *
     * Verifies that the response body contains the expected
     * field names in snake_case format.
     */
    public function testGetConfigResponseStructure(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')->willReturn(false);
        $this->configService->method('isUserTrackingEnabled')->willReturn(true);
        $this->configService->method('isTrackingEffective')->willReturn(false);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('global_enabled', $data);
        $this->assertArrayHasKey('user_enabled', $data);
        $this->assertArrayHasKey('effective', $data);
    }

    /**
     * Test response when both global and user enabled.
     *
     * Verifies correct values when GPS tracking is fully enabled.
     */
    public function testGetConfigWithBothEnabled(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')->willReturn(true);
        $this->configService->method('isUserTrackingEnabled')->willReturn(true);
        $this->configService->method('isTrackingEffective')->willReturn(true);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['global_enabled']);
        $this->assertTrue($data['user_enabled']);
        $this->assertTrue($data['effective']);
    }

    /**
     * Test response when global is disabled.
     *
     * Verifies correct values when global GPS tracking is off.
     */
    public function testGetConfigWithGlobalDisabled(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')->willReturn(false);
        $this->configService->method('isUserTrackingEnabled')->willReturn(true);
        $this->configService->method('isTrackingEffective')->willReturn(false);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['global_enabled']);
        $this->assertTrue($data['user_enabled']);
        $this->assertFalse($data['effective']);
    }

    /**
     * Test response when user tracking is disabled.
     *
     * Verifies correct values when user GPS preference is off.
     */
    public function testGetConfigWithUserDisabled(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')->willReturn(true);
        $this->configService->method('isUserTrackingEnabled')->willReturn(false);
        $this->configService->method('isTrackingEffective')->willReturn(false);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['global_enabled']);
        $this->assertFalse($data['user_enabled']);
        $this->assertFalse($data['effective']);
    }

    /**
     * Test response when both are disabled.
     *
     * Verifies correct values when both settings are off.
     */
    public function testGetConfigWithBothDisabled(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')->willReturn(false);
        $this->configService->method('isUserTrackingEnabled')->willReturn(false);
        $this->configService->method('isTrackingEffective')->willReturn(false);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['global_enabled']);
        $this->assertFalse($data['user_enabled']);
        $this->assertFalse($data['effective']);
    }

    // ========================================
    // Tests for error handling
    // ========================================

    /**
     * Test 401 response when user is not authenticated.
     *
     * Verifies that the endpoint returns 401 when getUser()
     * returns null (should not happen with #[IsGranted] but
     * testing the fallback).
     */
    public function testGetConfigReturns401WhenNotAuthenticated(): void
    {
        $controller = $this->createControllerWithUser(null);
        $response = $controller->getConfig();

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals(401, $data['code']);
    }

    /**
     * Test 500 response when service throws exception.
     *
     * Verifies that the endpoint returns 500 with proper
     * error format when the service throws an exception.
     */
    public function testGetConfigReturns500OnServiceException(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->method('isGlobalTrackingEnabled')
            ->willThrowException(new \Exception('Database connection failed'));

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConfig();

        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals(500, $data['code']);
        $this->assertStringContainsString('Database connection failed', $data['message']);
    }

    /**
     * Test error response follows Kimai convention.
     *
     * Verifies that error responses have 'code' and 'message' fields
     * following Kimai's error response format.
     */
    public function testErrorResponseFollowsKimaiConvention(): void
    {
        $controller = $this->createControllerWithUser(null);
        $response = $controller->getConfig();

        $data = json_decode($response->getContent(), true);

        // Kimai convention: error responses have 'code' and 'message' fields
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertIsInt($data['code']);
        $this->assertIsString($data['message']);
    }

    // ========================================
    // Tests for service method calls
    // ========================================

    /**
     * Test that all service methods are called with correct user.
     *
     * Verifies that the controller calls each service method
     * with the authenticated user as parameter.
     */
    public function testServiceMethodsCalledWithUser(): void
    {
        $user = $this->createMock(User::class);

        $this->configService->expects($this->once())
            ->method('isGlobalTrackingEnabled')
            ->willReturn(true);

        $this->configService->expects($this->once())
            ->method('isUserTrackingEnabled')
            ->with($user)
            ->willReturn(true);

        $this->configService->expects($this->once())
            ->method('isTrackingEffective')
            ->with($user)
            ->willReturn(true);

        $controller = $this->createControllerWithUser($user);
        $controller->getConfig();
    }
}
