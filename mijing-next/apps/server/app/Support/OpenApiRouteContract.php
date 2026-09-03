<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use RuntimeException;

class OpenApiRouteContract
{
    /**
     * @return list<array{operationId: string, method: string, path: string}>
     */
    public function parseOperations(string $openApiPath): array
    {
        if (! is_readable($openApiPath)) {
            throw new RuntimeException("OpenAPI file not readable: {$openApiPath}");
        }

        $operations = [];
        $currentPath = null;
        $currentMethod = null;

        foreach (file($openApiPath) as $line) {
            if (preg_match('#^  (/[^:]+):\s*$#', $line, $matches)) {
                $currentPath = $matches[1];
                $currentMethod = null;

                continue;
            }

            if ($currentPath === null) {
                continue;
            }

            if (preg_match('#^    (get|post|put|patch|delete):\s*$#', $line, $matches)) {
                $currentMethod = strtoupper($matches[1]);

                continue;
            }

            if ($currentMethod !== null && preg_match('#^\s+operationId:\s*([A-Za-z0-9_]+)\s*$#', $line, $matches)) {
                $operations[] = [
                    'operationId' => $matches[1],
                    'method' => $currentMethod,
                    'path' => $currentPath,
                ];
                $currentMethod = null;
            }
        }

        return $operations;
    }

    /**
     * @param  list<array{operationId: string, method: string, path: string}>  $operations
     * @return list<string>
     */
    public function missingOperationIds(array $operations): array
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        $missing = [];

        foreach ($operations as $operation) {
            if (! $this->operationHasRoute($operation, $routes)) {
                $missing[] = $operation['operationId'];
            }
        }

        sort($missing);

        return $missing;
    }

    public function operationHasRoute(array $operation, ?array $routes = null): bool
    {
        $routes ??= RouteFacade::getRoutes()->getRoutes();
        $expectedUri = $this->normalizeUri('api/v1'.$operation['path']);

        foreach ($routes as $route) {
            if (! in_array($operation['method'], $route->methods(), true)) {
                continue;
            }

            if ($this->normalizeUri($route->uri()) === $expectedUri) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUri(string $uri): string
    {
        $uri = trim(strtolower($uri), '/');
        $segments = array_map(
            static fn (string $segment) => preg_match('#^\{.+\}$#', $segment) ? '{param}' : $segment,
            explode('/', $uri),
        );

        return implode('/', $segments);
    }
}
