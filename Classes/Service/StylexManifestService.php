<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Service;

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
 * Replicates stylex.props() behavior in PHP:
 * - Accepts multiple style keys
 * - Merges CSS properties left-to-right: later keys win on conflict
 * - Caches the merged manifest using TYPO3's caching framework
 */
final class StylexManifestService
{
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
        private readonly CacheManager $cacheManager
    ) {
        try {
            $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('stylex_connector');
        } catch (\Throwable) {
            $this->extConf = [];
        }
    }

    /**
     * Resolves one or more StyleX style keys to a space-separated class name string.
     *
     * Equivalent to calling stylex.props(styles.key1, styles.key2, ...) in JavaScript.
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
                if ($this->shouldWarnOnMissingKeys()) {
                    trigger_error(
                        sprintf(
                            '[StyleX Connector] Unknown style key "%s". Available keys: %s',
                            $key,
                            implode(', ', array_keys($this->styles))
                        ),
                        E_USER_WARNING
                    );
                }
                continue;
            }

            $styleDefinition = $this->styles[$key];
            if (!empty($styleDefinition['properties']) && is_array($styleDefinition['properties'])) {
                foreach ($styleDefinition['properties'] as $property => $className) {
                    if (is_string($property) && is_string($className)) {
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

        $allClasses = array_merge(array_values($resolvedProperties), array_values($fallbackClasses));
        return implode(' ', array_unique($allClasses));
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

        try {
            $cache = $this->getCacheInstance();
            $cache->flush();
        } catch (\Throwable) {
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

        $cacheKey = 'stylex_merged_manifest_v1';
        $lifetime = $this->getCacheLifetime();

        if ($lifetime > 0) {
            try {
                $cache = $this->getCacheInstance();
                if ($cache->has($cacheKey)) {
                    $cachedData = $cache->get($cacheKey);
                    if (is_array($cachedData)) {
                        $this->styles = $cachedData;
                        $this->loaded = true;
                        return;
                    }
                }
            } catch (\Throwable) {
                // Cache unavailable -- continue without cache
            }
        }

        $this->styles = $this->loadAndMergeManifests();

        if ($lifetime > 0) {
            try {
                $cache = $this->getCacheInstance();
                $cache->set($cacheKey, $this->styles, ['stylex'], $lifetime);
            } catch (\Throwable) {
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
        $manifestPaths = $this->collectManifestPaths();

        if (empty($manifestPaths)) {
            if (Environment::getContext()->isDevelopment()) {
                trigger_error(
                    '[StyleX Connector] No manifests registered. '
                    . 'Call StylexRegistry::registerManifest() in your sitepackage ext_localconf.php.',
                    E_USER_NOTICE
                );
            }
            return [];
        }

        foreach ($manifestPaths as $extKey => $rawPath) {
            $absolutePath = $this->resolveManifestPath($rawPath);

            if ($absolutePath === null || !file_exists($absolutePath)) {
                if (Environment::getContext()->isDevelopment()) {
                    trigger_error(
                        sprintf(
                            '[StyleX Connector] Manifest for extension "%s" not found at: %s. '
                            . 'Run "npm run build" in your sitepackage.',
                            $extKey,
                            $rawPath
                        ),
                        E_USER_WARNING
                    );
                }
                continue;
            }

            $json = file_get_contents($absolutePath);
            if ($json === false) {
                continue;
            }

            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['styles']) || !is_array($data['styles'])) {
                trigger_error(
                    sprintf(
                        '[StyleX Connector] Manifest JSON invalid for extension "%s": %s',
                        $extKey,
                        json_last_error_msg()
                    ),
                    E_USER_WARNING
                );
                continue;
            }

            // Later-registered manifests win for conflicting keys
            foreach ($data['styles'] as $styleKey => $definition) {
                if (is_string($styleKey) && is_array($definition)) {
                    $merged[$styleKey] = $definition;
                }
            }
        }

        return $merged;
    }

    /**
     * Collects all manifest paths from:
     * 1. StylexRegistry (PHP registration -- highest priority)
     * 2. Extension Manager configuration (fallback)
     *
     * @return array<string, string>
     */
    private function collectManifestPaths(): array
    {
        $paths = StylexRegistry::getRegisteredManifests();

        // Add fallback from Extension Manager configuration
        $extConfPath = trim((string)($this->extConf['manifestPath'] ?? ''));
        if ($extConfPath !== '' && !in_array($extConfPath, $paths, true)) {
            $paths['_extconf'] = $extConfPath;
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
