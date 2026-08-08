<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('sessions')->get();
echo "=== sessions table (" . count($rows) . " rows) ===\n";
foreach ($rows as $r) {
    echo "id=" . $r->id
        . " user_id=" . var_export($r->user_id, true)
        . " last_activity=" . $r->last_activity
        . " payload=" . substr((string) $r->payload, 0, 40)
        . "\n";
}
