<?php

namespace App\Contracts;

interface MagentoPathResolverInterface
{
    public function resolve(?string $pathOption): string;
}
