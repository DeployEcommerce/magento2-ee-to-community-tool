<?php

namespace App\ValueObjects;

final readonly class RowIdScanResult
{
    public function __construct(
        public string $filePath,
        public int $lineNumber,
        public string $lineContent,
        public string $extensionName,
    ) {}

    public function toArray(): array
    {
        return [
            'filePath' => $this->filePath,
            'lineNumber' => $this->lineNumber,
            'lineContent' => $this->lineContent,
            'extensionName' => $this->extensionName,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            filePath: $data['filePath'],
            lineNumber: $data['lineNumber'],
            lineContent: $data['lineContent'],
            extensionName: $data['extensionName'],
        );
    }
}
