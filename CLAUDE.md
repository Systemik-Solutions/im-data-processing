# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IM Manuscripts Data Processing — a pipeline that extracts manuscript annotation data from PostgreSQL, transforms it into IIIF Presentation API 3.0 resources, and provides an interactive web viewer.

**Tech stack:** PHP 7.4+ (PDO/PostgreSQL), vanilla JavaScript, HTML5/CSS3. No build tools, package manager, or test framework.

## Commands

```bash
# Extract data from database to JSON
php extract-im-data.php DBNAME [OUTPUT_FILE]
# e.g. php extract-im-data.php RS22_02 output/RS22_02.json

# Generate IIIF resources from extracted JSON
php generate-im-iiif.php DBNAME
# reads output/{DBNAME}.json, writes to output/{DBNAME}/

# Validate extracted JSON structure and counts
php validate-output.php

# Validate referential integrity with sample data
php validate-detailed.php

# Verify line ordering matches database
php verify-ordering.php
```

## Architecture

Three-phase pipeline: **Database → JSON → IIIF → Web UI**

### Phase 1: Extraction (`extract-im-data.php`)
Queries PostgreSQL and outputs a single JSON file with 8 entity arrays. Extraction order is strict for referential integrity:
1. Graphemes (deduplicated by `gra_grapheme`)
2. Annotated Graphemes (all instances, with `textCriticalMark`)
3. Segments (syllable clusters from `syllablecluster` + `segment` tables)
4. Tokens (words/compounds with concatenated graphemes)
5. Lines (sequences — order derived from `seq_entity_ids`, not sequence position)
6. Editions, Images, Texts

**PostgreSQL-specific parsing:** Term table values use `lang=>"Label"` format (extract just the Label). Array columns use `{val1,val2,...}` format.

### Phase 2: IIIF Generation (`generate-im-iiif.php`)
Reads extracted JSON, produces 5 IIIF files per manuscript:
- `{DB}_manifest.json` — Manifest with canvas items
- `{DB}_annotation_page_segments.json` — Segment (syllable) annotations
- `{DB}_annotation_page_tokens.json` — Token annotations
- `{DB}_annotation_page_lines.json` — Line annotations
- `{DB}_annotation_collection.json` — Collection referencing all pages

Annotation bodies use HTML with structured `<p>` fields containing `<span class="field-label">` and `<span class="field-value">` pairs. Grapheme references are `<a>` tags with optional `data-tcm` attributes for text-critical marks. Targets use SVG polygon selectors on canvases.

URI prefix is configured in `config.json`.

### Phase 3: Viewer (`index.html`)
Single-page app using Glycerine Viewer (loaded from CDN). CSS Grid layout: 60% image viewer (left), 40% split between metadata panel (top-right) and tabbed lists (bottom-right).

Data flow: fetches manifest + 3 annotation pages → parses HTML annotation bodies with DOMParser → builds syllable/token/line data structures → renders tabbed lists → on selection, highlights annotation region in viewer and shows metadata breakdown.

## Configuration

- `db.json` — PostgreSQL connection (host, port, user, password). **Gitignored.**
- `config.json` — `uriPrefix` for generated IIIF resource URIs.

## Agent Skills

Four domain-specific skill documents in `.agent/skills/` provide detailed specifications:
- **extracting-im-data** — Database schema, SQL queries, JSON output format
- **generating-im-iiif** — IIIF mapping rules, annotation body structure
- **parsing-annotation-body** — HTML body parsing specification for the viewer
- **frontend-design** — UI design guidelines and component patterns
