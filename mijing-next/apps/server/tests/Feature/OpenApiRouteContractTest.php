<?php

namespace Tests\Feature;

use Tests\Support\OpenApiRouteContract;
use Tests\TestCase;

class OpenApiRouteContractTest extends TestCase
{
    public function test_openapi_operation_ids_map_to_registered_api_v1_routes(): void
    {
        $contract = OpenApiRouteContract::analyze();

        $this->assertGreaterThan(200, $contract['totalOperations']);
        $this->assertGreaterThanOrEqual(
            95.0,
            $contract['coveragePercent'],
            'Unmatched OpenAPI operations: '.implode(', ', array_slice($contract['unmatchedOperationIds'], 0, 20))
        );
    }

    public function test_reports_partial_openapi_route_contract_coverage(): void
    {
        $contract = OpenApiRouteContract::analyze();

        fwrite(
            STDERR,
            sprintf(
                "OpenAPI route contract coverage: %.1f%% (%d/%d operations matched to api.php routes)\n",
                $contract['coveragePercent'],
                $contract['matchedOperations'],
                $contract['totalOperations'],
            ),
        );

        if ($contract['unmatchedOperationIds'] !== []) {
            fwrite(
                STDERR,
                'Unmatched operationIds: '.implode(', ', $contract['unmatchedOperationIds'])."\n",
            );
        }

        $this->assertSame($contract['totalOperations'], count($contract['operations']));
        $this->assertSame(
            $contract['matchedOperations'] + count($contract['unmatchedOperationIds']),
            $contract['totalOperations'],
        );
    }
}
