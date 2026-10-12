const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync('views/js/admin.js', 'utf8');
const controller = fs.readFileSync('controllers/admin/AdminYujuConfigurationController.php', 'utf8');
const sandbox = {
    module: {exports: {}},
    document: {},
    window: {},
    $: function() {
        return {ready: function() {}};
    }
};

vm.runInNewContext(source + '\nmodule.exports = YujuAdmin;', sandbox);

assert.strictEqual(
    sandbox.module.exports.getStoreId({id_account: 270933719, id_shop: 1086063}),
    270933719,
    'Account response should display id_account as the store ID.'
);
assert.strictEqual(
    sandbox.module.exports.getStoreId({id: 12}),
    12,
    'Existing generic IDs should remain supported.'
);
assert.doesNotMatch(source, /<strong>Información de Debug:<\/strong>/, 'Debug details should not be displayed.');
assert.doesNotMatch(source, /html \+= '<small>Monitor de telemetría:/, 'Telemetry registration errors should remain hidden in this result.');
assert.doesNotMatch(controller, /'debug_info'\s*=>|'stores_raw'\s*=>|'monitor'\s*=>/, 'Diagnostic data should not be sent to the browser.');

console.log('Connectivity display tests passed.');
