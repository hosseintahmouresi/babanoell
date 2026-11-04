<?php
declare(strict_types=1); namespace Babanoell\Panel\Platform;
final class CoreCompatibility { public const RELEASE='1.9.0'; public const IDENTITY_MIN='1.9.0'; public const LOANS_MIN='1.9.0'; public const DOCUMENTS_MIN='1.9.0'; public static function services():array{return['identity'=>self::IDENTITY_MIN,'loans'=>self::LOANS_MIN,'documents'=>self::DOCUMENTS_MIN];} }
