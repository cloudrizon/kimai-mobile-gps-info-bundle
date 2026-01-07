<?php

/*
 * This file is part of the KimaiMobileGPSInfoBundle plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\KimaiMobileGPSInfoBundle\Controller;

use App\Entity\User;
use KimaiPlugin\KimaiMobileGPSInfoBundle\Service\GpsConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API controller for GPS configuration endpoints.
 *
 * Provides REST API endpoints for mobile clients to query GPS tracking
 * configuration. Returns the current user's GPS tracking status based on
 * global system settings and user-level preferences.
 *
 * All endpoints require Bearer token authentication.
 */
#[Route('/gps')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class GpsConfigController extends AbstractController
{
    /**
     * Constructor with autowired dependencies.
     *
     * @param GpsConfigService $configService GPS configuration service
     */
    public function __construct(
        private readonly GpsConfigService $configService
    ) {
    }

    /**
     * Get GPS tracking configuration for current user.
     *
     * Returns global and user-level GPS tracking settings along with
     * computed effective status. Mobile clients use this to determine
     * whether to capture and send GPS coordinates.
     *
     * Response format:
     * ```json
     * {
     *   "global_enabled": true,   // System-wide setting (admin-controlled)
     *   "user_enabled": false,    // Per-user setting (admin-controlled)
     *   "effective": false        // Computed: both must be true
     * }
     * ```
     *
     * @return JsonResponse Configuration response or error
     */
    #[Route('/config', name: 'gps_config', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        try {
            /** @var User|null $user */
            $user = $this->getUser();

            // This should not happen due to #[IsGranted] attribute,
            // but we check anyway for type safety
            if (!$user instanceof User) {
                return $this->json([
                    'code' => 401,
                    'message' => 'Full authentication is required to access this resource.',
                ], 401);
            }

            $globalEnabled = $this->configService->isGlobalTrackingEnabled();
            $userEnabled = $this->configService->isUserTrackingEnabled($user);
            $effective = $this->configService->isTrackingEffective($user);

            return $this->json([
                'global_enabled' => $globalEnabled,
                'user_enabled' => $userEnabled,
                'effective' => $effective,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to retrieve GPS configuration: ' . $e->getMessage(),
            ], 500);
        }
    }
}
