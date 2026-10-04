# Improvement validation

Verified locally on 4 October 2026. These results cover the working tree changes, not a published release.

| Check | Result |
| --- | --- |
| PHPUnit on TYPO3 12.4.45 / Fluid 2.15.0 / PHP 8.4.5 | 18 tests pass |
| PHPUnit on TYPO3 13.4.35 / Fluid 4.6.1 / PHP 8.4.5 | 18 tests pass |
| PHPUnit on TYPO3 14.3.7 / Fluid 5.3.2 / PHP 8.4.5 | 18 tests, 69 assertions pass |
| Maintained producer and actual Vite development/production fixtures | 6 Node tests pass, StyleX 0.19.1 and Vite 7.3.6 |
| Actual compiler composition parity | Clearing, shorthand/longhand, hover and media conditions match JavaScript class sets in both orders, in property-specificity and application-order modes |
| Composer validation, PHP lint, PHPCS, TYPO3 coding standard and PHPStan | Pass |
| JavaScript dependency audit | No advisories in the pinned fixture dependencies |
| TYPO3 documentation minimal render | Pass, 19 rendered pages |
| Live DDEV TYPO3 14.3.7, PHP 8.3 | `stylex:validate --required-key Nav.link` passes with 69 legacy keys; an absent required key returns nonzero |
| Live Site Set consumer fixture | Real page-link/image attributes receive compiled classes; Site Set CSS delivery and Bootstrap `forceOnTop` order pass; no deprecated priority/layer output |
| Live development CSS | Entry imports load; virtual CSS is nonempty and contains the known `x1oq41bz` class |
| Live stopped-server production check | Vite endpoint returns 502 after stopping; build succeeds; TYPO3 emits built CSS/JS with HTTP 200 and no development URLs; CSS contains rendered classes |

The generic producer's development test also edits a declaration, invokes its hot-update path, checks changed virtual CSS, closes the server and verifies the endpoint no longer responds. The existing project's live check uses its project-owned writer; the extension's maintained producer has separate real Vite fixtures. Existing copied writers must migrate to preserve null-clearing entries.

The content-hash strategy measured about 0.252 ms per SHA-256 read of the local 19,893-byte site manifest across 1,000 iterations. This is a local filesystem measurement, not a deployment performance guarantee. The service hashes once per instance and parses JSON only on a persistent cache miss.

Temporary site settings, additional configuration, CSS and setup files were restored, then TYPO3 caches were flushed. No external publication or release tag was created. The CI matrix retains minimum PHP versions; those combinations were not run locally. The reusable DDEV fixture targets TYPO3 13/14 Site Sets; the TYPO3 12 suite checks runtime/Fluid compatibility, while its optional CSS mechanisms were checked against the core implementation.

The project token alias adapter remains outside the extension. Arbitrary dynamic class-plus-variable support and site-specific manifest selection remain deferred as described in the analysis. Atomic manifest writes require staged deployment of the matching CSS generation; the connector does not make a multi-file deployment atomic.
