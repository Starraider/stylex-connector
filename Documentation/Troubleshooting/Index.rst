.. _troubleshooting:

===============
Troubleshooting
===============

No classes appear
=================

Confirm that the sitepackage registers the manifest and that its generated
file exists at the registered path. In a Development context the connector
reports a missing registration or file as a PHP notice or warning. Rebuild the
frontend assets, then flush TYPO3 caches.

An unknown style key returns no classes. Enable ``warnOnMissingKeys`` and use a
Development context to see the available keys in the warning. Check spelling
and make sure the corresponding StyleX source file is imported by the Vite
entrypoint.

Styles do not show during Vite development
==========================================

Ensure the browser receives ``/virtual:stylex.css`` from the Vite server. See
:ref:`vite-and-asset-delivery` for the runtime link example. The connector
resolves class names but does not serve CSS.

Bootstrap or FSC wins the cascade
=================================

Inspect the element's computed styles and the compiled selector. If Bootstrap
is unlayered, set ``useCSSLayers: false`` in the StyleX Vite plugin. If the
project uses named layers, make sure both stylesheets actually use the layer
order declared first in the consuming CSS build. See :ref:`bootstrap-and-cascade-layers`.

Fluid prints the ViewHelper expression
======================================

Check that the extension is active and that TYPO3 caches have been cleared
after installation. The extension registers the ``stylex`` namespace globally.
When a conditional uses a nested variable, assign it to an ``f:variable`` and
use that simple name as the ``when`` key.

Manifest changes remain stale
=============================

In Development, the cache lifetime is already zero. In other contexts, flush
TYPO3 caches after deploying a changed manifest or set an appropriate
``manifestCacheLifetime`` in Extension Manager configuration.
