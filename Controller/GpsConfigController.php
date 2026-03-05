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
use OpenApi\Attributes as OA;
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
#[OA\Tag(name: 'GPS')]
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
     * computed effective status and geofence configuration. Mobile clients
     * use this to determine whether to capture and send GPS coordinates,
     * and to enforce geofence boundaries if configured.
     *
     * Response format:
     * ```json
     * {
     *   "global_enabled": true,   // System-wide setting (admin-controlled)
     *   "user_enabled": false,    // Per-user setting (admin-controlled)
     *   "effective": false,       // Computed: both must be true
     *   "geofences": [...]        // Array of geofence configurations (empty if none)
     * }
     * ```
     *
     * @return JsonResponse Configuration response or error
     */
    #[OA\Response(
        response: 200,
        description: 'Returns GPS tracking configuration for current user',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'global_enabled',
                    type: 'boolean',
                    description: 'System-wide GPS tracking setting (admin-controlled)',
                    example: true
                ),
                new OA\Property(
                    property: 'user_enabled',
                    type: 'boolean',
                    description: 'Per-user GPS tracking setting (admin-controlled)',
                    example: true
                ),
                new OA\Property(
                    property: 'effective',
                    type: 'boolean',
                    description: 'Computed effective status: both global and user must be true',
                    example: true
                ),
                new OA\Property(
                    property: 'geofences',
                    type: 'array',
                    description: 'Geofence configurations (empty array if none configured)',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: 'default'),
                            new OA\Property(property: 'name', type: 'string', example: 'Workplace'),
                            new OA\Property(property: 'enabled', type: 'boolean', example: true),
                            new OA\Property(property: 'center_lat', type: 'number', format: 'float', example: 52.5162748),
                            new OA\Property(property: 'center_lng', type: 'number', format: 'float', example: 13.3774573),
                            new OA\Property(property: 'radius', type: 'integer', description: 'Radius in meters (10-1000)', example: 500),
                            new OA\Property(property: 'notify_after_minutes', type: 'integer', description: 'Minutes before notification triggers (0-60)', example: 5),
                            new OA\Property(property: 'restrict_mobile_tracking', type: 'boolean', description: 'Block time tracking outside geofence', example: false),
                        ],
                        type: 'object'
                    )
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Authentication required',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Full authentication is required to access this resource.'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 500),
                new OA\Property(property: 'message', type: 'string', example: 'Failed to retrieve GPS configuration'),
            ],
            type: 'object'
        )
    )]
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
                'geofences' => $this->configService->getGeofencesConfig(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to retrieve GPS configuration: ' . $e->getMessage(),
            ], 500);
        }
    }
}
