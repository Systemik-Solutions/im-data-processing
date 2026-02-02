<?php

/**
 * Validation script for IM data extraction output
 */

$jsonFile = __DIR__ . '/output.json';

if (!file_exists($jsonFile)) {
    echo "Error: output.json not found\n";
    exit(1);
}

$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "✓ JSON is valid\n\n";

// Check structure
$expectedKeys = ['texts', 'images', 'editions', 'graphemes', 'annotatedGraphemes', 'segments', 'tokens', 'lines'];
$actualKeys = array_keys($data);

echo "Structure validation:\n";
foreach ($expectedKeys as $key) {
    if (in_array($key, $actualKeys)) {
        $count = count($data[$key]);
        echo "  ✓ $key: $count items\n";
    } else {
        echo "  ✗ $key: MISSING\n";
    }
}

echo "\n";

// Sample validation for each entity type
echo "Sample data validation:\n\n";

// Graphemes
if (!empty($data['graphemes'])) {
    $sample = $data['graphemes'][0];
    echo "Grapheme sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  grapheme: " . ($sample['grapheme'] ?? 'MISSING') . "\n";
    echo "  type: " . ($sample['type'] ?? 'MISSING') . "\n";
    echo "  sortCode: " . ($sample['sortCode'] ?? 'MISSING') . "\n";
}

echo "\n";

// Annotated Graphemes
if (!empty($data['annotatedGraphemes'])) {
    $sample = $data['annotatedGraphemes'][0];
    echo "Annotated Grapheme sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  grapheme: " . ($sample['grapheme'] ?? 'MISSING') . "\n";
    echo "  textCriticalMark: " . ($sample['textCriticalMark'] ?? 'null') . "\n";
}

echo "\n";

// Segments
if (!empty($data['segments'])) {
    $sample = $data['segments'][0];
    echo "Segment sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  graphemes count: " . count($sample['graphemes'] ?? []) . "\n";
    echo "  clarity: " . ($sample['clarity'] ?? 'null') . "\n";
    echo "  coordinates: " . (isset($sample['coordinates']) ? substr($sample['coordinates'], 0, 50) . '...' : 'null') . "\n";
}

echo "\n";

// Tokens
if (!empty($data['tokens'])) {
    $sample = $data['tokens'][0];
    echo "Token sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  graphemes count: " . count($sample['graphemes'] ?? []) . "\n";
}

echo "\n";

// Lines
if (!empty($data['lines'])) {
    $sample = $data['lines'][0];
    echo "Line sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  segments count: " . count($sample['segments'] ?? []) . "\n";
    echo "  label: " . ($sample['label'] ?? 'MISSING') . "\n";
    echo "  parentSeq: " . ($sample['parentSeq'] ?? 'MISSING') . "\n";
}

echo "\n";

// Editions
if (!empty($data['editions'])) {
    $sample = $data['editions'][0];
    echo "Edition sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  label: " . ($sample['label'] ?? 'MISSING') . "\n";
    echo "  lines count: " . count($sample['lines'] ?? []) . "\n";
    echo "  type: " . ($sample['type'] ?? 'null') . "\n";
    echo "  owner: " . ($sample['owner'] ?? 'null') . "\n";
    echo "  text: " . ($sample['text'] ?? 'null') . "\n";
}

echo "\n";

// Images
if (!empty($data['images'])) {
    $sample = $data['images'][0];
    echo "Image sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  label: " . ($sample['label'] ?? 'MISSING') . "\n";
    echo "  url: " . (isset($sample['url']) ? substr($sample['url'], 0, 50) . '...' : 'null') . "\n";
    echo "  type: " . ($sample['type'] ?? 'null') . "\n";
}

echo "\n";

// Texts
if (!empty($data['texts'])) {
    $sample = $data['texts'][0];
    echo "Text sample:\n";
    echo "  id: " . ($sample['id'] ?? 'MISSING') . "\n";
    echo "  label: " . ($sample['label'] ?? 'MISSING') . "\n";
    echo "  ckn: " . ($sample['ckn'] ?? 'MISSING') . "\n";
    echo "  textRef: " . ($sample['textRef'] ?? 'MISSING') . "\n";
    echo "  types count: " . count($sample['types'] ?? []) . "\n";
    echo "  images count: " . count($sample['images'] ?? []) . "\n";
}

echo "\n✓ Validation complete\n";
