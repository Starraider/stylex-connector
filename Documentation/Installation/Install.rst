.. _install-dependencies:

============================================
Install the extension and asset dependencies
============================================

First make sure the sitepackage is installed as a Composer package of type
``typo3-cms-extension``. Its page layout must already render in TYPO3. A local
package under ``packages/`` can use a Composer path repository. The project
must require that package so ``EXT:my_sitepackage`` paths resolve.

Install the connector from the TYPO3 project root:

.. code-block:: bash

   composer require skom/stylex-connector
   vendor/bin/typo3 extension:activate stylex_connector

Run the activation command only if TYPO3 has not already activated the
extension. If the sitepackage uses connector classes, declare
``skom/stylex-connector`` in the sitepackage's ``composer.json`` as well.

This guide uses ``praetorius/vite-asset-collector`` to serve the Vite build
in development and production:

.. code-block:: bash

   composer require praetorius/vite-asset-collector

The collector is an asset-delivery choice, not a dependency of the connector.
Projects using another asset system can keep it, provided it loads the built
CSS and JavaScript once. The connector's optional TypoScript CSS include is
described in :ref:`css-delivery-settings`.

Next, configure the build in :doc:`Vite`.
