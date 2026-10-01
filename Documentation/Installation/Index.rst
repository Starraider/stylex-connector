.. _installation:

============
Installation
============

Start here to connect a StyleX build to TYPO3. The connector resolves class
names from a JSON manifest; it does not compile StyleX or deliver CSS. A
working site therefore needs the extension, a frontend build, manifest
registration, and an asset include in the rendered page.

This guide uses a Composer-based TYPO3 project and a sitepackage named
``my_sitepackage`` under ``packages/my_sitepackage``. Run commands from the
project root and substitute your own package name and paths. The extension
targets TYPO3 12.4, 13.4, and 14.3 or later in the 14.x line. TYPO3 12.4
supports PHP 8.1 through 8.4; TYPO3 13.4 and 14.3 require PHP 8.2 or later.
This extension currently limits its PHP support to 8.4. You also need
a Node.js version supported by your Vite release.

TYPO3 12.4 is in ELTS. Use a current patched ELTS core release in production.

Follow these pages in order:

.. toctree::
   :maxdepth: 1

   Install
   Ddev
   Vite
   Connect
   Verify

If the project already has Vite, keep its package manager, lockfile, plugins,
and entrypoint. Add the StyleX and manifest steps in :doc:`Vite` to that
build. For a new DDEV project, read :doc:`Ddev` after installing the
dependencies, then return to the Vite guide. DDEV changes how the development
server runs; it does not change the manifest or TYPO3 configuration.

After verification, see :ref:`using-the-viewhelper` for class composition.
For Content Blocks, Fluid Styled Content, or Bootstrap, choose the relevant
guide under :ref:`integrations` after the base installation works.

Working with AI agents
======================

The `typo3-stylex Skill <https://github.com/Starraider/ai-typo3-integrator-plugin/tree/main/skills/typo3-stylex>`_
provides agent-specific setup and verification instructions for this stack.
