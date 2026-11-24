<?php

namespace Tests\Feature;

use App\Models\ProductionRecord;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    public function test_master_production_routes_bind_machines_by_name(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($route) => in_array('PUT', $route->methods(), true)
                && $route->uri() === 'api/master-productions/{machine}'
        );

        $this->assertNotNull($route);
        $this->assertSame('name', $route->bindingFieldFor('machine'));
    }

    public function test_production_record_filter_applies_part_number(): void
    {
        $query = ProductionRecord::query()->filter(['partNumber' => 'PN-42']);

        $this->assertStringContainsString('part_number', $query->toSql());
        $this->assertContains('PN-42', $query->getBindings());
    }
}
