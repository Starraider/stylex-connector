<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\StylexConnector\Configuration\StylexRegistry;

/**
 * Loads one or more StyleX manifest JSON files and resolves style keys
 * to atomic CSS class name strings.
 *
 * Supports static compiled conflict maps and complete class recipes:
 * - Accepts multiple style keys
 * - Merges CSS properties left-to-right: later keys win on conflict
 * - Caches the merged manifest using TYPO3's caching framework
 */
final class StylexManifestService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private array $diagnostics = [];
    private array $registrations = [];
    private bool $complete = true;
    private ?\Throwable $configurationError = null;
    private array $missingKeys = [];
    /**
     * Merged style registry from all registered manifests.
     * Format: { "Nav.link": { "className": "x5mn x6pq", "properties": { "color": "x5mn", "borderBottom": "x6pq" } } }
     *
     * @var array<string, array<string, mixed>>
     */
    private array $styles = [];

    private bool $loaded = false;

    private ?FrontendInterface $cache = null;

    private readonly array $extConf;

    public function __construct(
        private readonly CacheManager $cacheManager,
        ?ExtensionConfiguration $configuration = null
    ) {
        try {
            $this->extConf = ($configuration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class))
                ->get('stylex_connector');
        } catch (\Throwable $exception) {
            $this->configurationError = $exception;
            $this->extConf = [];
        }
    }

    /**
     * Resolves one or more StyleX style keys to a space-separated class name string.
     *
     * Composes supported static conflict maps. Recipes concatenate without conflict resolution.
     * Later keys override earlier keys for conflicting CSS properties.
     *
     * @param string ...$styleKeys e.g. 'Nav.link', 'Nav.linkActive' or comma-separated 'Nav.link, Nav.linkActive'
     * @return string e.g. 'x5mn x6pq x8uv'
     */
    public function getClasses(string ...$styleKeys): string
    {
        $this->ensureLoaded();

        // Flatten any comma-separated entries in $styleKeys
        $flatKeys = [];
        foreach ($styleKeys as $key) {
            if (str_contains($key, ',')) {
                foreach (explode(',', $key) as $subKey) {
                    $subKey = trim($subKey);
                    if ($subKey !== '') {
                        $flatKeys[] = $subKey;
                    }
                }
            } else {
                $key = trim($key);
                if ($key !== '') {
                    $flatKeys[] = $key;
                }
            }
        }

        // property -> winning class name (last key wins on conflict)
        $resolvedProperties = [];
        $fallbackClasses = [];

        foreach ($flatKeys as $key) {
            if (!isset($this->styles[$key])) {
                if (!isset($this->missingKeys[$key]) && count($this->missingKeys) < 100) {
                    $this->missingKeys[$key] = true;
                    $this->logger?->warning('Unknown StyleX key.', ['key' => $key]);
                }
                if ($this->shouldWarnOnMissingKeys()) {
                    trigger_error(
                        sprintf(
                            '[StyleX Connector] Unknown style key "%s". Available keys: %s',
                            $key,
                            implode(', ', array_slice(array_keys($this->styles), 0, 12))
                        ),
                        E_USER_WARNING
                    );
                }
                continue;
            }

            $styleDefinition = $this->styles[$key];
            if ($styleDefinition['kind'] === 'compiled') {
                foreach ($styleDefinition['properties'] as $property => $className) {
                    if ($className === null) {
                        unset($resolvedProperties[$property]);
                    } else {
                        $resolvedProperties[$property] = $className;
                    }
                }
            } elseif (isset($styleDefinition['className']) && is_string($styleDefinition['className'])) {
                // Fallback if no granular property mapping is present
                foreach (explode(' ', trim($styleDefinition['className'])) as $cls) {
                    $cls = trim($cls);
                    if ($cls !== '') {
                        $fallbackClasses[$cls] = $cls;
                    }
                }
            }
        }

        $classList = array_merge(array_values($resolvedProperties), array_values($fallbackClasses));
        $allClasses = preg_split('/\s+/', trim(implode(' ', $classList)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_unique($allClasses));
    }

    public function hasStyle(string $key): bool
    {
        $this->ensureLoaded();
        return isset($this->styles[$key]);
    }

    /** Ordered registrations and bounded, owner-aware load diagnostics for CLI tooling. */
    public function getReport(): array
    {
        $this->ensureLoaded();
        return [
            'registrations' => $this->registrations,
            'diagnostics' => $this->diagnostics,
            'count' => count($this->styles),
        ];
    }

    /**
     * Returns all available style keys from all registered manifests.
     * Useful for debugging and IDE tooling generation.
     *
     * @return string[]
     */
    public function getAvailableKeys(): array
    {
        $this->ensureLoaded();
        return array_keys($this->styles);
    }

    /**
     * Returns the number of loaded style entries across all manifests.
     */
    public function getStyleCount(): int
    {
        $this->ensureLoaded();
        return count($this->styles);
    }

    /**
     * Clears the in-memory cache, forcing a re-read on next access.
     * Also clears the TYPO3 persistent cache for this request.
     */
    public function clearCache(): void
    {
        $this->loaded = false;
        $this->styles = [];
        $this->diagnostics = [];
        $this->registrations = [];

        try {
            $cache = $this->getCacheInstance();
            $cache->flush();
        } catch (\Throwable $exception) {
            $this->logger?->warning('StyleX cache flush failed.', ['exception' => $exception]);
            // Cache not available
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        if ($this->configurationError !== null) {
            $this->logger?->notice('StyleX extension configuration unavailable; using defaults.', [
                'exception' => $this->configurationError,
            ]);
        }
        $identity = [];
        foreach ($this->collectManifestPaths() as $owner => $rawPath) {
            $path = $this->resolveManifestPath($rawPath);
            $path = $path !== null ? (realpath($path) ?: $path) : $rawPath;
            $identity[] = [
                $owner, StylexRegistry::allowsOverrides($owner), $path,
                is_file($path) && is_readable($path) ? hash_file('sha256', $path) : null,
            ];
        }
        // Content hashing catches atomic replacement even when size and mtime are preserved.
        $cacheKey = 'stylex_v2_' . hash('sha256', serialize($identity));
        $lifetime = $this->getCacheLifetime();

        if ($lifetime > 0) {
            try {
                $cache = $this->getCacheInstance();
                if ($cache->has($cacheKey)) {
                    $cachedData = $cache->get($cacheKey);
                    if (
                        is_array($cachedData)
                        && isset($cachedData['styles'], $cachedData['diagnostics'], $cachedData['registrations'])
                    ) {
                        $this->styles = $cachedData['styles'];
                        $this->diagnostics = $cachedData['diagnostics'];
                        $this->registrations = $cachedData['registrations'];
                        $this->loaded = true;
                        return;
                    }
                }
            } catch (\Throwable $exception) {
                $this->logger?->warning('StyleX persistent cache unavailable.', ['exception' => $exception]);
                // Cache unavailable -- continue without cache
            }
        }

        $this->styles = $this->loadAndMergeManifests();

        if ($lifetime > 0 && $this->complete) {
            try {
                $cache = $this->getCacheInstance();
                $snapshot = [
                    'styles' => $this->styles,
                    'diagnostics' => $this->diagnostics,
                    'registrations' => $this->registrations,
                ];
                $cache->set($cacheKey, $snapshot, ['stylex'], $lifetime);
            } catch (\Throwable $exception) {
                $this->logger?->warning('StyleX persistent cache unavailable.', ['exception' => $exception]);
                // Cache unavailable -- styles loaded but not persisted
            }
        }

        $this->loaded = true;
    }

    /**
     * Loads all registered manifests and merges their style entries.
     * Later-registered manifests override earlier ones for the same key.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadAndMergeManifests(): array
    {
        $merged = [];
        $owners = [];
        $this->complete = true;
        $this->diagnostics = [];
        $this->registrations = [];
        $reader = new ManifestReader();
        foreach ($this->collectManifestPaths() as $owner => $rawPath) {
            $path = $this->resolveManifestPath($rawPath);
            $json = $path !== null && is_file($path) && is_readable($path) ? file_get_contents($path) : false;
            if ($json === false) {
                $this->diagnose('unreadable', $owner, $path ?? $rawPath, 'Manifest is missing or unreadable.');
                continue;
            }
            $result = $reader->read($json);
            foreach ($result['errors'] as $error) {
                $this->diagnose('invalid', $owner, $path, $error);
            }
            $this->registrations[] = [
                'owner' => $owner, 'path' => $path,
                'count' => count($result['styles']), 'metadata' => $result['metadata'],
            ];
            foreach ($result['styles'] as $key => $definition) {
                if (isset($owners[$key]) && !StylexRegistry::allowsOverrides($owner)) {
                    $message = sprintf('Key "%s" overrides owner "%s".', $key, $owners[$key]);
                    $this->diagnose('collision', $owner, $path, $message);
                }
                $merged[$key] = $definition;
                $owners[$key] = $owner;
            }
        }
        return $merged;
    }

    private function diagnose(string $category, string $owner, string $path, string $message): void
    {
        $this->complete = false;
        $diagnostic = ['category' => $category, 'owner' => $owner, 'path' => $path, 'message' => $message];
        if (count($this->diagnostics) < 100) {
            $this->diagnostics[] = $diagnostic;
            $this->logger?->warning('StyleX manifest: ' . $message, $diagnostic);
        }
    }

    /**
     * Collects all manifest paths from:
     * 1. StylexRegistry in registration order
     * 2. Extension Manager path last by default, preserving v1 precedence
     *
     * @return array<string, string>
     */
    private function collectManifestPaths(): array
    {
        $paths = StylexRegistry::getRegisteredManifests();

        // Add fallback from Extension Manager configuration
        $extConfPath = trim((string)($this->extConf['manifestPath'] ?? ''));
        if ($extConfPath !== '' && !in_array($extConfPath, $paths, true)) {
            if (($this->extConf['manifestPathMode'] ?? 'legacyOverride') === 'fallback') {
                $paths = ['_extconf' => $extConfPath] + $paths;
            } else {
                $paths['_extconf'] = $extConfPath;
            }
        }

        return $paths;
    }

    /**
     * Resolves an EXT: path or absolute path to an absolute filesystem path.
     */
    private function resolveManifestPath(string $path): ?string
    {
        if (str_starts_with($path, 'EXT:')) {
            $resolved = GeneralUtility::getFileAbsFileName($path);
            return $resolved !== '' ? $resolved : null;
        }
        return $path;
    }

    private function getCacheInstance(): FrontendInterface
    {
        if ($this->cache === null) {
            $this->cache = $this->cacheManager->getCache('stylex_manifest');
        }
        return $this->cache;
    }

    private function getCacheLifetime(): int
    {
        if (Environment::getContext()->isDevelopment()) {
            return 0; // No caching in development
        }
        return (int)($this->extConf['manifestCacheLifetime'] ?? 86400);
    }

    private function shouldWarnOnMissingKeys(): bool
    {
        if (!Environment::getContext()->isDevelopment()) {
            return false;
        }
        return (bool)($this->extConf['warnOnMissingKeys'] ?? true);
    }
}
