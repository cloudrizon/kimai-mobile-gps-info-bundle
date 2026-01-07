<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\EventSubscriber;

use App\Event\ThemeEvent;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\GpsMapThemeSubscriber;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\PermissionsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Unit tests for GpsMapThemeSubscriber.
 *
 * Tests the Leaflet.js map visualization injection to ensure proper
 * event registration, permission gating, and content injection.
 */
class GpsMapThemeSubscriberTest extends TestCase
{
    /**
     * Test that subscriber registers correct events.
     *
     * Verifies that the subscriber listens to both ThemeEvent::STYLESHEET
     * and ThemeEvent::JAVASCRIPT with the correct methods and priorities.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = GpsMapThemeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ThemeEvent::STYLESHEET, $events);
        $this->assertArrayHasKey(ThemeEvent::JAVASCRIPT, $events);
        $this->assertEquals(['renderStylesheet', 100], $events[ThemeEvent::STYLESHEET]);
        $this->assertEquals(['renderJavascript', 100], $events[ThemeEvent::JAVASCRIPT]);
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

        $subscriber = new GpsMapThemeSubscriber($authChecker);
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

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);
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

        $subscriber = new GpsMapThemeSubscriber($authChecker);
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

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);
    }

    /**
     * Test that Leaflet CSS CDN URL is included in stylesheet.
     *
     * Verifies that the injected CSS content includes the Leaflet
     * CSS CDN URL from unpkg.com.
     */
    public function testLeafletCssCdnUrlIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('unpkg.com/leaflet', $capturedContent);
        $this->assertStringContainsString('leaflet.css', $capturedContent);
    }

    /**
     * Test that Leaflet JS CDN URL is included in JavaScript.
     *
     * Verifies that the injected JS content includes the Leaflet
     * JS CDN URL from unpkg.com.
     */
    public function testLeafletJsCdnUrlIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('unpkg.com/leaflet', $capturedContent);
        $this->assertStringContainsString('leaflet.js', $capturedContent);
    }

    /**
     * Test that custom CSS styles are included.
     *
     * Verifies that the injected CSS content includes custom
     * styles for the GPS map container.
     */
    public function testCustomCssStylesIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('.gps-map-container', $capturedContent);
        $this->assertStringContainsString('.gps-map', $capturedContent);
        $this->assertStringContainsString('.gps-map-header', $capturedContent);
    }

    /**
     * Test that map initialization JavaScript is included.
     *
     * Verifies that the injected JS content includes the custom
     * map initialization functions.
     */
    public function testMapInitScriptIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('initGpsMaps', $capturedContent);
        $this->assertStringContainsString('findGpsFields', $capturedContent);
        $this->assertStringContainsString('parseCoordinates', $capturedContent);
    }

    /**
     * Test that GPS field selectors are included in JavaScript.
     *
     * Verifies that the JS code searches for GPS input fields
     * using the correct name patterns (contains selector for Kimai meta fields).
     */
    public function testGpsFieldSelectorsIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        // Reason: Uses *=" (contains) selector since Kimai meta field names end with [value]
        $this->assertStringContainsString('name*="[gps_start_location]"', $capturedContent);
        $this->assertStringContainsString('name*="[gps_stop_location]"', $capturedContent);
    }

    /**
     * Test that OpenStreetMap tile URL is included.
     *
     * Verifies that the JS code uses OpenStreetMap tiles
     * (free, no API key required).
     */
    public function testOpenStreetMapTileUrlIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('tile.openstreetmap.org', $capturedContent);
    }

    /**
     * Test that marker colors are correct (green for start, red for stop).
     *
     * Verifies that the JS code uses #28a745 (green) for start
     * and #dc3545 (red) for stop markers.
     */
    public function testMarkerColorsCorrect(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        // Green for start
        $this->assertStringContainsString('#28a745', $capturedContent);
        // Red for stop
        $this->assertStringContainsString('#dc3545', $capturedContent);
    }

    /**
     * Test that responsive CSS is included.
     *
     * Verifies that the CSS includes media queries for
     * responsive design on mobile devices.
     */
    public function testResponsiveCssIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('@media', $capturedContent);
        $this->assertStringContainsString('768px', $capturedContent);
    }

    /**
     * Test that Font Awesome marker icon class is included.
     *
     * Verifies that the JS code uses Font Awesome icons
     * for the map markers.
     */
    public function testFontAwesomeMarkerIconIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('fa-map-marker-alt', $capturedContent);
    }

    /**
     * Test that loading indicator CSS is included.
     *
     * Verifies that the CSS includes styles for the loading
     * spinner that displays while map tiles load.
     */
    public function testLoadingIndicatorCssIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('gps-map-loading', $capturedContent);
        $this->assertStringContainsString('gps-map-spinner', $capturedContent);
        $this->assertStringContainsString('gps-spin', $capturedContent);
    }

    /**
     * Test that loading indicator HTML is included in JavaScript.
     *
     * Verifies that the JS code creates a loading indicator
     * element in the map container.
     */
    public function testLoadingIndicatorHtmlIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderJavascript($event);

        $this->assertStringContainsString('gps-map-loading', $capturedContent);
        $this->assertStringContainsString('gps-map-spinner', $capturedContent);
        $this->assertStringContainsString('hideLoading', $capturedContent);
    }

    /**
     * Test that box shadow CSS is included.
     *
     * Verifies that the CSS includes box-shadow for
     * visual separation of the map container.
     */
    public function testBoxShadowCssIncluded(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $capturedContent = '';
        $event = $this->createMock(ThemeEvent::class);
        $event->method('addContent')
            ->willReturnCallback(function ($content) use (&$capturedContent, $event) {
                $capturedContent = $content;
                return $event;
            });

        $subscriber = new GpsMapThemeSubscriber($authChecker);
        $subscriber->renderStylesheet($event);

        $this->assertStringContainsString('box-shadow', $capturedContent);
    }
}
