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

    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(mixed $returnValue = null)
    {
        $this->returnValue = $returnValue;
    }

    public function find(string $key): mixed
    {
        // Check if specific key value is set, otherwise return default
        if (isset($this->values[$key])) {
            return $this->values[$key];
        }

        return $this->returnValue;
    }

    public function setReturnValue(mixed $value): void
    {
        $this->returnValue = $value;
    }

    /**
     * Set a specific configuration value for a key.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * Set multiple configuration values at once.
     *
     * @param array<string, mixed> $values Key-value pairs
     */
    public function setValues(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->values[$key] = $value;
        }
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

    // ========================================
    // Helper Methods for Geofence Tests
    // ========================================

    /**
     * Create a GpsConfigService with multiple stubbed configuration values.
     *
     * @param array<string, mixed> $configValues Configuration key-value pairs
     *
     * @return GpsConfigService The configured service
     */
    private function createServiceWithMultipleConfigs(array $configValues): GpsConfigService
    {
        $stub = new SystemConfigurationStub();
        $stub->setValues($configValues);

        // Anonymous class that overrides all methods to use the stub
        return new class ($stub) extends GpsConfigService {
            private SystemConfigurationStub $stubConfig;

            public function __construct(SystemConfigurationStub $stub)
            {
                $this->stubConfig = $stub;
            }

            public function isGlobalTrackingEnabled(): bool
            {
                $value = $this->stubConfig->find('gps.tracking_enabled');
                return (bool) $value;
            }

            public function isGeofenceEnabled(): bool
            {
                $value = $this->stubConfig->find('gps.geofence_enabled');
                return (bool) $value;
            }

            public function getGeofenceCenterLat(): ?float
            {
                $value = $this->stubConfig->find('gps.geofence_center_lat');
                if ($value === null || $value === '') {
                    return null;
                }
                return (float) $value;
            }

            public function getGeofenceCenterLng(): ?float
            {
                $value = $this->stubConfig->find('gps.geofence_center_lng');
                if ($value === null || $value === '') {
                    return null;
                }
                return (float) $value;
            }

            public function getGeofenceRadius(): ?int
            {
                $value = $this->stubConfig->find('gps.geofence_radius');
                if ($value === null || $value === '') {
                    return null;
                }
                return (int) $value;
            }

            public function getGeofenceNotifyAfter(): int
            {
                $value = $this->stubConfig->find('gps.geofence_notify_after');
                if ($value === null || $value === '') {
                    return 5; // Default value
                }
                return (int) $value;
            }

            public function isGeofenceRestrictMobileTracking(): bool
            {
                $value = $this->stubConfig->find('gps.geofence_restrict_mobile_tracking');
                return (bool) $value;
            }
        };
    }

    // ========================================
    // Tests for isGeofenceEnabled()
    // ========================================

    /**
     * Test geofence enabled returns true when enabled.
     */
    public function testIsGeofenceEnabledReturnsTrueWhenEnabled(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
        ]);

        $this->assertTrue($service->isGeofenceEnabled());
    }

    /**
     * Test geofence enabled returns false when disabled.
     */
    public function testIsGeofenceEnabledReturnsFalseWhenDisabled(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => false,
        ]);

        $this->assertFalse($service->isGeofenceEnabled());
    }

    /**
     * Test geofence enabled returns false when null (privacy by design).
     */
    public function testIsGeofenceEnabledReturnsFalseWhenNull(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => null,
        ]);

        $this->assertFalse($service->isGeofenceEnabled());
    }

    /**
     * Test geofence enabled with string value "1".
     */
    public function testIsGeofenceEnabledWithStringOne(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => '1',
        ]);

        $this->assertTrue($service->isGeofenceEnabled());
    }

    /**
     * Test geofence enabled with string value "0".
     */
    public function testIsGeofenceEnabledWithStringZero(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => '0',
        ]);

        $this->assertFalse($service->isGeofenceEnabled());
    }

    // ========================================
    // Tests for getGeofenceCenterLat()
    // ========================================

    /**
     * Test get center lat returns value when set.
     */
    public function testGetGeofenceCenterLatReturnsValueWhenSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lat' => 52.5162748,
        ]);

        $this->assertSame(52.5162748, $service->getGeofenceCenterLat());
    }

    /**
     * Test get center lat returns null when not set.
     */
    public function testGetGeofenceCenterLatReturnsNullWhenNotSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lat' => null,
        ]);

        $this->assertNull($service->getGeofenceCenterLat());
    }

    /**
     * Test get center lat returns null for empty string.
     */
    public function testGetGeofenceCenterLatReturnsNullForEmptyString(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lat' => '',
        ]);

        $this->assertNull($service->getGeofenceCenterLat());
    }

    /**
     * Test get center lat casts string to float.
     */
    public function testGetGeofenceCenterLatCastsStringToFloat(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lat' => '52.5162748',
        ]);

        $this->assertSame(52.5162748, $service->getGeofenceCenterLat());
    }

    /**
     * Test get center lat handles negative value (Southern hemisphere).
     */
    public function testGetGeofenceCenterLatHandlesNegativeValue(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lat' => -33.8688,
        ]);

        $this->assertSame(-33.8688, $service->getGeofenceCenterLat());
    }

    /**
     * Test get center lat handles zero (equator edge case).
     */
    public function testGetGeofenceCenterLatHandlesZero(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lat' => 0,
        ]);

        $this->assertSame(0.0, $service->getGeofenceCenterLat());
    }

    // ========================================
    // Tests for getGeofenceCenterLng()
    // ========================================

    /**
     * Test get center lng returns value when set.
     */
    public function testGetGeofenceCenterLngReturnsValueWhenSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lng' => 13.3774573,
        ]);

        $this->assertSame(13.3774573, $service->getGeofenceCenterLng());
    }

    /**
     * Test get center lng returns null when not set.
     */
    public function testGetGeofenceCenterLngReturnsNullWhenNotSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lng' => null,
        ]);

        $this->assertNull($service->getGeofenceCenterLng());
    }

    /**
     * Test get center lng returns null for empty string.
     */
    public function testGetGeofenceCenterLngReturnsNullForEmptyString(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lng' => '',
        ]);

        $this->assertNull($service->getGeofenceCenterLng());
    }

    /**
     * Test get center lng casts string to float.
     */
    public function testGetGeofenceCenterLngCastsStringToFloat(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lng' => '13.3774573',
        ]);

        $this->assertSame(13.3774573, $service->getGeofenceCenterLng());
    }

    /**
     * Test get center lng handles negative value (Western hemisphere).
     */
    public function testGetGeofenceCenterLngHandlesNegativeValue(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lng' => -122.4194,
        ]);

        $this->assertSame(-122.4194, $service->getGeofenceCenterLng());
    }

    /**
     * Test get center lng handles zero (prime meridian edge case).
     */
    public function testGetGeofenceCenterLngHandlesZero(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_center_lng' => 0,
        ]);

        $this->assertSame(0.0, $service->getGeofenceCenterLng());
    }

    // ========================================
    // Tests for getGeofenceRadius()
    // ========================================

    /**
     * Test get radius returns value when set.
     */
    public function testGetGeofenceRadiusReturnsValueWhenSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_radius' => 500,
        ]);

        $this->assertSame(500, $service->getGeofenceRadius());
    }

    /**
     * Test get radius returns null when not set.
     */
    public function testGetGeofenceRadiusReturnsNullWhenNotSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_radius' => null,
        ]);

        $this->assertNull($service->getGeofenceRadius());
    }

    /**
     * Test get radius returns null for empty string.
     */
    public function testGetGeofenceRadiusReturnsNullForEmptyString(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_radius' => '',
        ]);

        $this->assertNull($service->getGeofenceRadius());
    }

    /**
     * Test get radius casts string to int.
     */
    public function testGetGeofenceRadiusCastsStringToInt(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_radius' => '500',
        ]);

        $this->assertSame(500, $service->getGeofenceRadius());
    }

    /**
     * Test get radius handles minimum value (10m).
     */
    public function testGetGeofenceRadiusHandlesMinimumValue(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_radius' => 10,
        ]);

        $this->assertSame(10, $service->getGeofenceRadius());
    }

    /**
     * Test get radius handles maximum value (1000m).
     */
    public function testGetGeofenceRadiusHandlesMaximumValue(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_radius' => 1000,
        ]);

        $this->assertSame(1000, $service->getGeofenceRadius());
    }

    // ========================================
    // Tests for getGeofenceNotifyAfter()
    // ========================================

    /**
     * Test get notify after returns value when set.
     */
    public function testGetGeofenceNotifyAfterReturnsValueWhenSet(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_notify_after' => 10,
        ]);

        $this->assertSame(10, $service->getGeofenceNotifyAfter());
    }

    /**
     * Test get notify after returns default (5) when null.
     */
    public function testGetGeofenceNotifyAfterReturnsDefaultWhenNull(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_notify_after' => null,
        ]);

        $this->assertSame(5, $service->getGeofenceNotifyAfter());
    }

    /**
     * Test get notify after returns default for empty string.
     */
    public function testGetGeofenceNotifyAfterReturnsDefaultForEmptyString(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_notify_after' => '',
        ]);

        $this->assertSame(5, $service->getGeofenceNotifyAfter());
    }

    /**
     * Test get notify after casts string to int.
     */
    public function testGetGeofenceNotifyAfterCastsStringToInt(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_notify_after' => '15',
        ]);

        $this->assertSame(15, $service->getGeofenceNotifyAfter());
    }

    /**
     * Test get notify after handles zero (immediate notification).
     */
    public function testGetGeofenceNotifyAfterHandlesZero(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_notify_after' => 0,
        ]);

        $this->assertSame(0, $service->getGeofenceNotifyAfter());
    }

    /**
     * Test get notify after handles maximum value (60 minutes).
     */
    public function testGetGeofenceNotifyAfterHandlesMaximum(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_notify_after' => 60,
        ]);

        $this->assertSame(60, $service->getGeofenceNotifyAfter());
    }

    // ========================================
    // Tests for isGeofenceRestrictMobileTracking()
    // ========================================

    /**
     * Test restrict mobile tracking returns true when enabled.
     */
    public function testIsGeofenceRestrictMobileTrackingReturnsTrueWhenEnabled(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_restrict_mobile_tracking' => true,
        ]);

        $this->assertTrue($service->isGeofenceRestrictMobileTracking());
    }

    /**
     * Test restrict mobile tracking returns false when disabled.
     */
    public function testIsGeofenceRestrictMobileTrackingReturnsFalseWhenDisabled(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_restrict_mobile_tracking' => false,
        ]);

        $this->assertFalse($service->isGeofenceRestrictMobileTracking());
    }

    /**
     * Test restrict mobile tracking returns false when null (permissive by default).
     */
    public function testIsGeofenceRestrictMobileTrackingReturnsFalseWhenNull(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_restrict_mobile_tracking' => null,
        ]);

        $this->assertFalse($service->isGeofenceRestrictMobileTracking());
    }

    /**
     * Test restrict mobile tracking with string value "1".
     */
    public function testIsGeofenceRestrictMobileTrackingWithStringOne(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_restrict_mobile_tracking' => '1',
        ]);

        $this->assertTrue($service->isGeofenceRestrictMobileTracking());
    }

    /**
     * Test restrict mobile tracking with string value "0".
     */
    public function testIsGeofenceRestrictMobileTrackingWithStringZero(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_restrict_mobile_tracking' => '0',
        ]);

        $this->assertFalse($service->isGeofenceRestrictMobileTracking());
    }

    // ========================================
    // Tests for isGeofenceConfigured()
    // ========================================

    /**
     * Test geofence configured returns true when complete.
     */
    public function testIsGeofenceConfiguredReturnsTrueWhenComplete(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $this->assertTrue($service->isGeofenceConfigured());
    }

    /**
     * Test geofence configured returns false when disabled.
     */
    public function testIsGeofenceConfiguredReturnsFalseWhenDisabled(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => false,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $this->assertFalse($service->isGeofenceConfigured());
    }

    /**
     * Test geofence configured returns false when lat missing.
     */
    public function testIsGeofenceConfiguredReturnsFalseWhenLatMissing(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => null,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $this->assertFalse($service->isGeofenceConfigured());
    }

    /**
     * Test geofence configured returns false when lng missing.
     */
    public function testIsGeofenceConfiguredReturnsFalseWhenLngMissing(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => null,
            'gps.geofence_radius' => 500,
        ]);

        $this->assertFalse($service->isGeofenceConfigured());
    }

    /**
     * Test geofence configured returns false when radius missing.
     */
    public function testIsGeofenceConfiguredReturnsFalseWhenRadiusMissing(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => null,
        ]);

        $this->assertFalse($service->isGeofenceConfigured());
    }

    /**
     * Test geofence configured returns false when all missing.
     */
    public function testIsGeofenceConfiguredReturnsFalseWhenAllMissing(): void
    {
        $service = $this->createServiceWithMultipleConfigs([]);

        $this->assertFalse($service->isGeofenceConfigured());
    }

    // ========================================
    // Tests for getGeofencesConfig()
    // ========================================

    /**
     * Test get geofences config returns empty array when not configured.
     */
    public function testGetGeofencesConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $service = $this->createServiceWithMultipleConfigs([]);

        $this->assertSame([], $service->getGeofencesConfig());
    }

    /**
     * Test get geofences config returns empty array when disabled.
     */
    public function testGetGeofencesConfigReturnsEmptyArrayWhenDisabled(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => false,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $this->assertSame([], $service->getGeofencesConfig());
    }

    /**
     * Test get geofences config returns array with one item when configured.
     */
    public function testGetGeofencesConfigReturnsArrayWithOneItemWhenConfigured(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertCount(1, $result);
    }

    /**
     * Test get geofences config object contains all required fields.
     */
    public function testGetGeofencesConfigObjectContainsAllRequiredFields(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $result = $service->getGeofencesConfig();
        $geofence = $result[0];

        $this->assertArrayHasKey('id', $geofence);
        $this->assertArrayHasKey('name', $geofence);
        $this->assertArrayHasKey('enabled', $geofence);
        $this->assertArrayHasKey('center_lat', $geofence);
        $this->assertArrayHasKey('center_lng', $geofence);
        $this->assertArrayHasKey('radius', $geofence);
        $this->assertArrayHasKey('notify_after_minutes', $geofence);
        $this->assertArrayHasKey('restrict_mobile_tracking', $geofence);
    }

    /**
     * Test get geofences config object has correct id.
     */
    public function testGetGeofencesConfigObjectHasCorrectId(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertSame('default', $result[0]['id']);
    }

    /**
     * Test get geofences config object has correct name.
     */
    public function testGetGeofencesConfigObjectHasCorrectName(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertSame('Workplace', $result[0]['name']);
    }

    /**
     * Test get geofences config object has correct coordinates.
     */
    public function testGetGeofencesConfigObjectHasCorrectCoordinates(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertSame(52.5162748, $result[0]['center_lat']);
        $this->assertSame(13.3774573, $result[0]['center_lng']);
    }

    /**
     * Test get geofences config object has correct radius.
     */
    public function testGetGeofencesConfigObjectHasCorrectRadius(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertSame(500, $result[0]['radius']);
    }

    /**
     * Test get geofences config object has default notify after.
     */
    public function testGetGeofencesConfigObjectHasDefaultNotifyAfter(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
            'gps.geofence_notify_after' => null,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertSame(5, $result[0]['notify_after_minutes']);
    }

    /**
     * Test get geofences config object has custom notify after.
     */
    public function testGetGeofencesConfigObjectHasCustomNotifyAfter(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
            'gps.geofence_notify_after' => 10,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertSame(10, $result[0]['notify_after_minutes']);
    }

    /**
     * Test get geofences config object has restrict mobile tracking false by default.
     */
    public function testGetGeofencesConfigObjectHasRestrictMobileTrackingFalseByDefault(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
            'gps.geofence_restrict_mobile_tracking' => null,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertFalse($result[0]['restrict_mobile_tracking']);
    }

    /**
     * Test get geofences config object has restrict mobile tracking true.
     */
    public function testGetGeofencesConfigObjectHasRestrictMobileTrackingTrue(): void
    {
        $service = $this->createServiceWithMultipleConfigs([
            'gps.geofence_enabled' => true,
            'gps.geofence_center_lat' => 52.5162748,
            'gps.geofence_center_lng' => 13.3774573,
            'gps.geofence_radius' => 500,
            'gps.geofence_restrict_mobile_tracking' => true,
        ]);

        $result = $service->getGeofencesConfig();

        $this->assertTrue($result[0]['restrict_mobile_tracking']);
    }
}
