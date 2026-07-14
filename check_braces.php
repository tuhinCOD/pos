<?php
$content = file_get_contents('E:/laragon/www/inventory/Modules/Purchase/app/Http/Controllers/PurchaseController.php');
$brace = 0;
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    $open = substr_count($line, '{');
    $close = substr_count($line, '}');
    $brace += $open;
    $brace -= $close;
    if ($open > 0 || $close > 0) {
        echo "Line " . ($i+1) . " (brace: $brace): $line\n";
    }
}
echo "Final brace count: $brace\n";