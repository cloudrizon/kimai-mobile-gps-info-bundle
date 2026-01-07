<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use App\Form\Type\YesNoType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for adding GPS tracking system configuration.
 *
 * Registers the global GPS tracking setting in Kimai's System -> Settings
 * admin page. This setting controls whether GPS tracking is enabled
 * system-wide for all users.
 *
 * The setting `gps.tracking_enabled` defaults to false for privacy by design.
 * Administrators must explicitly enable GPS tracking before it becomes active.
 */
final class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    /**
     * Returns the events this subscriber listens to.
     *
     * @return array<string, array{string, int}> Event to method mapping with priority
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    /**
     * Adds GPS tracking configuration to system settings.
     *
     * Creates a new "GPS Tracking" section in the system configuration
     * with a single toggle setting for enabling/disabling GPS tracking
     * globally across the entire Kimai instance.
     *
     * @param SystemConfigurationEvent $event The system configuration event
     */
    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $configuration = (new SystemConfiguration('gps_tracking'))
            ->setTranslation('gps.tracking')
            ->setTranslationDomain('messages')
            ->setConfiguration([
                (new Configuration('gps.tracking_enabled'))
                    ->setLabel('gps.tracking_enabled')
                    ->setTranslationDomain('messages')
                    ->setType(YesNoType::class)
                    ->setValue(false)
                    ->setRequired(false)
                    ->setOptions([
                        'help' => 'gps.tracking_enabled.help',
                    ]),
            ]);

        $event->addConfiguration($configuration);
    }
}
