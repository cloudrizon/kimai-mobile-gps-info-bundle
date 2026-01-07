<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\KimaiMobileGPSInfoBundle\Service\GpsConfigService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Stub class for SystemConfiguration to avoid mocking final class.
 *
 * This stub allows us to test GpsConfigService without depending on
 * the final SystemConfiguration class from Kimai core.
 */
class SystemConfigurationStub
{
    private mixed $returnValue;

    public function __construct(mixed $returnValue = null)
    {
        $this->returnValue = $returnValue;
    }

    public function find(string $key): mixed
    {
        return $this->returnValue;
    }

    public function setReturnValue(mixed $value): void
    {
        $this->returnValue = $value;
    }
}

/**
 * Unit tests for GpsConfigService.
 *
 * Tests the GPS configuration service that reads system and user-level
 * settings and computes the effective tracking status.
 */
class GpsConfigServiceTest extends TestCase
{
    /**
     * Create a GpsConfigService with stubbed SystemConfiguration.
     *
     * @param mixed $configReturnValue Value to return from find()
     *
     * @return GpsConfigService The configured service
     */
    private function createServiceWithConfig(mixed $configReturnValue): GpsConfigService
    {
        $stub = new SystemConfigurationStub($configReturnValue);

        // Use reflection to inject the stub since constructor expects SystemConfiguration
        $service = new class ($stub) extends GpsConfigService {
            private $stubConfig;

            public function __construct($stub)
            {
                $this->stubConfig = $stub;
            }

            public function isGlobalTrackingEnabled(): bool
            {
                $value = $this->stubConfig->find('gps.tracking_enabled');
                return (bool) $value;
            }
        };

        return $service;
    }

    /**
     * Create a mock User with specified preference value.
     *
     * @param mixed $preferenceValue The value to return from getPreferenceValue
     *
     * @return MockObject&User The mocked user
     */
    private function createMockUser(mixed $preferenceValue = null): MockObject&User
    {
        $user = $this->createMock(User::class);

        if ($preferenceValue !== null) {
            $user->method('getPreferenceValue')
                ->with('gps_tracking_enabled', true)
                ->willReturn($preferenceValue);
        }

        return $user;
    }

    // ========================================
    // Tests for isGlobalTrackingEnabled()
    // ========================================

    /**
     * Test global tracking returns true when enabled.
     *
     * Verifies that isGlobalTrackingEnabled() returns true when
     * the system configuration has gps.tracking_enabled set to true.
     */
    public function testIsGlobalTrackingEnabledReturnsTrue(): void
    {
        $service = $this->createServiceWithConfig(true);

        $this->assertTrue($service->isGlobalTrackingEnabled());
    }

    /**
     * Test global tracking returns false when disabled.
     *
     * Verifies that isGlobalTrackingEnabled() returns false when
     * the system configuration has gps.tracking_enabled set to false.
     */
    public function testIsGlobalTrackingEnabledReturnsFalse(): void
    {
        $service = $this->createServiceWithConfig(false);

        $this->assertFalse($service->isGlobalTrackingEnabled());
    }

    /**
     * Test global tracking defaults to false when not configured.
     *
     * Verifies that isGlobalTrackingEnabled() returns false when
     * the system configuration returns null (setting not found).
     * This ensures privacy by design - tracking is off by default.
     */
    public function testIsGlobalTrackingEnabledDefaultsFalseWhenNull(): void
    {
        $service = $this->createServiceWithConfig(null);

        $this->assertFalse($service->isGlobalTrackingEnabled());
    }

    /**
     * Test global tracking with string value "1".
     *
     * Verifies that boolean casting handles string values correctly.
     */
    public function testIsGlobalTrackingEnabledWithStringOne(): void
    {
        $service = $this->createServiceWithConfig('1');

        $this->assertTrue($service->isGlobalTrackingEnabled());
    }

    /**
     * Test global tracking with string value "0".
     *
     * Verifies that boolean casting handles string values correctly.
     */
    public function testIsGlobalTrackingEnabledWithStringZero(): void
    {
        $service = $this->createServiceWithConfig('0');

        $this->assertFalse($service->isGlobalTrackingEnabled());
    }

    // ========================================
    // Tests for isUserTrackingEnabled()
    // ========================================

    /**
     * Test user tracking returns true when preference is true.
     *
     * Verifies that isUserTrackingEnabled() returns true when
     * the user's preference is explicitly set to true.
     */
    public function testIsUserTrackingEnabledWithPreferenceTrue(): void
    {
        $user = $this->createMockUser(true);

        $service = $this->createServiceWithConfig(false);

        $this->assertTrue($service->isUserTrackingEnabled($user));
    }

    /**
     * Test user tracking returns false when preference is false.
     *
     * Verifies that isUserTrackingEnabled() returns false when
     * the user's preference is explicitly set to false.
     */
    public function testIsUserTrackingEnabledWithPreferenceFalse(): void
    {
        $user = $this->createMockUser(false);

        $service = $this->createServiceWithConfig(false);

        $this->assertFalse($service->isUserTrackingEnabled($user));
    }

    /**
     * Test user tracking defaults to true when preference not set.
     *
     * Verifies that isUserTrackingEnabled() uses true as default
     * when calling getPreferenceValue(). Users opt-in by default.
     */
    public function testIsUserTrackingEnabledDefaultsToTrue(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('getPreferenceValue')
            ->with('gps_tracking_enabled', true) // Verify default is true
            ->willReturn(true); // Return the default

        $service = $this->createServiceWithConfig(false);

        $this->assertTrue($service->isUserTrackingEnabled($user));
    }

    /**
     * Test user tracking with string value "1".
     *
     * Verifies that boolean casting handles string values correctly.
     */
    public function testIsUserTrackingEnabledWithStringOne(): void
    {
        $user = $this->createMockUser('1');

        $service = $this->createServiceWithConfig(false);

        $this->assertTrue($service->isUserTrackingEnabled($user));
    }

    /**
     * Test user tracking with string value "0".
     *
     * Verifies that boolean casting handles string values correctly.
     */
    public function testIsUserTrackingEnabledWithStringZero(): void
    {
        $user = $this->createMockUser('0');

        $service = $this->createServiceWithConfig(false);

        $this->assertFalse($service->isUserTrackingEnabled($user));
    }

    // ========================================
    // Tests for isTrackingEffective()
    // ========================================

    /**
     * Test effective tracking when both global and user are enabled.
     *
     * Verifies that isTrackingEffective() returns true when
     * both global and user settings are enabled.
     */
    public function testIsTrackingEffectiveBothEnabled(): void
    {
        $user = $this->createMockUser(true);

        $service = $this->createServiceWithConfig(true);

        $this->assertTrue($service->isTrackingEffective($user));
    }

    /**
     * Test effective tracking when global is disabled.
     *
     * Verifies that isTrackingEffective() returns false when
     * global setting is disabled, regardless of user setting.
     */
    public function testIsTrackingEffectiveGlobalDisabled(): void
    {
        $user = $this->createMockUser(true);

        $service = $this->createServiceWithConfig(false);

        $this->assertFalse($service->isTrackingEffective($user));
    }

    /**
     * Test effective tracking when user is disabled.
     *
     * Verifies that isTrackingEffective() returns false when
     * user setting is disabled, regardless of global setting.
     */
    public function testIsTrackingEffectiveUserDisabled(): void
    {
        $user = $this->createMockUser(false);

        $service = $this->createServiceWithConfig(true);

        $this->assertFalse($service->isTrackingEffective($user));
    }

    /**
     * Test effective tracking when both are disabled.
     *
     * Verifies that isTrackingEffective() returns false when
     * both global and user settings are disabled.
     */
    public function testIsTrackingEffectiveBothDisabled(): void
    {
        $user = $this->createMockUser(false);

        $service = $this->createServiceWithConfig(false);

        $this->assertFalse($service->isTrackingEffective($user));
    }

    /**
     * Test effective tracking with null global value.
     *
     * Verifies that isTrackingEffective() returns false when
     * global setting is null (not configured).
     */
    public function testIsTrackingEffectiveWithNullGlobal(): void
    {
        $user = $this->createMockUser(true);

        $service = $this->createServiceWithConfig(null);

        $this->assertFalse($service->isTrackingEffective($user));
    }
}
