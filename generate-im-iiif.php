<?php

if ($argc < 2) {
    die("Usage: php generate-im-iiif.php <database_name>\n");
}

$databaseName = $argv[1];
$inputFilePath = __DIR__ . "/output/{$databaseName}.json";
$outputDir = __DIR__ . "/output/{$databaseName}";

if (!file_exists($inputFilePath)) {
    die("Error: Input file '{$inputFilePath}' not found.\n");
}

if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0777, true)) {
        die("Error: Failed to create output directory '{$outputDir}'.\n");
    }
}

$jsonData = file_get_contents($inputFilePath);
$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Failed to decode JSON data: " . json_last_error_msg() . "\n");
}

// Load Configuration
$configPath = __DIR__ . '/config.json';
$uriPrefix = 'https://example.com/';

if (file_exists($configPath)) {
    $configContent = file_get_contents($configPath);
    $configData = json_decode($configContent, true);
    if (isset($configData['uriPrefix'])) {
        $uriPrefix = rtrim($configData['uriPrefix'], '/') . '/';
    }
}

// Helpers
function generateUri($path)
{
    global $uriPrefix;
    return $uriPrefix . ltrim($path, '/');
}

function saveJson($filename, $content, $dir)
{
    // Add @context if likely a top level resource (Manifest, Collection, Page) or if not present
    if (!isset($content['@context'])) {
        $content = array_merge(['@context' => 'http://iiif.io/api/presentation/3/context.json'], $content);
    }

    $filepath = $dir . "/" . $filename;
    file_put_contents($filepath, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "Saved: " . basename($filepath) . "\n";
}

// Build Maps
$graphemesMap = [];
foreach ($data['graphemes'] ?? [] as $item) {
    $graphemesMap[$item['id']] = $item;
}

$annotatedGraphemesMap = [];
foreach ($data['annotatedGraphemes'] ?? [] as $item) {
    $annotatedGraphemesMap[$item['id']] = $item;
}

// ---------------------------------------------------------
// 7. Graphemes (Conceptual URI generation, used in Segments)
// ---------------------------------------------------------

// ---------------------------------------------------------
// 6. Segments
// ---------------------------------------------------------
// Pre-process segments to index graphemes for Token offset calculation
// Map: AnnotatedGraphemeID -> { segmentId, segmentUri, indexInSegment, segmentLength }
$graphemeToSegmentIndex = [];
$segmentAnnotations = [];
$segmentAnnotationIds = []; // SegID -> Uri

$imageEntity = $data['images'][0] ?? null;
$canvasId = $imageEntity ? generateUri("canvas/" . $imageEntity['id']) : generateUri("canvas/unknown");

$segments = $data['segments'] ?? [];

foreach ($segments as $segment) {
    $segId = $segment['id'];
    $segUri = generateUri("segment/{$segId}");
    $segmentAnnotationIds[$segId] = $segUri;

    $segGraphemes = $segment['graphemes'] ?? [];
    $segLen = count($segGraphemes);

    foreach ($segGraphemes as $idx => $gId) {
        $graphemeToSegmentIndex[$gId] = [
            'segmentId' => $segId,
            'segmentUri' => $segUri,
            'index' => $idx,
            'length' => $segLen
        ];
    }

    // Build Annotation Body
    $bodies = [];

    // Clarity
    if (isset($segment['clarity'])) {
        $bodies[] = [
            'type' => 'TextualBody',
            'language' => 'en',
            'format' => 'text/html',
            'value' => "<p><span class=\"field-label\"><b>Clarity</b></span>: <span class=\"field-value\">{$segment['clarity']}</span></p>",
            'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/67'
        ];
    }

    // Obscurations
    if (isset($segment['obscurations'])) {
        $bodies[] = [
            'type' => 'TextualBody',
            'language' => 'en',
            'format' => 'text/html',
            'value' => "<p><span class=\"field-label\"><b>Obscurations</b></span>: <span class=\"field-value\">{$segment['obscurations']}</span></p>",
            'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/68'
        ];
    }

    // Graphemes Body
    foreach ($segGraphemes as $agId) {
        $ag = $annotatedGraphemesMap[$agId] ?? null;
        if (!$ag)
            continue;

        $gDefId = $ag['grapheme'];
        $gDef = $graphemesMap[$gDefId] ?? null;
        $gLabel = $gDef ? $gDef['grapheme'] : '';

        // URI for grapheme: https://example.com/grapheme/{grapheme}
        $gUri = generateUri("grapheme/" . rawurlencode($gLabel));

        $tcm = $ag['textCriticalMark'];
        $tcmAttr = $tcm ? " data-tcm=\"{$tcm}\"" : "";

        $htmlValue = "<p><span class=\"field-label\"><b>Grapheme</b></span>: <span class=\"field-value\"><a{$tcmAttr} href=\"{$gUri}\">{$gLabel}</a></span></p>";

        $bodies[] = [
            'type' => 'TextualBody',
            'language' => 'en',
            'format' => 'text/html',
            'value' => $htmlValue,
            'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/66'
        ];
    }

    // Selector
    $svgSelector = "";
    if (isset($segment['coordinates'])) {
        // Input: "((x,y),(x,y)...)"
        // Output: "<svg><polygon points=\"x,y x,y ...\" /></svg>"
        $cleanCoords = str_replace(['(', ')', '{', '}', '"'], '', $segment['coordinates']);
        $parts = explode(',', $cleanCoords);
        $points = [];
        for ($i = 0; $i < count($parts); $i += 2) {
            if (isset($parts[$i + 1])) {
                $points[] = $parts[$i] . ',' . $parts[$i + 1];
            }
        }
        $pointsStr = implode(' ', $points);
        $svgSelector = "<svg><polygon points=\"{$pointsStr}\" /></svg>";
    }

    $segmentAnnotations[] = [
        'id' => $segUri,
        'type' => 'Annotation',
        'motivation' => 'describing',
        'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK',
        'target' => [
            'source' => $canvasId,
            'selector' => [
                'type' => 'SvgSelector',
                'value' => $svgSelector
            ]
        ],
        'body' => $bodies
    ];
}

// ---------------------------------------------------------
// 8. Tokens
// ---------------------------------------------------------
$tokens = $data['tokens'] ?? [];
$tokenAnnotations = [];

foreach ($tokens as $token) {
    $tokId = $token['id'];
    $tokUri = generateUri("token/{$tokId}");
    $tokGraphemes = $token['graphemes'] ?? [];

    if (empty($tokGraphemes))
        continue;

    // Calculate offset and identify segments
    $firstGId = $tokGraphemes[0];
    $lastGId = $tokGraphemes[count($tokGraphemes) - 1];

    $startInfo = $graphemeToSegmentIndex[$firstGId] ?? null;
    $endInfo = $graphemeToSegmentIndex[$lastGId] ?? null;

    $targetSegments = [];
    $offset = 0;

    if ($startInfo && $endInfo) {
        $startIndex = $startInfo['index'];
        $endIndex = $endInfo['index'];
        $lastSegLen = $endInfo['length'];

        // Offset = startIndex - (lastSegmentLength - 1 - endIndex)
        // Explanation: 
        // startIndex > 0 implies we skipped 'startIndex' graphemes at the start. (+ve)
        // endIndex < (len-1) implies we skipped (len-1-endIndex) graphemes at the end. (-ve)
        $offset = $startIndex - ($lastSegLen - 1 - $endIndex);

        // Collect distinct segment URIs
        // We iterate token graphemes to find unique segments in order
        $seenSegs = [];
        foreach ($tokGraphemes as $gId) {
            if (isset($graphemeToSegmentIndex[$gId])) {
                $sId = $graphemeToSegmentIndex[$gId]['segmentId'];
                if (!in_array($sId, $seenSegs)) {
                    $seenSegs[] = $sId;
                    $targetSegments[] = $graphemeToSegmentIndex[$gId]['segmentUri'];
                }
            }
        }
    }

    $tokenAnnotations[] = [
        'id' => $tokUri,
        'type' => 'Annotation',
        'motivation' => 'linking',
        'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6PTSE4R77Y5KWEBH0RZ7SA',
        'body' => [
            [
                'type' => 'TextualBody',
                'language' => 'en',
                'format' => 'text/html',
                'value' => "<p><span class=\"field-label\"><b>Offset</b></span>: <span class=\"field-value\">{$offset}</span></p>",
                'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6PTSE4R77Y5KWEBH0RZ7SA/field/69'
            ]
        ],
        'target' => count($targetSegments) === 1 ? $targetSegments[0] : $targetSegments
    ];
}

// ---------------------------------------------------------
// 5. Lines
// ---------------------------------------------------------
$lines = $data['lines'] ?? [];
$lineAnnotations = [];

foreach ($lines as $line) {
    $lineId = $line['id'];
    $lineUri = generateUri("line/$lineId");

    $targetSegUris = [];
    foreach ($line['segments'] ?? [] as $segId) {
        if (isset($segmentAnnotationIds[$segId])) {
            $targetSegUris[] = $segmentAnnotationIds[$segId];
        }
    }

    $lineAnnotations[] = [
        'id' => $lineUri,
        'type' => 'Annotation',
        'motivation' => 'linking',
        'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6QDRZGTAD862RMZA73AZJF',
        'body' => [
            [
                'type' => 'TextualBody',
                'language' => 'en',
                'format' => 'text/html',
                'value' => "<p><span class=\"field-label\"><b>Label</b></span>: <span class=\"field-value\">{$line['label']}</span></p>",
                'generator' => 'https://w3id.org/iaw/data/publications/annotation-template/01KG6QDRZGTAD862RMZA73AZJF/field/70'
            ]
        ],
        'target' => count($targetSegUris) === 1 ? $targetSegUris[0] : $targetSegUris
    ];
}

// ---------------------------------------------------------
// Prepare Page URIs and Cross references
// ---------------------------------------------------------
$pageSegmentsUri = generateUri("{$databaseName}_annotation_page_segments.json");
$pageTokensUri = generateUri("{$databaseName}_annotation_page_tokens.json");
$pageLinesUri = generateUri("{$databaseName}_annotation_page_lines.json");
$collectionUri = generateUri("{$databaseName}_annotation_collection.json");

$editionEntity = $data['editions'][0] ?? [];
$datasetLabel = $editionEntity['label'] ?? $databaseName;

// ---------------------------------------------------------
// Construct Pages
// ---------------------------------------------------------

// Segments Page
$segmentsPage = [
    'id' => $pageSegmentsUri,
    'type' => 'AnnotationPage',
    'partOf' => [
        'id' => $collectionUri,
        'type' => 'AnnotationCollection',
        'label' => ['en' => [$datasetLabel]]
    ],
    'next' => [
        'id' => $pageTokensUri,
        'type' => 'AnnotationPage'
    ],
    'items' => $segmentAnnotations
];

// Tokens Page
$tokensPage = [
    'id' => $pageTokensUri,
    'type' => 'AnnotationPage',
    'partOf' => [
        'id' => $collectionUri,
        'type' => 'AnnotationCollection',
        'label' => ['en' => [$datasetLabel]]
    ],
    'prev' => [
        'id' => $pageSegmentsUri,
        'type' => 'AnnotationPage'
    ],
    'next' => [
        'id' => $pageLinesUri,
        'type' => 'AnnotationPage'
    ],
    'items' => $tokenAnnotations
];

// Lines Page
$linesPage = [
    'id' => $pageLinesUri,
    'type' => 'AnnotationPage',
    'partOf' => [
        'id' => $collectionUri,
        'type' => 'AnnotationCollection',
        'label' => ['en' => [$datasetLabel]]
    ],
    'prev' => [
        'id' => $pageTokensUri,
        'type' => 'AnnotationPage'
    ],
    'items' => $lineAnnotations
];

// ---------------------------------------------------------
// 4. Edition (Collection)
// ---------------------------------------------------------
$collection = [
    'id' => $collectionUri,
    'type' => 'AnnotationCollection',
    'label' => ['en' => [$datasetLabel]],
    'metadata' => [
        ['label' => ['en' => ['Type']], 'value' => ['en' => [$editionEntity['type'] ?? '']]],
        ['label' => ['en' => ['Owner']], 'value' => ['en' => [$editionEntity['owner'] ?? '']]]
    ],
    'total' => count($segmentAnnotations) + count($tokenAnnotations) + count($lineAnnotations),
    'first' => [
        'id' => $pageSegmentsUri,
        'type' => 'AnnotationPage'
    ]
];

// ---------------------------------------------------------
// 3. Image (Canvas)
// ---------------------------------------------------------
$canvasItems = [];
$imageUrl = $imageEntity['url'] ?? '';
$width = 0;
$height = 0;

if ($imageUrl) {
    $size = @getimagesize($imageUrl);
    if ($size) {
        $width = $size[0];
        $height = $size[1];
    }
}

// Canvas Items (Annotation Page -> Annotation -> Image)
$annoPageId = generateUri("image/" . ($imageEntity['id'] ?? '1') . "/page/1");
$annoId = generateUri("image/" . ($imageEntity['id'] ?? '1') . "/page/1/annotation/1");

$canvas = [
    'id' => $canvasId,
    'type' => 'Canvas',
    'label' => ['en' => [$imageEntity['label'] ?? '']],
    'height' => $height,
    'width' => $width,
    'metadata' => [
        ['label' => ['en' => ['Type']], 'value' => ['en' => [$imageEntity['type'] ?? '']]]
    ],
    'items' => [
        [
            'id' => $annoPageId,
            'type' => 'AnnotationPage',
            'items' => [
                [
                    'id' => $annoId,
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'target' => $canvasId,
                    'body' => [
                        'id' => $imageUrl,
                        'type' => 'Image',
                        'format' => 'image/jpeg',
                        'height' => $height,
                        'width' => $width
                    ]
                ]
            ]
        ]
    ],
    // Embed segment page
    'annotations' => [
        $segmentsPage
    ]
];

// ---------------------------------------------------------
// 2. Text (Manifest)
// ---------------------------------------------------------
$textEntity = $data['texts'][0] ?? [];
$manifestUri = generateUri("{$databaseName}_manifest.json");

$manifest = [
    'id' => $manifestUri,
    'type' => 'Manifest',
    'label' => ['en' => [$textEntity['label'] ?? '']],
    'metadata' => [
        ['label' => ['en' => ['CKM']], 'value' => ['en' => [$textEntity['ckn'] ?? '']]],
        ['label' => ['en' => ['Text Ref']], 'value' => ['en' => [$textEntity['textRef'] ?? '']]],
        ['label' => ['en' => ['Types']], 'value' => ['en' => [implode(', ', $textEntity['types'] ?? [])]]]
    ],
    'items' => [
        $canvas
    ]
];

// ---------------------------------------------------------
// Save All
// ---------------------------------------------------------
saveJson("{$databaseName}_manifest.json", $manifest, $outputDir);
saveJson("{$databaseName}_annotation_collection.json", $collection, $outputDir);
saveJson("{$databaseName}_annotation_page_segments.json", $segmentsPage, $outputDir);
saveJson("{$databaseName}_annotation_page_tokens.json", $tokensPage, $outputDir);
saveJson("{$databaseName}_annotation_page_lines.json", $linesPage, $outputDir);

echo "All IIIF resources generated successfully.\n";
