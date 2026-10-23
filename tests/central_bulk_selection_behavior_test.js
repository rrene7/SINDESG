'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/requests.js'), 'utf8');
const first = source.indexOf('/* Central: validación local SIN POST');
const last = source.indexOf('/* Vista nominal:', first);
assert.ok(first > 0 && last > first, 'Central script missing');

function control(value = '') {
    return {
        value, textContent: '', checked: false, dataset: {}, handlers: {},
        addEventListener(type, callback) { (this.handlers[type] ||= []).push(callback); },
        fire(type, evt = {}) { for (const handler of this.handlers[type] || []) handler(evt); }
    };
}
function group(variant, requested, available, count) {
    const quantity = control('0');
    const applyButton = control();
    const completeButton = control();
    const selected = control();
    const items = Array.from({length: count}, () => {
        const row = control('0');
        row.dataset = {variantId: String(variant), requested: '1.000'};
        return row;
    });
    const found = {
        '[data-central-quantity]': quantity,
        '[data-central-apply]': applyButton,
        '[data-central-complete-group]': completeButton,
        '[data-central-selected]': selected
    };
    return {
        dataset: {variantId: String(variant), centralRequested: String(requested),
            variantAvailable: String(available)},
        querySelector(selector) { return found[selector] || null; },
        quantity, applyButton, completeButton, items
    };
}
const groups = Array.from({length: 17}, (_, i) => {
    const count = i === 16 ? 50 : 42;
    const available = i === 16 ? 49 : 42;
    return group(i + 1, count, available, count);
});
const items = groups.flatMap(g => g.items);
assert.equal(items.length, 722);

const summary = control();
const bulkFeedback = control();
const validateButton = control();
const completeAllButton = control();
const clearAllButton = control();
const partialCheck = control();
const rejectCheck = control();
const notes = control('Observación fundada y detallada');
const form = control();
const selectors = {
    '[data-central-message]': summary,
    '[data-central-validate]': validateButton,
    '[data-central-complete-all]': completeAllButton,
    '[data-central-clear-all]': clearAllButton,
    '[data-central-bulk-feedback]': bulkFeedback,
    '[data-central-partial-check]': partialCheck,
    '[data-central-reject-check]': rejectCheck,
    '[name="review_notes"]': notes
};
form.querySelector = selector => selectors[selector] || null;
form.querySelectorAll = selector => {
    if (selector === '[data-central-variant]') return groups;
    if (selector === '[data-central-item]') return items;
    return [];
};
let confirmationCount = 0;
vm.runInNewContext(source.slice(first, last), {
    document: {
        readyState: 'complete',
        querySelector: selector => selector === '[data-central-review]' ? form : null
    },
    window: { confirm: () => { confirmationCount++; return true; } }
}, {filename: 'requests.js'});

function submit(action) {
    let blocked = false;
    form.fire('submit', {
        submitter: {value: action},
        preventDefault() { blocked = true; }
    });
    return blocked;
}
assert.equal(items.filter(x => Number(x.value) !== 0).length, 0,
    'No nominal quantity must be preselected');
assert.equal(submit('approve'), true, 'Submit without validation must fail');

completeAllButton.fire('click');
assert.equal(items.filter(x => Number(x.value) > 0).length, 721,
    '722 nominal rows minus one unavailable must be selected');
assert.equal(groups.reduce((sum, group) => sum + Number(group.quantity.value), 0), 721);
assert.match(bulkFeedback.textContent, /faltan 1 unidad/);
assert.match(bulkFeedback.textContent, /SIN GUARDAR/);
assert.equal(summary.dataset.checked, 'no', 'Bulk must never mark validation as complete');
assert.equal(submit('approve'), true, 'Bulk alone must never allow submitting');

validateButton.fire('click');
assert.equal(summary.dataset.checked, 'yes');
assert.equal(submit('approve'), true,
    'Partial approval must require explicit nominal confirmation');
partialCheck.checked = true;
assert.equal(submit('approve'), false, 'Partial should require final browser confirmation');
assert.equal(confirmationCount, 1);

clearAllButton.fire('click');
assert.ok(items.every(x => Number(x.value) === 0));
assert.ok(groups.every(g => Number(g.quantity.value) === 0));
assert.equal(summary.dataset.checked, 'no');
assert.equal(submit('approve'), true, 'Clear invalidates previously validated selection');

groups[16].completeButton.fire('click');
assert.equal(Number(groups[16].quantity.value), 49);
assert.equal(groups[16].items.filter(x => Number(x.value) > 0).length, 49);
assert.match(bulkFeedback.textContent, /Talla limitada por stock/);
assert.equal(summary.dataset.checked, 'no');

groups[16].items[0].value = '0.000';
groups[16].items[0].fire('input');
assert.equal(Number(groups[16].quantity.value), 48);
assert.equal(summary.dataset.checked, 'no');
validateButton.fire('click');
assert.equal(summary.dataset.checked, 'yes');

console.log('[OK] Central: 722 destinatarios, completar todo hasta 721, talla limitada, limpiar, editar nominal, validacion y aprobacion parcial protegidas.');
