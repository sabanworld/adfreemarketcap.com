/*
 * Builds the self-hosted Material Symbols Rounded subset behind `<x-afmc.icon>`.
 *
 * The upstream variable font is 5.3 MB because it carries every icon in the set,
 * and subsetting by ligature name keeps all of them (every name is spelled with
 * plain letters). So the subset is built from the icon codepoints instead, and
 * resources/fonts/material-symbols.json records the codepoint per icon name for
 * App\Support\Icons to read.
 *
 * Self-hosting keeps visitor IP addresses away from Google's font CDN, which the
 * privacy and cookie policies both rely on. See docs/privacy-and-legal.md.
 *
 * Add an icon by putting its name in the JSON with an empty value, then run:
 *   ./vendor/bin/sail yarn icons:build
 */
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import * as fontkit from 'fontkit';
import subsetFont from 'subset-font';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const source = resolve(root, 'node_modules/material-symbols/material-symbols-rounded.woff2');
const manifestPath = resolve(root, 'resources/fonts/material-symbols.json');
const target = resolve(root, 'resources/fonts/material-symbols-rounded-subset.woff2');

const original = await readFile(source);
const font = fontkit.create(original);
const names = Object.keys(JSON.parse(await readFile(manifestPath, 'utf8'))).sort();

const glyphToCodePoint = new Map();
for (const codePoint of font.characterSet) {
    const glyph = font.glyphForCodePoint(codePoint);

    if (glyph && ! glyphToCodePoint.has(glyph.id)) {
        glyphToCodePoint.set(glyph.id, codePoint);
    }
}

const manifest = {};
for (const name of names) {
    const glyphs = font.layout(name).glyphs;
    const codePoint = glyphs.length === 1 ? glyphToCodePoint.get(glyphs[0].id) : undefined;

    if (! codePoint) {
        throw new Error(`Unknown Material Symbols icon: ${name}`);
    }

    manifest[name] = codePoint.toString(16);
}

const text = Object.values(manifest)
    .map((hex) => String.fromCodePoint(parseInt(hex, 16)))
    .join('');

const subset = await subsetFont(original, text, { targetFormat: 'woff2' });

const missing = Object.entries(manifest).filter(([, hex]) => {
    const glyph = fontkit.create(subset).glyphForCodePoint(parseInt(hex, 16));

    return ! glyph || glyph.path.commands.length === 0;
});

if (missing.length > 0) {
    throw new Error(`Glyph lost in subset: ${missing.map(([name]) => name).join(', ')}`);
}

await writeFile(manifestPath, `${JSON.stringify(manifest, null, 4)}\n`);
await writeFile(target, subset);

const kb = (bytes) => `${(bytes / 1024).toFixed(1)} kB`;

console.log(`${names.length} icons, ${kb(original.length)} -> ${kb(subset.length)}`);
console.log(`Wrote ${target}`);
