#!/usr/bin/env node
/**
 * Obfuscate src.js but keep `const config = {...}` and `const ICON_BUTTONS = [...]` unobfuscated.
 * Input : ./src.js
 * Output: ./dist/custom.obf.js (+ custom.obf.js.map)
 */

const fs = require('fs');
const path = require('path');
const JavaScriptObfuscator = require('javascript-obfuscator');

const INPUT  = path.resolve(process.cwd(), 'src.js');
const OUTDIR = path.resolve(process.cwd(), 'dist');
const OUTJS  = path.join(OUTDIR, 'custom.obf.js');

if (!fs.existsSync(INPUT)) {
    console.error(`[ERR] Не найден файл ${INPUT}`);
    process.exit(1);
}

const src = fs.readFileSync(INPUT, 'utf8');

/**
 * Извлекает объявление константы: const <name> = <STRUCT>;  (STRUCT: {...} или [...])
 * Корректно обрабатывает строки/шаблоны/экраны и баланс { } [ ] ( ).
 * Возвращает { block: 'текст объявления с ;', rest: 'исходник без объявления' } или null, если не найдено.
 */
function extractConstDeclaration(source, name) {
    const re = new RegExp(`\\bconst\\s+${name}\\s*=`, 'm');
    const m = re.exec(source);
    if (!m) return null;

    const start = m.index;                    // начало 'const name ='
    let i = m.index + m[0].length;            // позиция после '='
    const n = source.length;

    // пропустим пробелы
    while (i < n && /\s/.test(source[i])) i++;

    // ожидаем структуру { ... } или [ ... ]
    const first = source[i];
    if (first !== '{' && first !== '[') {
        // fallback: найдём до первой ; (на случай присваивания функции и т.п.)
        const semi = source.indexOf(';', i);
        const endIdx = semi >= 0 ? semi + 1 : n;
        const block = source.slice(start, endIdx);
        const rest  = source.slice(0, start) + source.slice(endIdx);
        return { block, rest };
    }

    // Парсинг с учётом строк/экрана
    let depthCurly = 0;
    let depthSquare = 0;
    let depthParen = 0;
    let inStr = false, strQuote = '', escaped = false;
    let inTpl = false; // template literal `
    let j = i;

    const pushChar = (ch) => {
        if (inStr) {
            if (escaped) { escaped = false; return; }
            if (ch === '\\') { escaped = true; return; }
            if (!inTpl && ch === strQuote) { inStr = false; strQuote = ''; return; }
            // template strings могут содержать ${ ... }, но мы учитываем только `...`
            if (inTpl && ch === '`') { inStr = false; inTpl = false; strQuote = ''; return; }
            return;
        } else {
            if (ch === '"' || ch === "'") { inStr = true; strQuote = ch; return; }
            if (ch === '`') { inStr = true; inTpl = true; strQuote = '`'; return; }
            if (ch === '{') depthCurly++;
            else if (ch === '}') depthCurly--;
            else if (ch === '[') depthSquare++;
            else if (ch === ']') depthSquare--;
            else if (ch === '(') depthParen++;
            else if (ch === ')') depthParen--;
        }
    };

    // инициализируем стартовую глубину (учитывая первый символ)
    pushChar(first);
    j++;

    for (; j < n; j++) {
        pushChar(source[j]);
        // выходим, когда все глубины закрылись и следующий значимый символ — ';'
        if (!inStr && depthCurly === 0 && depthSquare === 0 && depthParen === 0) {
            // найдём точку с запятой
            let k = j + 1;
            while (k < n && /\s/.test(source[k])) k++;
            if (source[k] === ';') {
                const endIdx = k + 1;
                const block = source.slice(start, endIdx);
                const rest  = source.slice(0, start) + source.slice(endIdx);
                return { block, rest };
            }
        }
    }

    // если не нашли ; — возьмём до конца файла
    const block = source.slice(start);
    const rest  = source.slice(0, start);
    return { block, rest };
}

// 1) Пытаемся вынуть config и ICON_BUTTONS
let working = src;
let keptBlocks = '';

const configRes = extractConstDeclaration(working, 'config');
if (configRes) {
    keptBlocks += configRes.block + '\n\n';
    working = configRes.rest;
}

const iconsRes = extractConstDeclaration(working, 'ICON_BUTTONS');
if (iconsRes) {
    keptBlocks += iconsRes.block + '\n\n';
    working = iconsRes.rest;
}

// 2) Обфускация "остального" кода
const obfOptions = {
    compact: true,
    controlFlowFlattening: true,
    controlFlowFlatteningThreshold: 0.75,
    deadCodeInjection: true,
    deadCodeInjectionThreshold: 0.2,
    disableConsoleOutput: false,
    identifierNamesGenerator: 'mangled',
    numbersToExpressions: true,
    renameGlobals: true,
    reservedNames: [
        // чтобы ссылки на эти идентификаторы не поломались
        '^config$', '^ICON_BUTTONS$'
    ],
    selfDefending: true,
    simplify: true,
    splitStrings: true,
    splitStringsChunkLength: 8,
    stringArray: true,
    stringArrayEncoding: ['rc4'],
    stringArrayThreshold: 0.75,
    target: 'browser',
    transformObjectKeys: true,
    unicodeEscapeSequence: false,

    // sourcemap:
    sourceMap: true,
    sourceMapMode: 'separate',
    sourceMapBaseUrl: '',
    sourceMapFileName: path.basename(OUTJS) + '.map',
};

const obfuscated = JavaScriptObfuscator.obfuscate(working, obfOptions);

const finalCode =
    '/* === PLAIN CONFIG (do not obfuscate) === */\n' +
    keptBlocks +
    '\n/* === OBFUSCATED APP CODE === */\n' +
    obfuscated.getObfuscatedCode();

if (!fs.existsSync(OUTDIR)) fs.mkdirSync(OUTDIR, { recursive: true });
fs.writeFileSync(OUTJS, finalCode, 'utf8');

// sourcemap (separate)
const mapPath = OUTJS + '.map';
const mapContent = obfuscated.getSourceMap();
if (mapContent) {
    fs.writeFileSync(mapPath, mapContent, 'utf8');
}

console.log(`[OK] Обфускация готова:
  input : ${INPUT}
  output: ${OUTJS}
  map   : ${mapPath}`);
if (!configRes) console.warn('[WARN] Блок "const config = ..." не найден — весь код обфусцирован.');
if (!iconsRes)  console.warn('[WARN] Блок "const ICON_BUTTONS = ..." не найден — весь код обфусцирован (или ICON_BUTTONS отсутствует).');
