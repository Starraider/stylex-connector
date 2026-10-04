# DDEV consumer verification

Use a development checkout with an installed TYPO3 13/14 site, an active sitepackage Site Set, a working image, and a built StyleX manifest/CSS pair. Run from the Composer project root. The test temporarily changes site settings and setup, adds two public CSS fixtures, and disables Vite development delivery. Its `finally` block restores those files and flushes caches.

```bash
python3 packages/stylex_connector/Tests/Consumer/verify-ddev.py \
  --sitepackage packages/my_sitepackage --set SitePackage --site main \
  --extension-key my_sitepackage --key Button.root \
  --image EXT:my_sitepackage/Resources/Public/Images/logo.svg \
  --url https://my-project.ddev.site/
```

It verifies Site Set settings reach the optional CSS include, known Bootstrap CSS appears before connector CSS, and late layer/unsupported priority attributes are absent. A real Fluid template renders the manifest lookup in literal HTML, `f:link.page` and `f:image` attributes. Use a disposable development site and let the test finish so restoration can run.

The JavaScript suite separately starts a real Vite development server, loads transformed StyleX modules and nonempty virtual CSS, changes a declaration, checks the changed CSS, closes the server and verifies it no longer responds. Its production fixture imports CSS, builds real StyleX, and checks manifest classes against emitted CSS. Neither fixture depends on a component library.
