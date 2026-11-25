<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

final class JwksClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $cacheFile,
        private readonly int $ttlSeconds = 300,
    ) {}

    public function json(): string
    {
        if ($this->cacheFresh()) {
            return (string) file_get_contents($this->cacheFile);
        }

        $body = $this->fetch();
        if ($body === null) {
            if (is_file($this->cacheFile)) {
                return (string) file_get_contents($this->cacheFile);
            }

            throw new TokenInfrastructureException('JWKS endpoint is unavailable.');
        }

        $directory = dirname($this->cacheFile);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new TokenInfrastructureException('JWKS cache directory cannot be created.');
        }
        file_put_contents($this->cacheFile, $body);

        return $body;
    }

    private function cacheFresh(): bool
    {
        return is_file($this->cacheFile) && (time() - (int) filemtime($this->cacheFile)) < $this->ttlSeconds;
    }

    private function fetch(): ?string
    {
        if ($this->url === '') {
            return null;
        }

        $handle = curl_init($this->url);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (! is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            return null;
        }

        return $body;
    }
}
