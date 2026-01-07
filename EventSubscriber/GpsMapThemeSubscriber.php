<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Injects Leaflet.js map visualization for GPS meta fields.
 *
 * Uses ThemeEvent to inject CSS and JavaScript on all pages.
 * The JavaScript detects GPS input fields in the timesheet edit form
 * and renders an interactive map showing start (green) and stop (red)
 * location markers using Font Awesome icons.
 *
 * Features:
 * - Permission-gated (requires gps_view_data permission)
 * - Single map with two markers (start=green, stop=red)
 * - Auto-fits bounds to show both markers
 * - Graceful fallback when no GPS data or Leaflet fails
 * - Responsive design (300px desktop, 200px mobile)
 */
final class GpsMapThemeSubscriber implements EventSubscriberInterface
{
    /**
     * Leaflet.js CDN version.
     */
    private const LEAFLET_VERSION = '1.9.4';

    /**
     * Constructor with autowired dependencies.
     *
     * @param AuthorizationCheckerInterface $authChecker Authorization checker for permission checks
     */
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker
    ) {
    }

    /**
     * Returns the events this subscriber listens to.
     *
     * @return array<string, array{string, int}> Event to method mapping with priority
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::STYLESHEET => ['renderStylesheet', 100],
            ThemeEvent::JAVASCRIPT => ['renderJavascript', 100],
        ];
    }

    /**
     * Injects Leaflet CSS and custom map styles.
     *
     * Only injects if user has gps_view_data permission.
     *
     * @param ThemeEvent $event The theme event
     */
    public function renderStylesheet(ThemeEvent $event): void
    {
        // Reason: Check permission before injecting any GPS-related assets
        if (!$this->authChecker->isGranted(PermissionsSubscriber::PERMISSION_VIEW_DATA)) {
            return;
        }

        $css = $this->getLeafletCss() . $this->getCustomCss();
        $event->addContent($css);
    }

    /**
     * Injects Leaflet JavaScript and custom map initialization code.
     *
     * Only injects if user has gps_view_data permission.
     *
     * @param ThemeEvent $event The theme event
     */
    public function renderJavascript(ThemeEvent $event): void
    {
        // Reason: Check permission before injecting any GPS-related assets
        if (!$this->authChecker->isGranted(PermissionsSubscriber::PERMISSION_VIEW_DATA)) {
            return;
        }

        $js = $this->getLeafletJs() . $this->getMapInitScript();
        $event->addContent($js);
    }

    /**
     * Returns Leaflet CSS CDN link tag.
     *
     * Uses unpkg CDN with integrity hash for security.
     *
     * @return string HTML link tag for Leaflet CSS
     */
    private function getLeafletCss(): string
    {
        $version = self::LEAFLET_VERSION;

        return <<<HTML
<!-- Leaflet.js CSS for GPS Map -->
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@{$version}/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="">
HTML;
    }

    /**
     * Returns custom CSS styles for GPS map container.
     *
     * Includes responsive styles for mobile devices.
     *
     * @return string HTML style tag with custom CSS
     */
    private function getCustomCss(): string
    {
        return <<<HTML
<style type="text/css">
/* GPS Map Container Styles */
.gps-map-container {
    margin: 15px 0;
    border: 1px solid var(--tblr-border-color, #dee2e6);
    border-radius: var(--tblr-border-radius, 4px);
    overflow: hidden;
    background: var(--tblr-bg-surface, #fff);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.gps-map-header {
    background: var(--tblr-bg-surface-secondary, #f8f9fa);
    padding: 8px 12px;
    font-weight: 500;
    font-size: 0.875rem;
    border-bottom: 1px solid var(--tblr-border-color, #dee2e6);
    color: var(--tblr-body-color, #212529);
}

.gps-map-header i {
    margin-right: 6px;
    color: var(--tblr-primary, #206bc4);
}

.gps-map {
    height: 300px;
    width: 100%;
}

/* Responsive: Smaller map on mobile */
@media (max-width: 768px) {
    .gps-map {
        height: 200px;
    }
}

/* Empty State */
.gps-map-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--tblr-muted, #6c757d);
}

.gps-map-empty i {
    display: block;
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

/* Custom Marker Styles */
.gps-marker {
    background: transparent;
    border: none;
}

.gps-marker i {
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* Map Wrapper for loading overlay positioning */
.gps-map-wrapper {
    position: relative;
}

/* Loading Indicator - positioned over map */
.gps-map-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--tblr-bg-surface, #fff);
    z-index: 1000;
    transition: opacity 0.2s ease-out;
}

.gps-map-loading.hidden {
    opacity: 0;
    pointer-events: none;
}

.gps-map-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid var(--tblr-border-color, #dee2e6);
    border-top-color: var(--tblr-primary, #206bc4);
    border-radius: 50%;
    animation: gps-spin 0.8s linear infinite;
}

@keyframes gps-spin {
    to { transform: rotate(360deg); }
}
</style>
HTML;
    }

    /**
     * Returns Leaflet JavaScript CDN script tag.
     *
     * Uses unpkg CDN with integrity hash for security.
     *
     * @return string HTML script tag for Leaflet JS
     */
    private function getLeafletJs(): string
    {
        $version = self::LEAFLET_VERSION;

        return <<<HTML
<!-- Leaflet.js for GPS Map -->
<script src="https://unpkg.com/leaflet@{$version}/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
HTML;
    }

    /**
     * Returns the custom JavaScript for GPS map initialization.
     *
     * The script detects GPS input fields in the timesheet edit form,
     * parses coordinates, and renders a Leaflet map with markers.
     *
     * @return string HTML script tag with map initialization code
     */
    private function getMapInitScript(): string
    {
        return <<<'HTML'
<script type="text/javascript">
(function() {
    'use strict';

    // Reason: Track if map is already initialized to prevent duplicates
    var mapInitialized = false;

    /**
     * Initialize GPS maps when DOM is ready AND watch for modals.
     * Kimai loads edit forms in Bootstrap modals via AJAX, so we need
     * to detect when GPS fields appear dynamically.
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Try immediately (for non-modal pages)
        tryInitGpsMaps();

        // Reason: Watch for Kimai's modal forms loaded via AJAX
        // Use MutationObserver to detect when GPS fields are added to DOM
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.addedNodes.length > 0) {
                    // Reason: Check if any added node contains GPS fields
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var node = mutation.addedNodes[j];
                        if (node.nodeType === 1) { // Element node
                            if (node.querySelector &&
                                (node.querySelector('input[name*="[gps_start_location]"]') ||
                                 node.querySelector('input[name*="[gps_stop_location]"]'))) {
                                // Reason: Reset flag when modal closes and reopens
                                mapInitialized = false;
                                setTimeout(tryInitGpsMaps, 100);
                                break;
                            }
                        }
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Reason: Also listen for Bootstrap modal events (Kimai uses Bootstrap)
        document.addEventListener('shown.bs.modal', function(e) {
            mapInitialized = false;
            setTimeout(tryInitGpsMaps, 100);
        });
    });

    /**
     * Try to initialize GPS maps if fields are present.
     * Guards against multiple initializations.
     */
    function tryInitGpsMaps() {
        if (mapInitialized) {
            return;
        }

        var fields = findGpsFields();
        if (!fields.startField && !fields.stopField) {
            return;
        }

        // Reason: Check if map container already exists (prevent duplicates)
        var existingContainer = document.querySelector('.gps-map-container');
        if (existingContainer) {
            return;
        }

        mapInitialized = true;
        initGpsMaps();
    }

    /**
     * Main entry point for GPS map initialization.
     * Detects GPS fields and renders map if data is available.
     * Uses retry mechanism to wait for Kimai to populate form values via AJAX.
     */
    function initGpsMaps() {
        var fields = findGpsFields();

        // Reason: Only proceed if we found at least one GPS field
        if (!fields.startField && !fields.stopField) {
            return;
        }

        // Reason: Create map container first so user sees loading spinner
        var lastField = fields.stopField || fields.startField;
        var container = createMapContainer(lastField);

        // Reason: Try to render map with retry mechanism for AJAX-populated values
        tryRenderMap(container, fields, 0);
    }

    /**
     * Attempt to render map, retrying if values not yet populated.
     * Kimai populates form field values via AJAX after modal loads,
     * so we need to wait and retry until values are available.
     *
     * @param {HTMLElement} container - The map container element
     * @param {Object} fields - Object with startField and stopField elements
     * @param {number} attempt - Current retry attempt (0-4, max 5 attempts)
     */
    function tryRenderMap(container, fields, attempt) {
        var startCoords = parseCoordinates(fields.startField ? fields.startField.value : '');
        var stopCoords = parseCoordinates(fields.stopField ? fields.stopField.value : '');

        // Reason: If we have valid coordinates, render the map immediately
        if (startCoords || stopCoords) {
            renderMap(container, startCoords, stopCoords);
            return;
        }

        // Reason: Retry with exponential backoff (100, 200, 400, 800, 1600ms)
        // Total wait time: ~3.1 seconds before giving up
        if (attempt < 5) {
            var delay = 100 * Math.pow(2, attempt);
            setTimeout(function() {
                tryRenderMap(container, fields, attempt + 1);
            }, delay);
            return;
        }

        // Reason: After 5 attempts, show empty state (truly no GPS data)
        showEmptyState(container);
    }

    /**
     * Find GPS input fields in the form.
     * Kimai renders meta fields with names like: timesheet[metaFields][gps_start_location][value]
     * The field name contains the meta field name but ends with [value].
     *
     * @returns {Object} Object with startField and stopField elements (or null)
     */
    function findGpsFields() {
        return {
            startField: document.querySelector('input[name*="[gps_start_location]"]'),
            stopField: document.querySelector('input[name*="[gps_stop_location]"]')
        };
    }

    /**
     * Parse GPS coordinates from "lat,lng" string format.
     *
     * @param {string} value - Coordinate string (e.g., "52.5162748,13.3774573")
     * @returns {Object|null} Object with lat/lng properties or null if invalid
     */
    function parseCoordinates(value) {
        if (!value || !value.trim()) {
            return null;
        }

        var parts = value.split(',');
        if (parts.length !== 2) {
            return null;
        }

        var lat = parseFloat(parts[0].trim());
        var lng = parseFloat(parts[1].trim());

        if (isNaN(lat) || isNaN(lng)) {
            return null;
        }

        return { lat: lat, lng: lng };
    }

    /**
     * Create the map container div and insert it after the GPS fields.
     *
     * @param {HTMLElement} afterElement - Element to insert after
     * @returns {HTMLElement} The created container element
     */
    function createMapContainer(afterElement) {
        var container = document.createElement('div');
        container.className = 'gps-map-container';
        container.innerHTML =
            '<div class="gps-map-header">' +
                '<i class="fas fa-map-marker-alt"></i> GPS Location Map' +
            '</div>' +
            '<div class="gps-map-wrapper">' +
                '<div class="gps-map-loading">' +
                    '<div class="gps-map-spinner"></div>' +
                '</div>' +
                '<div id="gps-map-display" class="gps-map"></div>' +
            '</div>' +
            '<div class="gps-map-empty" style="display:none;">' +
                '<i class="fas fa-map-marked-alt"></i>' +
                '<span>No GPS data available</span>' +
            '</div>';

        // Reason: Find the form group (mb-3) container to insert after
        var formGroup = afterElement.closest('.mb-3');
        if (formGroup && formGroup.parentNode) {
            formGroup.parentNode.insertBefore(container, formGroup.nextSibling);
        } else {
            // Fallback: insert after the element's parent
            afterElement.parentNode.insertBefore(container, afterElement.parentNode.nextSibling);
        }

        return container;
    }

    /**
     * Show the empty state message when no GPS data is available.
     *
     * @param {HTMLElement} container - The map container element
     */
    function showEmptyState(container) {
        var wrapperDiv = container.querySelector('.gps-map-wrapper');
        var emptyDiv = container.querySelector('.gps-map-empty');

        if (wrapperDiv) {
            wrapperDiv.style.display = 'none';
        }
        if (emptyDiv) {
            emptyDiv.style.display = 'block';
        }
    }

    /**
     * Hide loading indicator to reveal the map underneath.
     *
     * @param {HTMLElement} container - The map container element
     */
    function hideLoading(container) {
        var loadingDiv = container.querySelector('.gps-map-loading');
        if (loadingDiv) {
            loadingDiv.classList.add('hidden');
        }
    }

    /**
     * Render the Leaflet map with GPS markers.
     *
     * @param {HTMLElement} container - The map container element
     * @param {Object|null} startCoords - Start location coordinates
     * @param {Object|null} stopCoords - Stop location coordinates
     */
    function renderMap(container, startCoords, stopCoords) {
        var mapDiv = container.querySelector('#gps-map-display');
        if (!mapDiv) {
            return;
        }

        // Reason: Check if Leaflet is available
        if (typeof L === 'undefined') {
            console.error('GPS Map: Leaflet.js not loaded');
            showEmptyState(container);
            return;
        }

        try {
            // Reason: Initialize map with a default center (will be adjusted by fitBounds)
            var defaultCenter = startCoords || stopCoords;
            var map = L.map(mapDiv).setView([defaultCenter.lat, defaultCenter.lng], 15);

            // Reason: Add OpenStreetMap tile layer (free, no API key required)
            var tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(map);

            // Reason: Hide loading indicator when first tile loads
            tileLayer.once('load', function() {
                hideLoading(container);
            });

            // Reason: Fallback timeout if tiles fail to load (3 seconds)
            setTimeout(function() {
                hideLoading(container);
            }, 3000);

            var markers = [];

            // Reason: Add start marker (green) if coordinates exist
            if (startCoords) {
                var startMarker = L.marker([startCoords.lat, startCoords.lng], {
                    icon: createMarkerIcon('#28a745')
                }).addTo(map);
                startMarker.bindPopup('<strong>Start Location</strong>');
                markers.push(startMarker);
            }

            // Reason: Add stop marker (red) if coordinates exist
            if (stopCoords) {
                var stopMarker = L.marker([stopCoords.lat, stopCoords.lng], {
                    icon: createMarkerIcon('#dc3545')
                }).addTo(map);
                stopMarker.bindPopup('<strong>Stop Location</strong>');
                markers.push(stopMarker);
            }

            // Reason: Fit map bounds to show all markers with padding
            fitMapBounds(map, markers);

        } catch (e) {
            console.error('GPS Map: Error initializing map', e);
            showEmptyState(container);
        }
    }

    /**
     * Create a custom marker icon using Font Awesome.
     *
     * @param {string} color - CSS color value (e.g., '#28a745')
     * @returns {L.DivIcon} Leaflet divIcon with Font Awesome marker
     */
    function createMarkerIcon(color) {
        return L.divIcon({
            className: 'gps-marker',
            html: '<i class="fas fa-map-marker-alt" style="color:' + color + ';font-size:28px;"></i>',
            iconSize: [28, 28],
            iconAnchor: [14, 28],
            popupAnchor: [0, -28]
        });
    }

    /**
     * Fit the map bounds to show all markers.
     *
     * @param {L.Map} map - The Leaflet map instance
     * @param {Array} markers - Array of Leaflet markers
     */
    function fitMapBounds(map, markers) {
        if (markers.length === 0) {
            return;
        }

        if (markers.length === 1) {
            // Reason: Single marker - center on it with appropriate zoom
            map.setView(markers[0].getLatLng(), 15);
        } else {
            // Reason: Multiple markers - fit bounds with padding
            var group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
})();
</script>
HTML;
    }
}
