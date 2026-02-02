<?php

/**
 * IM Data Extraction Script
 * 
 * Extracts data from an IM manuscript PostgreSQL database and outputs JSON to stdout.
 * Usage: php extract-im-data.php DBNAME
 */

// Check for required database name parameter
if ($argc < 2) {
    fwrite(STDERR, "Error: Database name is required.\n");
    fwrite(STDERR, "Usage: php extract-im-data.php DBNAME [OUTPUT_FILE]\n");
    exit(1);
}

$dbName = $argv[1];
$outputFile = $argv[2] ?? null; // Optional output file parameter

// Load database connection details from db.json
$configFile = __DIR__ . '/db.json';
if (!file_exists($configFile)) {
    fwrite(STDERR, "Error: Configuration file 'db.json' not found.\n");
    exit(1);
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    fwrite(STDERR, "Error: Failed to parse 'db.json'.\n");
    exit(1);
}

// Establish database connection
try {
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s",
        $config['host'],
        $config['port'],
        $dbName
    );

    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Set client encoding to UTF-8
    $pdo->exec("SET CLIENT_ENCODING TO 'UTF8'");
} catch (PDOException $e) {
    fwrite(STDERR, "Error: Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Helper function to get term label from term table
 */
function getTermLabel($pdo, $termId)
{
    if ($termId === null) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT trm_labels FROM term WHERE trm_id = ?");
    $stmt->execute([$termId]);
    $result = $stmt->fetch();

    if (!$result || !$result['trm_labels']) {
        return null;
    }

    // Parse format: en=>"Label" to extract just "Label"
    if (preg_match('/=>"([^"]+)"/', $result['trm_labels'], $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Helper function to parse PostgreSQL array format
 */
function parsePostgresArray($pgArray)
{
    if ($pgArray === null || $pgArray === '') {
        return [];
    }

    // Remove curly braces and split by comma
    $pgArray = trim($pgArray, '{}');
    if ($pgArray === '') {
        return [];
    }

    return explode(',', $pgArray);
}

// Initialize target data structure
$targetData = [
    'texts' => [],
    'images' => [],
    'editions' => [],
    'graphemes' => [],
    'annotatedGraphemes' => [],
    'segments' => [],
    'tokens' => [],
    'lines' => []
];

// 1. Extract Graphemes (unique, deduplicated)
$stmt = $pdo->query("
    SELECT DISTINCT ON (gra_grapheme) 
        gra_grapheme, gra_type_id, gra_decomposition, gra_sort_code, gra_emmendation
    FROM grapheme
    ORDER BY gra_grapheme, gra_sort_code
");

$graphemeId = 1;
$graphemeMap = []; // Map grapheme string to ID for later lookup

while ($row = $stmt->fetch()) {
    $grapheme = [
        'id' => $graphemeId,
        'grapheme' => $row['gra_grapheme'],
        'type' => getTermLabel($pdo, $row['gra_type_id']),
        'decomposition' => $row['gra_decomposition'],
        'sortCode' => $row['gra_sort_code'],
        'emmendation' => $row['gra_emmendation']
    ];

    $targetData['graphemes'][] = $grapheme;
    $graphemeMap[$row['gra_grapheme']] = $graphemeId;
    $graphemeId++;
}

// 2. Extract Annotated Graphemes
$stmt = $pdo->query("
    SELECT gra_id, gra_grapheme, gra_text_critical_mark
    FROM grapheme
    ORDER BY gra_id
");

$annotatedGraphemeIds = []; // Track valid annotated grapheme IDs

while ($row = $stmt->fetch()) {
    $graphemeRef = isset($graphemeMap[$row['gra_grapheme']])
        ? $graphemeMap[$row['gra_grapheme']]
        : null;

    $annotatedGrapheme = [
        'id' => (int) $row['gra_id'],
        'grapheme' => $graphemeRef,
        'textCriticalMark' => $row['gra_text_critical_mark']
    ];

    $targetData['annotatedGraphemes'][] = $annotatedGrapheme;
    $annotatedGraphemeIds[] = (int) $row['gra_id'];
}

// 3. Extract Segments
$stmt = $pdo->query("
    SELECT 
        scl.scl_id, scl.scl_grapheme_ids,
        seg.seg_clarity_id, seg.seg_obscurations, seg.seg_image_pos
    FROM syllablecluster scl
    JOIN segment seg ON scl.scl_segment_id = seg.seg_id
    ORDER BY scl.scl_id
");

$segmentIds = []; // Track valid segment IDs

while ($row = $stmt->fetch()) {
    $graphemeIds = parsePostgresArray($row['scl_grapheme_ids']);
    $validGraphemes = [];

    foreach ($graphemeIds as $gid) {
        $gid = (int) $gid;
        if (in_array($gid, $annotatedGraphemeIds)) {
            $validGraphemes[] = $gid;
        }
    }

    $segment = [
        'id' => (int) $row['scl_id'],
        'graphemes' => $validGraphemes,
        'clarity' => getTermLabel($pdo, $row['seg_clarity_id']),
        'obscurations' => $row['seg_obscurations'],
        'coordinates' => $row['seg_image_pos']
    ];

    $targetData['segments'][] = $segment;
    $segmentIds[] = (int) $row['scl_id'];
}

// 4. Extract Tokens
$stmt = $pdo->query("
    SELECT tok_id, tok_grapheme_ids
    FROM token
    ORDER BY tok_id
");

while ($row = $stmt->fetch()) {
    $graphemeIds = parsePostgresArray($row['tok_grapheme_ids']);
    $validGraphemes = [];

    foreach ($graphemeIds as $gid) {
        $gid = (int) $gid;
        if (in_array($gid, $annotatedGraphemeIds)) {
            $validGraphemes[] = $gid;
        }
    }

    $token = [
        'id' => (int) $row['tok_id'],
        'graphemes' => $validGraphemes
    ];

    $targetData['tokens'][] = $token;
}

// 5. Extract Lines
// Step 1: Get parent sequences where seq_type_id = 736
$stmt = $pdo->query("
    SELECT seq_id, seq_entity_ids
    FROM sequence
    WHERE seq_type_id = 736
");

$parentSequences = [];
while ($row = $stmt->fetch()) {
    $parentSequences[(int) $row['seq_id']] = $row['seq_entity_ids'];
}

// Step 2 & 3: Get line sequences
foreach ($parentSequences as $parentSeqId => $entityIds) {
    $entityIds = parsePostgresArray($entityIds);
    $lineSeqIds = [];

    // Parse seq:id format and preserve order
    foreach ($entityIds as $entity) {
        if (preg_match('/seq:(\d+)/', $entity, $matches)) {
            $lineSeqIds[] = (int) $matches[1];
        }
    }

    if (empty($lineSeqIds)) {
        continue;
    }

    // Query line sequences
    $placeholders = implode(',', array_fill(0, count($lineSeqIds), '?'));
    $stmt = $pdo->prepare("
        SELECT seq_id, seq_entity_ids, seq_label
        FROM sequence
        WHERE seq_id IN ($placeholders)
    ");
    $stmt->execute($lineSeqIds);

    // Fetch all lines into a lookup map
    $lineDataMap = [];
    while ($row = $stmt->fetch()) {
        $segmentEntities = parsePostgresArray($row['seq_entity_ids']);
        $validSegments = [];

        // Parse scl:id format and validate
        foreach ($segmentEntities as $entity) {
            if (preg_match('/scl:(\d+)/', $entity, $matches)) {
                $segId = (int) $matches[1];
                if (in_array($segId, $segmentIds)) {
                    $validSegments[] = $segId;
                }
            }
        }

        $lineDataMap[(int) $row['seq_id']] = [
            'id' => (int) $row['seq_id'],
            'segments' => $validSegments,
            'label' => $row['seq_label'],
            'parentSeq' => $parentSeqId
        ];
    }

    // Add lines to target data in the order they appear in lineSeqIds
    foreach ($lineSeqIds as $lineSeqId) {
        if (isset($lineDataMap[$lineSeqId])) {
            $targetData['lines'][] = $lineDataMap[$lineSeqId];
        }
    }
}

// 6. Extract Editions
$stmt = $pdo->query("
    SELECT edn_id, edn_description, edn_sequence_ids, edn_type_id, edn_owner_id, edn_text_id
    FROM edition
    ORDER BY edn_id
");

while ($row = $stmt->fetch()) {
    $sequenceIds = parsePostgresArray($row['edn_sequence_ids']);
    $editionLines = [];

    // Find all lines with matching parentSeq, maintaining the order from target data
    foreach ($targetData['lines'] as $line) {
        $seqId = $line['parentSeq'];
        if (in_array($seqId, array_map('intval', $sequenceIds))) {
            $editionLines[] = $line['id'];
        }
    }

    // Get owner name
    $ownerName = null;
    if ($row['edn_owner_id'] !== null) {
        $ownerStmt = $pdo->prepare("
            SELECT ugr_given_name, ugr_family_name
            FROM usergroup
            WHERE ugr_id = ?
        ");
        $ownerStmt->execute([$row['edn_owner_id']]);
        $owner = $ownerStmt->fetch();
        if ($owner) {
            $ownerName = trim(($owner['ugr_given_name'] ?? '') . ' ' . ($owner['ugr_family_name'] ?? ''));
            if ($ownerName === '') {
                $ownerName = null;
            }
        }
    }

    $edition = [
        'id' => (int) $row['edn_id'],
        'label' => $row['edn_description'],
        'lines' => $editionLines,
        'type' => getTermLabel($pdo, $row['edn_type_id']),
        'owner' => $ownerName,
        'text' => $row['edn_text_id'] !== null ? (int) $row['edn_text_id'] : null
    ];

    $targetData['editions'][] = $edition;
}

// 7. Extract Images
$stmt = $pdo->query("
    SELECT img_id, img_title, img_url, img_type_id
    FROM image
    ORDER BY img_id
");

$imageIds = []; // Track valid image IDs

while ($row = $stmt->fetch()) {
    $image = [
        'id' => (int) $row['img_id'],
        'label' => $row['img_title'],
        'url' => $row['img_url'],
        'type' => getTermLabel($pdo, $row['img_type_id'])
    ];

    $targetData['images'][] = $image;
    $imageIds[] = (int) $row['img_id'];
}

// 8. Extract Texts
$stmt = $pdo->query("
    SELECT txt_id, txt_title, txt_ckn, txt_ref, txt_type_ids, txt_image_ids
    FROM text
    ORDER BY txt_id
");

while ($row = $stmt->fetch()) {
    $typeIds = parsePostgresArray($row['txt_type_ids']);
    $types = [];

    foreach ($typeIds as $typeId) {
        $typeLabel = getTermLabel($pdo, (int) $typeId);
        if ($typeLabel !== null) {
            $types[] = $typeLabel;
        }
    }

    $imgIds = parsePostgresArray($row['txt_image_ids']);
    $validImages = [];

    foreach ($imgIds as $imgId) {
        $imgId = (int) $imgId;
        if (in_array($imgId, $imageIds)) {
            $validImages[] = $imgId;
        }
    }

    $text = [
        'id' => (int) $row['txt_id'],
        'label' => $row['txt_title'],
        'ckn' => $row['txt_ckn'],
        'textRef' => $row['txt_ref'],
        'types' => $types,
        'images' => $validImages
    ];

    $targetData['texts'][] = $text;
}

// Output JSON
$jsonOutput = json_encode($targetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($outputFile) {
    // Write to file with UTF-8 encoding (no BOM)
    if (file_put_contents($outputFile, $jsonOutput) === false) {
        fwrite(STDERR, "Error: Failed to write to output file '$outputFile'\n");
        exit(1);
    }
    fwrite(STDERR, "Successfully extracted data to '$outputFile'\n");
} else {
    // Output to stdout
    echo $jsonOutput;
}
