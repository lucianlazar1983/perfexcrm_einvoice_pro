import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const settingsScript = readFileSync(
    new URL('../assets/js/settings.js', import.meta.url),
    'utf8'
);
const settingsView = readFileSync(
    new URL('../views/settings/settings.php', import.meta.url),
    'utf8'
);

assert.equal(
    /\.innerHTML\s*=/.test(settingsScript),
    false,
    'The settings script must not parse note values as HTML.'
);
assert.equal(
    /JSON\.parse\s*\(/.test(settingsScript),
    false,
    'jQuery must decode application/json responses instead of parsing unchecked text.'
);
assert.match(
    settingsScript,
    /text\.textContent\s*=\s*value/,
    'Custom note rows must use textContent.'
);
assert.equal(
    /<script(?!\s+src=)/i.test(settingsView),
    false,
    'The settings view must not introduce inline JavaScript.'
);

process.stdout.write('Frontend security checks passed.\n');
