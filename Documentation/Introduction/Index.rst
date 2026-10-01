.. _introduction:

============
Introduction
============

StyleX compiles style definitions into CSS during the frontend build. Fluid
templates run later, on the server, and cannot call ``stylex.props()``. The
connector closes that gap with a manifest file.

.. include:: StyleXAndBenefits.rst.txt

The build writes ``stylex-manifest.json``. Its ``styles`` object maps a style
key to a ``className`` and, when available, a per-property class map. TYPO3
loads the manifest and the ViewHelper writes the resolved classes into a
``class`` attribute.

.. code-block:: text

   StyleX source -> Vite build -> stylex-manifest.json
                                      |
                                      v
   Fluid template <- StyleX Connector <- registered manifest

Requirements
============

The extension targets TYPO3 12.4, 13.4, and 14.3 or later within their
respective major lines. It requires PHP 8.1 through 8.4 and
``typo3/cms-fluid`` in the same supported TYPO3 range.

The sitepackage also needs a frontend build that produces a compatible
manifest. The connector does not compile StyleX source files and it does not
provide a Vite configuration.

What the extension provides
===========================

* ``StylexRegistry`` registers one or more manifest paths.
* ``StylexManifestService`` loads and caches the merged manifest data.
* ``stylex:class`` resolves base, conditional, and fallback style keys in
  Fluid.
* A Site Set and TypoScript files can include the optional CSS delivery and
  cascade-layer settings.

The ViewHelper namespace is registered globally by ``ext_localconf.php``.
Templates can therefore use ``{stylex:class(...)}`` without declaring the
namespace themselves.

For StyleX authoring details, read the `StyleX documentation
<https://stylexjs.com/>`_.
