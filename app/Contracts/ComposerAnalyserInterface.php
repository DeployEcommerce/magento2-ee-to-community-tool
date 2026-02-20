<?php

namespace App\Contracts;

use App\ValueObjects\ComposerAnalysis;

interface ComposerAnalyserInterface
{
    public function analyse(string $composerJsonPath): ComposerAnalysis;

    public function isEnterpriseEdition(ComposerAnalysis $analysis): bool;

    public function detectConflicts(ComposerAnalysis $analysis): array;

    public function getPackagesToRemove(ComposerAnalysis $analysis): array;

    public function getPackagesToAdd(ComposerAnalysis $analysis): array;
}
