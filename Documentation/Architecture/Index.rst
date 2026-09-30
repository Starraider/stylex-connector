.. _architecture:

============
Architecture
============

Manifest loading
================

``StylexManifestService`` collects paths from ``StylexRegistry`` and then adds
the Extension Manager ``manifestPath`` as a fallback. It resolves ``EXT:``
paths with TYPO3's file utility and accepts absolute paths too.

The service reads the ``styles`` object from every valid manifest and merges
them in registration order. A later manifest replaces a complete entry with
the same key. In a Development context, missing registrations and missing
files produce notices or warnings. Invalid JSON with no usable ``styles``
object produces a warning.

Caching
=======

Outside Development, the service stores the merged styles in TYPO3's
``stylex_manifest`` cache. The cache is a ``VariableFrontend`` with a
``FileBackend`` and belongs to the ``all`` and ``pages`` cache groups. Its
default lifetime is one day.

The service loads the manifest at most once per request after a cache miss.
Use ``StylexManifestService::clearCache()`` only from PHP code that has a
reason to force a reload. Normal deployment work should flush TYPO3 caches
after replacing generated assets.

Class resolution
================

``StylexManifestService::getClasses()`` flattens comma-separated style keys.
For entries with property maps it keeps a map of CSS property to class name.
Later keys replace the previous value for the same property. Entries without
property maps fall back to their whole class list. The result removes duplicate
classes before returning a space-separated string.

The ViewHelper passes base keys, matching ``when`` keys, and an optional
``else`` fallback to this method. It HTML-escapes the returned string before
Fluid puts it in an attribute.
