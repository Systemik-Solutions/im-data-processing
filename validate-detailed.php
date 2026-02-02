<?php

/**
 * Detailed validation script for IM data extraction output
 */

$jsonFile = __DIR__ . '/output.json';
$data = json_decode(file_get_contents($jsonFile), true);

echo "=== GRAPHEME SAMPLE ===\n";
echo json_encode($data['graphemes'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== ANNOTATED GRAPHEME SAMPLE ===\n";
echo json_encode($data['annotatedGraphemes'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== SEGMENT SAMPLE ===\n";
echo json_encode($data['segments'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== TOKEN SAMPLE ===\n";
echo json_encode($data['tokens'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== LINE SAMPLE ===\n";
echo json_encode($data['lines'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== EDITION SAMPLE ===\n";
echo json_encode($data['editions'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== IMAGE SAMPLE ===\n";
echo json_encode($data['images'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== TEXT SAMPLE ===\n";
echo json_encode($data['texts'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Verify data integrity
echo "=== DATA INTEGRITY CHECKS ===\n";

// Check 1: Annotated graphemes reference valid graphemes
$graphemeIds = array_column($data['graphemes'], 'id');
$invalidRefs = 0;
foreach ($data['annotatedGraphemes'] as $ag) {
    if ($ag['grapheme'] !== null && !in_array($ag['grapheme'], $graphemeIds)) {
        $invalidRefs++;
    }
}
echo "Annotated graphemes with invalid grapheme references: $invalidRefs\n";

// Check 2: Segments reference valid annotated graphemes
$annotatedGraphemeIds = array_column($data['annotatedGraphemes'], 'id');
$invalidSegmentRefs = 0;
foreach ($data['segments'] as $seg) {
    foreach ($seg['graphemes'] as $gid) {
        if (!in_array($gid, $annotatedGraphemeIds)) {
            $invalidSegmentRefs++;
        }
    }
}
echo "Segment grapheme references that are invalid: $invalidSegmentRefs\n";

// Check 3: Lines reference valid segments
$segmentIds = array_column($data['segments'], 'id');
$invalidLineRefs = 0;
foreach ($data['lines'] as $line) {
    foreach ($line['segments'] as $sid) {
        if (!in_array($sid, $segmentIds)) {
            $invalidLineRefs++;
        }
    }
}
echo "Line segment references that are invalid: $invalidLineRefs\n";

// Check 4: Editions reference valid lines
$lineIds = array_column($data['lines'], 'id');
$invalidEditionRefs = 0;
foreach ($data['editions'] as $edn) {
    foreach ($edn['lines'] as $lid) {
        if (!in_array($lid, $lineIds)) {
            $invalidEditionRefs++;
        }
    }
}
echo "Edition line references that are invalid: $invalidEditionRefs\n";

// Check 5: Texts reference valid images
$imageIds = array_column($data['images'], 'id');
$invalidTextImageRefs = 0;
foreach ($data['texts'] as $txt) {
    foreach ($txt['images'] as $iid) {
        if (!in_array($iid, $imageIds)) {
            $invalidTextImageRefs++;
        }
    }
}
echo "Text image references that are invalid: $invalidTextImageRefs\n";

echo "\n✓ Detailed validation complete\n";
