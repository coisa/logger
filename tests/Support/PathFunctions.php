<?php

declare(strict_types=1);

namespace CoiSA\Logger\Handler;

/**
 * Resolves synthetic directories through independently cached ancestor aliases.
 *
 * @param string $path The synthetic parent path.
 * @return string|false The resolved directory, or a missing parent.
 */
function realpath(string $path): string|false
{
    $filesystem = &$GLOBALS['coisa_file_operations_paths'];
    if (isset($filesystem['cache'][$path])) {
        return $filesystem['cache'][$path];
    }
    $resolved = $path;
    foreach ($filesystem['aliases'] as $alias => $target) {
        if ($path === $alias || str_starts_with($path, $alias . '/')) {
            $resolved = ($filesystem['cache'][$alias] ??= $target) . substr($path, strlen($alias));
            break;
        }
    }
    if (!isset($filesystem['directories'][$resolved])) {
        return false;
    }

    return $filesystem['cache'][$path] = $filesystem['directories'][$resolved];
}

/**
 * Invalidates the synthetic realpath cache without inspecting the host filesystem.
 *
 * @param bool $clear_realpath_cache Whether to clear cached resolutions.
 * @param string $filename The optional exact cache key to invalidate.
 * @return void
 */
function clearstatcache(bool $clear_realpath_cache = false, string $filename = ''): void
{
    if (!$clear_realpath_cache) {
        return;
    }
    if (func_num_args() < 2) {
        $GLOBALS['coisa_file_operations_paths']['cache'] = [];
    } else {
        unset($GLOBALS['coisa_file_operations_paths']['cache'][$filename]);
    }
}
