.. _configuration:

=============
Configuration
=============

.. _the-manifest:

The manifest
============

The service expects a JSON object with a ``styles`` object. Each entry needs a
``className``. The optional ``properties`` object lets the connector remove
classes for properties overridden by a later style key.

.. code-block:: json

   {
     "styles": {
       "Button.root": {
         "className": "x-root x-color",
         "properties": {
           "display": "x-root",
           "color": "x-color"
         }
       },
       "Button.primary": {
         "className": "x-primary",
         "properties": {
           "color": "x-primary"
         }
       }
     }
   }

Register all manifests before the first ViewHelper call. When two manifests
define the same style key, the last registered manifest wins. The service also
accepts ``manifestPath`` from Extension Manager configuration as a fallback
when no registered path has the same value.

Extension Manager settings
==========================

The extension configuration has these settings:

.. list-table:: Extension Manager settings
   :header-rows: 1

   * - Setting
     - Default
     - Effect
   * - ``manifestPath``
     - empty
     - Fallback path for a manifest.
   * - ``warnOnMissingKeys``
     - ``1``
     - Emits a PHP warning for an unknown key in a Development context.
   * - ``manifestCacheLifetime``
     - ``86400``
     - Lifetime in seconds for the merged ``stylex_manifest`` cache.
   * - ``enableCssLayers``
     - ``1``
     - Kept as extension configuration. CSS layer output is controlled by the
       TypoScript or Site Set setting below.
   * - ``overrideBootstrapSpecificity``
     - ``1``
     - Kept as extension configuration. Bootstrap include priorities are
       controlled by the TypoScript or Site Set setting below.

In a Development context the service always uses a cache lifetime of ``0``.
It reads the manifest again on the next request. In other contexts, flush
TYPO3 caches after deploying a new manifest if the configured cache lifetime
has not elapsed.

.. _css-delivery-settings:

CSS delivery settings
=====================

The connector does not need TypoScript to resolve classes. Its TypoScript is
optional and has a separate job: include a prebuilt CSS file, declare layer
order, and adjust known Bootstrap include priorities.

With the Site Set, configure these settings in the site configuration. Without
it, configure their TypoScript counterparts in
``plugin.tx_stylexconnector.settings``.

.. list-table:: Site Set and TypoScript settings
   :header-rows: 1

   * - Site Set setting
     - TypoScript setting
     - Default
   * - ``StylexConnector.cssFilePath``
     - ``cssFilePath``
     - empty
   * - ``StylexConnector.includeCssViaTypoScript``
     - ``includeCssViaTypoScript``
     - ``false``
   * - ``StylexConnector.declareCssLayers``
     - ``declareCssLayers``
     - ``false``
   * - ``StylexConnector.cssPriority``
     - ``cssPriority``
     - ``60``
   * - ``StylexConnector.bootstrapCssPriority``
     - ``bootstrapCssPriority``
     - ``40``
   * - ``StylexConnector.overrideBootstrapPriority``
     - ``overrideBootstrapPriority``
     - ``false``

Set ``includeCssViaTypoScript`` only when TYPO3 should include a CSS file by
path. Leave it disabled when a Vite ViewHelper or another asset mechanism
already includes the build output. Including the same CSS twice makes cascade
problems harder to diagnose.

Set ``declareCssLayers`` only when both the relevant CSS outputs use named
layers. It injects this declaration into the page head:

.. code-block:: css

   @layer bootstrap, stylex, overrides;

The setting does not put Bootstrap CSS into a layer. See :ref:`bootstrap-and-cascade-layers`
before enabling it in a Bootstrap project.
