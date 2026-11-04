<?php
declare(strict_types=1);

namespace Babanoell\Auth\Update;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;

/**
 * Extraction scanner used by the 1.2.2 updater repair.
 *
 * This class intentionally has a new filename/class name so hosts with stale
 * OPcache entries for UpdateTool.php cannot keep executing the pre-1.2.1
 * off-by-one path scanner. It derives relative paths exclusively from
 * canonical real paths and silently skips protected installer/storage files.
 */
final class ExtractedPackageCollectorV122
{
    /**
     * @return list<array{target:string,source:string}>
     */
    public static function collect(string $packageRoot): array
    {
        if (!is_dir($packageRoot)) {
            throw new RuntimeException('the identity folder is missing inside the extraction');
        }

        $realRoot = realpath($packageRoot);
        if ($realRoot === false) {
            throw new RuntimeException('the extraction directory is not accessible');
        }

        $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $rootPrefix = $realRoot . DIRECTORY_SEPARATOR;
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('symlinks are not allowed inside the archive');
            }

            if (!$item->isFile()) {
                continue;
            }

            $realFile = realpath($item->getPathname());
            if ($realFile === false || !str_starts_with($realFile, $rootPrefix)) {
                throw new RuntimeException('an extracted file escapes the extraction directory');
            }

            $relative = substr($realFile, strlen($rootPrefix));
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            $target = UpdateTool::entryTarget(UpdateTool::PACKAGE_PREFIX . $relative);
            if ($target === null) {
                // Official full releases may contain these files, but the updater
                // must preserve the already-installed copies.
                if (UpdateTool::isProtectedTarget($relative)) {
                    continue;
                }

                throw new RuntimeException('an extracted file is not part of the identity package: ' . $relative);
            }

            $files[] = [
                'target' => $target,
                'source' => $realFile,
            ];
        }

        if ($files === []) {
            throw new RuntimeException('the extraction directory is empty');
        }

        return $files;
    }
}
