.. _configuration:

=============
Configuration
=============

.. _the-manifest:

The manifest
============

The service expects a JSON object with a ``styles`` object. Each entry needs a
``className``. The optional ``properties`` object contains opaque compiler conflict identifiers
and string or null values. A null value clears an earlier winner. It lets the connector remove
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
accepts ``manifestPath`` from Extension Manager configuration when no registered
path has the same value. The default ``manifestPathMode = legacyOverride``
appends it last, preserving v1 behavior. Select ``fallback`` to load it first,
letting registered entries win. Registration identifiers identify owners;
use distinct identifiers for several manifests from one extension. Reusing an
identifier replaces its path without changing its position in the order.

Duplicate style keys retain later-wins behavior during rendering and produce
owner-aware diagnostics. Pass ``true`` as the third argument of
``StylexRegistry::registerManifest()`` to explicitly permit that owner's
overrides. Strict validation rejects unacknowledged collisions.

V2 manifests require ``version: "2.0"`` and explicit entry kinds.
``compiled`` entries contain ``className`` and a ``properties`` conflict map.
``recipe`` entries contain a finished ``className`` and no conflict map.
Recipes concatenate; select one complete recipe per component slot.
Unversioned and ``1.0`` manifests remain supported with tolerant entry loading.
Unsupported versions or capabilities produce diagnostics and no entries.
Malformed entries generate diagnostics even when legacy rendering can continue.
The JSON schema ships in ``Resources/Private/Schema/manifest-v2.schema.json``.
Optional producer/compiler metadata, generation identity and CSS artifact
hashes support release checks. The frontend does not parse CSS on each request.

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
     - Additional manifest path, see manifestPathMode.
   * - ``warnOnMissingKeys``
     - ``1``
     - Emits a PHP warning for an unknown key in a Development context.
   * - ``manifestCacheLifetime``
     - ``86400``
     - Lifetime in seconds for the merged ``stylex_manifest`` cache.
   * - ``enableCssLayers``
     - ``1``
     - Kept as extension configuration. Deprecated and ineffective. Set layer order in the CSS build.
   * - ``overrideBootstrapSpecificity``
     - ``1``
     - Kept as extension configuration. Deprecated and ineffective. Configure asset ordering in the site.

In a Development context the service always uses a cache lifetime of ``0``.
It reads the manifest again on the next request. Other contexts use content
hashes and ordered registrations in the cache identity. Failed or incomplete
loads do not populate persistent cache entries. Flush TYPO3 page caches after
deployment because cached HTML still contains classes from the earlier build.

.. _css-delivery-settings:

CSS delivery settings
=====================

The connector does not need TypoScript to resolve classes. Its TypoScript is
optional. It includes a prebuilt CSS file and can move known Bootstrap
includeCSS entries to the top with ``forceOnTop``. It does not sort numeric
priorities.

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

``cssPriority`` and ``bootstrapCssPriority`` are deprecated, ignored inputs.
TYPO3 does not implement numeric ordering for ``page.includeCSS``.
``overrideBootstrapPriority`` retains its legacy name but now uses
``forceOnTop`` for known Bootstrap asset names. Inspect the final rendered head
because other asset collectors and custom renderers can affect order.
Compression and concatenation controls apply only on TYPO3 12/13.

``declareCssLayers`` is deprecated and emits no headerData. Put this declaration
first in the consuming CSS, before any named layers are introduced:

.. code-block:: css

   @layer bootstrap, stylex, overrides;

The declaration does not put unlayered CSS into a layer. See
:ref:`bootstrap-and-cascade-layers`.

Validation and reports
======================

.. code-block:: bash

   ddev exec vendor/bin/typo3 stylex:validate --required-key Button.root
   ddev exec vendor/bin/typo3 stylex:validate --report

The command returns nonzero for unreadable manifests, schema errors,
unacknowledged collisions, missing required keys or mismatched declared CSS
hashes. Repeat ``--required-key`` for every required key. Reports list owner,
resolved path, format/compiler metadata, entry counts and effective order.
Runtime diagnostics use TYPO3 logging. Reports contain at most 100 diagnostics.
Use ``StylexManifestService::hasStyle()`` for intentional runtime fallbacks.
