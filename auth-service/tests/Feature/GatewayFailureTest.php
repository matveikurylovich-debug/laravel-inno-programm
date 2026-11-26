<?php

declare(strict_types=1);

it('does not proxy search through auth', function (): void {
    $this->getJson('/api/v1/search?q=phone')->assertNotFound();
});
