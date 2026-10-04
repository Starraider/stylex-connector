.. _migration:

========================
Upgrade and deploy builds
========================

Existing Fluid calls and unversioned or ``1.0`` manifests keep working.
Rebuild with the maintained adapter to retain null-clearing entries: PHP
cannot reconstruct instructions already discarded by an older writer.
Complete class recipes do not gain conflict information during migration.

The maintained adapter ships at ``Resources/Private/Build/stylex-manifest.mjs``.
Replace copied writers with that import and keep project-specific token alias
bridges separate. Canonical keys include module and style object identities;
legacy basename aliases remain enabled until templates have migrated.

The Extension Manager path still overrides registered entries by default.
Switch ``manifestPathMode`` to ``fallback`` when registered entries should win.
Mark intentional manifest overrides with the third ``registerManifest`` argument.
The runtime retains later-wins behavior, while strict validation reports
unacknowledged collisions.

The numeric CSS priorities never provided the promised ordering. They are now
ignored. The legacy Bootstrap switch uses ``forceOnTop`` for known includeCSS
names. The layer switch no longer emits a declaration after existing CSS.
Move layer order to the start of the consuming stylesheet. Site Set and legacy
TypoScript defaults are false; deprecated Extension Manager layer/specificity
switches have no effect.

Build and deploy a coherent generation
======================================

Build CSS, the Vite asset manifest and the StyleX manifest in an unpublished
staging directory. Keep the nonempty entrypoint CSS import and verify that
Vite lists its CSS. The adapter records hashes for CSS assets from the bundle.
``artifacts`` paths are relative to the StyleX manifest, so keep the output
layout intact when staging and deploying.

Run ``stylex:validate`` against the registrations in the staged TYPO3 release,
including ``--required-key`` for template keys. A consumer can also supply
complete recipe manifests with generation identifiers and CSS hashes.

Promote the entire release directory together. Atomic replacement of the JSON
file alone cannot synchronize independently written CSS. Flush the manifest
and dependent TYPO3 page caches after promotion. Verify built asset responses
with the Vite server stopped. Cache content hashes prevent persistent lookup
reuse across file changes, but cannot rewrite HTML already in the page cache.

Retain the previous release directory for rollback. Restore its CSS, both
manifests and application code together, flush dependent caches again, and
repeat the stopped-server check. Do not overwrite a valid live generation
with build output that failed validation.

Compatibility evidence
======================

The PHP CI matrix remains TYPO3 12.4, 13.4 and 14.3 with their supported minimum
PHP versions and PHP 8.4. The optional producer fixtures pin StyleX 0.19.1 and
compile plain styles without a component framework dependency. They compare
class sets with PHP for clearing, shorthand conflicts, hover and media rules
in both property-specificity and application-order compiler modes.
Fluid tests exercise literal HTML attributes, TYPO3 link ViewHelper attributes
and malformed template inputs.
The Vite tests check a real development server, changed CSS, stopped-server
behavior, and production CSS pairing. The reusable DDEV consumer check in
``Tests/Consumer/`` renders ``f:link.page`` and ``f:image`` through TYPO3 and
checks optional Site Set stylesheet ordering.

Dynamic styles with runtime custom-property values are outside the class-only
contract. Build finite complete recipes or retain the JavaScript runtime for
those consumers. A dynamic PHP API requires a separate consumer contract.
