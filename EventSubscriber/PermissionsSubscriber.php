<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber;

use App\Event\PermissionSectionsEvent;
use App\Model\PermissionSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for registering GPS tracking permissions.
 *
 * This subscriber adds custom permissions for GPS tracking functionality
 * to Kimai's role-based permission system. The permissions appear in the
 * Roles admin screen and can be assigned to any role.
 *
 * Permissions registered:
 * - gps_edit_user_preference: Allows editing GPS tracking preference for users
 * - gps_view_data: Allows viewing GPS location data in web UI
 * - gps_edit_data: Allows editing GPS location data in web UI
 */
final class PermissionsSubscriber implements EventSubscriberInterface
{
    /**
     * Permission name for editing user GPS tracking preference.
     */
    public const PERMISSION_EDIT_USER_TRACKING = 'gps_edit_user_preference';

    /**
     * Permission name for viewing GPS location data.
     */
    public const PERMISSION_VIEW_DATA = 'gps_view_data';

    /**
     * Permission name for editing GPS location data.
     */
    public const PERMISSION_EDIT_DATA = 'gps_edit_data';

    /**
     * Section prefix for GPS permissions.
     */
    private const SECTION_PREFIX = 'gps_';

    /**
     * Returns the events this subscriber listens to.
     *
     * @return array<string, array{string, int}> Event to method mapping with priority
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PermissionSectionsEvent::class => ['onPermissionSections', 100],
        ];
    }

    /**
     * Adds the Kimai Mobile GPS Info section to the permissions screen.
     *
     * This section groups all GPS-related permissions together in the
     * Roles admin screen for better organization.
     *
     * @param PermissionSectionsEvent $event The permission sections event
     */
    public function onPermissionSections(PermissionSectionsEvent $event): void
    {
        // Reason: Using plain string for section title because Kimai's permission
        // section system does not use the standard translator for section titles.
        // The filter 'gps_' ensures only permissions starting with this prefix
        // appear in this section.
        $event->addSection(new PermissionSection('Kimai Mobile GPS Info', self::SECTION_PREFIX));
    }
}
