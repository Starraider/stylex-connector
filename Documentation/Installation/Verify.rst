.. _verify-installation:

==========================================
Verify development and production delivery
==========================================

In a TYPO3 Development context, start the project's Vite development server.
For a DDEV sidecar, use ``ddev vite``; otherwise use the project's existing
Vite command. Load a page that renders the configured layout and a
``stylex:class`` call.

Check all three parts of the page:

* The entrypoint loads from the Vite server.
* The rendered element has atomic classes from ``stylex-manifest.json``.
* The browser loads ``/virtual:stylex.css`` from the Vite origin without a
  network error. Changing a ``.stylex.js`` value updates the page after a
  reload or hot update.

For production, stop the development server and run the project's build,
followed by a TYPO3 cache flush. For a project with an npm build script:

.. code-block:: bash

   npm run build
   vendor/bin/typo3 cache:flush

With the DDEV sidecar, use ``ddev vite build`` and
``ddev exec vendor/bin/typo3 cache:flush`` instead.

Check both ``public/_assets/vite/.vite/manifest.json`` and
``packages/my_sitepackage/Resources/Public/StylexManifest/stylex-manifest.json``.
The first must list the entrypoint and its generated CSS. The second must
contain the key used by Fluid. Load the site with Vite stopped and confirm
that CSS and JavaScript come from the production asset path without 404s.

Deploy the built Vite assets and StyleX manifest together, then flush TYPO3
caches. See :ref:`troubleshooting` if classes, CSS, or updates are missing.
