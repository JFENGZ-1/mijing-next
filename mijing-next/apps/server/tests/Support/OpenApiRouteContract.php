<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as LaravelRoute;

final class OpenApiRouteContract
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    /**
     * @return array{
     *     totalOperations: int,
     *     matchedOperations: int,
     *     coveragePercent: float,
     *     operations: list<array{operationId: string, method: string, path: string, matched: bool}>,
     *     unmatchedOperationIds: list<string>
     * }
     */
    public static function analyze(?string $openApiPath = null): array
    {
        $openApiPath ??= dirname(__DIR__, 2).'/../../docs/openapi.yaml';
        $operations = self::parseOpenApiOperations($openApiPath);
        $apiRoutes = self::apiV1Routes();

        $matched = 0;
        $details = [];

        foreach ($operations as $operation) {
            $isMatched = self::operationHasRoute($operation, $apiRoutes);
            $details[] = [
                'operationId' => $operation['operationId'],
                'method' => $operation['method'],
                'path' => $operation['path'],
                'matched' => $isMatched,
            ];

            if ($isMatched) {
                $matched++;
            }
        }

        $total = count($operations);
        $coverage = $total === 0 ? 0.0 : round(($matched / $total) * 100, 1);

        return [
            'totalOperations' => $total,
            'matchedOperations' => $matched,
            'coveragePercent' => $coverage,
            'operations' => $details,
            'unmatchedOperationIds' => array_values(array_map(
                static fn (array $row): string => $row['operationId'],
                array_filter($details, static fn (array $row): bool => ! $row['matched']),
            )),
        ];
    }

    /**
     * @return list<array{operationId: string, method: string, path: string}>
     */
    private static function parseOpenApiOperations(string $openApiPath): array
    {
        $lines = file($openApiPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read OpenAPI spec at {$openApiPath}");
        }

        $operations = [];
        $currentPath = null;
        $pendingMethod = null;

        foreach ($lines as $line) {
            if (preg_match('/^  (\/[^:]+):\s*$/', $line, $matches) === 1) {
                $currentPath = $matches[1];
                $pendingMethod = null;

                continue;
            }

            if ($currentPath === null) {
                continue;
            }

            if (preg_match('/^    (get|post|put|patch|delete|head|options):\s*$/', $line, $matches) === 1) {
                $pendingMethod = strtoupper($matches[1]);

                continue;
            }

            if ($pendingMethod !== null && preg_match('/^      operationId: ([A-Za-z0-9_.-]+)\s*$/', $line, $matches) === 1) {
                $operations[] = [
                    'operationId' => $matches[1],
                    'method' => $pendingMethod,
                    'path' => $currentPath,
                ];
                $pendingMethod = null;
            }
        }

        return $operations;
    }

    /**
     * @return list<LaravelRoute>
     */
    private static function apiV1Routes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/v1'))
            ->values()
            ->all();
    }

    /**
     * @param  array{operationId: string, method: string, path: string}  $operation
     * @param  list<LaravelRoute>  $routes
     */
    private static function operationHasRoute(array $operation, array $routes): bool
    {
        $expectedUri = self::normalizeUri('api/v1'.$operation['path']);

        foreach ($routes as $route) {
            if (! in_array($operation['method'], $route->methods(), true)) {
                continue;
            }

            if (self::normalizeUri($route->uri()) === $expectedUri) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeUri(string $uri): string
    {
        $trimmed = trim($uri, '/');

        return preg_replace('/\{[^}]+\}/', '{}', $trimmed) ?? $trimmed;
    }
}
