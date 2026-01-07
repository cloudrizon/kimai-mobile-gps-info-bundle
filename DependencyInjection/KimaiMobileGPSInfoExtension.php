<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\DependencyInjection;

use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\PermissionsSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension class for loading service configuration and registering permissions.
 *
 * This class is REQUIRED for Symfony to load the services.yaml file.
 * It also implements PrependExtensionInterface to register GPS permissions
 * at compile time, which is required for permissions to be assignable to roles.
 */
final class KimaiMobileGPSInfoExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Load service configuration from YAML files.
     *
     * @param array<mixed> $configs Configuration values
     * @param ContainerBuilder $container Service container
     * @throws \Exception If configuration files cannot be loaded
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }

    /**
     * Register GPS permissions in Kimai's permission system.
     *
     * This runs at compile time and adds permissions to the
     * kimai.permission_names container parameter. Without this,
     * permissions would appear in the UI but return 404 when
     * trying to assign them to roles.
     *
     * Default role assignments:
     * - gps_edit_user_preference: ROLE_ADMIN, ROLE_SUPER_ADMIN
     * - gps_view_data: All roles (users can view their own GPS data)
     * - gps_edit_data: ROLE_ADMIN, ROLE_SUPER_ADMIN only
     *
     * @param ContainerBuilder $container Service container
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Reason: Kimai requires permissions to be registered at compile time
        // via prependExtensionConfig. The PermissionsEvent only handles UI
        // display, not the actual registration for role assignment.
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => [
                        PermissionsSubscriber::PERMISSION_EDIT_USER_TRACKING,
                        PermissionsSubscriber::PERMISSION_VIEW_DATA,
                        PermissionsSubscriber::PERMISSION_EDIT_DATA,
                    ],
                    'ROLE_ADMIN' => [
                        PermissionsSubscriber::PERMISSION_EDIT_USER_TRACKING,
                        PermissionsSubscriber::PERMISSION_VIEW_DATA,
                        PermissionsSubscriber::PERMISSION_EDIT_DATA,
                    ],
                    'ROLE_TEAMLEAD' => [
                        PermissionsSubscriber::PERMISSION_VIEW_DATA,
                    ],
                    'ROLE_USER' => [
                        PermissionsSubscriber::PERMISSION_VIEW_DATA,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Returns the extension alias.
     *
     * @return string The extension alias
     */
    public function getAlias(): string
    {
        return 'kimai_mobile_gps_info';
    }
}
