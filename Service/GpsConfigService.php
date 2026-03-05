<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Service;

use App\Configuration\SystemConfiguration;
use App\Entity\User;

/**
 * Service for managing GPS tracking configuration.
 *
 * Provides methods to read GPS tracking settings from both system
 * configuration (global) and user preferences (per-user), and to
 * compute the effective tracking status.
 *
 * Configuration hierarchy:
 * - Global setting: `gps.tracking_enabled` (admin-controlled, default: false)
 * - User setting: `gps_tracking_enabled` (admin-controlled, default: true)
 * - Effective: Both must be true for tracking to be active
 */
class GpsConfigService
{
    /**
     * System configuration key for global GPS tracking setting.
     */
    private const CONFIG_KEY_GLOBAL = 'gps.tracking_enabled';

    /**
     * User preference key for per-user GPS tracking setting.
     */
    private const PREFERENCE_KEY_USER = 'gps_tracking_enabled';

    // ========================================
    // Geofence Configuration Keys
    // ========================================

    /**
     * System configuration key for geofence enabled toggle.
     */
    private const CONFIG_KEY_GEOFENCE_ENABLED = 'gps.geofence_enabled';

    /**
     * System configuration key for geofence center latitude.
     */
    private const CONFIG_KEY_GEOFENCE_CENTER_LAT = 'gps.geofence_center_lat';

    /**
     * System configuration key for geofence center longitude.
     */
    private const CONFIG_KEY_GEOFENCE_CENTER_LNG = 'gps.geofence_center_lng';

    /**
     * System configuration key for geofence radius in meters.
     */
    private const CONFIG_KEY_GEOFENCE_RADIUS = 'gps.geofence_radius';

    /**
     * System configuration key for notification delay in minutes.
     */
    private const CONFIG_KEY_GEOFENCE_NOTIFY_AFTER = 'gps.geofence_notify_after';

    /**
     * System configuration key for mobile tracking restriction.
     */
    private const CONFIG_KEY_GEOFENCE_RESTRICT_MOBILE_TRACKING = 'gps.geofence_restrict_mobile_tracking';

    /**
     * Default notification delay in minutes.
     */
    private const DEFAULT_GEOFENCE_NOTIFY_AFTER = 5;

    /**
     * Default geofence identifier for single-geofence setup.
     */
    private const DEFAULT_GEOFENCE_ID = 'default';

    /**
     * Default geofence display name.
     */
    private const DEFAULT_GEOFENCE_NAME = 'Workplace';

    /**
     * Constructor with autowired dependencies.
     *
     * @param SystemConfiguration $systemConfig System configuration service
     */
    public function __construct(
        private readonly SystemConfiguration $systemConfig
    ) {
    }

    /**
     * Check if GPS tracking is globally enabled.
     *
     * Reads the `gps.tracking_enabled` system configuration setting.
     * Returns false if the setting is not configured (privacy by design).
     *
     * @return bool True if GPS tracking is enabled system-wide
     */
    public function isGlobalTrackingEnabled(): bool
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GLOBAL);

        // Return false if not set (privacy by design)
        return (bool) $value;
    }

    /**
     * Check if GPS tracking is enabled for a specific user.
     *
     * Reads the `gps_tracking_enabled` user preference. Returns true
     * by default if the preference is not set, meaning users have
     * tracking enabled unless explicitly disabled by an admin.
     *
     * @param User $user The user to check
     *
     * @return bool True if GPS tracking is enabled for this user
     */
    public function isUserTrackingEnabled(User $user): bool
    {
        // Default to true if preference not set
        // Users opt-in by default when global setting is enabled
        return (bool) $user->getPreferenceValue(self::PREFERENCE_KEY_USER, true);
    }

    /**
     * Compute effective tracking status for a user.
     *
     * GPS tracking is only active when BOTH the global setting AND
     * the user preference are enabled. This allows:
     * - Admins to disable tracking system-wide (global off)
     * - Admins to disable tracking for specific users (user pref off)
     * - GDPR Article 21 objection handling (disable per-user)
     *
     * @param User $user The user to check
     *
     * @return bool True if GPS tracking should be active for this user
     */
    public function isTrackingEffective(User $user): bool
    {
        return $this->isGlobalTrackingEnabled() && $this->isUserTrackingEnabled($user);
    }

    // ========================================
    // Geofence Configuration Methods
    // ========================================

    /**
     * Check if geofence boundary checking is enabled.
     *
     * Reads the `gps.geofence_enabled` system configuration setting.
     * Returns false if the setting is not configured (privacy by design).
     *
     * @return bool True if geofence is enabled system-wide
     */
    public function isGeofenceEnabled(): bool
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GEOFENCE_ENABLED);

        // Return false if not set (privacy by design)
        return (bool) $value;
    }

    /**
     * Get the geofence center latitude.
     *
     * Reads the `gps.geofence_center_lat` system configuration setting.
     * Returns null if the setting is not configured.
     *
     * @return float|null Latitude in decimal degrees (-90 to 90), or null if not set
     */
    public function getGeofenceCenterLat(): ?float
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GEOFENCE_CENTER_LAT);

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Get the geofence center longitude.
     *
     * Reads the `gps.geofence_center_lng` system configuration setting.
     * Returns null if the setting is not configured.
     *
     * @return float|null Longitude in decimal degrees (-180 to 180), or null if not set
     */
    public function getGeofenceCenterLng(): ?float
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GEOFENCE_CENTER_LNG);

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Get the geofence radius in meters.
     *
     * Reads the `gps.geofence_radius` system configuration setting.
     * Returns null if the setting is not configured.
     *
     * @return int|null Radius in meters (expected range: 10-1000), or null if not set
     */
    public function getGeofenceRadius(): ?int
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GEOFENCE_RADIUS);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Get the notification delay in minutes.
     *
     * Reads the `gps.geofence_notify_after` system configuration setting.
     * Returns 5 minutes as default if not configured. This value determines
     * how long a user must be inside the geofence before notification triggers.
     *
     * @return int Minutes (expected range: 0-60, default: 5)
     */
    public function getGeofenceNotifyAfter(): int
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GEOFENCE_NOTIFY_AFTER);

        if ($value === null || $value === '') {
            return self::DEFAULT_GEOFENCE_NOTIFY_AFTER;
        }

        return (int) $value;
    }

    /**
     * Check if mobile time tracking is restricted to within geofence.
     *
     * Reads the `gps.geofence_restrict_mobile_tracking` system configuration.
     * When true, mobile clients should prevent starting timers outside geofence.
     * Returns false if not configured (permissive by default).
     *
     * @return bool True if tracking should be restricted to geofence area
     */
    public function isGeofenceRestrictMobileTracking(): bool
    {
        $value = $this->systemConfig->find(self::CONFIG_KEY_GEOFENCE_RESTRICT_MOBILE_TRACKING);

        // Return false if not set (permissive by default)
        return (bool) $value;
    }

    /**
     * Check if geofence is fully configured and ready for use.
     *
     * A geofence is considered configured when:
     * 1. Geofence is enabled (toggle on)
     * 2. Center latitude is set
     * 3. Center longitude is set
     * 4. Radius is set
     *
     * @return bool True if geofence is enabled and all required values are set
     */
    public function isGeofenceConfigured(): bool
    {
        if (!$this->isGeofenceEnabled()) {
            return false;
        }

        $lat = $this->getGeofenceCenterLat();
        $lng = $this->getGeofenceCenterLng();
        $radius = $this->getGeofenceRadius();

        // All location values must be set (not null)
        return $lat !== null && $lng !== null && $radius !== null;
    }

    /**
     * Get the geofence configuration array for API response.
     *
     * Returns an empty array if geofence is not configured.
     * Returns an array with a single geofence object if configured.
     * The array format supports future extensibility for multiple geofences.
     *
     * Geofence object format:
     * ```php
     * [
     *     'id' => 'default',
     *     'name' => 'Workplace',
     *     'enabled' => true,
     *     'center_lat' => 52.5162748,
     *     'center_lng' => 13.3774573,
     *     'radius' => 500,
     *     'notify_after_minutes' => 5,
     *     'restrict_mobile_tracking' => false
     * ]
     * ```
     *
     * @return array<int, array<string, mixed>> Empty array or array with 1 geofence
     */
    public function getGeofencesConfig(): array
    {
        if (!$this->isGeofenceConfigured()) {
            return [];
        }

        return [
            [
                'id' => self::DEFAULT_GEOFENCE_ID,
                'name' => self::DEFAULT_GEOFENCE_NAME,
                'enabled' => true,
                'center_lat' => $this->getGeofenceCenterLat(),
                'center_lng' => $this->getGeofenceCenterLng(),
                'radius' => $this->getGeofenceRadius(),
                'notify_after_minutes' => $this->getGeofenceNotifyAfter(),
                'restrict_mobile_tracking' => $this->isGeofenceRestrictMobileTracking(),
            ],
        ];
    }
}
