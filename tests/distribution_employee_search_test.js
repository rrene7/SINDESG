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
    append(...items) { this.children.push(...items); }
    addEventListener(name, callback) { this.listeners.set(name, callback); }
    emit(name, extra = {}) { this.listeners.get(name)?.(extra); }
}

const input = new Element();
const hiddenId = new Element();
const results = new Element();
const selection = new Element();
const elements = {
    '[data-distribution-search]': input,
    '[data-distribution-id]': hiddenId,
    '[data-distribution-results]': results,
    '[data-distribution-selection]': selection,
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
        return name === '[data-distribution-picker]' ? [root] : [];
    },
    createElement() { return new Element(); },
    addEventListener() {},
};
const source = fs.readFileSync(
    path.join(__dirname, '../public/assets/js/distribution.js'), 'utf8'
).split('/* Distribución: resumen')[0];
vm.runInNewContext(source, {
    document, window, fetch, AbortController, encodeURIComponent,
}, { filename: 'distribution-picker.js' });

const settle = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    input.value = '16972';
    input.emit('input');
    fire(260);
    await settle();
    assert.equal(requests.length, 1);
    assert.match(requests[0].url, /q=16972$/);
    assert.equal(results.children[0].textContent, 'Buscando en RRHH2029...');

    // Editing text cancels the active request immediately and invalidates it.
    input.value = '16973';
    input.emit('input');
    assert.equal(requests[0].options.signal.aborted, true);
    fire(260);
    await settle();
    assert.equal(requests.length, 2);
    requests[1].resolve({
        ok: true,
        json: async () => ({ ok: true, items: [
            { employee_id: 22, display_name: 'FUNCIONARIO PRUEBA',
              document_number: '8-77-55', position_number: '16973' },
        ] }),
    });
    await settle();
    assert.equal(results.children.length, 1);
    assert.equal(results.children[0].children[0].textContent, 'FUNCIONARIO PRUEBA');
    results.children[0].emit('click');
    assert.equal(hiddenId.value, 22);
    assert.equal(input.value, 'FUNCIONARIO PRUEBA');
    assert.equal(selection.hidden, false);

    // Timeout must not leave an endless "Buscando..." message.
    input.value = '16974';
    input.emit('input');
    fire(260);
    await settle();
    assert.equal(requests.length, 3);
    fire(8500);
    await settle();
    assert.equal(requests[2].options.signal.aborted, true);
    assert.match(results.children[0].textContent, /tardó demasiado/);

    console.log('[OK] Selector RRHH: cancelación, resultado vigente y límite de espera.');
})().catch(error => { console.error(error); process.exitCode = 1; });
