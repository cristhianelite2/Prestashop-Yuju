<?php

$template = file_get_contents(dirname(__DIR__) . '/views/templates/admin/sync_info.tpl');

if ($template === false) {
    throw new RuntimeException('Could not read the synchronization template.');
}

if (preg_match('/\{[A-Za-z_][A-Za-z0-9_]*\s*:/', $template, $matches)) {
    throw new RuntimeException('Unescaped JavaScript object syntax can be parsed as a Smarty tag: ' . $matches[0]);
}

if (strpos($template, 'data: {ldelim}ajax: 1, action: action{rdelim}') === false) {
    throw new RuntimeException('The AJAX data object must use Smarty delimiter escapes.');
}

if (strpos($template, 'report.all_products || report.unsynced_products') === false) {
    throw new RuntimeException('The reconciliation table must render all products, not only unsynchronized products.');
}

if (strpos($template, '{ldelim}retry_download: true{rdelim}') === false) {
    throw new RuntimeException('The manual poll action must retry failed downloads for the existing report task.');
}

if (strpos($template, 'response && response.message') === false) {
    throw new RuntimeException('The reconciliation loader must display the backend recovery diagnostic.');
}

echo "Synchronization template Smarty test passed.\n";
