<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\EventSubscriber;

use App\Event\ThemeEvent;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\GeofenceAdminThemeSubscriber;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\PermissionsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Unit tests for GeofenceAdminThemeSubscriber.
 *
 * Tests the geofence field visibility JavaScript injection to ensure
 * proper event registration, permission gating, and correct field selectors.
 */
class GeofenceAdminThemeSubscriberTest extends TestCase
{
    /**
     * Test that subscriber registers both STYLESHEET and JAVASCRIPT events.
     *
     * Verifies that the subscriber listens to both ThemeEvent::STYLESHEET
     * and ThemeEvent::JAVASCRIPT with the correct methods and priorities.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = GeofenceAdminThemeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ThemeEvent::JAVASCRIPT, $events);
        $this->assertEquals(['renderJavascript', 100], $events[ThemeEvent::JAVASCRIPT]);

        $this->assertArrayHasKey(ThemeEvent::STYLESHEET, $events);
        $this->assertEquals(['renderStylesheet', 100], $events[ThemeEvent::STYLESHEET]);
    }

    /**
     * Test that subscriber DOES subscribe to stylesheet event for map CSS.
     *
     * Verifies that CSS is injected for Leaflet and custom map styles.
     */
    public function testSubscribesToStylesheetEvent(): void
    {
        $events = GeofenceAdminThemeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ThemeEvent::STYLESHEET, $events);
        $this->assertEquals(['renderStylesheet', 100], $events[ThemeEvent::STYLESHEET]);
    }

    /**
     * Test that JavaScript is injected when user has view permission.
     *
     * Verifies that JS content is added to the ThemeEvent when the
     * user has the gps_view_data permission.
     */
    public function testJavascriptInjectedWithViewPermission(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(PermissionsSubscriber::PERMISSION_VIEW_DATA)
            ->willReturn(true);

        $event = $this->createMock(ThemeEvent::class);
        $event->expects($this->once())
            ->method('addContent')
            ->with($this->isType('string'));

        $subscriber = new GeofenceAdminThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);
    }

    /**
     * Test that JavaScript is NOT injected without view permission.
     *
     * Verifies that no JS content is added when the user lacks
     * the gps_view_data permission.
     */
    public function testJavascriptNotInjectedWithoutViewPermission(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(PermissionsSubscriber::PERMISSION_VIEW_DATA)
            ->willReturn(false);

        $event = $this->createMock(ThemeEvent::class);
        $event->expects($this->never())
            ->method('addContent');

        $subscriber = new GeofenceAdminThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);
    }

    /**
     * Test that stylesheet is injected when user has view permission.
     *
     * Verifies that CSS content is added to the ThemeEvent when the
     * user has the gps_view_data permission.
     */
    public function testStylesheetInjectedWithViewPermission(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(PermissionsSubscriber::PERMISSION_VIEW_DATA)
            ->willReturn(true);

        $event = $this->createMock(ThemeEvent::class);
        $event->expects($this->once())
            ->method('addContent')
            ->with($this->isType('string'));

        $subscriber = new GeofenceAdminThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);
    }

    /**
     * Test that stylesheet is NOT injected without view permission.
     *
     * Verifies that no CSS content is added when the user lacks
     * the gps_view_data permission.
     */
    public function testStylesheetNotInjectedWithoutViewPermission(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(PermissionsSubscriber::PERMISSION_VIEW_DATA)
            ->willReturn(false);

        $event = $this->createMock(ThemeEvent::class);
        $event->expects($this->never())
            ->method('addContent');

        $subscriber = new GeofenceAdminThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);
    }

    /**
     * Test that Leaflet CSS CDN is included in stylesheet output.
     *
     * Verifies that the Leaflet CSS link from unpkg CDN is present.
     */
    public function testLeafletCssIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockStylesheetEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('unpkg.com/leaflet', $capturedContent);
        $this->assertStringContainsString('leaflet.css', $capturedContent);
        $this->assertStringContainsString('integrity=', $capturedContent);
    }

    /**
     * Test that custom geofence map CSS classes are included.
     *
     * Verifies that custom CSS for map container and related elements is present.
     */
    public function testGeofenceMapCssIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockStylesheetEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('.geofence-map-container', $capturedContent);
        $this->assertStringContainsString('.geofence-map-header', $capturedContent);
        $this->assertStringContainsString('.geofence-map', $capturedContent);
        $this->assertStringContainsString('.geofence-map-empty', $capturedContent);
        $this->assertStringContainsString('.geofence-map-loading', $capturedContent);
    }

    /**
     * Test that Leaflet JS CDN is included in javascript output.
     *
     * Verifies that the Leaflet JS script from unpkg CDN is present.
     */
    public function testLeafletJsIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('unpkg.com/leaflet', $capturedContent);
        $this->assertStringContainsString('leaflet.js', $capturedContent);
        $this->assertStringContainsString('integrity=', $capturedContent);
    }

    /**
     * Test that geofence toggle detection uses findConfigField function.
     *
     * Verifies that the JS code uses the findConfigField helper function
     * to locate the geofence_enabled config field by its config key name.
     */
    public function testGeofenceToggleDetectionIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        // Reason: The generic findConfigField function handles the hidden input lookup
        $this->assertStringContainsString(
            "findConfigField('gps.geofence_enabled')",
            $capturedContent
        );
    }

    /**
     * Test that all 5 dependent field config names are included.
     *
     * Verifies that the JS code contains all geofence config key names
     * used to find dependent fields via hidden input value lookup.
     */
    public function testAllDependentFieldConfigNamesIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        // Reason: All 5 config keys registered in SystemConfigurationSubscriber
        // that depend on geofence being enabled must be present
        $expectedConfigNames = [
            'gps.geofence_center_lat',
            'gps.geofence_center_lng',
            'gps.geofence_radius',
            'gps.geofence_notify_after',
            'gps.geofence_restrict_mobile_tracking',
        ];

        foreach ($expectedConfigNames as $configName) {
            $this->assertStringContainsString(
                $configName,
                $capturedContent,
                "Missing dependent field config name: {$configName}"
            );
        }
    }

    /**
     * Test that checkbox .checked property is used instead of .value.
     *
     * Verifies that the JS code reads the toggle state via the checkbox
     * .checked property (not .value) since YesNoType extends CheckboxType.
     */
    public function testUsesCheckedProperty(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('toggle.checked', $capturedContent);
    }

    /**
     * Test that debug logging is included for troubleshooting.
     *
     * Verifies that the JS code includes console.log statements
     * with the [GPS Geofence] prefix for browser console debugging.
     */
    public function testDebugLoggingIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('[GPS Geofence]', $capturedContent);
        $this->assertStringContainsString('console.log', $capturedContent);
    }

    /**
     * Test that parentElement is used for container targeting.
     *
     * Verifies that the JS code uses parentElement to target the compound
     * form widget wrapper div (not .closest('.mb-3') which finds a shared parent).
     */
    public function testParentElementSelectorIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('parentElement', $capturedContent);
    }

    /**
     * Test that change event listener is included.
     *
     * Verifies that the JS code binds a change event listener
     * to the geofence toggle for immediate field visibility updates.
     */
    public function testChangeEventListenerIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('addEventListener', $capturedContent);
        $this->assertStringContainsString("'change'", $capturedContent);
    }

    /**
     * Test that form reset event listener is included.
     *
     * Verifies that the JS handles HTML form reset to re-sync field
     * visibility, since standard form reset does not fire change events.
     */
    public function testFormResetEventListenerIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString("addEventListener('reset'", $capturedContent);
        // Reason: setTimeout is required to let the browser finish resetting DOM values
        $this->assertStringContainsString('setTimeout', $capturedContent);
    }

    /**
     * Test that map container creation code is included.
     *
     * Verifies that the JS code creates a map container element
     * with the expected structure and class names.
     */
    public function testMapContainerCreationCodeIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('createGeofenceMapContainer', $capturedContent);
        $this->assertStringContainsString('geofence-map-container', $capturedContent);
        $this->assertStringContainsString('geofence-map-display', $capturedContent);
        $this->assertStringContainsString('Geofence Preview', $capturedContent);
    }

    /**
     * Test that Leaflet map initialization code is included.
     *
     * Verifies that the JS code initializes a Leaflet map with
     * L.map, L.marker, and L.circle components.
     */
    public function testMapInitializationCodeIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        // Reason: Verify Leaflet library is used
        $this->assertStringContainsString("typeof L === 'undefined'", $capturedContent);
        $this->assertStringContainsString('L.map(', $capturedContent);
        $this->assertStringContainsString('L.marker(', $capturedContent);
        $this->assertStringContainsString('L.circle(', $capturedContent);
        $this->assertStringContainsString('L.tileLayer(', $capturedContent);
    }

    /**
     * Test that blur event listeners are set up for coordinate fields.
     *
     * Verifies that the JS code attaches blur event listeners to
     * lat, lng, and radius fields to update the map on value change.
     */
    public function testBlurEventListenersIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('setupFieldBlurListeners', $capturedContent);
        $this->assertStringContainsString("addEventListener('blur'", $capturedContent);
        $this->assertStringContainsString('updateGeofenceMap', $capturedContent);
    }

    /**
     * Test that map visibility is integrated with toggle state.
     *
     * Verifies that the JS code handles showing/hiding the map container
     * based on the geofence toggle state and destroys map when hidden.
     */
    public function testMapVisibilityIntegrationIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('handleMapVisibility', $capturedContent);
        $this->assertStringContainsString('destroyGeofenceMap', $capturedContent);
        $this->assertStringContainsString('initGeofenceMap', $capturedContent);
    }

    /**
     * Test that coordinate validation is included.
     *
     * Verifies that the JS code validates coordinate ranges
     * (lat -90 to 90, lng -180 to 180, radius 10 to 1000).
     */
    public function testCoordinateValidationIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('getGeofenceCoordinates', $capturedContent);
        $this->assertStringContainsString('lat >= -90', $capturedContent);
        $this->assertStringContainsString('lat <= 90', $capturedContent);
        $this->assertStringContainsString('lng >= -180', $capturedContent);
        $this->assertStringContainsString('lng <= 180', $capturedContent);
        $this->assertStringContainsString('radius >= 10', $capturedContent);
        $this->assertStringContainsString('radius <= 1000', $capturedContent);
    }

    /**
     * Test that zoom calculation function is included.
     *
     * Verifies that the JS code calculates appropriate zoom levels
     * based on the geofence radius.
     */
    public function testZoomCalculationIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('calculateZoom', $capturedContent);
        // Reason: Different zoom levels for different radius ranges
        $this->assertStringContainsString('radiusMeters <= 100', $capturedContent);
        $this->assertStringContainsString('return 17', $capturedContent);
        $this->assertStringContainsString('return 14', $capturedContent);
    }

    /**
     * Test that empty state handling is included.
     *
     * Verifies that the JS code shows an empty state message
     * when coordinates are not entered or invalid.
     */
    public function testEmptyStateHandlingIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('showEmptyState', $capturedContent);
        $this->assertStringContainsString('hideEmptyState', $capturedContent);
        $this->assertStringContainsString('geofence-map-empty', $capturedContent);
        $this->assertStringContainsString('Enter coordinates and radius', $capturedContent);
    }

    /**
     * Test that OpenStreetMap tile layer is used.
     *
     * Verifies that the JS code uses OpenStreetMap tiles (free, no API key).
     */
    public function testOpenStreetMapTileLayerIncluded(): void
    {
        $capturedContent = '';
        $event = $this->createMockEventWithCapture($capturedContent);

        $subscriber = new GeofenceAdminThemeSubscriber(
            $this->createGrantedAuthChecker()
        );
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('tile.openstreetmap.org', $capturedContent);
        $this->assertStringContainsString('OpenStreetMap', $capturedContent);
    }

    /**
     * Create a mock AuthorizationCheckerInterface that grants permission.
     *
     * @return AuthorizationCheckerInterface Mocked auth checker
     */
    private function createGrantedAuthChecker(): AuthorizationCheckerInterface
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        return $authChecker;
    }

    /**
     * Create a mock ThemeEvent that captures added content.
     *
     * @param string &$capturedContent Reference to store captured content
     * @return ThemeEvent Mocked theme event
     */
    private function createMockEventWithCapture(string &$capturedContent): ThemeEvent
    {
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        return $event;
    }

    /**
     * Create a mock ThemeEvent for stylesheet that captures added content.
     *
     * @param string &$capturedContent Reference to store captured content
     * @return ThemeEvent Mocked theme event
     */
    private function createMockStylesheetEventWithCapture(string &$capturedContent): ThemeEvent
    {
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        return $event;
    }
}
