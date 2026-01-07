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
}
