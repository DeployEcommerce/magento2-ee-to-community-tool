<?php

namespace App\Services;

use App\Contracts\MagentoPathResolverInterface;

class MagentoPathResolver implements MagentoPathResolverInterface
{
    public function resolve(?string $pathOption): string
    {
        $path = $pathOption ?? getcwd();
        $path = rtrim((string) $path, '/');

        $envPhp = $path . '/app/etc/env.php';

        if (!file_exists($envPhp)) {
            throw new \RuntimeException(
                "Not a valid Magento root: app/etc/env.php not found at [{$path}]"
            );
        }

        return $path;
    }
}
