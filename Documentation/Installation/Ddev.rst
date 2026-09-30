.. _vite-ddev-setup:

=======================
DDEV development server
=======================

This page adds a DDEV sidecar to the installation in :ref:`installation`.
The site, database, site configuration, Composer, Node.js in DDEV's web
container, and a working sitepackage must already be in place. Use the same
package manager and lockfile the project already uses.

Install the `ddev-vite-sidecar <https://github.com/s2b/ddev-vite-sidecar>`_
add-on once. Choose the project's package manager when prompted. The
``add-on get`` command needs DDEV 1.23.5 or later; older releases use
``ddev get``.

.. code-block:: bash

   ddev add-on get s2b/ddev-vite-sidecar
   ddev restart
   ddev vite --help

The sidecar exposes the Vite server at
``https://vite.<project>.ddev.site``. Its generated
``.ddev/config.vite.yaml`` holds ``VITE_SERVER_URI``, which the asset
collector detects in TYPO3's Development context.

Run the package installation commands from :doc:`Vite` with ``ddev exec``
when Node.js lives only in the web container. Likewise, use ``ddev composer``
for the Composer commands in :doc:`Install`. Follow :doc:`Vite` and
:doc:`Connect` for the shared build and TYPO3 setup; no DDEV-specific manifest
configuration is needed.

.. code-block:: bash

   ddev vite
   ddev vite build

The first command runs the development server. The second creates production
assets after the server has stopped. Finish with :doc:`Verify`.
