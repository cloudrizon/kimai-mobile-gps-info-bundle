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
 * Injects CSS and JavaScript for geofence admin settings page.
 *
 * Detects the "Enable Geofence" toggle on the System -> Settings page
 * and controls visibility of dependent geofence configuration fields
 * (lat, lng, radius, notify_after, restrict_mobile_tracking).
 *
 * When the toggle is OFF, all dependent fields and map preview are hidden.
 * When the toggle is ON, all dependent fields are shown immediately and
 * a Leaflet.js map preview displays the configured geofence area.
 *
 * Features:
 * - Permission-gated (requires gps_view_data permission)
 * - Conditional field visibility based on toggle state
 * - Interactive map preview with center marker and radius circle
 * - Map updates dynamically on field blur events
 *
 * This subscriber is separate from GpsMapThemeSubscriber because:
 * - Different concern (admin form UX vs. timesheet map rendering)
 * - GpsMapThemeSubscriber already exceeds 500-line convention
 */
final class GeofenceAdminThemeSubscriber implements EventSubscriberInterface
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
     * Subscribes to both STYLESHEET and JAVASCRIPT for:
     * - Leaflet CSS + custom map styles
     * - Leaflet JS + field visibility + map initialization
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

        $event->addContent($this->getLeafletCss() . $this->getGeofenceMapCss());
    }

    /**
     * Injects Leaflet JS and geofence visibility/map JavaScript.
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

        $event->addContent($this->getLeafletJs() . $this->getGeofenceVisibilityScript());
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
<!-- Leaflet.js CSS for Geofence Map Preview -->
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@{$version}/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="">
HTML;
    }

    /**
     * Returns custom CSS styles for geofence map preview.
     *
     * @return string HTML style tag with custom CSS
     */
    private function getGeofenceMapCss(): string
    {
        return <<<'HTML'
<style type="text/css">
/* Geofence Map Preview Container Styles */
.geofence-map-container {
    margin: 15px 0;
    border: 1px solid var(--tblr-border-color, #dee2e6);
    border-radius: var(--tblr-border-radius, 4px);
    overflow: hidden;
    background: var(--tblr-bg-surface, #fff);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.geofence-map-header {
    background: var(--tblr-bg-surface-secondary, #f8f9fa);
    padding: 8px 12px;
    font-weight: 500;
    font-size: 0.875rem;
    border-bottom: 1px solid var(--tblr-border-color, #dee2e6);
    color: var(--tblr-body-color, #212529);
}

.geofence-map-header i {
    margin-right: 6px;
    color: var(--tblr-primary, #206bc4);
}

.geofence-map {
    height: 250px;
    width: 100%;
}

/* Responsive: Smaller map on mobile */
@media (max-width: 768px) {
    .geofence-map {
        height: 200px;
    }
}

/* Empty State */
.geofence-map-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--tblr-muted, #6c757d);
}

.geofence-map-empty i {
    display: block;
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

/* Map Wrapper for loading overlay positioning */
.geofence-map-wrapper {
    position: relative;
}

/* Loading Indicator - positioned over map */
.geofence-map-loading {
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

.geofence-map-loading.hidden {
    opacity: 0;
    pointer-events: none;
}

.geofence-map-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid var(--tblr-border-color, #dee2e6);
    border-top-color: var(--tblr-primary, #206bc4);
    border-radius: 50%;
    animation: geofence-spin 0.8s linear infinite;
}

@keyframes geofence-spin {
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
<!-- Leaflet.js for Geofence Map Preview -->
<script src="https://unpkg.com/leaflet@{$version}/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
HTML;
    }

    /**
     * Returns JavaScript for geofence field visibility and map preview.
     *
     * The script self-gates by checking for a hidden input with
     * value="gps.geofence_enabled", which only exists on the admin
     * settings page. On all other pages, the script exits immediately.
     *
     * Kimai's SystemConfigurationType renders each config item with:
     * - A hidden field: <input type="hidden" name="...[N][name]" value="gps.geofence_enabled">
     * - A value field: <input type="checkbox" name="...[N][value]"> (for YesNoType)
     *
     * YesNoType extends CheckboxType, so toggle state is read via .checked property.
     *
     * @return string HTML script tag with geofence visibility and map logic
     */
    private function getGeofenceVisibilityScript(): string
    {
        return <<<'HTML'
<script type="text/javascript">
(function() {
    'use strict';

    // Reason: Debug logging flag - set to true to enable console output for development
    var GPS_GEOFENCE_DEBUG = false;

    /**
     * Conditional debug logging helper.
     * Only logs when GPS_GEOFENCE_DEBUG is true.
     */
    function debugLog() {
        if (GPS_GEOFENCE_DEBUG && typeof console !== 'undefined') {
            console.log.apply(console, arguments);
        }
    }

    /**
     * Conditional error logging helper.
     * Only logs when GPS_GEOFENCE_DEBUG is true.
     */
    function debugError() {
        if (GPS_GEOFENCE_DEBUG && typeof console !== 'undefined') {
            console.error.apply(console, arguments);
        }
    }

    // Reason: Track map instance to prevent duplicates and enable cleanup
    var geofenceMap = null;
    var geofenceMarker = null;
    var geofenceCircle = null;

    document.addEventListener('DOMContentLoaded', function() {
        initGeofenceFieldVisibility();
    });

    /**
     * Find a Kimai system configuration value field by its config key name.
     *
     * Kimai's SystemConfigurationType renders each config item with a hidden
     * field storing the config key (e.g., value="gps.geofence_enabled") and
     * a sibling value input (checkbox, number, etc.) in the same container.
     *
     * @param {string} configName - The configuration key (e.g., "gps.geofence_enabled")
     * @returns {HTMLElement|null} The value input element or null if not found
     */
    function findConfigField(configName) {
        // Reason: Each config item has a hidden input whose value is the config key
        var hiddenField = document.querySelector(
            'input[type="hidden"][value="' + configName + '"]'
        );
        if (!hiddenField) {
            return null;
        }

        // Reason: The hidden [name] field and the [value] input share a parent container.
        // Find the value input by replacing [name] with [value] in the hidden field's name.
        var hiddenName = hiddenField.getAttribute('name');
        if (hiddenName) {
            var valueName = hiddenName.replace('[name]', '[value]');
            var valueField = document.querySelector(
                'input[name="' + valueName + '"], select[name="' + valueName + '"]'
            );
            if (valueField) {
                return valueField;
            }
        }

        // Reason: Fallback - search within the compound form widget wrapper
        // (hidden field is a direct child of the compound wrapper div, not inside .mb-3)
        var container = hiddenField.parentElement;
        if (container) {
            return container.querySelector(
                'input:not([type="hidden"]), select'
            );
        }

        return null;
    }

    /**
     * Find the parent form group container for a config field.
     *
     * Symfony's Bootstrap 5 theme renders hidden fields WITHOUT a wrapper div
     * (hidden_row block), so the hidden field is a direct child of the compound
     * form widget div. Using parentElement targets just this one config item.
     *
     * @param {string} configName - The configuration key
     * @returns {HTMLElement|null} The compound wrapper div or null
     */
    function findConfigContainer(configName) {
        var hiddenField = document.querySelector(
            'input[type="hidden"][value="' + configName + '"]'
        );
        if (!hiddenField) {
            return null;
        }
        // Reason: parentElement returns the compound form widget div that wraps
        // just this config item (hidden [name] field + visible [value] field row).
        // Do NOT use .closest('.mb-3') - it traverses past the wrapper to a
        // shared ancestor, hiding the entire GPS Tracking section.
        return hiddenField.parentElement;
    }

    /**
     * Initialize geofence field visibility toggle on admin settings page.
     * Detects the geofence_enabled checkbox and controls visibility
     * of dependent geofence configuration fields and map preview.
     */
    function initGeofenceFieldVisibility() {
        // Reason: Only proceed if geofence toggle exists (admin settings page only)
        var toggle = findConfigField('gps.geofence_enabled');
        if (!toggle) {
            debugLog('[GPS Geofence] Toggle not found - not on admin settings page');
            return;
        }

        debugLog('[GPS Geofence] Toggle found, checked:', toggle.checked);

        // Set initial visibility based on current toggle state
        updateGeofenceFieldVisibility(toggle);

        // Reason: Listen for changes to immediately show/hide fields
        toggle.addEventListener('change', function() {
            debugLog('[GPS Geofence] Toggle changed to:', toggle.checked);
            updateGeofenceFieldVisibility(toggle);
        });

        // Reason: HTML form reset (via <input type="reset">) does NOT fire
        // 'change' events on individual inputs. Listen for the form's 'reset'
        // event and re-sync visibility after the browser finishes resetting
        // DOM values. Uses setTimeout(fn, 10) matching Kimai's own pattern
        // in KimaiFormSelect.js for handling form resets.
        var form = toggle.closest('form');
        if (form) {
            form.addEventListener('reset', function() {
                setTimeout(function() {
                    debugLog('[GPS Geofence] Form reset, toggle now:', toggle.checked);
                    updateGeofenceFieldVisibility(toggle);
                }, 10);
            });
        }

        // Reason: Set up blur listeners for coordinate/radius fields to update map
        setupFieldBlurListeners();
    }

    /**
     * Show or hide geofence dependent fields and map based on toggle state.
     * YesNoType extends CheckboxType, so state is read via .checked property.
     *
     * @param {HTMLInputElement} toggle - The geofence_enabled checkbox element
     */
    function updateGeofenceFieldVisibility(toggle) {
        // Reason: YesNoType extends CheckboxType - use .checked, not .value
        var isEnabled = toggle.checked;

        // Reason: These 5 config keys match the fields registered in
        // SystemConfigurationSubscriber that depend on geofence being enabled
        var configNames = [
            'gps.geofence_center_lat',
            'gps.geofence_center_lng',
            'gps.geofence_radius',
            'gps.geofence_notify_after',
            'gps.geofence_restrict_mobile_tracking'
        ];

        var foundCount = 0;
        configNames.forEach(function(name) {
            var container = findConfigContainer(name);
            if (container) {
                // Reason: Target the compound wrapper div (parentElement of hidden field)
                // to hide the entire config item (hidden field + label + input + help text)
                container.style.display = isEnabled ? '' : 'none';
                foundCount++;
            }
        });

        debugLog('[GPS Geofence] Fields toggled:', foundCount + '/' + configNames.length,
            '| Visible:', isEnabled);

        // Reason: Handle map container visibility and initialization
        handleMapVisibility(isEnabled);
    }

    /**
     * Handle map container visibility based on toggle state.
     * Creates map container if needed, shows/hides and initializes/destroys map.
     *
     * @param {boolean} isEnabled - Whether geofence is enabled
     */
    function handleMapVisibility(isEnabled) {
        var mapContainer = document.querySelector('.geofence-map-container');

        if (isEnabled) {
            // Reason: Create map container if it doesn't exist yet
            if (!mapContainer) {
                mapContainer = createGeofenceMapContainer();
            }

            if (mapContainer) {
                mapContainer.style.display = '';
                // Reason: Initialize map if not already done
                if (!geofenceMap) {
                    // Reason: Small delay to ensure container is visible before initializing
                    setTimeout(function() {
                        initGeofenceMap(mapContainer);
                    }, 50);
                }
            }
        } else if (mapContainer) {
            mapContainer.style.display = 'none';
            // Reason: Destroy map instance to free resources when hidden
            destroyGeofenceMap();
        }
    }

    /**
     * Create the map container and insert it after the radius field.
     *
     * @returns {HTMLElement|null} The created container element or null
     */
    function createGeofenceMapContainer() {
        // Reason: Insert map container after the radius field
        var radiusContainer = findConfigContainer('gps.geofence_radius');
        if (!radiusContainer || !radiusContainer.parentNode) {
            debugLog('[GPS Geofence] Cannot create map - radius container not found');
            return null;
        }

        var container = document.createElement('div');
        container.className = 'geofence-map-container';
        container.innerHTML =
            '<div class="geofence-map-header">' +
                '<i class="fas fa-bullseye"></i> Geofence Preview' +
            '</div>' +
            '<div class="geofence-map-wrapper">' +
                '<div class="geofence-map-loading">' +
                    '<div class="geofence-map-spinner"></div>' +
                '</div>' +
                '<div id="geofence-map-display" class="geofence-map"></div>' +
            '</div>' +
            '<div class="geofence-map-empty" style="display:none;">' +
                '<i class="fas fa-map-marked-alt"></i>' +
                '<span>Enter coordinates and radius to preview geofence area</span>' +
            '</div>';

        // Reason: Insert after radius field container
        radiusContainer.parentNode.insertBefore(container, radiusContainer.nextSibling);

        debugLog('[GPS Geofence] Map container created');
        return container;
    }

    /**
     * Initialize the Leaflet map with center marker and radius circle.
     *
     * @param {HTMLElement} container - The map container element
     */
    function initGeofenceMap(container) {
        var mapDiv = container.querySelector('#geofence-map-display');
        if (!mapDiv) {
            debugError('[GPS Geofence] Map div not found');
            return;
        }

        // Reason: Check if Leaflet is available
        if (typeof L === 'undefined') {
            debugError('[GPS Geofence] Leaflet.js not loaded');
            showEmptyState(container);
            return;
        }

        // Reason: Get current field values
        var coords = getGeofenceCoordinates();

        if (!coords.valid) {
            debugLog('[GPS Geofence] Invalid or empty coordinates - showing empty state');
            showEmptyState(container);
            return;
        }

        try {
            debugLog('[GPS Geofence] Initializing map at', coords.lat, coords.lng, 'radius:', coords.radius);

            // Reason: Initialize map centered on geofence location
            geofenceMap = L.map(mapDiv).setView([coords.lat, coords.lng], calculateZoom(coords.radius));

            // Reason: Add OpenStreetMap tile layer (free, no API key required)
            var tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(geofenceMap);

            // Reason: Hide loading indicator when first tile loads
            tileLayer.once('load', function() {
                hideLoading(container);
            });

            // Reason: Fallback timeout if tiles fail to load (3 seconds)
            setTimeout(function() {
                hideLoading(container);
            }, 3000);

            // Reason: Add center marker
            geofenceMarker = L.marker([coords.lat, coords.lng]).addTo(geofenceMap);
            geofenceMarker.bindPopup('<strong>Geofence Center</strong><br>Lat: ' + coords.lat + '<br>Lng: ' + coords.lng);

            // Reason: Add radius circle with Kimai primary color
            geofenceCircle = L.circle([coords.lat, coords.lng], {
                radius: coords.radius,
                color: '#206bc4',
                fillColor: '#206bc4',
                fillOpacity: 0.15,
                weight: 2
            }).addTo(geofenceMap);
            geofenceCircle.bindPopup('<strong>Geofence Area</strong><br>Radius: ' + coords.radius + ' meters');

            // Reason: Fit map bounds to show the entire circle
            geofenceMap.fitBounds(geofenceCircle.getBounds().pad(0.1));

            debugLog('[GPS Geofence] Map initialized successfully');

        } catch (e) {
            debugError('[GPS Geofence] Error initializing map:', e);
            showEmptyState(container);
        }
    }

    /**
     * Update the existing map with new coordinates/radius.
     * Called on field blur events.
     */
    function updateGeofenceMap() {
        var container = document.querySelector('.geofence-map-container');
        if (!container) {
            return;
        }

        var coords = getGeofenceCoordinates();

        if (!coords.valid) {
            // Reason: Show empty state if coordinates become invalid
            if (geofenceMap) {
                destroyGeofenceMap();
            }
            showEmptyState(container);
            return;
        }

        // Reason: If map doesn't exist yet, initialize it
        if (!geofenceMap) {
            hideEmptyState(container);
            initGeofenceMap(container);
            return;
        }

        debugLog('[GPS Geofence] Updating map to', coords.lat, coords.lng, 'radius:', coords.radius);

        // Reason: Update marker position
        if (geofenceMarker) {
            geofenceMarker.setLatLng([coords.lat, coords.lng]);
            geofenceMarker.setPopupContent('<strong>Geofence Center</strong><br>Lat: ' + coords.lat + '<br>Lng: ' + coords.lng);
        }

        // Reason: Update circle position and radius
        if (geofenceCircle) {
            geofenceCircle.setLatLng([coords.lat, coords.lng]);
            geofenceCircle.setRadius(coords.radius);
            geofenceCircle.setPopupContent('<strong>Geofence Area</strong><br>Radius: ' + coords.radius + ' meters');
        }

        // Reason: Re-fit map bounds to show the updated circle
        geofenceMap.fitBounds(geofenceCircle.getBounds().pad(0.1));
    }

    /**
     * Destroy the map instance to free resources.
     */
    function destroyGeofenceMap() {
        if (geofenceMap) {
            geofenceMap.remove();
            geofenceMap = null;
            geofenceMarker = null;
            geofenceCircle = null;
            debugLog('[GPS Geofence] Map destroyed');
        }
    }

    /**
     * Get current geofence coordinates from form fields.
     *
     * @returns {Object} Object with lat, lng, radius, and valid flag
     */
    function getGeofenceCoordinates() {
        var latField = findConfigField('gps.geofence_center_lat');
        var lngField = findConfigField('gps.geofence_center_lng');
        var radiusField = findConfigField('gps.geofence_radius');

        var lat = latField ? parseFloat(latField.value) : NaN;
        var lng = lngField ? parseFloat(lngField.value) : NaN;
        var radius = radiusField ? parseInt(radiusField.value, 10) : NaN;

        // Reason: Validate coordinate ranges and radius
        var valid = !isNaN(lat) && !isNaN(lng) && !isNaN(radius) &&
                    lat >= -90 && lat <= 90 &&
                    lng >= -180 && lng <= 180 &&
                    radius >= 10 && radius <= 1000;

        return {
            lat: lat,
            lng: lng,
            radius: radius,
            valid: valid
        };
    }

    /**
     * Calculate appropriate zoom level based on radius.
     *
     * @param {number} radiusMeters - Radius in meters
     * @returns {number} Zoom level (14-17)
     */
    function calculateZoom(radiusMeters) {
        // Reason: Zoom level that fits the circle with padding
        if (radiusMeters <= 100) return 17;
        if (radiusMeters <= 250) return 16;
        if (radiusMeters <= 500) return 15;
        return 14;
    }

    /**
     * Set up blur event listeners on coordinate and radius fields.
     */
    function setupFieldBlurListeners() {
        var fields = [
            findConfigField('gps.geofence_center_lat'),
            findConfigField('gps.geofence_center_lng'),
            findConfigField('gps.geofence_radius')
        ];

        fields.forEach(function(field) {
            if (field) {
                field.addEventListener('blur', function() {
                    debugLog('[GPS Geofence] Field blur, updating map');
                    updateGeofenceMap();
                });
            }
        });

        debugLog('[GPS Geofence] Blur listeners attached to', fields.filter(Boolean).length, 'fields');
    }

    /**
     * Show the empty state message when coordinates are invalid.
     *
     * @param {HTMLElement} container - The map container element
     */
    function showEmptyState(container) {
        var wrapperDiv = container.querySelector('.geofence-map-wrapper');
        var emptyDiv = container.querySelector('.geofence-map-empty');

        if (wrapperDiv) {
            wrapperDiv.style.display = 'none';
        }
        if (emptyDiv) {
            emptyDiv.style.display = 'block';
        }
    }

    /**
     * Hide the empty state message.
     *
     * @param {HTMLElement} container - The map container element
     */
    function hideEmptyState(container) {
        var wrapperDiv = container.querySelector('.geofence-map-wrapper');
        var emptyDiv = container.querySelector('.geofence-map-empty');

        if (wrapperDiv) {
            wrapperDiv.style.display = '';
        }
        if (emptyDiv) {
            emptyDiv.style.display = 'none';
        }
    }

    /**
     * Hide loading indicator to reveal the map underneath.
     *
     * @param {HTMLElement} container - The map container element
     */
    function hideLoading(container) {
        var loadingDiv = container.querySelector('.geofence-map-loading');
        if (loadingDiv) {
            loadingDiv.classList.add('hidden');
        }
    }
})();
</script>
HTML;
    }
}
