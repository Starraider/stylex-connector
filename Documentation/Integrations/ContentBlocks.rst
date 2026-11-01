.. _content-blocks-integration:

==============
Content Blocks
==============

Put private StyleX source in a block-owned ``src/`` directory, not in
Content Blocks' published ``assets/`` directory. Import it from the Vite entry
used for that rendering surface; the compiled StyleX rules are delivered with
the entry's CSS output.

.. code-block:: text

   ContentBlocks/ContentElements/card/
   ├── src/
   │   └── card.stylex.js
   ├── templates/
   │   ├── frontend.html
   │   └── backend-preview.html
   └── assets/
       └── icon.svg

Use lowercase kebab-case source filenames and make StyleX basenames unique
across every package that contributes to a manifest. With the maintained
adapter's default legacy aliases, ``card.stylex.js`` can expose keys such as
``card.root``. Renaming the source changes that alias; update Fluid references
and validate the rebuilt manifest together.

Import sources explicitly or use a source-only glob from the frontend entry:

.. code-block:: javascript

   import.meta.glob(
     ['../../../ContentBlocks/ContentElements/**/src/*.stylex.js'],
     { eager: true },
   );

Define finite, named styles in the block recipe and apply semantic manifest
keys in Fluid:

.. code-block:: javascript

   import * as stylex from '@stylexjs/stylex';
   import { colors } from '../../../../Resources/Private/JavaScript/StyleX/tokens/color-tokens.stylex.js';

   export const styles = stylex.create({
     root: { color: colors.textPrimary },
     title: { fontSize: '1.5rem', fontWeight: 600 },
   });

.. code-block:: html

   <div class="{stylex:class(styles: 'card.root')}">
       <h2 class="{stylex:class(styles: 'card.title')}">{data.header}</h2>
   </div>

The connector resolves keys from the manifest; it does not compile StyleX
source or load its CSS. The shared Vite asset integration loads the compiled
rules. Do not add another CSS asset call for the same StyleX output.

Avoid hand-authored block CSS for ordinary layout, spacing, colors, variants,
responsive behavior or states. Use the block recipe and shared tokens instead.
Keep CSS only for a documented requirement that cannot be represented or
reliably applied with StyleX, such as CKEditor-owned rich-text markup, a
browser-level construct unsupported by the pinned compiler, or opaque
third-party markup with no stable hook. Keep each exception small, scoped and
near its owner; use the same StyleX token values where the CSS integration
needs them.

Compile backend-preview recipes through a preview-specific Vite entry or the
site's backend asset integration. A separate preview stylesheet is not needed
for ordinary block presentation. Use one only when the preview or backend
integration has a specific CSS-only requirement.

Existing and third-party blocks
===============================

A block that already includes ``assets/frontend.css`` can keep it as a legacy
or third-party integration while it is needed. For styles on markup that the
site controls, migrate declarations to a colocated ``src/<block>.stylex.js``
recipe, import it from the proper Vite entry, replace old template classes with
manifest keys, and remove the redundant CSS include. If third-party or editor
markup prevents a clean StyleX mapping, retain only the scoped exception and
document its reason. Use shared tokens for CSS exception values rather than
copying design values.

A frontend asset call does not automatically style the TYPO3 backend preview.
If several sitepackages build separate StyleX manifests, each can call
``StylexRegistry::registerManifest()``. The connector merges them in
registration order; later entries with the same key replace earlier entries.
Namespace keys by package or use distinct aliases, and allow overrides only
when they are intentional. See :ref:`the-manifest`.
