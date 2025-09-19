#!/usr/bin/env node
/**
 * CSS obfuscator/minifier
 * Usage:
 *   node css-obfuscate.js src.css dist/style.obf.css --mangle --map --reserve obf-reserved.json
 *
 * - Минифицирует CSS (cssnano).
 * - Если указан --mangle, переименует .class / #id / @keyframes (и их использования).
 * - Резервы: файл JSON с whitelist для классов/ID/кейфреймов (точные строки или regex).
 * - Пишет mapping в dist/obf-map.json.
 */

const fs = require('fs-extra');
const path = require('path');
const postcss = require('postcss');
const cssnano = require('cssnano');
const selectorParser = require('postcss-selector-parser');
const valueParser = require('postcss-value-parser');

const argv = process.argv.slice(2);

// --- CLI args ---
const input  = path.resolve(process.cwd(), argv[0] || 'src.css');
const output = path.resolve(process.cwd(), argv[1] || 'dist/style.obf.css');
const outdir = path.dirname(output);

const has = (flag) => argv.includes(flag);
const mangle = has('--mangle');
const genMap = has('--map');

const reserveFileIdx = argv.findIndex(a => a === '--reserve');
const reserveFile = reserveFileIdx >= 0 ? path.resolve(process.cwd(), argv[reserveFileIdx + 1]) : null;

// --- Reserved config (whitelist) ---
/**
 * Формат obf-reserved.json:
 * {
 *   "classes": ["bonuses_table", "boxBody"],
 *   "ids": ["appRoot"],
 *   "keyframes": ["spin"],
 *   "classPatterns": ["^-?\\w+-module__.*$"],   // regex в виде строк
 *   "idPatterns": ["^root-"]
 * }
 */
let reserved = {
    classes: [],
    ids: [],
    keyframes: [],
    classPatterns: [
        // по умолчанию — много проектов на CSS Modules, не трогаем их
        '^-?\\w+-module__.*$'
    ],
    idPatterns: []
};

if (reserveFile && fs.existsSync(reserveFile)) {
    try {
        const user = JSON.parse(fs.readFileSync(reserveFile, 'utf8'));
        for (const k of Object.keys(user)) {
            if (Array.isArray(user[k])) reserved[k] = user[k];
        }
    } catch (e) {
        console.warn('[WARN] Не удалось прочитать reserve JSON:', e.message);
    }
}

// Компилируем regex
const classRegexes = reserved.classPatterns.map(s => new RegExp(s));
const idRegexes = reserved.idPatterns.map(s => new RegExp(s));

const isReservedClass = (name) =>
    reserved.classes.includes(name) || classRegexes.some(r => r.test(name));
const isReservedId = (name) =>
    reserved.ids.includes(name) || idRegexes.some(r => r.test(name));
const isReservedKeyframes = (name) =>
    reserved.keyframes.includes(name);

// --- Name generator (a..z, A..Z, aa, ab, ...) ---
const alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
function encodeNumber(num) {
    let s = '';
    const base = alphabet.length;
    do { s = alphabet[num % base] + s; num = Math.floor(num / base) - 1; } while (num >= 0);
    return s;
}
function* nameGenerator(prefix='') {
    let i = 0;
    while (true) yield prefix + encodeNumber(i++);
}
const classGen = nameGenerator('');
const idGen = nameGenerator('_'); // отличим id, но это не обязательно
const kfGen = nameGenerator('k');

// --- Mappings ---
const classMap = new Map();     // orig -> mangled
const idMap = new Map();
const keyframesMap = new Map();

// Получить / сгенерировать маппинг
function getClassName(name) {
    if (!mangle || isReservedClass(name)) return name;
    if (!classMap.has(name)) classMap.set(name, classGen.next().value);
    return classMap.get(name);
}
function getIdName(name) {
    if (!mangle || isReservedId(name)) return name;
    if (!idMap.has(name)) idMap.set(name, idGen.next().value);
    return idMap.get(name);
}
function getKeyframesName(name) {
    if (!mangle || isReservedKeyframes(name)) return name;
    if (!keyframesMap.has(name)) keyframesMap.set(name, kfGen.next().value);
    return keyframesMap.get(name);
}

// --- PostCSS plugin: mangle selectors + keyframes ---
const manglePlugin = postcss.plugin('mangle-plugin', () => {
    return (root) => {
        // 1) @keyframes (включая вендорные)
        root.walkAtRules((at) => {
            const nm = at.name.toLowerCase();
            if (nm === 'keyframes' || nm === '-webkit-keyframes') {
                const old = at.params.trim().replace(/^(['"])(.*)\1$/, '$2');
                const neo = getKeyframesName(old);
                if (neo !== old) at.params = at.params.replace(old, neo);
            }
        });

        // 2) селекторы: классы и id
        root.walkRules((rule) => {
            if (!rule.selector) return;
            const transformed = selectorParser((sel) => {
                sel.walkClasses((node) => {
                    const old = node.value;
                    node.value = getClassName(old);
                });
                sel.walkIds((node) => {
                    const old = node.value;
                    node.value = getIdName(old);
                });
            }).processSync(rule.selector);
            rule.selector = transformed;
        });

        // 3) использования @keyframes в декларациях
        root.walkDecls((decl) => {
            const prop = decl.prop && decl.prop.toLowerCase();
            if (prop !== 'animation' && prop !== 'animation-name') return;

            const parsed = valueParser(decl.value);
            parsed.walk((node) => {
                // animation-name: <ident>[, <ident>]*
                // animation: <name> <duration> ... , ...
                if (node.type === 'word') {
                    const old = node.value;
                    if (keyframesMap.has(old)) node.value = keyframesMap.get(old);
                }
            });
            decl.value = parsed.toString();
        });
    };
});

// --- Pipe сборки ---
async function run() {
    if (!fs.existsSync(input)) {
        console.error(`[ERR] Не найден входной CSS: ${input}`);
        process.exit(1);
    }
    const css = await fs.readFile(input, 'utf8');

    const plugins = [];
    if (mangle) plugins.push(manglePlugin());
    plugins.push(cssnano({ preset: 'default' }));

    const result = await postcss(plugins).process(css, {
        from: input,
        to: output,
        map: genMap ? { inline: false, annotation: true } : false
    });

    await fs.mkdirp(outdir);
    await fs.writeFile(output, result.css, 'utf8');
    if (result.map && genMap) {
        await fs.writeFile(output + '.map', result.map.toString(), 'utf8');
    }

    // mapping json
    const mapOut = {
        classes: Object.fromEntries(classMap),
        ids: Object.fromEntries(idMap),
        keyframes: Object.fromEntries(keyframesMap),
        reserved
    };
    await fs.writeFile(path.join(outdir, 'obf-map.json'), JSON.stringify(mapOut, null, 2), 'utf8');

    console.log(`[OK] CSS ${mangle ? 'обфусцирован и ' : ''}минифицирован:
  input : ${input}
  output: ${output}
  map   : ${genMap ? output + '.map' : '(no map)'}
  dict  : ${path.join(outdir, 'obf-map.json')}
  `);

    if (mangle) {
        console.log(`[NOTE] Включён --mangle:
  • Добавь в резерв (obf-reserved.json) селекторы, используемые в JS/HTML.
  • Или прогоняй HTML/JS заменителем по dict (см. ниже).`);
    }
}

run().catch((e) => {
    console.error('[ERR] Сборка упала:', e);
    process.exit(1);
});
