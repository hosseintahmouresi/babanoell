<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
final class ValidatedUpload
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $sizeBytes,
        public readonly string $sha256,
    ){}
}
