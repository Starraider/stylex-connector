.. _stylex-authoring:

===========================
StyleX authoring and themes
===========================

StyleX Connector resolves semantic keys from one or more generated manifests.
It does not compile ``.stylex.js`` files and it does not deliver their CSS.
The consuming site or extension owns its StyleX compiler, Vite entrypoints,
token sources, themes, recipes and generated assets.

This chapter recommends a project structure and source conventions for TYPO3
packages. The Connector does not enforce these names or paths; configure the
Vite build and manifest producer to match the project's chosen structure.

Recommended source layout
=========================

Keep shared, reusable design-system sources in the sitepackage or reusable
extension that owns them. Keep block-only recipes beside the block. The
following tree is a convention, not a TYPO3 or StyleX requirement:

.. code-block:: text

   my_sitepackage/
   ├── Resources/Private/JavaScript/
   │   ├── Main.entry.js
   │   └── StyleX/
   │       ├── tokens/
   │       │   ├── color-tokens.stylex.js
   │       │   ├── spacing-tokens.stylex.js
   │       │   └── components/
   │       │       └── button-tokens.stylex.js
   │       ├── themes/
   │       │   ├── neutral-theme.stylex.js
   │       │   └── ocean-theme.stylex.js
   │       └── components/
   │           └── button.stylex.js
   ├── ContentBlocks/ContentElements/card/
   │   ├── src/card.stylex.js
   │   ├── templates/frontend.html
   │   └── templates/backend-preview.html
   ├── Resources/Private/CSS/
   │   └── rte.css                 # only when editor integration needs CSS
   └── Resources/Public/
       ├── Assets/                  # generated CSS, JS, fonts and chunks
       └── StylexManifest/
           └── stylex-manifest.json # generated manifest

Use the project's existing TYPO3 casing for paths such as
``Resources/Private``. Use lowercase kebab-case for new StyleX folders and
source filenames, for example ``card.stylex.js`` and
``ocean-theme.stylex.js``. Keep source files out of published ``assets/``
directories; put Content Block build inputs in a private ``src/`` folder.

Token families and names
========================

Tokens name reusable values; recipes describe how components use those
values. Use ``stylex.defineVars()`` for themeable or runtime-overridable
values, and use ``stylex.defineConsts()`` for shared compile-time constants
that do not need theming. A useful family split is color, typography, spacing,
layout, shape, elevation and motion, with a component-specific family only
when a stable component contract warrants it.

.. code-block:: javascript

   // tokens/color-tokens.stylex.js
   import * as stylex from '@stylexjs/stylex';

   export const colors = stylex.defineVars({
     textPrimary: '#171717',
     textSecondary: '#555555',
     surface: '#ffffff',
     accent: '#005fcc',
     onAccent: '#ffffff',
   });

   // tokens/spacing-tokens.stylex.js
   import * as stylex from '@stylexjs/stylex';

   export const spacing = stylex.defineVars({
     compact: '0.5rem',
     normal: '1rem',
     spacious: '1.5rem',
   });

Prefer semantic roles such as ``textPrimary``, ``surface`` and
``statusError``. Avoid names tied to a component's temporary appearance, a
raw hex value, or a theme/mode such as ``oceanBlue`` or ``darkText``. When
raw CSS needs a stable public custom property, define an explicit,
namespaced bridge and generate it from the same token source where possible;
do not couple CSS to StyleX's compiler-generated variable identifiers.

Put token references into recipes rather than copying their values:

.. code-block:: javascript

   // components/button.stylex.js
   import * as stylex from '@stylexjs/stylex';
   import { colors } from '../tokens/color-tokens.stylex.js';
   import { spacing } from '../tokens/spacing-tokens.stylex.js';

   export const styles = stylex.create({
     root: {
       color: colors.onAccent,
       backgroundColor: colors.accent,
       paddingBlock: spacing.compact,
       paddingInline: spacing.normal,
     },
   });

Use token values across independent components when they represent a shared
design decision. Keep local structural values such as a one-off display or
layout rule inside the recipe instead of turning every literal into a public
token.

Recipes and manifest keys
=========================

Use one recipe module per reusable component or cohesive block. Keep styles
local to the elements that use them, and use named slots and finite variants
such as ``root``, ``title``, ``variant_primary`` and ``size_small``. Avoid
global selector trees and avoid encoding every combination as a new recipe.
Apply conditions with the project's supported StyleX composition pattern or
with the Connector's static ``when``/``else`` manifest keys.

.. code-block:: html

   <article class="{stylex:class(styles: 'card.root')}">
       <h2 class="{stylex:class(styles: 'card.title')}">{data.header}</h2>
   </article>

Manifest keys are supplied by the build producer. Prefer semantic, stable
keys and never copy generated atomic class names into Fluid. When the
maintained Vite adapter uses its default legacy alias, the source basename
contributes to aliases such as ``card.root``. Keep basenames unique across
registered packages, or disable aliases and use fully namespaced keys.
Renaming a source file can therefore change Fluid-facing keys; update
templates and validate the rebuilt manifest together.

The Connector's ``stylex:class`` ViewHelper resolves only keys present in the
manifest. It neither creates a recipe from token names nor loads the matching
CSS. See :ref:`using-the-viewhelper`, :ref:`the-manifest` and
:ref:`vite-and-asset-delivery`.

Theme strategy
==============

Separate theme identity from color mode. A theme represents a brand or
visual system such as ``neutral`` or ``ocean``. A mode represents a rendering
scheme such as ``light`` or ``dark``. Do not create duplicate theme
identities for each mode or encode mode in token names.

Use ``stylex.createTheme()`` to override a token group:

.. code-block:: javascript

   // themes/ocean-theme.stylex.js
   import * as stylex from '@stylexjs/stylex';
   import { colors } from '../tokens/color-tokens.stylex.js';

   export const oceanTheme = stylex.createTheme(colors, {
     accent: '#006b91',
     surface: '#f3fbff',
   });

Use CSS ``light-dark()`` for values that vary only with the browser color
scheme when it matches the supported browser baseline. Otherwise represent
mode with an explicit site setting or mode attribute and a deliberate
StyleX-compatible build strategy. Keep the semantic token name the same in
either case.

A nested ``createTheme()`` can override only selected values; omitted values
inherit through the CSS variable cascade. Verify that all variables consumed
inside a themed region resolve correctly when themes are nested or when a
component is rendered outside its normal parent. Give a subtree explicit
defaults for the variables it needs if its surrounding theme cannot be
guaranteed.

In server-rendered Fluid, a theme class must be emitted as a manifest key,
for example ``theme.ocean``:

.. code-block:: html

   <div class="{stylex:class(styles: 'theme.ocean')}">
       ...
   </div>

This example works only if the manifest producer emits that key. The
Connector does not inspect ``createTheme()`` declarations or invent theme
aliases. The maintained Vite adapter's static contract must be checked for
theme output; use a consumer adapter when theme classes need to be selected
from Fluid and are not otherwise present. Run
``vendor/bin/typo3 stylex:validate --required-key theme.ocean`` before
deployment.

CSS policy and asset delivery
=============================

StyleX recipes are the default for controlled frontend and backend-preview
markup. Do not add hand-authored CSS merely because a block owns its markup,
because a selector looks shorter, or because a legacy CSS file already
exists. A CSS file is not needed for each Content Block. Move ordinary
component layout, typography, spacing, color, responsive and state styling
into recipes that use shared tokens.

Keep CSS only for a concrete integration or browser requirement that cannot
be expressed or reliably applied through the pinned StyleX compiler and the
actual markup/build path. Typical exceptions include:

* Rich-text HTML produced by CKEditor, where the editor controls semantic
  descendants and TYPO3 may require a stylesheet through ``contentsCss``.
  Scope these rules to the editor/prose surface and use shared token values.
* Global reset or prose rules for arbitrary CMS HTML, font-face declarations,
  and browser-level constructs unsupported by the installed compiler.
* Opaque third-party markup with no stable hook, after checking whether the
  owner or controller can apply explicit StyleX classes.

Keep every exception small and scoped. Record why it cannot move into a
recipe. Do not maintain a parallel set of design values in CSS; reference a
documented token bridge or generated token sheet where appropriate.

The Vite integration may require a nonempty CSS entrypoint so its generated
StyleX rules are attached to a physical CSS asset. Keep that entry focused on
global reset or CSS-only integration rules; it is not a reason to put
component declarations there. The Connector's optional TypoScript CSS include
is an asset delivery option, not a StyleX compiler.

Treat compiled CSS, JavaScript, fonts, Vite manifests and StyleX manifests as
build output. Never edit generated atomic class names or generated manifests
by hand. Deploy the CSS and corresponding manifest from the same successful
build, then flush TYPO3 caches so rendered HTML does not retain old classes.

Official references
===================

* `Thinking in StyleX <https://stylexjs.com/docs/learn/thinking-in-stylex/>`_
  covers local reasoning, composition and StyleX's authoring principles.
* `StyleX and Vite <https://stylexjs.com/docs/learn/installation/vite/>`_
  explains compiler output and the CSS entrypoint.
* `StyleX API and theming <https://stylexjs.com/docs/api>`_ documents
  ``defineVars()``, ``createTheme()`` and the supported APIs.
