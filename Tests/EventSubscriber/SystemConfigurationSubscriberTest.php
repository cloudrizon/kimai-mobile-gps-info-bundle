<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Tests\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use App\Form\Type\YesNoType;
use KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber\SystemConfigurationSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Unit tests for SystemConfigurationSubscriber.
 *
 * Tests the GPS tracking system configuration event subscriber
 * to ensure proper event registration and configuration field creation.
 */
class SystemConfigurationSubscriberTest extends TestCase
{
    /**
     * Test that subscriber registers correct events.
     *
     * Verifies that the subscriber listens to SystemConfigurationEvent
     * with the correct method and priority.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = SystemConfigurationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(SystemConfigurationEvent::class, $events);
        $this->assertEquals(['onSystemConfiguration', 100], $events[SystemConfigurationEvent::class]);
    }

    /**
     * Test that configuration is added to the event.
     *
     * Verifies that calling onSystemConfiguration adds a new
     * SystemConfiguration object to the event.
     */
    public function testOnSystemConfigurationAddsConfiguration(): void
    {
        $subscriber = new SystemConfigurationSubscriber();

        // Create event with empty configurations array
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $this->assertCount(1, $configurations);
        $this->assertInstanceOf(SystemConfiguration::class, $configurations[0]);
    }

    /**
     * Test that configuration section is correctly named.
     *
     * Verifies that the GPS tracking configuration section
     * has the correct section identifier.
     */
    public function testConfigurationSection(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];

        $this->assertEquals('gps_tracking', $systemConfig->getSection());
    }

    /**
     * Test that configuration has correct translation settings.
     *
     * Verifies that the GPS tracking section uses the correct
     * translation key and domain.
     */
    public function testConfigurationTranslation(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];

        $this->assertEquals('gps.tracking', $systemConfig->getTranslation());
        $this->assertEquals('messages', $systemConfig->getTranslationDomain());
    }

    /**
     * Test that configuration contains the tracking enabled field.
     *
     * Verifies that the gps.tracking_enabled configuration field
     * exists and has the correct properties.
     */
    public function testConfigurationContainsTrackingEnabledField(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $fields = $systemConfig->getConfiguration();

        // Now we have 7 fields: 1 tracking + 6 geofence
        $this->assertCount(7, $fields);
        $this->assertInstanceOf(Configuration::class, $fields[0]);
        $this->assertEquals('gps.tracking_enabled', $fields[0]->getName());
    }

    /**
     * Test that configuration contains all geofence fields.
     *
     * Verifies that all 6 geofence configuration fields exist
     * with correct names in the expected order.
     */
    public function testConfigurationContainsAllGeofenceFields(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];

        $expectedFields = [
            'gps.geofence_enabled',
            'gps.geofence_center_lat',
            'gps.geofence_center_lng',
            'gps.geofence_radius',
            'gps.geofence_notify_after',
            'gps.geofence_restrict_mobile_tracking',
        ];

        foreach ($expectedFields as $fieldName) {
            $field = $systemConfig->getConfigurationByName($fieldName);
            $this->assertNotNull($field, sprintf('Field "%s" should exist', $fieldName));
        }
    }

    /**
     * Test that tracking enabled field has correct default value.
     *
     * Verifies that gps.tracking_enabled defaults to false
     * for privacy by design.
     */
    public function testConfigurationDefaultValue(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.tracking_enabled');

        $this->assertNotNull($field);
        $this->assertFalse($field->getValue());
    }

    /**
     * Test that tracking enabled field uses YesNoType.
     *
     * Verifies that the field uses Kimai's YesNoType for
     * consistent boolean toggle UI.
     */
    public function testConfigurationFieldType(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.tracking_enabled');

        $this->assertNotNull($field);
        $this->assertEquals(YesNoType::class, $field->getType());
    }

    /**
     * Test that tracking enabled field is not required.
     *
     * Verifies that the field is optional, allowing the system
     * to work without explicit configuration.
     */
    public function testConfigurationFieldNotRequired(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.tracking_enabled');

        $this->assertNotNull($field);
        $this->assertFalse($field->isRequired());
    }

    // ========================================
    // Geofence Field Tests
    // ========================================

    /**
     * Test geofence enabled field properties.
     *
     * Verifies type, default value, and that field is optional.
     */
    public function testGeofenceEnabledFieldProperties(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.geofence_enabled');

        $this->assertNotNull($field);
        $this->assertEquals(YesNoType::class, $field->getType());
        $this->assertFalse($field->getValue());
        $this->assertFalse($field->isRequired());
    }

    /**
     * Test geofence center latitude field properties.
     *
     * Verifies type, default value, and constraints (Regex + Callback for range).
     * Note: TextType is used instead of NumberType to preserve decimal precision
     * when saving to Kimai's Configuration entity.
     */
    public function testGeofenceCenterLatFieldProperties(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.geofence_center_lat');

        $this->assertNotNull($field);
        $this->assertEquals(TextType::class, $field->getType());
        $this->assertNull($field->getValue());
        $this->assertFalse($field->isRequired());

        // Check Regex and Callback constraints exist
        $constraints = $field->getConstraints();
        $this->assertCount(2, $constraints);
        $this->assertInstanceOf(Regex::class, $constraints[0]);
        $this->assertInstanceOf(Callback::class, $constraints[1]);
    }

    /**
     * Test geofence center longitude field properties.
     *
     * Verifies type, default value, and constraints (Regex + Callback for range).
     * Note: TextType is used instead of NumberType to preserve decimal precision
     * when saving to Kimai's Configuration entity.
     */
    public function testGeofenceCenterLngFieldProperties(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.geofence_center_lng');

        $this->assertNotNull($field);
        $this->assertEquals(TextType::class, $field->getType());
        $this->assertNull($field->getValue());
        $this->assertFalse($field->isRequired());

        // Check Regex and Callback constraints exist
        $constraints = $field->getConstraints();
        $this->assertCount(2, $constraints);
        $this->assertInstanceOf(Regex::class, $constraints[0]);
        $this->assertInstanceOf(Callback::class, $constraints[1]);
    }

    /**
     * Test geofence radius field properties.
     *
     * Verifies type, default value, and Range constraint (10-1000).
     */
    public function testGeofenceRadiusFieldProperties(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.geofence_radius');

        $this->assertNotNull($field);
        $this->assertEquals(IntegerType::class, $field->getType());
        $this->assertNull($field->getValue());
        $this->assertFalse($field->isRequired());

        // Check Range constraint exists
        $constraints = $field->getConstraints();
        $this->assertNotEmpty($constraints);
        $this->assertInstanceOf(Range::class, $constraints[0]);
        $this->assertEquals(10, $constraints[0]->min);
        $this->assertEquals(1000, $constraints[0]->max);
    }

    /**
     * Test geofence notify after field properties.
     *
     * Verifies type, default value (5), and Range constraint (0-60).
     */
    public function testGeofenceNotifyAfterFieldProperties(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.geofence_notify_after');

        $this->assertNotNull($field);
        $this->assertEquals(IntegerType::class, $field->getType());
        $this->assertEquals(5, $field->getValue());
        $this->assertFalse($field->isRequired());

        // Check Range constraint exists
        $constraints = $field->getConstraints();
        $this->assertNotEmpty($constraints);
        $this->assertInstanceOf(Range::class, $constraints[0]);
        $this->assertEquals(0, $constraints[0]->min);
        $this->assertEquals(60, $constraints[0]->max);
    }

    /**
     * Test geofence restrict mobile tracking field properties.
     *
     * Verifies type, default value (false), and that field is optional.
     */
    public function testGeofenceRestrictMobileTrackingFieldProperties(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];
        $field = $systemConfig->getConfigurationByName('gps.geofence_restrict_mobile_tracking');

        $this->assertNotNull($field);
        $this->assertEquals(YesNoType::class, $field->getType());
        $this->assertFalse($field->getValue());
        $this->assertFalse($field->isRequired());
    }

    /**
     * Test all geofence fields have help text configured.
     *
     * Verifies that each geofence field has a help text option.
     */
    public function testAllGeofenceFieldsHaveHelpText(): void
    {
        $subscriber = new SystemConfigurationSubscriber();
        $event = new SystemConfigurationEvent([]);

        $subscriber->onSystemConfiguration($event);

        $configurations = $event->getConfigurations();
        $systemConfig = $configurations[0];

        $geofenceFields = [
            'gps.geofence_enabled',
            'gps.geofence_center_lat',
            'gps.geofence_center_lng',
            'gps.geofence_radius',
            'gps.geofence_notify_after',
            'gps.geofence_restrict_mobile_tracking',
        ];

        foreach ($geofenceFields as $fieldName) {
            $field = $systemConfig->getConfigurationByName($fieldName);
            $this->assertNotNull($field, sprintf('Field "%s" should exist', $fieldName));

            $options = $field->getOptions();
            $this->assertArrayHasKey('help', $options, sprintf('Field "%s" should have help option', $fieldName));
            $this->assertEquals($fieldName . '.help', $options['help']);
        }
    }
}
