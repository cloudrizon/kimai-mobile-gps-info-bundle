# Kimai Mobile GPS Info Plugin

A Symfony bundle for Kimai 2.x that enables GPS location tracking for timesheet entries from mobile clients.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Permissions](#permissions)
- [Map Visualization](#map-visualization)
- [Privacy & GDPR](#privacy--gdpr)
- [License](#license)
- [Support](#support)

## Features

- GPS metadata storage for timesheet start and stop locations
- Mobile client integration via API
- Global and per-user GPS tracking controls
- Interactive map display with Leaflet.js
- Role-based visibility and edit permissions
- Multi-language support (English, German)

## Requirements

- Kimai 2.0.0 or higher
- PHP 8.1 or higher
- Symfony 6.x or higher

## Installation

1. Navigate to your Kimai installation's plugin directory:
   ```bash
   cd /path/to/kimai/var/plugins/
   ```

2. Clone or extract this plugin:
   ```bash
   git clone https://github.com/cloudrizon/kimai-mobile-gps-info-bundle.git KimaiMobileGPSInfoBundle
   ```

3. Clear Kimai's cache:
   ```bash
   cd /path/to/kimai
   bin/console cache:clear
   bin/console kimai:reload --env=prod
   ```

4. Verify the plugin is loaded:
   ```bash
   bin/console kimai:plugins
   ```

> **Note**: If you encounter cache issues, see [Kimai Cache Documentation](https://www.kimai.org/documentation/cache.html) for troubleshooting (e.g., file permissions).

## Quick Start

### Server Setup

1. Install the plugin (see [Installation](#installation))
2. Clear cache: `bin/console cache:clear`
3. Enable GPS tracking in **Admin > Settings > GPS Tracking**
4. GPS fields will now appear on timesheet forms

### Mobile App Requirements

For GPS tracking to work on mobile devices:

- **GPS/Location Services**: Must be enabled on the device
- **App Permissions**: Location permission must be granted to the Kimai mobile app
- **Compatible App**: Kimai Mobile version 1.3.19 or newer

> **Note**: If GPS is disabled or permission is denied on the device, the app will not capture or send any location data.

## Configuration

GPS tracking requires both global and user-level settings to be enabled.

### Global GPS Tracking

**Location**: Admin > Settings > GPS Tracking

Enable or disable GPS tracking system-wide. This setting is **disabled by default** (privacy by design).

### User-Level GPS Tracking

**Location**: Admin > Users > [Select User] > Preferences

Administrators can enable or disable GPS tracking for individual users. This setting defaults to **enabled** for new users.

### Configuration Logic

| Global Setting | User Setting | Effective Status |
|---------------|--------------|------------------|
| OFF           | OFF          | **Disabled**     |
| OFF           | ON           | **Disabled**     |
| ON            | OFF          | **Disabled**     |
| ON            | ON           | **Enabled**      |

## Permissions

The plugin provides three configurable permissions in Kimai's role management.

| Permission | Description |
|------------|-------------|
| `gps_view_data` | View GPS location data in timesheet forms |
| `gps_edit_data` | Edit GPS coordinates in the web UI |
| `gps_edit_user_preference` | Edit user GPS tracking preference |

### Default Role Assignments

| Permission | ROLE_USER | ROLE_TEAMLEAD | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|------------|-----------|---------------|------------|------------------|
| `gps_view_data` | Yes | Yes | Yes | Yes |
| `gps_edit_data` | No | No | Yes | Yes |
| `gps_edit_user_preference` | No | No | Yes | Yes |

Permissions can be modified in **Admin > Roles > [Select Role] > GPS Tracking**.

## Map Visualization

When viewing or editing a timesheet with GPS data, an interactive map displays the locations:

- **Green marker**: Start location
- **Red marker**: Stop location
- Auto-fit bounds to show all markers
- Uses OpenStreetMap tiles (no API key required)

Requirements: User must have `gps_view_data` permission and at least one GPS coordinate must be present.

## Privacy & GDPR

GPS location data is personal data under GDPR. Organizations must:

- Establish legal basis for processing
- Notify employees about GPS tracking
- Honor data subject rights (access, deletion, objection)

### Plugin Privacy Features

- **Default Off**: Global GPS tracking is disabled by default
- **Per-User Control**: Administrators can disable tracking for specific users
- **Automatic Deletion**: GPS data is deleted when the timesheet is deleted

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues, questions, or feature requests, please submit a support ticket:

[Open Support Ticket](https://kimaimobile.freshdesk.com/support/tickets/new)
