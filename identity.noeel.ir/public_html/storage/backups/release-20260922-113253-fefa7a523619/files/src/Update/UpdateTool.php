<?php
declare(strict_types=1);

namespace Babanoell\Auth\Update;

use RuntimeException;
use ZipArchive;

/**
 * Filesystem-safe update engine for the identity service.
 *
 * Workflow (used by public/update.php):
 *   1. validate the release ZIP (entry names, count, uncompressed size),
 *   2. extract into a private work directory inside storage/,
 *   3. back up every file that will be replaced,
 *   4. copy the new files into the service root,
 *   5. run pending migrations,
 *   6. on any failure restore the previous files from the backup.
 *
 * Rules enforced here (defence in depth; the web front controller also
 * enforces the same rules before doing anything):
 *   - entries must live under the identity/ prefix of the release package,
 *   - no absolute paths, no "..", no backslashes, no drive letters,
 *   - no hidden files except .htaccess, .env.example, .gitkeep,
 *   - protected service files are never overwritten (config/local.env.php,
 *     storage/, public/install.php, public/install.php.locked),
 *   - at most 5000 entries and 50 MiB uncompressed in total,
 *   - the package must contain the identity front controller and at least
 *     one migration file.
 */
final class UpdateTool
{
    public const PACKAGE_PREFIX = 'identity/';

    public const MAX_ENTRIES = 5000;

    public const MAX_TOTAL_UNCOMPRESSED_BYTES = 52428800;

    public const MAX_SINGLE_FILE_BYTES = 20971520;

    public const MIN_ENTRY_COUNT = 2;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'php', 'htaccess', 'md', 'txt', 'json', 'yml', 'yaml', 'xml',
        'html', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif',
        'ico', 'gitkeep', 'example',
    ];

    /** @var list<string> */
    private const ALLOWED_HIDDEN_BASENAMES = ['.htaccess', '.env.example', '.gitkeep', '.gitignore'];

    /**
     * Maps a raw ZIP entry name to a service-relative target path, or null
     * when the entry is not part of the release package and must be rejected.
     */
    public static function entryTarget(string $name): ?string
    {
        if ($name === '' || str_contains($name, "\0")) {
            return null;
        }

        // Directory entries are legal but not copied as files.
        if (str_ends_with($name, '/')) {
            return null;
        }

        if (!str_starts_with($name, self::PACKAGE_PREFIX)) {
            return null;
        }

        $relative = substr($name, strlen(self::PACKAGE_PREFIX));

        if ($relative === '') {
            return null;
        }

        // Normalize separators; backslashes are never allowed.
        if (str_contains($relative, '\\') || str_contains($relative, '//')) {
            return null;
        }

        // Absolute paths and Windows drive letters.
        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative) === 1) {
            return null;
        }

        $segments = explode('/', $relative);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            if (str_starts_with($segment, '.') && !in_array($segment, self::ALLOWED_HIDDEN_BASENAMES, true)) {
                return null;
            }
        }

        $basename = (string) end($segments);

        if (!str_starts_with($basename, '.')) {
            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                return null;
            }
        }

        if (self::isProtectedTarget($relative)) {
            return null;
        }

        return $relative;
    }

    /**
     * True when a service-relative target must never be replaced by the updater.
     */
    public static function isProtectedTarget(string $target): bool
    {
        if (
            $target === 'config/local.env.php'
            || $target === 'public/install.php'
            || $target === 'public/install.php.locked'
        ) {
            return true;
        }

        if ($target === 'storage' || str_starts_with($target, 'storage/')) {
            return true;
        }

        return false;
    }

    /**
     * Builds the update plan from raw ZIP entries.
     *
     * @param list<array{name:string,size:int}> $entries raw entries of the archive
     * @return array{
     *   files:list<array{zip:string,target:string,size:int}>,
     *   total_size:int,
     *   entry_count:int,
     *   has_front_controller:bool,
     *   migration_count:int,
     *   protected_entries:list<string>
     * }
     */
    public static function buildPlan(array $entries): array
    {
        if (count($entries) < self::MIN_ENTRY_COUNT) {
            throw new RuntimeException('the archive contains too few entries to be an identity package');
        }

        if (count($entries) > self::MAX_ENTRIES) {
            throw new RuntimeException('the archive contains more than ' . self::MAX_ENTRIES . ' entries');
        }

        $files = [];
        $seen = [];
        $totalSize = 0;
        $hasFrontController = false;
        $migrationCount = 0;
        $protectedEntries = [];

        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');
            $size = max(0, (int) ($entry['size'] ?? 0));

            if (str_ends_with($name, '/')) {
                continue;
            }

            $canonical = strtolower($name);
            if (isset($seen[$canonical])) {
                throw new RuntimeException('duplicate archive entry: ' . $name);
            }
            $seen[$canonical] = true;
            $target = self::entryTarget($name);

            if ($target === null) {
                if (self::isProtectedEntryInPackage($name)) {
                    $protectedEntries[] = $name;
                    continue;
                }

                throw new RuntimeException('unsafe archive entry: ' . $name);
            }

            if ($size > self::MAX_SINGLE_FILE_BYTES) {
                throw new RuntimeException('archive entry is too large: ' . $name);
            }

            $totalSize += $size;

            if ($totalSize > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('the archive exceeds ' . (int) (self::MAX_TOTAL_UNCOMPRESSED_BYTES / 1048576) . ' MiB uncompressed');
            }

            if ($target === 'public/index.php') {
                $hasFrontController = true;
            }

            if (preg_match('#^database/migrations/[^/]+\.php$#', $target) === 1) {
                $migrationCount++;
            }

            $files[] = [
                'zip' => $name,
                'target' => $target,
                'size' => $size,
            ];
        }

        if ($files === []) {
            throw new RuntimeException('the archive contains no installable identity files');
        }

        if (!$hasFrontController) {
            throw new RuntimeException('the archive is missing public/index.php');
        }

        if ($migrationCount === 0) {
            throw new RuntimeException('the archive contains no migration files');
        }

        return [
            'files' => $files,
            'total_size' => $totalSize,
            'entry_count' => count($entries),
            'has_front_controller' => $hasFrontController,
            'migration_count' => $migrationCount,
            'protected_entries' => $protectedEntries,
        ];
    }

    /**
     * Reads a ZIP archive from disk and returns the update plan.
     *
     * @return array{
     *   files:list<array{zip:string,target:string,size:int}>,
     *   total_size:int,
     *   entry_count:int,
     *   has_front_controller:bool,
     *   migration_count:int,
     *   protected_entries:list<string>
     * }
     */
    public static function readArchive(string $zipPath): array
    {
        $archive = new ZipArchive();
        $result = $archive->open($zipPath);

        if ($result !== true) {
            throw new RuntimeException('unable to open the ZIP archive (code ' . $result . ')');
        }

        try {
            $entries = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);

                if ($stat === false) {
                    throw new RuntimeException('unable to read the ZIP entry at index ' . $index);
                }

                $opsys = 0;
                $attributes = 0;
                if ($archive->getExternalAttributesIndex($index, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('symlink in archive');
                }
                $entries[] = [
                    'name' => (string) ($stat['name'] ?? ''),
                    'size' => (int) ($stat['size'] ?? 0),
                ];
            }

            return self::buildPlan($entries);
        } finally {
            $archive->close();
        }
    }

    /**
     * Scans the package root inside the extraction directory and returns the
     * files to copy, re-validating every path after extraction (defence in
     * depth). The package root must be the identity/ folder of the release.
     *
     * @return list<array{target:string,source:string}>
     */
    public static function collectExtracted(string $packageRoot): array
    {
        if (!is_dir($packageRoot)) {
            throw new RuntimeException('the identity folder is missing inside the extraction');
        }

        $realRoot = realpath($packageRoot);

        if ($realRoot === false) {
            throw new RuntimeException('the extraction directory is not accessible');
        }

        // PACKAGE_PREFIX intentionally ends with '/'. Callers may therefore
        // pass a package root that also ends with a directory separator.
        // Always derive relative paths from the canonical real path so we
        // never drop the first character of a filename (e.g. .htaccess).
        $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $rootPrefix = $realRoot . DIRECTORY_SEPARATOR;

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realRoot, \FilesystemIterator::SKIP_DOTS)
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
            $entryName = self::PACKAGE_PREFIX . $relative;
            $target = self::entryTarget($entryName);

            if ($target === null) {
                // Protected files are allowed to exist in official full release
                // packages, but they are deliberately never copied by updates.
                // The archive planning stage applies the same rule; mirror it
                // after extraction instead of rejecting the whole release.
                if (self::isProtectedEntryInPackage($entryName)) {
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

    /**
     * Creates a timestamped backup of the given service-relative targets,
     * keeps only the 3 most recent backups, and returns the backup directory.
     *
     * @param list<string> $targets
     */
    public static function backupFiles(string $storageDir, string $serviceRoot, array $targets): string
    {
        $backupRoot = rtrim($storageDir, '/') . '/backups';
        $stamp = gmdate('Ymd-His');
        $backupDir = $backupRoot . '/update-' . $stamp;

        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new RuntimeException('unable to create the backup directory');
        }

        $manifest = [
            'created_at' => gmdate('c'),
            'service' => 'identity',
            'files' => [],
        ];

        foreach ($targets as $target) {
            $source = $serviceRoot . '/' . $target;

            if (!is_file($source)) {
                continue;
            }

            $destination = $backupDir . '/' . $target;
            $destinationDirectory = dirname($destination);

            if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, true) && !is_dir($destinationDirectory)) {
                throw new RuntimeException('unable to create the backup sub-directory');
            }

            if (!copy($source, $destination)) {
                throw new RuntimeException('unable to back up ' . $target);
            }

            $manifest['files'][] = $target;
        }

        $manifest['count'] = count($manifest['files']);

        if (file_put_contents($backupDir . '/manifest.json', json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ), LOCK_EX) === false) {
            throw new RuntimeException('unable to write the backup manifest');
        }

        self::pruneBackups($backupRoot, 3);

        return $backupDir;
    }

    /**
     * Copies validated files into the service root.
     *
     * @param list<array{target:string,source:string}> $files
     */
    public static function copyFiles(array $files, string $serviceRoot): int
    {
        $copied = 0;

        foreach ($files as $file) {
            $target = (string) ($file['target'] ?? '');
            $source = (string) ($file['source'] ?? '');

            if ($target === '' || !is_file($source)) {
                throw new RuntimeException('a planned file is missing from the extraction');
            }

            $destination = $serviceRoot . '/' . $target;
            $destinationDirectory = dirname($destination);

            if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, true) && !is_dir($destinationDirectory)) {
                throw new RuntimeException('unable to create directory for ' . $target);
            }

            if (!@copy($source, $destination)) {
                throw new RuntimeException('unable to write ' . $target);
            }

            @chmod($destination, 0644);
            $copied++;
        }

        return $copied;
    }

    /**
     * Restores files from a backup directory back into the service root.
     */
    public static function restoreFiles(string $backupDir, string $serviceRoot): int
    {
        $restored = 0;

        if (!is_dir($backupDir)) {
            throw new RuntimeException('the backup directory is missing');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isFile() || $item->getFilename() === 'manifest.json') {
                continue;
            }

            $relative = substr($item->getPathname(), strlen($backupDir) + 1);
            $target = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $destination = $serviceRoot . '/' . $target;
            $destinationDirectory = dirname($destination);

            if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, true) && !is_dir($destinationDirectory)) {
                throw new RuntimeException('unable to create directory while restoring ' . $target);
            }

            if (!@copy($item->getPathname(), $destination)) {
                throw new RuntimeException('unable to restore ' . $target);
            }

            $restored++;
        }

        return $restored;
    }

    /**
     * Recursively removes a directory (or a single file) best-effort.
     */
    public static function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    /**
     * Appends a line to the private update log (caps the log size, no secrets).
     */
    public static function appendLog(string $storageDir, string $line): void
    {
        $logFile = rtrim($storageDir, '/') . '/update.log';

        $existing = is_file($logFile) ? (string) file_get_contents($logFile) : '';
        $existing .= '[' . gmdate('Y-m-d H:i:s') . 'Z] ' . $line . PHP_EOL;

        if (strlen($existing) > 262144) {
            $existing = substr($existing, -262144);
        }

        @file_put_contents($logFile, $existing, LOCK_EX);
    }

    /**
     * Ensures storage/ stays private and a folder index reply exists.
     */
    public static function ensureStorageProtection(string $storageDir): void
    {
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            return;
        }

        @file_put_contents($storageDir . '/.htaccess', "Require all denied\n");
        @file_put_contents($storageDir . '/index.html', '');
    }

    private static function isProtectedEntryInPackage(string $name): bool
    {
        foreach (['config/local.env.php', 'storage', 'public/install.php', 'public/install.php.locked'] as $protected) {
            $full = self::PACKAGE_PREFIX . $protected;

            if ($name === $full || str_starts_with($name, $full . '/')) {
                return true;
            }
        }

        if ($name === self::PACKAGE_PREFIX . '.git' || str_starts_with($name, self::PACKAGE_PREFIX . '.git/')) {
            return true;
        }

        return false;
    }

    private static function pruneBackups(string $backupRoot, int $keep): void
    {
        $directories = glob($backupRoot . '/update-*') ?: [];
        rsort($directories, SORT_STRING);

        foreach (array_slice($directories, $keep) as $old) {
            self::removePath($old);
        }
    }
}
