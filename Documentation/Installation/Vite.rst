.. _vite-and-asset-delivery:

=======================
Vite and asset delivery
=======================

Vite produces two different manifests. The asset collector reads
``public/_assets/vite/.vite/manifest.json`` to find built CSS and JavaScript.
StyleX Connector reads the site's ``stylex-manifest.json`` to turn semantic
keys into class names. Build and deploy both outputs together.

Install build packages
======================

Use the project's existing package manager. With npm at the project root:

.. code-block:: bash

   npm install @stylexjs/stylex
   npm install --save-dev vite vite-plugin-typo3 @stylexjs/unplugin @babel/parser

``@babel/parser`` is needed by the optional maintained manifest adapter. Install a
Node.js release supported by the Vite version selected for your project.

Declare an entrypoint
=====================

``vite-plugin-typo3`` reads the installed sitepackage's
``Configuration/ViteEntrypoints.json``. Paths in that file are relative to
the JSON file:

.. code-block:: json
   :caption: packages/my_sitepackage/Configuration/ViteEntrypoints.json

   ["../Resources/Private/JavaScript/Main.entry.js"]

The entrypoint must import every StyleX source file whose keys Fluid uses.
Start with explicit imports. For many custom Content Blocks, see
:ref:`content-blocks-integration` for glob discovery.

.. code-block:: javascript
   :caption: packages/my_sitepackage/Resources/Private/JavaScript/Main.entry.js

   import '../CSS/main.css';
   import './Stylex/Button.stylex.js';
   import './main.js';

Compile StyleX and write its manifest
=====================================

Add ``@stylexjs/unplugin`` and the maintained static manifest adapter to
``vite.config.js``. The adapter accepts an options object. Its
``outputPath`` must match the PHP registration in :doc:`Connect`. The
connector ships it at ``Resources/Private/Build/stylex-manifest.mjs``.
Prebuilt-manifest consumers do not need Node.js at PHP runtime.

.. code-block:: javascript
   :caption: vite.config.js

   import { defineConfig } from 'vite';
   import typo3 from 'vite-plugin-typo3';
   import stylexPlugin from '@stylexjs/unplugin';
   import stylexManifestPlugin from './vendor/skom/stylex-connector/Resources/Private/Build/stylex-manifest.mjs';

   export default defineConfig({
     plugins: [
       typo3(),
       stylexPlugin.vite({
         useCSSLayers: false,
         unstable_moduleResolution: {
           type: 'commonJS',
           rootDir: process.cwd(),
         },
       }),
       stylexManifestPlugin({
         outputPath: 'packages/my_sitepackage/Resources/Public/StylexManifest/stylex-manifest.json',
       }),
     ],
   });

``useCSSLayers: false`` suits sites that load unlayered CSS, including a
typical Bootstrap or Fluid Styled Content setup. For layered CSS, read
:ref:`bootstrap-and-cascade-layers` before changing the option.

The adapter supports static compiled conflict maps from ``*.stylex.js``,
``*.stylex.jsx``, ``*.stylex.ts`` and ``*.stylex.tsx``. Run Vite from the
Composer project root, where the build dependencies are installed. Its tests
pin StyleX 0.19.1. Dynamic functions and other unsupported conflict values
fail the build. Use a separate consumer adapter to generate complete recipes.

The canonical key includes namespace, root-relative module path, style object
and variant, for example ``site/packages/my_sitepackage/Resources/Private/JavaScript/Stylex/Button.stylex.styles.root``.
Set ``root`` to the source package and ``namespace`` to its stable package name
for shorter keys. Real paths remove Composer symlink differences.

``legacyAliases`` defaults to true, adding ``Button.root`` for existing Fluid
templates. Ambiguous aliases and duplicate keys fail instead of silently
replacing entries. Disable aliases after migrating templates. ``include``
accepts a regular expression or predicate for other source naming policies.
``compilerVersion`` records your pinned compiler version.

The adapter replaces each module's entries after a successful transform,
removes deleted modules, and resets collection for each production build.
Production publication runs after bundle writing. It hashes emitted CSS and
writes a deterministic v2 manifest with an atomic temporary-file replacement.
A failed transform or build leaves the previous manifest intact. Build into a
staging directory: atomic JSON replacement does not make separately written
CSS atomic. See :doc:`../Migration/Index` for deployment and rollback.

Import a nonempty CSS file from the entrypoint. StyleX 0.19.1 attaches its CSS
to an existing Vite CSS asset, so an entrypoint without that import can leave
its fallback CSS outside the Vite manifest's entrypoint references.

For a first style, create ``Button.stylex.js`` beside the other StyleX
sources and import it in ``Main.entry.js``:

.. code-block:: javascript

   import * as stylex from '@stylexjs/stylex';

   export const styles = stylex.create({
     root: { padding: '0.75rem 1rem', backgroundColor: 'rebeccapurple' },
   });

Development CSS and production assets
=====================================

TYPO3 renders the document, so Vite does not inject StyleX's virtual CSS
through ``transformIndexHtml``. Add this once to the JavaScript entrypoint
to load it from the Vite origin during development:

.. code-block:: javascript
   :caption: Add to Main.entry.js

   if (import.meta.env.DEV) {
     const linkId = '__stylex_dev_css__';
     let link = document.getElementById(linkId);
     if (!link) {
       link = document.createElement('link');
       link.id = linkId;
       link.rel = 'stylesheet';
       document.head.appendChild(link);
     }
     const cssUrl = `${new URL(import.meta.url).origin}/virtual:stylex.css`;
     link.href = cssUrl;
     const refresh = () => { link.href = `${cssUrl}?t=${Date.now()}`; };
     import.meta.hot?.on('stylex:css-update', refresh);
     import.meta.hot?.on('vite:afterUpdate', refresh);
   }

In production, Vite emits physical CSS. The asset collector includes it
through the built entrypoint. Do not also enable the connector's TypoScript
CSS include for that same file. Next, connect these outputs to TYPO3 in
:doc:`Connect`.
