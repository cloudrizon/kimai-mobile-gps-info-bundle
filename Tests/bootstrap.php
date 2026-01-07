<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Dotenv\Dotenv;

// Plugin is installed in var/plugins/ directory of Kimai
// Path: var/plugins/KimaiMobileGPSInfoBundle/Tests/bootstrap.php
// Kimai root: ../../../../ (4 levels up from Tests/)
// Tests/ -> KimaiMobileGPSInfoBundle/ -> plugins/ -> var/ -> /opt/kimai/

$kimaiRoot = dirname(__DIR__, 4);
$autoloadPath = $kimaiRoot . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new RuntimeException(
        'Kimai autoloader not found. ' .
        'This plugin must be installed in the var/plugins/ directory of a Kimai installation. ' .
        'Expected path: ' . $autoloadPath
    );
}

require $autoloadPath;

// Load Kimai's bootstrap if available
$bootstrapPath = $kimaiRoot . '/config/bootstrap.php';
if (file_exists($bootstrapPath)) {
    require $bootstrapPath;
} elseif (class_exists(Dotenv::class) && method_exists(Dotenv::class, 'bootEnv')) {
    $envPath = $kimaiRoot . '/.env';
    if (file_exists($envPath)) {
        (new Dotenv())->bootEnv($envPath);
    }
}
