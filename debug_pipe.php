<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('sessions')->orderBy('last_activity', 'desc')->limit(15)->get();
foreach ($rows as $r) {
    echo "id=" . substr($r->id, 0, 44)
        . (strpos((string) $r->id, '|') !== false ? " <-- PIPE" : "")
        . " user_id=" . var_export($r->user_id, true)
        . " last_activity=" . $r->last_activity
        . "\n";
}
