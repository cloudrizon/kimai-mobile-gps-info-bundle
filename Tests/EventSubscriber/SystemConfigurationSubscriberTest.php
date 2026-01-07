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

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Configuration::class, $fields[0]);
        $this->assertEquals('gps.tracking_enabled', $fields[0]->getName());
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
}
