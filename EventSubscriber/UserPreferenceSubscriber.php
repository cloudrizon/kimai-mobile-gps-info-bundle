<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use App\Form\Type\YesNoType;
use KimaiPlugin\KimaiMobileGPSInfoBundle\Service\GpsConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Event subscriber for adding GPS tracking user preference.
 *
 * Registers a per-user GPS tracking setting controlled by the
 * `gps_edit_user_preference` permission. Users with this permission
 * can enable/disable GPS tracking for specific users.
 *
 * This supports GDPR Article 21 objection requests by allowing authorized
 * users to disable GPS tracking for individuals who have objected.
 *
 * The preference `gps_tracking_enabled` defaults to true, meaning users
 * will have GPS tracking enabled when the global setting is on.
 */
final class UserPreferenceSubscriber implements EventSubscriberInterface
{
    /**
     * Constructor with autowired dependencies.
     *
     * @param AuthorizationCheckerInterface $security Authorization checker for role verification
     * @param GpsConfigService $gpsConfigService GPS configuration service for global/user settings
     * @param TranslatorInterface $translator Translator for building dynamic help text
     */
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly GpsConfigService $gpsConfigService,
        private readonly TranslatorInterface $translator
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
            UserPreferenceEvent::class => ['onUserPreference', 100],
        ];
    }

    /**
     * Adds GPS tracking preference for users.
     *
     * The preference is only visible and editable by users with the
     * `gps_edit_user_preference` permission. This allows authorized users
     * to selectively enable/disable GPS tracking for specific users
     * (e.g., for GDPR Article 21 objection requests).
     *
     * The field is disabled (grayed out) when global GPS tracking is disabled,
     * as the user preference has no effect without the global setting.
     *
     * @param UserPreferenceEvent $event The user preference event
     */
    public function onUserPreference(UserPreferenceEvent $event): void
    {
        // Check if user has permission to edit GPS tracking settings
        $canEdit = $this->canEditUserTracking();

        // Get global GPS tracking status
        $globalEnabled = $this->gpsConfigService->isGlobalTrackingEnabled();

        // Get the user being edited and their GPS tracking preference
        $user = $event->getUser();
        $userEnabled = $this->gpsConfigService->isUserTrackingEnabled($user);

        // Compute effective status (both must be enabled)
        $effective = $globalEnabled && $userEnabled;

        // Build dynamic help text with effective status
        $helpText = $this->buildHelpText($globalEnabled, $effective);

        // Field enabled only if: has permission AND global is enabled
        // When global is disabled, field is grayed out to indicate it has no effect
        $fieldEnabled = $canEdit && $globalEnabled;

        $preference = (new UserPreference('gps_tracking_enabled', true))
            ->setType(YesNoType::class)
            ->setEnabled($fieldEnabled)
            ->setSection('gps')
            ->setOrder(1000)
            ->setOptions([
                'label' => 'gps.user_tracking_enabled',
                'help' => $helpText,
            ]);

        $event->addPreference($preference);
    }

    /**
     * Builds the dynamic help text with explanation and effective status.
     *
     * The help text includes:
     * 1. Static explanation about GPS tracking requirements
     * 2. Dynamic effective status with reason if inactive
     *
     * @param bool $globalEnabled Whether global GPS tracking is enabled
     * @param bool $effective Whether GPS tracking is effectively active for this user
     *
     * @return string The formatted help text
     */
    private function buildHelpText(bool $globalEnabled, bool $effective): string
    {
        // Get static explanation text
        $explanation = $this->translator->trans('gps.user_tracking.help.explanation');
        $statusLabel = $this->translator->trans('gps.user_tracking.effective_status');

        if ($effective) {
            $status = $this->translator->trans('gps.user_tracking.status.active');
        } else {
            $status = $this->translator->trans('gps.user_tracking.status.inactive');
            if (!$globalEnabled) {
                $reason = $this->translator->trans('gps.user_tracking.status.global_disabled');
                $status .= ' ' . $reason;
            }
        }

        // Combine explanation and status on separate lines
        return $explanation . "\n" . $statusLabel . ': ' . $status;
    }

    /**
     * Check if the current user can edit GPS tracking for users.
     *
     * Uses the `gps_edit_user_preference` permission which is configurable
     * per role in the Roles admin screen. This replaces the previous
     * hardcoded admin role check.
     *
     * @return bool True if user has permission to edit GPS tracking settings
     */
    private function canEditUserTracking(): bool
    {
        return $this->security->isGranted(PermissionsSubscriber::PERMISSION_EDIT_USER_TRACKING);
    }
}
