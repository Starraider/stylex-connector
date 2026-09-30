# StyleX Connector for TYPO3

StyleX Connector makes classes compiled by [StyleX](https://stylexjs.com/) available in TYPO3 Fluid templates. A sitepackage registers a JSON manifest produced by its frontend build. The `{stylex:class(...)}` ViewHelper resolves semantic keys from that manifest into atomic class names.

The extension supports TYPO3 12.4, 13.4, and 14.x with PHP 8.1 through 8.4. It does not compile StyleX source files or serve the generated CSS.

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

Install the extension in the TYPO3 project:

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

Follow the [installation guide](Documentation/Installation/Index.rst) for the required build and asset setup around this example.

## Privacy and Data Handling

The StyleX Connector extension operates entirely at template render time to resolve style class names from local JSON manifests. It does not collect, track, persist, or transmit any personal user data.

## Issues and Support

Please report issues, ask questions, or contribute on GitHub:
https://github.com/Starraider/stylex-connector/issues

## License

This project is licensed under the [GNU General Public License v2.0 or later](LICENSE).
