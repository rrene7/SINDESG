'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
    constructor() {
        this.children = [];
        this.listeners = {};
        this.value = '';
        this.hidden = false;
        this.checked = false;
        this.dataset = {};
        this._text = '';
        this._html = '';
    }
    set innerHTML(v) { this._html = v; this.children = []; }
    get innerHTML() { return this._html; }
    set textContent(v) { this._text = v; this.children = []; }
    get textContent() { return this._text; }
    addEventListener(name, fn) { this.listeners[name] = fn; }
    emit(name, event = {}) { this.listeners[name]?.(event); }
    append(...children) { this.children.push(...children); }
    appendChild(item) { this.children.push(item); }
}
const selectors = [
    '[data-unit-search]', '[data-unit-id]', '[data-unit-results]', '[data-unit-selection]',
    '[data-p4-subunit-search]', '[data-p4-subunit-id]', '[data-p4-subunit-results]',
    '[data-p4-subunit-selection]', '[data-p4-employee-search]', '[data-p4-employee-id]',
    '[data-p4-employee-results]', '[data-p4-employee-selection]',
    '[data-p4-subunit-section]', '[data-p4-subunit-area]', '[data-p4-use-subunit]'
];
const elements = Object.fromEntries(selectors.map(s => [s, new Element()]));
elements['[data-p4-subunit-section]'].hidden = true;
elements['[data-p4-subunit-area]'].hidden = true;

const unitRoot = {
    dataset: { searchUrl: '/SINDESG/public/api/units.php?scope=p4' },
    querySelector: sel => elements[sel],
    contains: () => true,
};
const childRoot = {
    dataset: { searchUrl: '/SINDESG/public/api/units.php?scope=p4-subunits' },
    querySelector: sel => elements[sel],
    contains: () => true,
};
const employeeRoot = {
    dataset: { searchUrl: '/SINDESG/public/api/employees.php' },
    querySelector: sel => elements[sel],
    contains: () => true,
};
const roots = {
    '[data-unit-picker]': unitRoot,
    '[data-p4-subunit-picker]': childRoot,
    '[data-p4-employee-picker]': employeeRoot
};
const document = {
    querySelector: sel => elements[sel] || roots[sel] || null,
    createElement: () => new Element(),
    addEventListener: () => {},
};
let nextId = 0;
const timers = new Map();
const window = {
    location: { href: 'http://localhost/SINDESG/?module=warehouses&action=new' },
    setTimeout(fn, ms) { const id = ++nextId; timers.set(id, { fn, ms }); return id; },
    clearTimeout(id) { timers.delete(id); }
};
const fire = (ms) => {
    [...timers].forEach(([id, task]) => {
        if (task.ms === ms) { timers.delete(id); task.fn(); }
    });
};
const pending = [];
const fetch = (url, options) => new Promise((resolve, reject) => {
    pending.push({ url, options, resolve, reject });
    options.signal.addEventListener('abort', () => {
        const error = new Error('Aborted');
        error.name = 'AbortError';
        reject(error);
    }, { once: true });
});
const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/warehouses.js'), 'utf8');
vm.runInNewContext(source, { document, window, fetch, URL, AbortController });
const flush = () => new Promise(resolve => setImmediate(resolve));
const el = sel => elements[sel];

(async () => {
    el('[data-unit-search]').value = 'penitenciaria';
    el('[data-unit-search]').emit('input');
    fire(260);
    await flush();
    assert.equal(pending.length, 1);
    pending[0].resolve({ ok: true, json: async () => ({
        ok: true, items: [{ id: 39, official_name: 'Dirección Penitenciaria',
            unit_type: 'direccion_nacional', system_code: 'DIR-000039' }]
    }) });
    await flush();
    assert.equal(el('[data-unit-results]').children.length, 1);
    el('[data-unit-results]').children[0].emit('click');
    assert.equal(el('[data-unit-id]').value, 39);
    assert.equal(el('[data-p4-subunit-section]').hidden, false);

    const toggle = el('[data-p4-use-subunit]');
    toggle.checked = true;
    toggle.emit('change');
    await flush();
    assert.equal(el('[data-p4-subunit-area]').hidden, false);
    assert.equal(pending.length, 2);
    const url = new URL(pending[1].url);
    assert.equal(url.searchParams.get('scope'), 'p4-subunits');
    assert.equal(url.searchParams.get('parent_id'), '39');
    assert.equal(url.searchParams.get('q'), '');
    pending[1].resolve({ ok: true, json: async () => ({
        ok: true, items: [{ id: 402, parent_id: 39, official_name: 'Centro Penitenciario A',
            unit_type: 'centro_penitenciario', system_code: 'CEN-402', depth: 1 }]
    }) });
    await flush();
    assert.equal(el('[data-p4-subunit-results]').children.length, 1);
    el('[data-p4-subunit-results]').children[0].emit('click');
    assert.equal(el('[data-p4-subunit-id]').value, 402);

    // Changing the institutional head invalidates the subordinate selection.
    el('[data-unit-search]').value = 'otra direccion';
    el('[data-unit-search]').emit('input');
    assert.equal(el('[data-unit-id]').value, '');
    assert.equal(el('[data-p4-subunit-id]').value, '');
    assert.equal(toggle.checked, false);
    assert.equal(el('[data-p4-subunit-area]').hidden, true);
    assert.equal(el('[data-p4-subunit-section]').hidden, true);

    console.log('[OK] P4 selector: cabecera, dependencia opcional, alcance y limpieza.');
})().catch(error => { console.error(error); process.exitCode = 1; });
