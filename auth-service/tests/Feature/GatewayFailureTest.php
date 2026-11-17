<?php

use Illuminate\Support\Facades\Http;

it('returns a generic 502 without exception text or upstream address', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::failedConnection('private upstream URL and credentials')]);
    $this->getJson('/api/search?q=phone')->assertStatus(502)
        ->assertExactJson(['error' => 'Upstream Service Unavailable']);
});
