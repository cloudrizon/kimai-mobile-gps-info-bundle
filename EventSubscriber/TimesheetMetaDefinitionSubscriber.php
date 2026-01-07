<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber;

use App\Entity\TimesheetMeta;
use App\Event\TimesheetMetaDefinitionEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Event subscriber for defining GPS metadata fields on timesheets.
 *
 * Registers GPS location meta fields (start and stop) in Kimai's timesheet
 * metadata system. These fields store GPS coordinates received from mobile
 * clients when starting and stopping time tracking.
 *
 * The fields accept coordinates in the format "latitude,longitude" with
 * optional whitespace around the comma (e.g., "52.5162748,13.3774573" or
 * "52.5162748, 13.3774573").
 *
 * Both fields are optional (not required) following privacy-by-design
 * principles. GPS data is only collected when the mobile client sends it.
 *
 * Field visibility and editability are controlled by permissions:
 * - gps_view_data: Required to see GPS fields in the form
 * - gps_edit_data: Required to edit GPS fields (otherwise read-only)
 */
final class TimesheetMetaDefinitionSubscriber implements EventSubscriberInterface
{
    /**
     * Meta field name for GPS start location.
     */
    public const FIELD_GPS_START = 'gps_start_location';

    /**
     * Meta field name for GPS stop location.
     */
    public const FIELD_GPS_STOP = 'gps_stop_location';

    /**
     * Maximum length for GPS coordinate string.
     *
     * Accommodates full precision coordinates like "-90.123456,-180.123456".
     */
    private const MAX_LENGTH = 50;

    /**
     * Regex pattern for validating GPS coordinates.
     *
     * Accepts formats:
     * - "52.5162748,13.3774573" (no space)
     * - "52.5162748, 13.3774573" (with space)
     * - "-90,-180" (negative coordinates)
     * - "0,0" (zero coordinates)
     */
    private const COORDINATE_PATTERN = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';

    /**
     * Constructor with autowired dependencies.
     *
     * @param Security $security Symfony security service for permission checks
     */
    public function __construct(
        private readonly Security $security
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
            TimesheetMetaDefinitionEvent::class => ['onMetaDefinition', 200],
        ];
    }

    /**
     * Defines GPS meta fields when timesheet metadata is initialized.
     *
     * Adds two GPS location fields to the timesheet:
     * - gps_start_location: GPS coordinates when timer started
     * - gps_stop_location: GPS coordinates when timer stopped
     *
     * Both fields are visible in the UI and exports, optional (not required),
     * and validated for correct coordinate format.
     *
     * Field visibility and editability depend on user permissions:
     * - Without gps_view_data: Fields are hidden (not visible)
     * - Without gps_edit_data: Fields are visible but disabled (read-only)
     *
     * @param TimesheetMetaDefinitionEvent $event The meta definition event
     */
    public function onMetaDefinition(TimesheetMetaDefinitionEvent $event): void
    {
        // Reason: Check view permission first - if user can't view GPS data,
        // don't define the fields at all (they won't appear in the form)
        if (!$this->security->isGranted(PermissionsSubscriber::PERMISSION_VIEW_DATA)) {
            return;
        }

        $entity = $event->getEntity();

        // Reason: Check edit permission - if user can't edit GPS data,
        // make the fields read-only by setting disabled option
        $canEdit = $this->security->isGranted(PermissionsSubscriber::PERMISSION_EDIT_DATA);
        $formOptions = $canEdit ? [] : ['disabled' => true];

        // Define GPS start location field
        $startMeta = new TimesheetMeta();
        $startMeta
            ->setName(self::FIELD_GPS_START)
            ->setLabel('gps.start_location')
            ->setType(TextType::class)
            ->setIsRequired(false)
            ->setIsVisible(true)
            ->setOptions($formOptions)
            ->setConstraints($this->getCoordinateConstraints());

        $entity->setMetaField($startMeta);

        // Define GPS stop location field
        $stopMeta = new TimesheetMeta();
        $stopMeta
            ->setName(self::FIELD_GPS_STOP)
            ->setLabel('gps.stop_location')
            ->setType(TextType::class)
            ->setIsRequired(false)
            ->setIsVisible(true)
            ->setOptions($formOptions)
            ->setConstraints($this->getCoordinateConstraints());

        $entity->setMetaField($stopMeta);
    }

    /**
     * Returns validation constraints for GPS coordinate fields.
     *
     * Applies Length and Regex constraints to ensure coordinates
     * are within acceptable length and match the expected format.
     *
     * @return array<Length|Regex> Array of validation constraints
     */
    private function getCoordinateConstraints(): array
    {
        return [
            new Length(['max' => self::MAX_LENGTH]),
            new Regex([
                'pattern' => self::COORDINATE_PATTERN,
                'message' => 'gps.coordinate_format_invalid',
            ]),
        ];
    }
}
