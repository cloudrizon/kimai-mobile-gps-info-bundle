<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\EventSubscriber;

use App\Entity\Timesheet;
use App\Event\TimesheetMetaDefinitionEvent;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\PermissionsSubscriber;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\TimesheetMetaDefinitionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Unit tests for TimesheetMetaDefinitionSubscriber.
 *
 * Tests the GPS meta field definitions to ensure proper event registration,
 * field creation, validation constraint configuration, and permission-based
 * visibility/editability.
 */
class TimesheetMetaDefinitionSubscriberTest extends TestCase
{
    /**
     * Creates a subscriber with mocked Security service.
     *
     * @param bool $canView Whether user has gps_view_data permission
     * @param bool $canEdit Whether user has gps_edit_data permission
     *
     * @return TimesheetMetaDefinitionSubscriber Configured subscriber
     */
    private function createSubscriber(bool $canView = true, bool $canEdit = true): TimesheetMetaDefinitionSubscriber
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(function (string $permission) use ($canView, $canEdit): bool {
                return match ($permission) {
                    PermissionsSubscriber::PERMISSION_VIEW_DATA => $canView,
                    PermissionsSubscriber::PERMISSION_EDIT_DATA => $canEdit,
                    default => false,
                };
            });

        return new TimesheetMetaDefinitionSubscriber($security);
    }

    /**
     * Test that subscriber registers correct events.
     *
     * Verifies that the subscriber listens to TimesheetMetaDefinitionEvent
     * with the correct method and priority (200 for early execution).
     */
    public function testGetSubscribedEvents(): void
    {
        $events = TimesheetMetaDefinitionSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(TimesheetMetaDefinitionEvent::class, $events);
        $this->assertEquals(['onMetaDefinition', 200], $events[TimesheetMetaDefinitionEvent::class]);
    }

    /**
     * Test that priority is set to 200 for early execution.
     *
     * Higher priority ensures GPS fields are defined before other subscribers.
     */
    public function testEventPriorityIsHigh(): void
    {
        $events = TimesheetMetaDefinitionSubscriber::getSubscribedEvents();
        $config = $events[TimesheetMetaDefinitionEvent::class];

        $this->assertEquals(200, $config[1]);
    }

    /**
     * Test that both GPS meta fields are defined when user has view permission.
     *
     * Verifies that calling onMetaDefinition adds both start and stop
     * GPS location fields to the timesheet entity.
     */
    public function testBothGpsFieldsAreDefined(): void
    {
        $subscriber = $this->createSubscriber(canView: true, canEdit: true);
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $entity = $event->getEntity();

        // Get meta fields by name
        $startField = $entity->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $entity->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $this->assertNotNull($startField, 'gps_start_location field should be defined');
        $this->assertNotNull($stopField, 'gps_stop_location field should be defined');
    }

    /**
     * Test that GPS fields are NOT defined when user lacks view permission.
     *
     * Verifies that fields are hidden for users without gps_view_data permission.
     */
    public function testFieldsNotDefinedWithoutViewPermission(): void
    {
        $subscriber = $this->createSubscriber(canView: false, canEdit: false);
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $entity = $event->getEntity();

        $startField = $entity->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $entity->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $this->assertNull($startField, 'gps_start_location should not be defined without view permission');
        $this->assertNull($stopField, 'gps_stop_location should not be defined without view permission');
    }

    /**
     * Test that GPS fields are editable when user has edit permission.
     *
     * Verifies that fields have no disabled option when user can edit.
     */
    public function testFieldsEditableWithEditPermission(): void
    {
        $subscriber = $this->createSubscriber(canView: true, canEdit: true);
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $options = $startField->getOptions();

        $this->assertArrayNotHasKey('disabled', $options);
    }

    /**
     * Test that GPS fields are disabled when user lacks edit permission.
     *
     * Verifies that fields are read-only for users without gps_edit_data permission.
     */
    public function testFieldsDisabledWithoutEditPermission(): void
    {
        $subscriber = $this->createSubscriber(canView: true, canEdit: false);
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $startOptions = $startField->getOptions();
        $stopOptions = $stopField->getOptions();

        $this->assertArrayHasKey('disabled', $startOptions);
        $this->assertTrue($startOptions['disabled']);
        $this->assertArrayHasKey('disabled', $stopOptions);
        $this->assertTrue($stopOptions['disabled']);
    }

    /**
     * Test that GPS start field has correct name constant.
     *
     * Verifies the FIELD_GPS_START constant value.
     */
    public function testGpsStartFieldNameConstant(): void
    {
        $this->assertEquals('gps_start_location', TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
    }

    /**
     * Test that GPS stop field has correct name constant.
     *
     * Verifies the FIELD_GPS_STOP constant value.
     */
    public function testGpsStopFieldNameConstant(): void
    {
        $this->assertEquals('gps_stop_location', TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);
    }

    /**
     * Test that GPS start field has correct label.
     *
     * Verifies the translation key is set correctly.
     */
    public function testGpsStartFieldLabel(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $field = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);

        $this->assertEquals('gps.start_location', $field->getLabel());
    }

    /**
     * Test that GPS stop field has correct label.
     *
     * Verifies the translation key is set correctly.
     */
    public function testGpsStopFieldLabel(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $field = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $this->assertEquals('gps.stop_location', $field->getLabel());
    }

    /**
     * Test that GPS fields use TextType form field.
     *
     * Verifies both fields use Symfony's TextType for string input.
     */
    public function testGpsFieldsUseTextType(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $this->assertEquals(TextType::class, $startField->getType());
        $this->assertEquals(TextType::class, $stopField->getType());
    }

    /**
     * Test that GPS fields are not required.
     *
     * Verifies fields are optional (privacy by design - GPS is optional).
     */
    public function testGpsFieldsAreNotRequired(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $this->assertFalse($startField->isRequired());
        $this->assertFalse($stopField->isRequired());
    }

    /**
     * Test that GPS fields are visible.
     *
     * Verifies fields appear in UI and exports.
     */
    public function testGpsFieldsAreVisible(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $this->assertTrue($startField->isVisible());
        $this->assertTrue($stopField->isVisible());
    }

    /**
     * Test that GPS fields have validation constraints.
     *
     * Verifies that Length and Regex constraints are applied.
     */
    public function testGpsFieldsHaveConstraints(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $constraints = $startField->getConstraints();

        $this->assertCount(2, $constraints);
    }

    /**
     * Test that GPS fields have Length constraint.
     *
     * Verifies max length of 50 characters is applied.
     */
    public function testGpsFieldsHaveLengthConstraint(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $constraints = $startField->getConstraints();

        $lengthConstraint = null;
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Length) {
                $lengthConstraint = $constraint;
                break;
            }
        }

        $this->assertNotNull($lengthConstraint, 'Length constraint should be present');
        $this->assertEquals(50, $lengthConstraint->max);
    }

    /**
     * Test that GPS fields have Regex constraint.
     *
     * Verifies coordinate format validation pattern is applied.
     */
    public function testGpsFieldsHaveRegexConstraint(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $constraints = $startField->getConstraints();

        $regexConstraint = null;
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Regex) {
                $regexConstraint = $constraint;
                break;
            }
        }

        $this->assertNotNull($regexConstraint, 'Regex constraint should be present');
        $this->assertEquals('/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/', $regexConstraint->pattern);
    }

    /**
     * Test that Regex constraint has correct error message.
     *
     * Verifies the validation error message translation key.
     */
    public function testRegexConstraintHasCorrectMessage(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $constraints = $startField->getConstraints();

        $regexConstraint = null;
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Regex) {
                $regexConstraint = $constraint;
                break;
            }
        }

        $this->assertEquals('gps.coordinate_format_invalid', $regexConstraint->message);
    }

    /**
     * Test regex pattern accepts standard coordinates.
     *
     * Validates pattern matches "52.5162748,13.3774573".
     */
    public function testRegexAcceptsStandardCoordinates(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertMatchesRegularExpression($pattern, '52.5162748,13.3774573');
    }

    /**
     * Test regex pattern accepts coordinates with space.
     *
     * Validates pattern matches "52.5162748, 13.3774573".
     */
    public function testRegexAcceptsCoordinatesWithSpace(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertMatchesRegularExpression($pattern, '52.5162748, 13.3774573');
    }

    /**
     * Test regex pattern accepts negative coordinates.
     *
     * Validates pattern matches "-90,-180".
     */
    public function testRegexAcceptsNegativeCoordinates(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertMatchesRegularExpression($pattern, '-90,-180');
    }

    /**
     * Test regex pattern accepts zero coordinates.
     *
     * Validates pattern matches "0,0".
     */
    public function testRegexAcceptsZeroCoordinates(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertMatchesRegularExpression($pattern, '0,0');
    }

    /**
     * Test regex pattern rejects non-numeric values.
     *
     * Validates pattern does not match "abc,xyz".
     */
    public function testRegexRejectsNonNumericValues(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertDoesNotMatchRegularExpression($pattern, 'abc,xyz');
    }

    /**
     * Test regex pattern rejects missing comma.
     *
     * Validates pattern does not match "52.5162748 13.3774573".
     */
    public function testRegexRejectsMissingComma(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertDoesNotMatchRegularExpression($pattern, '52.5162748 13.3774573');
    }

    /**
     * Test regex pattern rejects single value.
     *
     * Validates pattern does not match "52.5162748".
     */
    public function testRegexRejectsSingleValue(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertDoesNotMatchRegularExpression($pattern, '52.5162748');
    }

    /**
     * Test regex pattern rejects empty string.
     *
     * Validates pattern does not match "".
     */
    public function testRegexRejectsEmptyString(): void
    {
        $pattern = '/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/';
        $this->assertDoesNotMatchRegularExpression($pattern, '');
    }

    /**
     * Test that both stop field constraints match start field.
     *
     * Verifies both fields have identical validation configuration.
     */
    public function testStopFieldConstraintsMatchStartField(): void
    {
        $subscriber = $this->createSubscriber();
        $timesheet = new Timesheet();
        $event = new TimesheetMetaDefinitionEvent($timesheet);

        $subscriber->onMetaDefinition($event);

        $startField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_START);
        $stopField = $event->getEntity()->getMetaField(TimesheetMetaDefinitionSubscriber::FIELD_GPS_STOP);

        $startConstraints = $startField->getConstraints();
        $stopConstraints = $stopField->getConstraints();

        $this->assertCount(count($startConstraints), $stopConstraints);
    }
}
