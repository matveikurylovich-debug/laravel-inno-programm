<?php

it('does not proxy search through auth', function () {
    $this->getJson('/api/search?q=phone')->assertNotFound();
});
