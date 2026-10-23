'use strict';

// Real distribution browser logic, executed in a minimal DOM harness with no dependencies.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(require('node:path').join(__dirname, '../public/assets/js/distribution.js'), 'utf8');
const start = source.indexOf('/* Distribución: resumen por artículo y talla');
assert.ok(start >= 0, 'Missing browser selection implementation');

function control(value = '') {
    return {
        value,
        textContent: '',
        dataset: {},
        handlers: {},
        addEventListener(event, handler) { (this.handlers[event] ||= []).push(handler); },
        fire(event, payload = {}) {
            (this.handlers[event] || []).forEach(fn => fn(payload));
        }
    };
}

function createGroup(variant, pending, stock, lines) {
    const field = control('0');
    const applyButton = control();
    const maxButton = control();
    const selected = control();
    const rows = Array.from({length: lines}, () => {
        const row = control('0');
        row.dataset = {variantId: String(variant), max: '1.000'};
        return row;
    });
    const parts = {
        '[data-transfer-group-quantity]': field,
        '[data-transfer-apply]': applyButton,
        '[data-transfer-max-group]': maxButton,
        '[data-transfer-selected]': selected
    };
    return {
        dataset: {variantId: String(variant), pending: String(pending), stock: String(stock)},
        querySelector(selector) { return parts[selector] || null; },
        field, applyButton, maxButton, selected, rows
    };
}

const first = createGroup(10, 2, 2, 2);
const second = createGroup(11, 3, 1, 3);
const groups = [first, second];
const rows = groups.flatMap(group => group.rows);
const summary = control();
const selectedGlobal = control();
const validateButton = control();
const selectAllButton = control();
const clearAllButton = control();
const submitButton = control();
const parts = {
    '[data-transfer-message]': summary,
    '[data-transfer-global-selected]': selectedGlobal,
    '[data-transfer-validate]': validateButton,
    '[data-transfer-select-all]': selectAllButton,
    '[data-transfer-clear-all]': clearAllButton
};
const form = control();
form.querySelector = selector => parts[selector] || null;
form.querySelectorAll = selector => selector === '[data-transfer-group]'
    ? groups : selector === '[data-transfer-item]' ? rows : [];
let confirms = 0;
const browser = {
    document: {
        readyState: 'complete',
        querySelector: selector => selector === '[data-distribution-grouped]' ? form : null
    },
    window: {confirm: () => { confirms++; return true; }}
};
vm.runInNewContext(source.slice(start), browser, {filename:'distribution.js'});

assert.ok(rows.every(x => x.value === '0'), 'Never preselect automatically');
let blocked = false;
form.fire('submit', {preventDefault() { blocked = true; }});
assert.equal(blocked, true, 'Must not prepare before validate');

selectAllButton.fire('click');
assert.deepEqual(rows.map(row => Number(row.value)), [1,1,1,0,0],
    'Bulk selection must assign only pending nominal rows under group stock');
assert.equal(Number(selectedGlobal.textContent), 3);
assert.match(summary.textContent, /2 unidades permanecen sin seleccionar/);
assert.equal(summary.dataset.validated, 'no', 'Bulk selection cannot silently validate');

validateButton.fire('click');
assert.equal(summary.dataset.validated, 'yes');
blocked = false;
form.fire('submit', {preventDefault() { blocked = true; }});
assert.equal(blocked, false, 'Validated eligible selection should be submittable after confirmation');
assert.equal(confirms, 1, 'Preparing must ask for final confirmation');

clearAllButton.fire('click');
assert.ok(rows.every(row => Number(row.value) === 0), 'Clear must reset all nominal rows');
assert.ok(groups.every(group => Number(group.field.value) === 0));
assert.equal(Number(selectedGlobal.textContent), 0);
assert.equal(summary.dataset.validated, 'no');
blocked = false;
form.fire('submit', {preventDefault() { blocked = true; }});
assert.equal(blocked, true, 'Cleared selection may not reuse prior validation');

second.maxButton.fire('click');
assert.deepEqual(second.rows.map(row => Number(row.value)), [1,0,0]);
assert.equal(Number(second.field.value), 1);
assert.match(summary.textContent, /Talla incompleta por stock/);

first.maxButton.fire('click');
assert.deepEqual(first.rows.map(row => Number(row.value)), [1,1]);
assert.equal(Number(selectedGlobal.textContent), 3);

first.rows[1].value = '0.000';
first.rows[1].fire('input');
assert.equal(Number(first.field.value), 1, 'Manual beneficiary override should adjust group total');
assert.equal(Number(selectedGlobal.textContent), 2, 'Manual override should update global total');
assert.equal(summary.dataset.validated, 'no', 'Manual edit invalidates prior validation');
validateButton.fire('click');
assert.equal(summary.dataset.validated, 'yes');

console.log('[OK] Selección masiva y por talla en un clic, límites de stock, limpieza, asignación nominal y confirmación sin preparación automática.');
