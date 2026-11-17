<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogAdminAccessTest extends TestCase
{
    public function test_direct_panel_requires_auth(): void
    {
        $this->get('/catalog-admin')->assertUnauthorized();
    }

    public function test_customer_cannot_enter_panel_with_valid_token(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['user' => ['id' => 1, 'email' => 'buyer@example.com', 'role' => 'customer']])]);
        $this->withToken('valid-customer-token')->get('/catalog-admin')->assertForbidden();
    }
}
