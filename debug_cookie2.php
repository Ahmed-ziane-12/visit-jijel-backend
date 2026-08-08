<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Crypt;

$v = $argv[1];
echo "cookie len: " . strlen($v) . "\n";
$raw = Crypt::decrypt($v, false);
echo "decrypted raw len: " . strlen($raw) . "\n";
echo "hex: " . bin2hex($raw) . "\n";
echo "is base64: " . (base64_decode($raw, true) !== false ? "yes" : "no") . "\n";
$maybe = base64_decode($raw, true);
if ($maybe !== false) {
    echo "base64-decoded len: " . strlen($maybe) . "\n";
    echo "base64-decoded: " . $maybe . "\n";
    $row = Illuminate\Support\Facades\DB::table('sessions')->where('id', $maybe)->first();
    echo "session row: " . ($row ? ("user_id=" . var_export($row->user_id, true) . " last_activity=" . $row->last_activity) : "NOT FOUND") . "\n";
}
