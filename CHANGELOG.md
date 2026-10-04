# Changelog

## Unreleased

- Preserve compiled null-clearing entries and distinguish static composition from complete recipes.
- Validate legacy/v2 manifests, expose style existence and owner-aware reports, and add strict `stylex:validate` with required-key and paired-CSS checks.
- Identify persistent cache entries by ordered registrations and content hashes; avoid caching incomplete loads.
- Retain legacy Extension Manager precedence with an explicit lower-precedence fallback mode and acknowledge intentional overrides.
- Ship an optional maintained static Vite adapter with stable module/object keys, legacy aliases, deletion cleanup, deterministic output and atomic manifest writes.
- Wire public Site Set settings into CSS conditions, correct ordering controls, retire late layer output, align defaults and apply compression options only to TYPO3 12/13.
- Harden Fluid inputs and private getter handling; support Composer-only Skom aliases without changing the canonical Vendor namespace.
- Add actual compiler parity, Fluid rendering, validation and producer lifecycle fixtures.


## 1.0.0

Initial release of StyleX Connector for TYPO3 12.4, 13.4, and 14.3.

- Resolve StyleX manifest keys in Fluid with the `stylex:class` ViewHelper.
- Merge manifests registered by sitepackages and cache their style mappings.
- Provide optional TypoScript for CSS delivery and layer ordering.

There is no upgrade migration for this first release. Deploy the generated StyleX manifest and CSS together, then flush TYPO3 caches after a build update.
