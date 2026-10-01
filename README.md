# StyleX Connector for TYPO3

StyleX Connector makes classes compiled by [StyleX](https://stylexjs.com/) available in TYPO3 Fluid templates. A sitepackage registers a JSON manifest produced by its frontend build. The `{stylex:class(...)}` ViewHelper resolves semantic keys from that manifest into atomic class names.

The extension targets TYPO3 12.4, 13.4, and 14.3 or later in the 14.x line. PHP 8.1 is supported with TYPO3 12.4; TYPO3 13.4 and 14.3 require PHP 8.2 or later. The extension currently limits its PHP support to 8.4. It does not compile StyleX source files or serve the generated CSS.

TYPO3 12.4 is in ELTS. Production installations on that line need a current patched ELTS core release.

## Documentation

Start with the [installation guide](Documentation/Installation/Index.rst). It covers dependencies, the Vite build and both manifests, TYPO3 registration, asset delivery, and verification in order.

- [Vite and asset delivery](Documentation/Installation/Vite.rst)
- [DDEV development server](Documentation/Installation/Ddev.rst)
- [Using the ViewHelper](Documentation/Usage/Index.rst)
- [Content Blocks, Fluid Styled Content, and Bootstrap integrations](Documentation/Integrations/Index.rst)
- [Configuration reference](Documentation/Configuration/Index.rst)
- [Architecture](Documentation/Architecture/Index.rst)
- [Troubleshooting](Documentation/Troubleshooting/Index.rst)

## Minimal example

After the package is registered on Packagist, install the extension in the TYPO3 project:

```bash
composer require skom/stylex-connector
```

Register the build's StyleX manifest in the sitepackage's `ext_localconf.php`:

```php
\Vendor\StylexConnector\Configuration\StylexRegistry::registerManifest(
    'my_sitepackage',
    'EXT:my_sitepackage/Resources/Public/StylexManifest/stylex-manifest.json'
);
```

After the frontend build creates that file and the page includes its CSS, use a manifest key in Fluid:

```html
<button class="{stylex:class(styles: 'Button.root')}">Save</button>
```

Until then, add the [Git repository](https://github.com/Starraider/stylex-connector) as a Composer VCS repository and require `skom/stylex-connector:dev-main` in a test project. Follow the [installation guide](Documentation/Installation/Index.rst) for the required build and asset setup around this example.

## Upgrades

Version 1.0.0 is the first release. There is no earlier database schema or data migration. When updating a deployed build, deploy the StyleX manifest and CSS together and flush TYPO3 caches so the connector reads the new manifest. See the [changelog](CHANGELOG.md) for release notes.

## Privacy and data handling

StyleX Connector reads local JSON manifests and caches their style keys and CSS class names in TYPO3's cache. It does not collect, store, or transmit personal data. Flushing TYPO3 caches deletes the cached manifest data. No privacy-specific configuration or retention schedule is needed for the connector itself; the host site remains responsible for its own data handling.

## Issues and security

Report bugs and ask questions through [GitHub issues](https://github.com/Starraider/stylex-connector/issues). Send suspected security vulnerabilities privately to [sven@skom.de](mailto:sven@skom.de) instead of opening a public issue.

## License

This project is licensed under the [GNU General Public License v2.0 or later](LICENSE).
