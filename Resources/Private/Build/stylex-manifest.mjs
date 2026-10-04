import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { createRequire } from 'node:module';
// Build dependencies belong to the consuming project, which runs Vite from its root.
const { parse } = createRequire(path.join(process.cwd(), 'package.json'))('@babel/parser');

const digest = value => crypto.createHash('sha256').update(value).digest('hex');
const moduleId = id => id.split('?')[0].replaceAll('\\', '/');
function canonicalId(id) {
  const clean = moduleId(id);
  if (fs.existsSync(clean)) return fs.realpathSync(clean);
  const parent = path.dirname(clean);
  return parent === clean ? clean : path.join(canonicalId(parent), path.basename(clean));
}

/** Static compiled StyleX adapter. Run after @stylexjs/unplugin. */
export default function stylexManifest({ outputPath, root = process.cwd(), namespace = 'site',
  legacyAliases = true, include = /\.stylex\.(js|jsx|ts|tsx)$/, compilerVersion = 'unspecified' } = {}) {
  if (!outputPath) throw new Error('StyleX manifest outputPath is required.');
  root = fs.realpathSync(root);
  outputPath = path.resolve(outputPath);
  const modules = new Map();
  let server;
  let timer;
  let failed = false;

  const selected = id => typeof include === 'function' ? include(id) : (include.lastIndex = 0, include.test(id));
  function collect(code, id) {
    const clean = moduleId(id);
    const absolute = fs.existsSync(clean) ? fs.realpathSync(clean) : clean;
    const relative = path.relative(root, absolute).replaceAll('\\', '/');
    if (relative.startsWith('../') || path.isAbsolute(relative)) throw new Error(`StyleX source outside root: ${id}`);
    const module = relative.replace(/\.(js|jsx|ts|tsx)$/, '');
    const basename = path.basename(clean).replace(/\.stylex\.(js|jsx|ts|tsx)$/, '');
    const entries = {};
    const ast = parse(code, { sourceType: 'module', plugins: ['jsx', 'typescript'] });
    for (const node of ast.program.body) {
      const declaration = node.type === 'ExportNamedDeclaration' ? node.declaration : node;
      if (declaration?.type !== 'VariableDeclaration') continue;
      for (const variable of declaration.declarations) {
        if (variable.id.type !== 'Identifier' || variable.init?.type !== 'ObjectExpression') continue;
        for (const variant of variable.init.properties) {
          if (variant.type !== 'ObjectProperty') continue;
          if (['ArrowFunctionExpression', 'FunctionExpression'].includes(variant.value.type)) {
            throw new Error(`Dynamic style ${module}.${variable.id.name} requires a consumer recipe adapter.`);
          }
          if (variant.value.type !== 'ObjectExpression') continue;
          const marker = variant.value.properties.find(p => p.type === 'ObjectProperty' && (p.key.name ?? p.key.value) === '$$css');
          if (!marker || marker.value.type !== 'BooleanLiteral' || !marker.value.value) continue;
          const properties = {};
          for (const property of variant.value.properties) {
            if (property.type !== 'ObjectProperty' || property.computed) throw new Error(`Unsupported compiled property in ${id}`);
            const key = property.key.name ?? property.key.value;
            if (key === '$$css') continue;
            if (property.value.type === 'StringLiteral') properties[key] = property.value.value;
            else if (property.value.type === 'NullLiteral') properties[key] = null;
            else throw new Error(`Unsupported conflict value ${key} in ${id}`);
          }
          const name = variant.key.name ?? variant.key.value;
          const entry = { kind: 'compiled', className: [...new Set(Object.values(properties).filter(v => v !== null))].join(' '), properties };
          entries[`${namespace}/${module}.${variable.id.name}.${name}`] = entry;
          if (legacyAliases) {
            const alias = `${basename}.${name}`;
            if (entries[alias]) throw new Error(`Ambiguous legacy alias ${alias}; disable legacyAliases.`);
            entries[alias] = entry;
          }
        }
      }
    }
    return entries;
  }

  function snapshot(artifacts = []) {
    const styles = {};
    for (const [id, entries] of [...modules].sort(([a], [b]) => a.localeCompare(b))) {
      for (const [key, entry] of Object.entries(entries).sort(([a], [b]) => a.localeCompare(b))) {
        if (styles[key]) throw new Error(`Duplicate StyleX key ${key} from ${id}`);
        styles[key] = entry;
      }
    }
    return { version: '2.0', producer: { name: 'skom/stylex-connector', version: '2.0' },
      compiler: { name: '@stylexjs/stylex', version: compilerVersion },
      capabilities: ['static-conflicts', 'null-clearing'],
      generation: digest(JSON.stringify({ styles, artifacts })), artifacts, styles };
  }

  function publish(artifacts = []) {
    const json = JSON.stringify(snapshot(artifacts), null, 2) + '\n';
    fs.mkdirSync(path.dirname(outputPath), { recursive: true });
    const temporary = `${outputPath}.${process.pid}.tmp`;
    try { fs.writeFileSync(temporary, json); fs.renameSync(temporary, outputPath); }
    finally { if (fs.existsSync(temporary)) fs.unlinkSync(temporary); }
    server?.ws.send({ type: 'full-reload' });
  }

  function schedule() {
    if (!server) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      try { publish(); } catch (error) {
        server.config.logger.error(error.message);
        server.ws.send({ type: 'error', err: { message: error.message, stack: error.stack } });
      }
    }, 100);
  }

  return {
    name: 'stylex-manifest', enforce: 'post',
    buildStart() { if (!server) modules.clear(); failed = false; },
    transform(code, id) {
      const clean = canonicalId(id);
      if (!selected(clean)) return null;
      // Parse before replacing the previous module, preserving valid output on failure.
      const entries = collect(code, clean);
      const previous = modules.get(clean);
      modules.set(clean, entries);
      try { snapshot(); } catch (error) {
        if (previous) modules.set(clean, previous); else modules.delete(clean);
        throw error;
      }
      schedule();
      return null;
    },
    buildEnd(error) { failed = Boolean(error); clearTimeout(timer); },
    writeBundle(options, bundle) {
      if (failed) return;
      // Runs only after Rollup writes a successful bundle. Hash physical CSS, not stale guesses.
      const directory = path.resolve(options.dir ?? path.dirname(options.file));
      const artifacts = Object.values(bundle).filter(item => item.type === 'asset' && item.fileName.endsWith('.css'))
        .map(item => ({ path: path.relative(path.dirname(outputPath), path.join(directory, item.fileName)).replaceAll('\\', '/'),
          sha256: digest(fs.readFileSync(path.join(directory, item.fileName))) })).sort((a, b) => a.path.localeCompare(b.path));
      publish(artifacts);
    },
    configureServer(viteServer) {
      server = viteServer;
      const unlink = file => { if (modules.delete(canonicalId(file))) schedule(); };
      server.watcher.on('unlink', unlink);
      server.httpServer?.once('close', () => { clearTimeout(timer); server.watcher.off('unlink', unlink); });
    },
    async handleHotUpdate({ file, server: viteServer }) {
      if (!selected(moduleId(file))) return;
      clearTimeout(timer);
      await viteServer.transformRequest(file);
      publish();
    },
  };
}
