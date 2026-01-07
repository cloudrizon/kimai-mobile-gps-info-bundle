<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\EventSubscriber;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use App\Form\Type\YesNoType;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\PermissionsSubscriber;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\UserPreferenceSubscriber;
use KimaiPlugin\KimaiMobileGPSInfoBundle\Service\GpsConfigService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for UserPreferenceSubscriber.
 *
 * Tests the GPS tracking user preference event subscriber
 * to ensure proper event registration, preference creation,
 * permission-based visibility, and dynamic help text with effective status.
 */
class UserPreferenceSubscriberTest extends TestCase
{
    /**
     * Creates a mock User entity.
     *
     * @param bool $gpsPreferenceValue The value to return for GPS tracking preference
     *
     * @return User&MockObject
     */
    private function createUserMock(bool $gpsPreferenceValue = true): User
    {
        $user = $this->createMock(User::class);
        $user->method('getPreferenceValue')
            ->with('gps_tracking_enabled', true)
            ->willReturn($gpsPreferenceValue);

        return $user;
    }

    /**
     * Creates a UserPreferenceSubscriber with configurable mock dependencies.
     *
     * @param bool $hasPermission Whether the current user has gps_edit_user_preference permission
     * @param bool $globalEnabled Whether global GPS tracking is enabled
     * @param bool $userEnabled Whether the user's GPS preference is enabled
     *
     * @return UserPreferenceSubscriber The configured subscriber instance
     */
    private function createSubscriber(
        bool $hasPermission = true,
        bool $globalEnabled = true,
        bool $userEnabled = true
    ): UserPreferenceSubscriber {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')
            ->with(PermissionsSubscriber::PERMISSION_EDIT_USER_TRACKING)
            ->willReturn($hasPermission);

        $gpsConfigService = $this->createMock(GpsConfigService::class);
        $gpsConfigService->method('isGlobalTrackingEnabled')
            ->willReturn($globalEnabled);
        $gpsConfigService->method('isUserTrackingEnabled')
            ->willReturn($userEnabled);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(fn(string $key) => $key);

        return new UserPreferenceSubscriber($security, $gpsConfigService, $translator);
    }

    /**
     * Test that subscriber registers correct events.
     *
     * Verifies that the subscriber listens to UserPreferenceEvent
     * with the correct method and priority.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = UserPreferenceSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(UserPreferenceEvent::class, $events);
        $this->assertEquals(['onUserPreference', 100], $events[UserPreferenceEvent::class]);
    }

    /**
     * Test that preference is enabled when user has permission and global is on.
     *
     * Verifies that the GPS tracking preference is added to the event
     * and is enabled when the current user has permission and global is enabled.
     */
    public function testPreferenceEnabledWhenHasPermissionAndGlobalOn(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: true,
            globalEnabled: true,
            userEnabled: true
        );

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $this->assertCount(1, $preferences);

        $preference = $preferences[0];
        $this->assertInstanceOf(UserPreference::class, $preference);
        $this->assertEquals('gps_tracking_enabled', $preference->getName());
        $this->assertTrue($preference->isEnabled());
    }

    /**
     * Test that preference is disabled without permission.
     *
     * Verifies that the GPS tracking preference is added but
     * disabled when the current user lacks the permission.
     */
    public function testPreferenceDisabledWithoutPermission(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: false,
            globalEnabled: true,
            userEnabled: true
        );

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $this->assertCount(1, $preferences);

        $preference = $preferences[0];
        $this->assertFalse($preference->isEnabled());
    }

    /**
     * Test that preference is disabled when global is off even with permission.
     *
     * Verifies that the field is grayed out when global tracking is disabled,
     * even if the user has permission to edit.
     */
    public function testPreferenceDisabledWhenGlobalOff(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: true,
            globalEnabled: false,
            userEnabled: true
        );

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];

        $this->assertFalse($preference->isEnabled());
    }

    /**
     * Test that preference is disabled when both permission and global are off.
     */
    public function testPreferenceDisabledWhenNoPermissionAndGlobalOff(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: false,
            globalEnabled: false,
            userEnabled: true
        );

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];

        $this->assertFalse($preference->isEnabled());
    }

    /**
     * Test that correct permission is checked.
     *
     * Verifies that the subscriber checks for the gps_edit_user_preference
     * permission and not hardcoded admin roles.
     */
    public function testCorrectPermissionIsChecked(): void
    {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with('gps_edit_user_preference')
            ->willReturn(true);

        $gpsConfigService = $this->createMock(GpsConfigService::class);
        $gpsConfigService->method('isGlobalTrackingEnabled')->willReturn(true);
        $gpsConfigService->method('isUserTrackingEnabled')->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $subscriber = new UserPreferenceSubscriber($security, $gpsConfigService, $translator);

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $this->assertTrue($preference->isEnabled());
    }

    /**
     * Test that preference has correct default value.
     *
     * Verifies that gps_tracking_enabled defaults to true
     * (users opt-in by default when global setting is enabled).
     */
    public function testPreferenceDefaultValue(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];

        // Default value is true (users opt-in by default)
        $this->assertTrue($preference->getValue());
    }

    /**
     * Test that preference uses YesNoType.
     *
     * Verifies that the preference uses Kimai's YesNoType
     * for consistent boolean toggle UI.
     */
    public function testPreferenceFieldType(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];

        $this->assertEquals(YesNoType::class, $preference->getType());
    }

    /**
     * Test that preference has correct section.
     *
     * Verifies that the preference is grouped in the 'gps' section.
     */
    public function testPreferenceSection(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];

        $this->assertEquals('gps', $preference->getSection());
    }

    /**
     * Test that preference has correct order.
     *
     * Verifies that the preference has order 1000 to appear
     * after standard Kimai preferences.
     */
    public function testPreferenceOrder(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];

        $this->assertEquals(1000, $preference->getOrder());
    }

    /**
     * Test that preference has correct label option.
     *
     * Verifies that the preference uses the correct translation key
     * for its label.
     */
    public function testPreferenceLabel(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $options = $preference->getOptions();

        $this->assertArrayHasKey('label', $options);
        $this->assertEquals('gps.user_tracking_enabled', $options['label']);
    }

    // =========================================================================
    // Phase 3.2: Dynamic Help Text Tests
    // =========================================================================

    /**
     * Test help text contains static explanation.
     *
     * Verifies that the help text includes the explanation about
     * GPS tracking requirements.
     */
    public function testHelpTextContainsExplanation(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $options = $preference->getOptions();

        $this->assertArrayHasKey('help', $options);
        $this->assertStringContainsString('gps.user_tracking.help.explanation', $options['help']);
    }

    /**
     * Test help text shows "Active" status when both settings enabled.
     *
     * Verifies that when global is ON and user is ON, the effective
     * status shows as "Active".
     */
    public function testHelpTextShowsActiveStatusWhenBothEnabled(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: true,
            globalEnabled: true,
            userEnabled: true
        );

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $options = $preference->getOptions();

        $this->assertStringContainsString('gps.user_tracking.effective_status', $options['help']);
        $this->assertStringContainsString('gps.user_tracking.status.active', $options['help']);
        $this->assertStringNotContainsString('gps.user_tracking.status.inactive', $options['help']);
    }

    /**
     * Test help text shows "Inactive" with reason when global disabled.
     *
     * Verifies that when global is OFF, the effective status shows
     * as "Inactive (global tracking is disabled)".
     */
    public function testHelpTextShowsInactiveWithReasonWhenGlobalDisabled(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: true,
            globalEnabled: false,
            userEnabled: true
        );

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $options = $preference->getOptions();

        $this->assertStringContainsString('gps.user_tracking.status.inactive', $options['help']);
        $this->assertStringContainsString('gps.user_tracking.status.global_disabled', $options['help']);
    }

    /**
     * Test help text shows "Inactive" without reason when only user disabled.
     *
     * Verifies that when global is ON but user is OFF, the effective
     * status shows as "Inactive" without the global disabled reason.
     */
    public function testHelpTextShowsInactiveWithoutReasonWhenUserDisabled(): void
    {
        $subscriber = $this->createSubscriber(
            hasPermission: true,
            globalEnabled: true,
            userEnabled: false
        );

        $user = $this->createUserMock(false);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $options = $preference->getOptions();

        $this->assertStringContainsString('gps.user_tracking.status.inactive', $options['help']);
        $this->assertStringNotContainsString('gps.user_tracking.status.global_disabled', $options['help']);
    }

    /**
     * Test help text format includes newline separator.
     *
     * Verifies that the help text has explanation on first line
     * and effective status on second line.
     */
    public function testHelpTextFormatIncludesNewline(): void
    {
        $subscriber = $this->createSubscriber();

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);

        $preferences = $event->getPreferences();
        $preference = $preferences[0];
        $options = $preference->getOptions();

        $this->assertStringContainsString("\n", $options['help']);
    }

    /**
     * Test that all translation keys are used in help text construction.
     *
     * Verifies that the translator is called with the correct keys.
     */
    public function testTranslatorCalledWithCorrectKeys(): void
    {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')->willReturn(true);

        $gpsConfigService = $this->createMock(GpsConfigService::class);
        $gpsConfigService->method('isGlobalTrackingEnabled')->willReturn(true);
        $gpsConfigService->method('isUserTrackingEnabled')->willReturn(true);

        $expectedKeys = [
            'gps.user_tracking.help.explanation',
            'gps.user_tracking.effective_status',
            'gps.user_tracking.status.active',
        ];

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->exactly(3))
            ->method('trans')
            ->willReturnCallback(function (string $key) use ($expectedKeys) {
                $this->assertContains($key, $expectedKeys);
                return $key;
            });

        $subscriber = new UserPreferenceSubscriber($security, $gpsConfigService, $translator);

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);
    }

    /**
     * Test that inactive status includes global disabled reason translation key.
     *
     * Verifies that when global is disabled, the global_disabled reason
     * translation key is included.
     */
    public function testInactiveStatusIncludesGlobalDisabledReasonKey(): void
    {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')->willReturn(true);

        $gpsConfigService = $this->createMock(GpsConfigService::class);
        $gpsConfigService->method('isGlobalTrackingEnabled')->willReturn(false);
        $gpsConfigService->method('isUserTrackingEnabled')->willReturn(true);

        $expectedKeys = [
            'gps.user_tracking.help.explanation',
            'gps.user_tracking.effective_status',
            'gps.user_tracking.status.inactive',
            'gps.user_tracking.status.global_disabled',
        ];

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->exactly(4))
            ->method('trans')
            ->willReturnCallback(function (string $key) use ($expectedKeys) {
                $this->assertContains($key, $expectedKeys);
                return $key;
            });

        $subscriber = new UserPreferenceSubscriber($security, $gpsConfigService, $translator);

        $user = $this->createUserMock(true);
        $event = new UserPreferenceEvent($user, []);

        $subscriber->onUserPreference($event);
    }
}
