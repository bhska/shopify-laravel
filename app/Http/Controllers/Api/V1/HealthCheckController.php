<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Health",
 *     description="Health check and system status endpoints"
 * )
 */
class HealthCheckController extends Controller
{
    public function __construct(private ShopifyService $shopifyService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/health",
     *     summary="Basic health check",
     *     description="Quick health check to verify API is running",
     *     tags={"Health"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="API is healthy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="version", type="string", example="1.0.0")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/health/detailed",
     *     summary="Detailed health check",
     *     description="Comprehensive health check including database, Shopify, and storage",
     *     tags={"Health"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="All systems are healthy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", enum={"healthy", "unhealthy"}, example="healthy", description="Overall system status"),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Health check timestamp"),
     *             @OA\Property(
     *                 property="services",
     *                 type="object",
     *                 @OA\Property(property="database", type="string", enum={"healthy", "unhealthy"}, example="healthy"),
     *                 @OA\Property(property="shopify", type="string", enum={"healthy", "unhealthy"}, example="healthy"),
     *                 @OA\Property(property="storage", type="string", enum={"healthy", "unhealthy"}, example="healthy")
     *             ),
     *             @OA\Property(property="version", type="string", example="1.0.0", description="API version"),
     *             @OA\Property(property="environment", type="string", example="local", description="Application environment")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=503,
     *         description="One or more services are unhealthy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="unhealthy"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="services",
     *                 type="object",
     *                 @OA\Property(property="database", type="string", example="unhealthy"),
     *                 @OA\Property(property="shopify", type="string", example="healthy"),
     *                 @OA\Property(property="storage", type="string", example="healthy")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function detailed(): JsonResponse
    {
        $healthStatus = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [],
            'errors' => [],
            'version' => '1.0.0',
            'environment' => config('app.env'),
        ];

        // Check Database
        try {
            DB::select('SELECT 1');
            $healthStatus['services']['database'] = 'healthy';
        } catch (\Exception $e) {
            $healthStatus['services']['database'] = 'unhealthy';
            $healthStatus['errors'][] = 'Database connection failed: '.$e->getMessage();
            $healthStatus['status'] = 'unhealthy';
        }

        // Check Shopify connection
        try {
            $isShopifyHealthy = $this->shopifyService->validateCredentials();
            $healthStatus['services']['shopify'] = $isShopifyHealthy ? 'healthy' : 'unhealthy';

            if (! $isShopifyHealthy) {
                $healthStatus['errors'][] = 'Shopify API connection failed';
                $healthStatus['status'] = 'unhealthy';
            }
        } catch (\Exception $e) {
            $healthStatus['services']['shopify'] = 'unhealthy';
            $healthStatus['errors'][] = 'Shopify service error: '.$e->getMessage();
            $healthStatus['status'] = 'unhealthy';
        }

        // Check Storage
        try {
            Storage::put('health-check.txt', 'test');
            Storage::delete('health-check.txt');
            $healthStatus['services']['storage'] = 'healthy';
        } catch (\Exception $e) {
            $healthStatus['services']['storage'] = 'unhealthy';
            $healthStatus['errors'][] = 'Storage system error: '.$e->getMessage();
            $healthStatus['status'] = 'unhealthy';
        }

        $statusCode = $healthStatus['status'] === 'healthy' ? 200 : 503;

        return response()->json($healthStatus, $statusCode);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/health/database",
     *     summary="Database health check",
     *     description="Check database connectivity and basic operations",
     *     tags={"Health"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Database is healthy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="connection", type="string", example="mysql"),
     *             @OA\Property(property="database", type="string", example="shopify_laravel"),
     *             @OA\Property(property="response_time_ms", type="integer", example=15)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=503,
     *         description="Database is unhealthy"
     *     )
     * )
     */
    public function database(): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $result = DB::select('SELECT 1 as test');
            $responseTime = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'healthy',
                'connection' => config('database.default'),
                'database' => config('database.connections.mysql.database'),
                'response_time_ms' => $responseTime,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/health/shopify",
     *     summary="Shopify API health check",
     *     description="Check Shopify API connectivity and authentication",
     *     tags={"Health"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Shopify API is accessible",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="store_url", type="string", example="https://mystore.myshopify.com"),
     *             @OA\Property(property="api_version", type="string", example="2025-10"),
     *             @OA\Property(property="response_time_ms", type="integer", example=250)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=503,
     *         description="Shopify API is not accessible"
     *     )
     * )
     */
    public function shopify(): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $isValid = $this->shopifyService->validateCredentials();
            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($isValid) {
                return response()->json([
                    'status' => 'healthy',
                    'store_url' => config('services.shopify.domain'),
                    'api_version' => '2025-10',
                    'response_time_ms' => $responseTime,
                ]);
            } else {
                return response()->json([
                    'status' => 'unhealthy',
                    'error' => 'Invalid Shopify credentials',
                ], 503);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
