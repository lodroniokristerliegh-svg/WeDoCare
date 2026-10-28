<?php
/**
 * WeDoCare — Database Migration: Lab Result Upload
 *
 * Run this ONCE in your browser if you already imported schema.sql
 * before the patient lab-result-upload feature was added:
 *     http://localhost/backend/migrate_lab_results.php
 *
 * Safe to run more than once. Delete this file after running it.
 */
require_once __DIR__ . '/config.php';

$columns = [
    'result_file'  => 'VARCHAR(255) NULL',
    'result_notes' => 'VARCHAR(255) NULL',
];

echo "<h3>WeDoCare — Lab Result Upload Migration</h3><ul>";
foreach ($columns as $name => $definition) {
    $exists = $pdo->query("SHOW COLUMNS FROM lab_referrals LIKE '$name'")->fetch();
    if ($exists) {
        echo "<li>Column <code>$name</code> already exists — skipped.</li>";
    } else {
        $pdo->exec("ALTER TABLE lab_referrals ADD COLUMN $name $definition");
        echo "<li>Added column <code>$name</code>.</li>";
    }
}
echo "</ul><p><strong>Migration complete.</strong> You may now delete this file.</p>";
