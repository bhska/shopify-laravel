<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ApiExceptionHandler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Resource not found', 404);
        } catch (NotFoundHttpException $e) {
            return $this->errorResponse('Endpoint not found', 404);
        } catch (MethodNotAllowedHttpException $e) {
            return $this->errorResponse('Method not allowed', 405);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse('Unauthorized access', 401);
        } catch (MissingAbilityException $e) {
            return $this->errorResponse('Insufficient permissions', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, [
                'errors' => $e->errors(),
            ]);
        } catch (\Exception $e) {
            // Log the detailed error for debugging
            \Log::error('API Error: '.$e->getMessage(), [
                'request' => $request->all(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Internal server error',
                500
            );
        }
    }

    /**
     * Return a standardized error response.
     */
    private function errorResponse(string $message, int $code, array $additional = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $code,
            'timestamp' => now()->toISOString(),
        ];

        if (! empty($additional)) {
            $response = array_merge($response, $additional);
        }

        return response()->json($response, $code);
    }
}
