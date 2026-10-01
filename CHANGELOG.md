# Changelog

## 1.0.0

Initial release of StyleX Connector for TYPO3 12.4, 13.4, and 14.3.

- Resolve StyleX manifest keys in Fluid with the `stylex:class` ViewHelper.
- Merge manifests registered by sitepackages and cache their style mappings.
- Provide optional TypoScript for CSS delivery and layer ordering.

There is no upgrade migration for this first release. Deploy the generated StyleX manifest and CSS together, then flush TYPO3 caches after a build update.
