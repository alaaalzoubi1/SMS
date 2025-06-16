<?php
// artisan-web.php

// Only allow local execution (optional)
// if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') exit('Unauthorized');

// Run artisan commands
$output = [];
exec('php artisan migrate --seed',$output);
exec('php artisan config:cache', $output);
exec('php artisan route:cache', $output);
exec('php artisan view:cache', $output);

// Delete this script after execution for security
unlink(__FILE__);

// Show output
echo "<h2>Laravel cache commands executed successfully.</h2><pre>";
echo implode("\n", $output);
echo "</pre><p><strong>This file has been auto-deleted for security.</strong></p>";
