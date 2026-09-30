.. _fluid-styled-content-integration:

====================
Fluid Styled Content
====================

Choose how much of Fluid Styled Content's presentation the site will keep.
Both choices use the installation in :ref:`installation` and the same Vite
bundle. The difference is which templates and CSS the site loads.

Keep existing FSC styles
========================

For an existing site, keep ``typo3/fluid-styled-content`` and
``typo3/fluid-styled-content-css`` in the active Site Set. Override only the
templates or partials whose markup should use StyleX. Set the sitepackage's
template fallback paths in its Site Set settings, for example:

.. code-block:: yaml

   styles.templates.layoutRootPath: EXT:my_sitepackage/Resources/Private/ContentElements/Layouts
   styles.templates.partialRootPath: EXT:my_sitepackage/Resources/Private/ContentElements/Partials
   styles.templates.templateRootPath: EXT:my_sitepackage/Resources/Private/ContentElements/Templates

Copy the selected FSC template into the matching sitepackage directory, then
apply ``stylex:class`` keys to its elements. Existing elements continue to
use FSC's templates and CSS. Check the browser's computed styles where FSC
selectors and atomic classes set the same property.

Own the presentation in the sitepackage
=======================================

If the site supplies styles for its standard elements, omit
``typo3/fluid-styled-content-css`` from the active Site Set while retaining
``typo3/fluid-styled-content`` where its content rendering is used. Author
the site's templates and Content Blocks, and import their StyleX files from
the Vite entrypoint. See :ref:`content-blocks-integration` for glob discovery.

Rich-text output still contains elements such as paragraphs and links that
have no StyleX class on each element. Include a scoped base stylesheet in
the Vite entrypoint for that output:

.. code-block:: javascript

   import '../CSS/rte.css';

.. code-block:: css

   .ce-bodytext p { margin-block-end: 1rem; }
   .ce-bodytext a { text-decoration: underline; }

With either choice, keep ``useCSSLayers: false`` while FSC or other CSS is
unlayered. The cascade guidance in :ref:`bootstrap-and-cascade-layers`
explains why a layer-order declaration alone does not layer existing CSS.
