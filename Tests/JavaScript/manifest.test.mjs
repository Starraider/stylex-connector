import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';
import { createRequire } from 'node:module';
import { EventEmitter } from 'node:events';
import stylexManifest from '../../Resources/Private/Build/stylex-manifest.mjs';
const require = createRequire(new URL('../../Build/JavaScript/package.json', import.meta.url));
const compiled = (name, value) => `export const ${name} = {root: {$$css: true, kColor: ${JSON.stringify(value)}}};`;
function fixture(t, options = {}) {
  const root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'stylex-producer-')));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const outputPath = path.join(root, 'manifest.json');
  const plugin = stylexManifest({ root, outputPath, ...options });
  plugin.buildStart();
  const read = () => JSON.parse(fs.readFileSync(outputPath, 'utf8'));
  const publish = () => plugin.writeBundle({ dir: root }, {});
  return { root, outputPath, plugin, read, publish };
}

test('clearing, query IDs, deterministic output and deleted variants', t => {
  const f = fixture(t);
  f.plugin.transform(compiled('styles', null), path.join(f.root, 'Button.stylex.js?v=1'));
  f.publish();
  assert.equal(f.read().styles['Button.root'].properties.kColor, null);
  const first = fs.readFileSync(f.outputPath, 'utf8');
  f.publish();
  assert.equal(fs.readFileSync(f.outputPath, 'utf8'), first);
  f.plugin.transform('export const styles = {};', path.join(f.root, 'Button.stylex.js'));
  f.publish();
  assert.deepEqual(f.read().styles, {});
});

test('module/object identities coexist and ambiguous legacy aliases fail', t => {
  const f = fixture(t, { legacyAliases: false, namespace: 'example' });
  f.plugin.transform(compiled('one', 'x1') + compiled('two', 'x2'), path.join(f.root, 'Button.stylex.js'));
  f.publish();
  assert.deepEqual(Object.keys(f.read().styles), ['example/Button.stylex.one.root', 'example/Button.stylex.two.root']);
  const legacy = fixture(t);
  legacy.plugin.transform(compiled('one', 'x1'), path.join(legacy.root, 'a/Button.stylex.js'));
  assert.throws(() => legacy.plugin.transform(compiled('two', 'x2'), path.join(legacy.root, 'b/Button.stylex.js')), /Duplicate StyleX key/);
  assert.throws(() => legacy.plugin.transform(compiled('one', 'x1') + compiled('two', 'x2'), path.join(legacy.root, 'Button.stylex.js')), /Ambiguous/);
});

test('failed builds and parse failures retain last valid manifest', t => {
  const f = fixture(t);
  f.plugin.transform(compiled('one', 'x1'), path.join(f.root, 'Button.stylex.js'));
  f.publish();
  const first = fs.readFileSync(f.outputPath, 'utf8');
  assert.throws(() => f.plugin.transform('invalid {{{', path.join(f.root, 'Button.stylex.js')));
  f.plugin.buildEnd(new Error('failed'));
  f.publish();
  assert.equal(fs.readFileSync(f.outputPath, 'utf8'), first);
  f.plugin.buildStart();
  f.publish();
  assert.deepEqual(f.read().styles, {});
});

test('development unlink removes entries and publishes a new snapshot', async t => {
  const f = fixture(t);
  const watcher = new EventEmitter();
  const httpServer = new EventEmitter();
  f.plugin.configureServer({ watcher, httpServer, ws: { send() {} }, config: { logger: { error(message) { throw new Error(message); } } } });
  t.after(() => httpServer.emit('close'));
  const file = path.join(f.root, 'Button.stylex.js');
  f.plugin.transform(compiled('one', 'x1'), file);
  await new Promise(resolve => setTimeout(resolve, 150));
  assert.ok(f.read().styles['Button.root']);
  watcher.emit('unlink', file);
  await new Promise(resolve => setTimeout(resolve, 150));
  assert.deepEqual(f.read().styles, {});
});

test('actual Vite build emits paired CSS and static manifest', async t => {
  const f = fixture(t);
  const { build } = await import('../../Build/JavaScript/node_modules/vite/dist/node/index.js');
  const { default: stylex } = await import('../../Build/JavaScript/node_modules/@stylexjs/unplugin/lib/index.js');
  fs.writeFileSync(path.join(f.root, 'Button.stylex.js'), `import * as stylex from '@stylexjs/stylex'; export const styles = stylex.create({root:{color:'red'}, clear:{color:null}});`);
  fs.writeFileSync(path.join(f.root, 'base.css'), 'body { margin: 0 }');
  fs.writeFileSync(path.join(f.root, 'entry.js'), `import './base.css'; import {styles} from './Button.stylex.js'; console.log(styles);`);
  await build({ configFile: false, root: f.root, logLevel: 'silent', plugins: [stylex.vite({ useCSSLayers: false, runtimeInjection: false }), f.plugin],
    resolve: { alias: { '@stylexjs/stylex': require.resolve('@stylexjs/stylex') } },
    build: { outDir: path.join(f.root, 'dist'), rollupOptions: { input: path.join(f.root, 'entry.js') } } });
  assert.ok(f.read().styles['Button.root'].className);
  assert.equal(f.read().styles['Button.clear'].properties[Object.keys(f.read().styles['Button.clear'].properties)[0]], null);
  assert.ok(f.read().artifacts.length > 0);
  const css = f.read().artifacts.map(artifact => fs.readFileSync(path.resolve(f.root, artifact.path), 'utf8')).join('\n');
  assert.match(css, /color:\s*(red|#f00)/);
  assert.ok(css.includes(f.read().styles['Button.root'].className));
});

test('actual Vite development CSS follows transforms and stops serving after close', { timeout: 30000 }, async t => {
  const f = fixture(t);
  const { createServer } = await import('../../Build/JavaScript/node_modules/vite/dist/node/index.js');
  const { default: stylex } = await import('../../Build/JavaScript/node_modules/@stylexjs/unplugin/lib/index.js');
  const sourcePath = path.join(f.root, 'Button.stylex.js');
  const source = color => `import * as stylex from '@stylexjs/stylex'; export const styles = stylex.create({root:{color:'${color}'}});`;
  fs.writeFileSync(sourcePath, source('red'));
  fs.writeFileSync(path.join(f.root, 'entry.js'), `import {styles} from './Button.stylex.js'; console.log(styles);`);
  const server = await createServer({ configFile: false, root: f.root, logLevel: 'silent', plugins: [stylex.vite({ useCSSLayers: false }), f.plugin],
    optimizeDeps: { noDiscovery: true, include: [] },
    resolve: { alias: { '@stylexjs/stylex': require.resolve('@stylexjs/stylex') } }, server: { host: '127.0.0.1', port: 0 } });
  t.after(() => server.close());
  await server.listen();
  const origin = `http://127.0.0.1:${server.httpServer.address().port}`;
  const request = url => fetch(url, { signal: AbortSignal.timeout(10000) });
  await request(`${origin}/entry.js`);
  assert.equal((await request(`${origin}/Button.stylex.js`)).status, 200);
  const css = await (await request(`${origin}/virtual:stylex.css`)).text();
  assert.match(css, /color:\s*(red|#f00)/);
  await new Promise(resolve => setTimeout(resolve, 150));
  assert.ok(css.includes(f.read().styles['Button.root'].className.split(' ').find(value => value.startsWith('x'))));
  fs.writeFileSync(sourcePath, source('blue'));
  server.moduleGraph.invalidateAll();
  await f.plugin.handleHotUpdate({ file: sourcePath, server });
  const changed = await (await request(`${origin}/virtual:stylex.css?t=2`)).text();
  assert.match(changed, /color:\s*(blue|#00f)/);
  await server.close();
  await assert.rejects(request(`${origin}/virtual:stylex.css`));
});
