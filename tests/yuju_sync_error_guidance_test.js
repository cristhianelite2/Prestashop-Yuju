const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const template = fs.readFileSync('views/templates/admin/sync_info.tpl', 'utf8');
const start = template.indexOf('function getYujuSyncErrorGuidance(message) {');
const end = template.indexOf('function renderProductSyncMessage(', start);
assert.notStrictEqual(start, -1, 'The synchronization template should define its actionable error helper.');
assert.notStrictEqual(end, -1, 'The synchronization template should keep the helper beside error rendering.');
const helperSource = template.slice(start, end).trim();
const getGuidance = vm.runInNewContext('(' + helperSource + ')');

const guidance = getGuidance(
    "Authorization header requires 'Credential' parameter. Authorization header requires 'Signature' parameter."
);

assert.ok(guidance, 'AWS SigV4 errors should produce actionable instructions.');
assert.ok(guidance.steps.length >= 3, 'The guidance should tell the site owner what to do.');
assert.match(guidance.documentationUrl, /^https:\/\/api-docs\.yuju\.io\//);
assert.match(guidance.supportUrl, /^https:\/\/help\.yuju\.io\//);
assert.match(guidance.supportEmail, /^mailto:soporte@yuju\.io$/);
assert.strictEqual(
    getGuidance('A different API error'),
    null,
    'Unrelated API errors should keep their original message.'
);
assert.match(template, /getYujuSyncErrorGuidance\(message\)/, 'The synchronization error view should render the guidance.');

console.log('Yuju sync error guidance tests passed.');
