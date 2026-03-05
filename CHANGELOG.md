# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-03-04

### Added

- Geofence boundary configuration (enable, center lat/lng, radius, notify delay, restrict mobile tracking)
- Interactive map preview on admin settings page for geofence visualization
- Conditional field visibility (geofence fields show/hide based on toggle)
- Geofence configuration returned via `/api/gps/config` API endpoint
- OpenAPI documentation for the GPS config endpoint
- GeofenceAdminThemeSubscriber for admin UI map and form logic

## [1.0.0] - 2026-01-07

### Added

- GPS metadata storage for timesheet start and stop locations
- Global GPS tracking toggle (Admin > Settings, disabled by default)
- Per-user GPS tracking preference (Admin > Users > Preferences)
- Interactive map display with Leaflet.js (green=start, red=stop markers)
- REST API endpoint `/api/gps/config` for mobile client configuration
- Three role-based permissions: `gps_view_data`, `gps_edit_data`, `gps_edit_user_preference`
- Privacy by design: GPS tracking off by default, per-user control, auto-deletion with timesheet
