<?php

/**
 * Verify line ordering matches the database seq_entity_ids order
 */

// Load database connection details
$configFile = __DIR__ . '/db.json';
$config = json_decode(file_get_contents($configFile), true);

// Connect to database
$dsn = sprintf("pgsql:host=%s;port=%s;dbname=RS22_02", $config['host'], $config['port']);
$pdo = new PDO($dsn, $config['user'], $config['password']);
$pdo->exec("SET CLIENT_ENCODING TO 'UTF8'");

// Load output JSON
$data = json_decode(file_get_contents(__DIR__ . '/output.json'), true);

echo "=== LINE ORDERING VERIFICATION ===\n\n";

// Get parent sequence
$stmt = $pdo->query("SELECT seq_id, seq_entity_ids FROM sequence WHERE seq_type_id = 736");
$parentSeq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parentSeq) {
    echo "No parent sequence found!\n";
    exit(1);
}

$parentSeqId = $parentSeq['seq_id'];
echo "Parent Sequence ID: $parentSeqId\n";

// Parse seq_entity_ids
$entityIds = trim($parentSeq['seq_entity_ids'], '{}');
$lineSeqIds = [];
foreach (explode(',', $entityIds) as $entity) {
    if (preg_match('/seq:(\d+)/', $entity, $matches)) {
        $lineSeqIds[] = (int) $matches[1];
    }
}

echo "Expected line sequence IDs (from database): " . implode(', ', $lineSeqIds) . "\n";
echo "Expected count: " . count($lineSeqIds) . "\n\n";

// Get actual line IDs from output
$actualLineIds = array_column($data['lines'], 'id');
echo "Actual line IDs (from output.json): " . implode(', ', $actualLineIds) . "\n";
echo "Actual count: " . count($actualLineIds) . "\n\n";

// Compare
if ($lineSeqIds === $actualLineIds) {
    echo "✓ Line ordering is CORRECT - matches database order exactly!\n";
} else {
    echo "✗ Line ordering is INCORRECT\n";
    echo "\nDifferences:\n";

    $maxCount = max(count($lineSeqIds), count($actualLineIds));
    for ($i = 0; $i < $maxCount; $i++) {
        $expected = $lineSeqIds[$i] ?? 'MISSING';
        $actual = $actualLineIds[$i] ?? 'MISSING';
        $match = $expected === $actual ? '✓' : '✗';
        echo "  Position $i: Expected=$expected, Actual=$actual $match\n";
    }
}

echo "\n=== EDITION LINE ORDERING VERIFICATION ===\n\n";

// Get edition
$edition = $data['editions'][0];
echo "Edition ID: {$edition['id']}\n";
echo "Edition lines: " . implode(', ', $edition['lines']) . "\n";
echo "Expected (from lines array): " . implode(', ', $actualLineIds) . "\n";

if ($edition['lines'] === $actualLineIds) {
    echo "✓ Edition line ordering is CORRECT - matches lines array order!\n";
} else {
    echo "✗ Edition line ordering is INCORRECT\n";
}

echo "\n✓ Verification complete\n";
