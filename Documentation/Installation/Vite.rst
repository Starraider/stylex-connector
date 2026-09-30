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

``@babel/parser`` is needed by the example manifest writer below. Install a
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

Add ``@stylexjs/unplugin`` and a project-owned manifest writer to
``vite.config.js``. The writer shown here accepts an options object. Its
``outputPath`` must match the PHP registration in :doc:`Connect`. The
connector itself does not supply this Vite plugin.

.. code-block:: javascript
   :caption: vite.config.js

   import { defineConfig } from 'vite';
   import typo3 from 'vite-plugin-typo3';
   import stylexPlugin from '@stylexjs/unplugin';
   import stylexManifestPlugin from './vite-plugin-stylex-manifest.js';

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

This small writer supports ``*.stylex.js`` files with ``stylex.create()``
exports. The source file's basename becomes the manifest key prefix, so give
each StyleX source file a distinct basename. For example,
``Button.stylex.js`` yields ``Button.root``. Adapt the parser if the project
uses other source forms.

.. code-block:: javascript
   :caption: vite-plugin-stylex-manifest.js at the project root

   import fs from 'node:fs';
   import path from 'node:path';
   import { parse } from '@babel/parser';

   export default function stylexManifestPlugin({ outputPath }) {
     const styles = {};
     let timer;
     let server;

     function write() {
       fs.mkdirSync(path.dirname(outputPath), { recursive: true });
       fs.writeFileSync(outputPath, JSON.stringify({ styles }, null, 2));
       server?.ws.send({ type: 'full-reload' });
     }

     return {
       name: 'stylex-manifest',
       enforce: 'post',
       configureServer(viteServer) { server = viteServer; },
       buildStart() { Object.keys(styles).forEach(key => delete styles[key]); },
       transform(code, id) {
         if (!/\.stylex\.js$/.test(id)) return null;
         const prefix = path.basename(id, '.stylex.js');
         for (const key of Object.keys(styles)) {
           if (key.startsWith(`${prefix}.`)) delete styles[key];
         }
         const ast = parse(code, { sourceType: 'module' });
         for (const node of ast.program.body) {
           const declaration = node.type === 'ExportNamedDeclaration'
             ? node.declaration : node;
           if (declaration?.type !== 'VariableDeclaration') continue;
           for (const variable of declaration.declarations) {
             if (variable.init?.type !== 'ObjectExpression') continue;
             for (const variant of variable.init.properties) {
               if (variant.type !== 'ObjectProperty' ||
                   variant.value.type !== 'ObjectExpression') continue;
               const properties = {};
               let compiled = false;
               for (const property of variant.value.properties) {
                 if (property.type !== 'ObjectProperty') continue;
                 const name = property.key.name ?? property.key.value;
                 if (name === '$$css') compiled = true;
                 else if (property.value.type === 'StringLiteral') {
                   properties[name] = property.value.value;
                 }
               }
               if (!compiled) continue;
               const name = variant.key.name ?? variant.key.value;
               styles[`${prefix}.${name}`] = {
                 className: Object.values(properties).join(' '),
                 properties,
               };
             }
           }
         }
         clearTimeout(timer);
         timer = setTimeout(write, 100);
         return null;
       },
       buildEnd() { clearTimeout(timer); write(); },
     };
   }

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
