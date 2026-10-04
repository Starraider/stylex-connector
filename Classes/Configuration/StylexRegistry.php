<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Configuration;

/**
 * Registry for StyleX manifest files.
 *
 * Sitepackages call StylexRegistry::registerManifest() in their ext_localconf.php
 * to tell the connector where to find the generated stylex-manifest.json.
 *
 * Multiple sitepackages can be registered; their manifests are merged.
 * Later-registered manifests take precedence for conflicting style keys.
 *
 * Usage in sitepackage ext_localconf.php:
 *
 *   \Vendor\StylexConnector\Configuration\StylexRegistry::registerManifest(
 *       'my_sitepackage',
 *       'EXT:my_sitepackage/Resources/Public/StylexManifest/stylex-manifest.json'
 *   );
 */
final class StylexRegistry
{
    /** @var array<string, string> extensionKey => absolute or EXT: manifest path */
    private static array $manifests = [];

    private static array $overrides = [];

    /**
     * Register a StyleX manifest file for a sitepackage.
     *
     * @param string $extensionKey  The registering extension's key (e.g. 'my_sitepackage')
     * @param string $manifestPath  EXT: path or absolute path to the manifest JSON file
     * @param bool $allowOverrides Explicitly permit this owner to replace existing style keys.
     */
    public static function registerManifest(
        string $extensionKey,
        string $manifestPath,
        bool $allowOverrides = false
    ): void {
        if (trim($extensionKey) === '' || trim($manifestPath) === '') {
            throw new \InvalidArgumentException('Manifest owner and path must not be empty.', 1791060002);
        }
        self::$manifests[$extensionKey] = $manifestPath;
        self::$overrides[$extensionKey] = $allowOverrides;
    }

    /**
     * Returns all registered manifests as [extensionKey => path].
     *
     * @return array<string, string>
     */
    public static function getRegisteredManifests(): array
    {
        return self::$manifests;
    }

    public static function allowsOverrides(string $owner): bool
    {
        return self::$overrides[$owner] ?? false;
    }

    /**
     * Returns true if at least one manifest has been registered.
     */
    public static function hasManifests(): bool
    {
        return !empty(self::$manifests);
    }

    /**
     * Removes a registered manifest (useful in tests).
     */
    public static function unregisterManifest(string $extensionKey): void
    {
        unset(self::$manifests[$extensionKey], self::$overrides[$extensionKey]);
    }

    /**
     * Resets all registrations (useful in tests).
     */
    public static function reset(): void
    {
        self::$manifests = [];
        self::$overrides = [];
    }
}
