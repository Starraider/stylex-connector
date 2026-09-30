.. _bootstrap-integration:

===============================
Bootstrap and bootstrap_package
===============================

Decide which system supplies the site's Bootstrap CSS before adding StyleX.
This changes CSS imports and template ownership, not the base connector or
manifest setup in :ref:`installation`.

.. list-table:: Choose an approach
   :header-rows: 1

   * - Site
     - Bootstrap CSS source
     - StyleX work
   * - New site using plain Bootstrap
     - Import ``bootstrap/dist/css/bootstrap.min.css`` in the Vite entrypoint.
     - Style the site's own templates and Content Blocks.
   * - Existing ``bootstrap_package`` site with selected overrides
     - Keep ``bootstrap_package`` CSS and templates.
     - Override only selected templates or add new components.
   * - Site replacing many ``bootstrap_package`` templates
     - Keep its CSS while the replacement is incomplete.
     - Move components to StyleX gradually and remove unused CSS deliberately.

For plain Bootstrap, install the npm package and import its CSS once in
``Main.entry.js``. For ``bootstrap_package``, keep its sitepackage and Site
Set dependencies, and import only the JavaScript or additional styles that
the project needs. Check whether the existing theme already loads Bootstrap
JavaScript before importing it through Vite too. The asset collector call in
:ref:`connect-typo3` remains the entrypoint for StyleX in either case.

.. _bootstrap-and-cascade-layers:

Bootstrap and cascade layers
============================

The connector can declare this layer order when ``declareCssLayers`` is
enabled:

.. code-block:: css

   @layer bootstrap, stylex, overrides;

This declaration does not move Bootstrap CSS into a layer. For normal CSS
rules, unlayered CSS wins over layered CSS regardless of selector
specificity. A typical Bootstrap import and ``bootstrap_package`` output
are unlayered, so use ``useCSSLayers: false`` in the StyleX Vite plugin for
those setups. The base Vite example does this.

Enable StyleX layers and the connector's layer declaration only when the
project also compiles Bootstrap into ``@layer bootstrap``. Check the emitted
CSS and computed styles before relying on that order. If TYPO3 includes CSS
through the connector's optional TypoScript path, the
``overrideBootstrapPriority`` setting affects only the known asset names
``bootstrap``, ``bootstrap_package_bootstrap``, and ``bootstrap_cdn``. See
:ref:`css-delivery-settings` for all related settings.
