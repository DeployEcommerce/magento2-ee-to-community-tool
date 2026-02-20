<?php

namespace App\ValueObjects;

final readonly class ComposerAnalysis
{
    public function __construct(
        public array $data,
        public string $eeVersion,
        public bool $hasEnterpriseEdition,
        public array $replaceKeys,
    ) {}
}
