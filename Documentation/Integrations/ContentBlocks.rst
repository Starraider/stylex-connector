.. _content-blocks-integration:

==============
Content Blocks
==============

Custom Content Blocks can keep their ``.stylex.js`` source beside the Fluid
template. The block does not need its own stylesheet request when the shared
Vite entrypoint imports that source.

.. code-block:: text

   ContentBlocks/ContentElements/card/
   ├── assets/Card.stylex.js
   └── templates/frontend.html

Import files explicitly or discover them from
``Resources/Private/JavaScript/Main.entry.js``:

.. code-block:: javascript

   import.meta.glob(
     ['../../../ContentBlocks/ContentElements/**/assets/*.stylex.js'],
     { eager: true },
   );

Use the generated manifest keys in the block template:

.. code-block:: html

   <div class="{stylex:class(styles: 'Card.root')}">
       <h2 class="{stylex:class(styles: 'Card.title')}">{data.header}</h2>
   </div>

The shared asset call in :ref:`connect-typo3` loads the compiled rules. Do
not add another CSS asset call for the same StyleX output. Give each StyleX
source file a distinct basename if using the example manifest writer in
:ref:`vite-and-asset-delivery`, since ``Card.stylex.js`` produces keys such
as ``Card.root``.

Existing and third-party blocks
===============================

A block with its own ``assets/frontend.css`` can keep loading that CSS
through its existing asset mechanism. It joins the page's CSS cascade, so
inspect computed styles when both its selectors and StyleX classes target the
same element. For custom blocks, move styles into a colocated StyleX file,
import it from Vite, replace the template's old classes with manifest keys,
then remove that block's redundant CSS include.

Backend previews may need a separate lightweight stylesheet or an explicit
backend asset include. The frontend asset call does not automatically style
the TYPO3 backend preview. If several sitepackages build separate StyleX
manifests, each can call ``StylexRegistry::registerManifest()``. The
connector merges them in registration order; later entries with the same key
replace earlier entries. See :ref:`the-manifest`.
