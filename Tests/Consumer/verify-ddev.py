#!/usr/bin/env python3
"""Exercise the connector in an existing DDEV TYPO3 13/14 site, restoring source files."""
import argparse
import re
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--sitepackage', required=True, help='Filesystem path to the installed sitepackage source')
parser.add_argument('--set', required=True, help='Sitepackage Site Set directory name')
parser.add_argument('--site', required=True, help='TYPO3 site identifier')
parser.add_argument('--extension-key', required=True, help='Sitepackage extension key')
parser.add_argument('--key', required=True, help='Existing compiled manifest key')
parser.add_argument('--image', required=True, help='An existing EXT: image path for the image ViewHelper')
parser.add_argument('--url', required=True)
args = parser.parse_args()
package = Path(args.sitepackage)
setup = package / 'Configuration/Sets' / args.set / 'setup.typoscript'
settings = Path('config/sites') / args.site / 'settings.yaml'
additional = Path('config/system/additional.php')
bootstrap_css = package / 'Resources/Public/connector-bootstrap-probe.css'
stylex_css = package / 'Resources/Public/connector-stylex-probe.css'
paths = [setup, settings, additional, bootstrap_css, stylex_css]
originals = {p: p.read_bytes() if p.exists() else None for p in paths}

def flush():
    subprocess.run(['ddev', 'exec', 'vendor/bin/typo3', 'cache:flush'], check=True, capture_output=True)

try:
    # Source package paths are passed explicitly to avoid guessing the site's asset owner.
    extension_key = args.extension_key
    stylex_path = f'EXT:{extension_key}/Resources/Public/connector-stylex-probe.css'
    bootstrap_path = f'EXT:{extension_key}/Resources/Public/connector-bootstrap-probe.css'
    bootstrap_css.write_text('.connector-cascade-probe { color: red }\n')
    stylex_css.write_text('.connector-cascade-probe { color: blue }\n')
    additional.write_bytes(originals[additional] + b"\n$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['vite_asset_collector']['useDevServer'] = false;\n")
    settings.parent.mkdir(parents=True, exist_ok=True)
    settings.write_bytes((originals[settings] or b'') + (
        '\nStylexConnector.includeCssViaTypoScript: true\n'
        f"StylexConnector.cssFilePath: '{stylex_path}'\n"
        'StylexConnector.overrideBootstrapPriority: true\n'
        'StylexConnector.declareCssLayers: true\n'
    ).encode())
    setup.write_bytes(originals[setup] + (
        f'\npage.includeCSS.bootstrap = {bootstrap_path}\n'
        'page.999 = FLUIDTEMPLATE\n'
        'page.999.file = EXT:stylex_connector/Tests/Fixtures/Consumer.html\n'
        'page.999.variables.probeStyle = TEXT\n'
        f'page.999.variables.probeStyle.value = {args.key}\n'
        'page.999.variables.probeImage = TEXT\n'
        f'page.999.variables.probeImage.value = {args.image}\n'
    ).encode())
    flush()
    html = subprocess.check_output(['curl', '-ksS', '--max-time', '30', args.url]).decode()
    Path('/tmp/stylex-connector-consumer.html').write_text(html)
    assert 'id="stylex-connector-probe"' in html, 'Consumer template did not render'
    probe = html[html.index('id="stylex-connector-probe"'):]
    for tag in ['a', 'img']:
        assert re.search(r'<' + tag + r'\b[^>]*class="[^"]*x[a-z0-9]', probe), tag + ' has no compiled class'
    assert html.index('connector-bootstrap-probe.css') < html.index('connector-stylex-probe.css'), 'Incorrect CSS order'
    assert '@layer bootstrap' not in html, 'Deprecated layer output remains'
    assert 'priority="' not in html, 'Unsupported priority attribute remains'
    assert 'disableCompression="' not in html, 'Removed TYPO3 14 attribute leaked'
    assert not re.search(r'https?://vite\.[^/]+/', html), 'Development URL in production output'
    print('Site Set CSS inclusion and Bootstrap forceOnTop order pass.')
    print('Real TYPO3 page link and image attributes contain compiled classes.')
finally:
    for p, content in originals.items():
        if content is None:
            if p.exists():
                p.unlink()
        else:
            p.write_bytes(content)
    flush()
    print('Restored site settings, setup, CSS and development asset configuration.')
