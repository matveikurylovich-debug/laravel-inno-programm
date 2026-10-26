<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET', 'HEAD'])]
    #[Route('/recommendations/health', name: 'recommendations_health', methods: ['GET', 'HEAD'])]
    #[OA\Get(
        path: '/health',
        summary: 'Health check',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    #[OA\Get(
        path: '/recommendations/health',
        summary: 'Recommendations health check',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
