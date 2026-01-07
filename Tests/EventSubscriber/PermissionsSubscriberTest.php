<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\EventSubscriber;

use App\Event\PermissionSectionsEvent;
use App\Model\PermissionSection;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\PermissionsSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PermissionsSubscriber.
 *
 * Tests the GPS tracking permission section registration to ensure proper
 * event registration and section creation.
 *
 * Note: This subscriber only handles PermissionSectionsEvent to add a section
 * for grouping GPS permissions. The actual permissions are registered via
 * Kimai's permission configuration, not through a PermissionsEvent handler.
 */
class PermissionsSubscriberTest extends TestCase
{
    /**
     * Test that subscriber registers correct events.
     *
     * Verifies that the subscriber listens to PermissionSectionsEvent
     * with the correct method and priority.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = PermissionsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PermissionSectionsEvent::class, $events);
        $this->assertEquals(['onPermissionSections', 100], $events[PermissionSectionsEvent::class]);
    }

    /**
     * Test that permission section is added to the event.
     *
     * Verifies that calling onPermissionSections adds a new
     * PermissionSection object to the event.
     */
    public function testOnPermissionSectionsAddsSection(): void
    {
        $subscriber = new PermissionsSubscriber();
        $event = new PermissionSectionsEvent();

        $subscriber->onPermissionSections($event);

        $sections = $event->getSections();
        $this->assertCount(1, $sections);
        $this->assertInstanceOf(PermissionSection::class, $sections[0]);
    }

    /**
     * Test that permission section has correct title.
     *
     * Verifies that the GPS Tracking permission section uses
     * the correct title for display in the admin panel.
     */
    public function testPermissionSectionTitle(): void
    {
        $subscriber = new PermissionsSubscriber();
        $event = new PermissionSectionsEvent();

        $subscriber->onPermissionSections($event);

        $sections = $event->getSections();
        $section = $sections[0];

        $this->assertEquals('Kimai Mobile GPS Info', $section->getTitle());
    }

    /**
     * Test that permission section filters GPS permissions correctly.
     *
     * Verifies that the section's filter correctly matches
     * permissions starting with 'gps_'.
     */
    public function testPermissionSectionFilter(): void
    {
        $subscriber = new PermissionsSubscriber();
        $event = new PermissionSectionsEvent();

        $subscriber->onPermissionSections($event);

        $sections = $event->getSections();
        $section = $sections[0];

        // Should match gps_ prefixed permissions
        $this->assertTrue($section->filter('gps_edit_user_preference'));
        $this->assertTrue($section->filter('gps_view_data'));
        $this->assertTrue($section->filter('gps_edit_data'));
        $this->assertTrue($section->filter('gps_some_other_permission'));

        // Should not match non-gps permissions
        $this->assertFalse($section->filter('user_view'));
        $this->assertFalse($section->filter('timesheet_edit'));
    }

    /**
     * Test that PERMISSION_EDIT_USER_TRACKING constant has correct value.
     *
     * Verifies that the constant is set to the expected value.
     */
    public function testPermissionEditUserTrackingConstantValue(): void
    {
        $this->assertEquals('gps_edit_user_preference', PermissionsSubscriber::PERMISSION_EDIT_USER_TRACKING);
    }

    /**
     * Test that PERMISSION_VIEW_DATA constant has correct value.
     *
     * Verifies that the constant is set to the expected value.
     */
    public function testPermissionViewDataConstantValue(): void
    {
        $this->assertEquals('gps_view_data', PermissionsSubscriber::PERMISSION_VIEW_DATA);
    }

    /**
     * Test that PERMISSION_EDIT_DATA constant has correct value.
     *
     * Verifies that the constant is set to the expected value.
     */
    public function testPermissionEditDataConstantValue(): void
    {
        $this->assertEquals('gps_edit_data', PermissionsSubscriber::PERMISSION_EDIT_DATA);
    }

    /**
     * Test that subscriber is properly instantiated.
     *
     * Verifies that the subscriber can be created without errors.
     */
    public function testSubscriberCanBeInstantiated(): void
    {
        $subscriber = new PermissionsSubscriber();

        $this->assertInstanceOf(PermissionsSubscriber::class, $subscriber);
    }
}
