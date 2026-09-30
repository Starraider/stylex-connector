.. _connect-typo3:

==========================
Connect the build to TYPO3
==========================

Add the connector Site Set to the site's active Site Set dependencies. Keep
the dependencies the sitepackage already needs:

.. code-block:: yaml
   :caption: packages/my_sitepackage/Configuration/Sets/Sitepackage/config.yaml

   name: vendor/my-sitepackage
   dependencies:
     - skom/stylex-connector

For TYPO3 12 or a site without Site Sets, import the connector's supplied
TypoScript setup and constants in the sitepackage instead:

.. code-block:: typoscript

   @import 'EXT:stylex_connector/Configuration/TypoScript/setup.typoscript'
   @import 'EXT:stylex_connector/Configuration/TypoScript/constants.typoscript'

Neither Site Sets nor TypoScript generate the StyleX manifest. Register the
exact output path configured in :doc:`Vite` from the sitepackage:

.. code-block:: php
   :caption: packages/my_sitepackage/ext_localconf.php

   <?php

   declare(strict_types=1);

   defined('TYPO3') or die();

   \Vendor\StylexConnector\Configuration\StylexRegistry::registerManifest(
       'my_sitepackage',
       'EXT:my_sitepackage/Resources/Public/StylexManifest/stylex-manifest.json'
   );

``Skom\\StylexConnector`` is an alias for the same public classes. The
extension registers the ``stylex`` Fluid namespace globally, so a separate
namespace registration file is unnecessary.

Render the Vite entrypoint
==========================

Add one asset collector call to the page layout TYPO3 actually renders. The
entry path must be the same as in ``ViteEntrypoints.json``:

.. code-block:: html
   :caption: packages/my_sitepackage/Resources/Private/PageView/Layouts/PageLayout.html

   <html
       data-namespace-typo3-fluid="true"
       xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
       xmlns:vite="http://typo3.org/ns/Praetorius/ViteAssetCollector/ViewHelpers"
   >
       <vite:asset entry="EXT:my_sitepackage/Resources/Private/JavaScript/Main.entry.js" />
       <f:render section="Main" />
   </html>

Keep the site's existing layout structure. Remove any older inclusion of the
same built CSS or JavaScript so each asset loads once. The asset collector
uses the Vite development server when available and the built manifest in
production.

With this delivery path, leave
``StylexConnector.includeCssViaTypoScript`` disabled. If another asset system
already serves the build, keep that system and register the StyleX manifest
the same way. See :ref:`css-delivery-settings` for the optional prebuilt CSS
include and the full settings reference.

Use a manifest key
==================

Once Vite has written the manifest, a Fluid template can resolve its keys:

.. code-block:: html

   <button class="{stylex:class(styles: 'Button.root')}">Save</button>

The ViewHelper writes classes; the page's asset include supplies their CSS.
Continue with :doc:`Verify`, then see :ref:`using-the-viewhelper` for
conditional styles and key composition.
