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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
     * Create a coordinate range validation callback.
     *
     * Validates that a coordinate value falls within the specified range.
     * Allows null/empty values to pass (optional field handling).
     *
     * @param float $min Minimum valid value (inclusive)
     * @param float $max Maximum valid value (inclusive)
     * @param string $fieldName Human-readable field name for error message
     *
     * @return Callback Symfony validation callback constraint
     */
    private function createCoordinateRangeCallback(float $min, float $max, string $fieldName): Callback
    {
        return new Callback([
            'callback' => function ($value, ExecutionContextInterface $context) use ($min, $max, $fieldName): void {
                if ($value === null || $value === '') {
                    return;
                }
                $floatValue = (float) $value;
                if ($floatValue < $min || $floatValue > $max) {
                    $context->buildViolation(sprintf('%s must be between %d and %d.', $fieldName, (int) $min, (int) $max))
                        ->addViolation();
                }
            },
        ]);
    }

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
                // Geofence configuration fields
                (new Configuration('gps.geofence_enabled'))
                    ->setLabel('gps.geofence_enabled')
                    ->setTranslationDomain('messages')
                    ->setType(YesNoType::class)
                    ->setValue(false)
                    ->setRequired(false)
                    ->setOptions([
                        'help' => 'gps.geofence_enabled.help',
                    ]),
                (new Configuration('gps.geofence_center_lat'))
                    ->setLabel('gps.geofence_center_lat')
                    ->setTranslationDomain('messages')
                    ->setType(TextType::class)
                    ->setValue(null)
                    ->setRequired(false)
                    ->setConstraints([
                        new Regex([
                            'pattern' => '/^-?\d+(\.\d+)?$/',
                            'message' => 'Invalid coordinate format',
                        ]),
                        $this->createCoordinateRangeCallback(-90.0, 90.0, 'Latitude'),
                    ])
                    ->setOptions([
                        'help' => 'gps.geofence_center_lat.help',
                        'attr' => ['inputmode' => 'decimal'],
                    ]),
                (new Configuration('gps.geofence_center_lng'))
                    ->setLabel('gps.geofence_center_lng')
                    ->setTranslationDomain('messages')
                    ->setType(TextType::class)
                    ->setValue(null)
                    ->setRequired(false)
                    ->setConstraints([
                        new Regex([
                            'pattern' => '/^-?\d+(\.\d+)?$/',
                            'message' => 'Invalid coordinate format',
                        ]),
                        $this->createCoordinateRangeCallback(-180.0, 180.0, 'Longitude'),
                    ])
                    ->setOptions([
                        'help' => 'gps.geofence_center_lng.help',
                        'attr' => ['inputmode' => 'decimal'],
                    ]),
                (new Configuration('gps.geofence_radius'))
                    ->setLabel('gps.geofence_radius')
                    ->setTranslationDomain('messages')
                    ->setType(IntegerType::class)
                    ->setValue(null)
                    ->setRequired(false)
                    ->setConstraints([
                        new Range(['min' => 10, 'max' => 1000]),
                    ])
                    ->setOptions([
                        'help' => 'gps.geofence_radius.help',
                    ]),
                (new Configuration('gps.geofence_notify_after'))
                    ->setLabel('gps.geofence_notify_after')
                    ->setTranslationDomain('messages')
                    ->setType(IntegerType::class)
                    ->setValue(5)
                    ->setRequired(false)
                    ->setConstraints([
                        new Range(['min' => 0, 'max' => 60]),
                    ])
                    ->setOptions([
                        'help' => 'gps.geofence_notify_after.help',
                    ]),
                (new Configuration('gps.geofence_restrict_mobile_tracking'))
                    ->setLabel('gps.geofence_restrict_mobile_tracking')
                    ->setTranslationDomain('messages')
                    ->setType(YesNoType::class)
                    ->setValue(false)
                    ->setRequired(false)
                    ->setOptions([
                        'help' => 'gps.geofence_restrict_mobile_tracking.help',
                    ]),
            ]);

        $event->addConfiguration($configuration);
    }
}
