import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import stylexManifest from '../../Resources/Private/Build/stylex-manifest.mjs';
const require = createRequire(new URL('../../Build/JavaScript/package.json', import.meta.url));
const { transformSync } = require('@babel/core');
const compiler = require('@stylexjs/babel-plugin');
const stylex = require('@stylexjs/stylex');
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../Fixtures');
const source = `import * as stylex from '@stylexjs/stylex';
export const styles = stylex.create({
  base: { color: 'red', padding: 8, marginTop: 4, ':hover': {color: 'purple'} },
  clear: { color: null },
  shorthand: { margin: 0 },
  conditional: { color: { default: 'blue', ':hover': 'green', '@media (min-width: 800px)': 'navy' } },
});`;
for (const mode of ['property-specificity', 'application-order']) {
  const suffix = mode === 'application-order' ? '-application-order' : '';
  const { code } = transformSync(source, { filename: path.join(root, 'Parity.stylex.js'), plugins: [[compiler, {
    dev: false, runtimeInjection: false, styleResolution: mode,
  }]], configFile: false, babelrc: false });
  const compiled = new Function(code.replace(/^import .*?;$/gm, '').replace('export const', 'const') + ';return styles;')();
  const outputPath = path.join(root, `compiler${suffix}-manifest.json`);
  const plugin = stylexManifest({ root, outputPath, compilerVersion: '0.19.1' });
  plugin.buildStart();
  plugin.transform(code, path.join(root, 'Parity.stylex.js'));
  plugin.writeBundle({ dir: root }, {});
  const orders = [['base','clear'], ['clear','base'], ['base','shorthand'], ['shorthand','base'], ['base','conditional'], ['conditional','base']];
  fs.writeFileSync(path.join(root, `compiler${suffix}-parity.json`), JSON.stringify(orders.map(order => ({
    keys: order.map(key => `Parity.${key}`), classes: stylex.props(...order.map(key => compiled[key])).className.split(' ').filter(Boolean).sort(),
  })), null, 2) + '\n');
}
