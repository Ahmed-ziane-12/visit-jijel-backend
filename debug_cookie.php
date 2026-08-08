<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Crypt;

$values = $argv;
array_shift($values);

foreach ($values as $v) {
    echo "=== cookie: " . substr($v, 0, 40) . "...\n";
    try {
        $plain = Crypt::decrypt(urldecode($v));
        echo "  plaintext session id: " . $plain . "\n";
        $row = Illuminate\Support\Facades\DB::table('sessions')->where('id', $plain)->first();
        if ($row) {
            echo "  DB row: user_id=" . var_export($row->user_id, true)
                . " last_activity=" . $row->last_activity . "\n";
        } else {
            echo "  DB row: NOT FOUND in sessions table\n";
        }
    } catch (\Throwable $e) {
        echo "  decrypt error: " . $e->getMessage() . "\n";
    }
}
