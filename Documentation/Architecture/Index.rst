.. _architecture:

============
Architecture
============

Manifest loading
================

``StylexManifestService`` collects paths from ``StylexRegistry`` and then adds
the Extension Manager ``manifestPath`` according to ``manifestPathMode``. It resolves ``EXT:``
paths with TYPO3's file utility and accepts absolute paths too.

The service reads the ``styles`` object from every valid manifest and merges
them in registration order. A later manifest replaces a complete entry with
the same key. Missing files, malformed entries and unintended collisions produce bounded
logger-backed diagnostics with owner and path. Rendering keeps available
styles; ``stylex:validate`` applies the strict release policy.

Caching
=======

Outside Development, the service stores the merged styles in TYPO3's
``stylex_manifest`` cache. The cache is a ``VariableFrontend`` with a
``FileBackend`` and belongs to the ``all`` and ``pages`` cache groups. Its
default lifetime is one day. Ordered owners, override permissions, normalized
paths and SHA-256 file content hashes identify the cache entry. Content hashing
reads each artifact once per service instance even on a cache hit. JSON parsing
happens only on a miss. Incomplete loads never populate the persistent cache.

The service loads the manifest at most once per request after a cache miss.
Use ``StylexManifestService::clearCache()`` only from PHP code that has a
reason to force a reload. Normal deployment work should flush TYPO3 caches
after replacing generated assets.

Class resolution
================

``StylexManifestService::getClasses()`` flattens comma-separated style keys.
For compiled entries it keeps a map of opaque conflict identifier to class name.
Later keys replace the previous value for the same identifier; null removes it. Complete recipes concatenate their whole class lists without conflict resolution. The result removes duplicate
classes before returning a space-separated string.

The ViewHelper passes base keys, matching ``when`` keys, and an optional
``else`` fallback to this method. It HTML-escapes the returned string before
Fluid puts it in an attribute.

The canonical PHP namespace remains ``Vendor\StylexConnector``. Composer
autoloads ``Configuration/Compatibility.php`` to provide explicit Skom aliases
even before TYPO3 bootstrap. The bootstrap also loads that file for classic
extension installations. Both namespaces share the same registry and DI services.
