'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
    constructor() {
        this.children = [];
        this.listeners = new Map();
        this.hidden = false;
        this.value = '';
        this.dataset = {};
        this._text = '';
        this._html = '';
    }
    set innerHTML(value) { this._html = value; this.children = []; }
    get innerHTML() { return this._html; }
    set textContent(value) { this._text = value; this.children = []; }
    get textContent() { return this._text; }
    appendChild(child) { this.children.push(child); }
    append(...elements) { this.children.push(...elements); }
    addEventListener(name, callback) { this.listeners.set(name, callback); }
    emit(name, event = {}) { this.listeners.get(name)?.(event); }
}

const input = new Element();
const hidden = new Element();
const results = new Element();
const selection = new Element();
const elements = {
    '[data-security-search]': input,
    '[data-security-id]': hidden,
    '[data-security-results]': results,
    '[data-security-selection]': selection,
};
const root = {
    dataset: { searchUrl: '/SINDESG/public/api/employees.php' },
    querySelector: name => elements[name] || null,
    contains: () => true,
};
let nextTimerId = 0;
const timers = new Map();
const window = {
    setTimeout(fn, delay) {
        const id = ++nextTimerId;
        timers.set(id, { fn, delay });
        return id;
    },
    clearTimeout(id) { timers.delete(id); },
};
const fire = delay => {
    for (const [id, timer] of [...timers]) {
        if (timer.delay === delay) {
            timers.delete(id);
            timer.fn();
        }
    }
};

const requests = [];
const fetch = (url, options) => new Promise((resolve, reject) => {
    requests.push({ url, options, resolve, reject });
    options.signal.addEventListener('abort', () => {
        const error = new Error('Aborted');
        error.name = 'AbortError';
        reject(error);
    }, { once: true });
});
const document = {
    querySelectorAll(name) {
        return name === '[data-security-picker]' ? [root] : [];
    },
    createElement() { return new Element(); },
    addEventListener() {},
};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/security.js'), 'utf8');
vm.runInNewContext(source, {
    document, window, fetch, AbortController, encodeURIComponent,
}, { filename: 'security-picker.js' });
const settle = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    input.value = '46857';
    input.emit('input');
    fire(260);
    await settle();
    assert.equal(requests.length, 1);
    assert.match(requests[0].url, /q=46857$/);
    assert.equal(results.children[0].textContent, 'Buscando en RRHH2029...');

    // Change input before previous HTTP finishes. Cancel it immediately.
    input.value = '46858';
    input.emit('input');
    assert.equal(requests[0].options.signal.aborted, true);
    fire(260);
    await settle();
    assert.equal(requests.length, 2);
    requests[1].resolve({
        ok: true,
        json: async () => ({ ok: true, items: [
            { employee_id: 25, display_name: 'FUNCIONARIO PRUEBA',
              document_number: '8-12-34', position_number: '46858' },
        ] }),
    });
    await settle();
    assert.equal(results.children.length, 1);
    assert.equal(results.children[0].children[0].textContent, 'FUNCIONARIO PRUEBA');
    results.children[0].emit('click');
    assert.equal(hidden.value, 25);
    assert.equal(input.value, 'FUNCIONARIO PRUEBA');
    assert.equal(selection.hidden, false);

    // A pending search cannot leave a permanent spinner.
    input.value = '46859';
    input.emit('input');
    assert.equal(hidden.value, '');
    fire(260);
    await settle();
    assert.equal(requests.length, 3);
    fire(8500);
    await settle();
    assert.equal(requests[2].options.signal.aborted, true);
    assert.match(results.children[0].textContent, /superó 8,5 segundos/);

    // Deleting search input cancels request, hides stale result.
    input.value = '46860';
    input.emit('input');
    fire(260);
    await settle();
    assert.equal(requests.length, 4);
    input.value = '';
    input.emit('input');
    assert.equal(requests[3].options.signal.aborted, true);
    assert.equal(results.hidden, true);

    console.log('[OK] Administración RRHH: búsqueda vigente, cancelación y timeout.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
