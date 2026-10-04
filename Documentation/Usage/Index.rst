.. _usage:

.. _using-the-viewhelper:

====================
Using the ViewHelper
====================

Use semantic manifest keys in the ``class`` attribute. The ViewHelper escapes
its output for use in HTML attributes.

.. code-block:: html

   <div class="{stylex:class(styles: 'Button.root, Button.primary')}">
       Save
   </div>

You can pass a Fluid array instead of a comma-separated string:

.. code-block:: html

   <div class="{stylex:class(styles: {0: 'Button.root', 1: 'Button.primary'})}">
       Save
   </div>

Conditional styles
==================

The ``when`` argument maps a template variable name to a style key. The
ViewHelper adds every key whose condition resolves to a non-empty value.

.. code-block:: html

   <f:link.page
       pageUid="{page.uid}"
       class="{stylex:class(styles: 'Nav.link', when: {isActive: 'Nav.linkActive'})}"
   >
       {page.title}
   </f:link.page>

If none of the conditions match, ``else`` adds its fallback key or keys:

.. code-block:: html

   <f:variable name="isActive" value="{item.active}" />
   <span class="{stylex:class(
       styles: 'Button.root',
       when: {isActive: 'Button.primary'},
       else: 'Button.outline'
   )}">
       {item.label}
   </span>

The service can resolve a dotted variable path, but Fluid inline dictionary
syntax may reject a dotted condition key. Assign a nested value to an
``f:variable`` first, as in the preceding example.

Tag syntax
==========

The same ViewHelper also supports tag syntax:

.. code-block:: html

   <button class="<stylex:class styles='Button.root, Button.primary' />">
       Save
   </button>

How classes are merged
======================

Style keys are processed left to right. If style entries contain
``properties`` maps, a later style key replaces the class for an opaque conflict
identifier used by an earlier key. Null values remove an earlier winner. This mirrors the last-key-wins behaviour expected when
composing StyleX styles.

For the manifest in :ref:`the-manifest`, this call produces
``x-root x-primary`` rather than both color classes:

.. code-block:: html

   <button class="{stylex:class(styles: 'Button.root, Button.primary')}">
       Save
   </button>

When a manifest entry only has ``className``, the service keeps its individual
classes and removes duplicates. It cannot infer which CSS properties they
represent. Generate property maps whenever the build tooling can provide them.

Complete recipes contain finished class lists. Select one recipe for each
component slot; combining recipes only concatenates and deduplicates classes.
Dynamic functions that return runtime CSS custom-property values are outside
this class-only API.
