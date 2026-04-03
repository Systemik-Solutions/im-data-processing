---
name: extracting-im-data
description: Use this skill when the user wants to do works related to extracting data for IM manuscripts. Or use this skill if the agent wants to understand how the extracted data is structured.  The skill documents the specification of the related source data schema and maps to the target data schema.
---

# Extracting IM Data Skill

## When to use this skill

Use this skill if the agent wants to understand the data extraction process from a IM manuscript 
database. The skill have the specification of what to be extracted from the source database and
how to consolidate the extracted data into the target data schema in JSON format.

## How to use it

### Source data

The source data is in a PostgreSQL database. All tables are in the `public` schema.

The database contains a `term` table which acts as a controlled vocabulary table. When querying a term, use the `trm_id` to query the row and then output value from the `trm_labels` column. Note that the
format in `trm_labels` column is like `lang=>"Label"`, where `lang` is the language code and `Label` is the label value. For example, `en=>"SystemOntology"`. When getting the term label, simply return the label part without the language code. For example, `SystemOntology`.

### Target data

The target data should be a JSON object looks similar as the following, where each property defines
a list of entity objects.

```json
{
    "texts": [],
    "images": [],
    "editions": [],
    "graphemes": [],
    "annotatedGraphemes": [],
    "segments": [],
    "tokens": [],
    "lines": []
}
```

### Extracting Process

#### 1. Graphemes

Get a unique list of graphemes from the `grapheme` table ordered by the `gra_sort_code` column. 
Deduplicate them by the `gra_grapheme` column. Then add each unique grapheme to the `graphemes` array 
in the target data. Each grapheme object should have the following properties:

- `id`: assign a sequential integer starting from 1.
- `grapheme`: the `gra_grapheme` column as is.
- `type`: use the id from the `gra_type_id` column to query the `term` table and get the term label.
- `decomposition`: the `gra_decomposition` column as is.
- `sortCode`: the `gra_sort_code` column as is.
- `emmendation`: the `gra_emmendation` column as is.

For example:

```json
{
    "id": 1,
    "grapheme": "v",
    "type": "Consonant",
    "decomposition": "v.",
    "sortCode": "100",
    "emmendation": "v+"
}
```

#### 2. Annotated Graphemes

Get the whole list of graphemes from the `grapheme` table ordered by the `gra_id` column. Then add each 
grapheme to the `annotatedGraphemes` array in the target data. Each grapheme object should have the 
following properties:

- `id`: the `gra_id` column as is.
- `grapheme`: use the value from the `gra_grapheme` column to search the `graphemes` array in the 
target data and use the `id` of the result grapheme in this property.
- `textCriticalMark`: the `gra_text_critical_mark` column as is.

For example:

```json
{
    "id": 1,
    "grapheme": 1,
    "textCriticalMark": "U"
}
```

#### 3. Segments

Get the list of segments from the `syllablecluster` table joining the `segment` table on the 
`scl_segment_id` column. Then add each segment to the `segments` array in the target data. Each segment 
object should have the following properties:

- `id`: the `scl_id` column as is.
- `graphemes`: the `scl_grapheme_ids` column. This column is an array of grapheme ids. Firstly validate
each id in the array to make sure it exists in the `annotatedGraphemes` array in the target data. If it 
is valid, add it to the `graphemes` property array. Otherwise, skip it.
- `clarity`: use the value from `seg_clarity_id` column to query the `term` table and get the term 
label.
- `obscurations`: the `seg_obscurations` column as is.
- `coordinates`: the `seg_image_pos` column as is.

For example:

```json
{
    "id": 1,
    "graphemes": [1, 2],
    "clarity": "5",
    "obscurations": "Scratch",
    "coordinates": "((1596,1402),(1614,1316),(1645,1281),(1676,1283),(1696,1307),(1725,1396),(1706,1425),(1657,1438),(1623,1434))"
}
```

#### 4. Tokens

The compounds from the database will be concatenated into tokens in the target data. Get a list of compounds from the `compound` table. Record the mapping of `com_id` and token IDs from the `cmp_component_ids` column. Note that each token ID is in the format of `tok:id` (e.g. `tok:1`). The order of token IDs in the `cmp_component_ids` column defines the order of tokens in the compound.

Get the list of tokens from the `token` table. Then add each token to the `tokens` array in the target 
data. Note that tokens from compounds need to be concatenated into a single token. Each token object should have the following properties:

- `id`: the `tok_id` column as is. If the token is from a compound, use the compound ID in the format of `cmp:id` (e.g. `cmp:1`).
- `graphemes`: the `tok_grapheme_ids` column. This column is an array of grapheme ids. Firstly validate
each id in the array to make sure it exists in the `annotatedGraphemes` array in the target data. If it 
is valid, add it to the `graphemes` property array. Otherwise, skip it. If the token is from a compound, the grapheme ids should be the concatenation of the grapheme ids from all tokens in the compound.

For example:

```json
{
    "id": 1,
    "graphemes": [1, 2, 3]
}
```

For a compound token:

```json
{
    "id": "cmp:1",
    "graphemes": [1, 2, 3, 4, 5]
}
```

#### 5. Lines

Following these steps to get the list of lines from the source database:

1. Query the rows from the `sequence` table where the `seq_type_id` is equal to 736.
2. Get the value from the `seq_entity_ids` column. The value is an array of sub sequence IDs where each 
item is in the format of `seq:id` (e.g. `seq:1`). Parse each item to get the ID number and produce the 
array of sequence IDs.
3. Query the rows from the `sequence` table where the `seq_id` is in the array of sequence IDs from the previous step. Then add each sequence to the `lines` array in the target data. Remember the order of lines should be exactly the same as the order in the `seq_entity_ids` column from step 2. Each sequence object should have the following properties:

- `id`: the `seq_id` column as is.
- `segments`: the `seq_entity_ids` column. This column is an array of segment ids where each item is in the format of `scl:id` (e.g. `scl:1`). Parse each item to get the ID number and produce the array of segment IDs. Validate each id in the array to make sure it exists in the `segments` array in the target data. If it is valid, add it to the `segments` property array. Otherwise, skip it.
- `label`: the `seq_label` column as is.
- `parentSeq`: the `seq_id` of the parent sequence (`seq_type_id` = 736) from step 1.

For example:

```json
{
    "id": 1,
    "segments": [1, 2, 3],
    "label": "Line 1",
    "parentSeq": 1
}
```

#### 6. Editions

Get the list of editions from the `edition` table. Then add each edition to the `editions` array in the target data. Each edition object should have the following properties:

- `id`: the `edn_id` column as is.
- `label`: the `edn_description` column as is.
- `lines`: the value from the`edn_sequence_ids` column is an array of sequence IDs. For each ID, find all the lines that have the ID as their `parentSeq` from the `lines` array in the target data, and add the `id` of each line to the `lines` property array. Remember the order of lines should be exactly the same as the order in the `lines` array in the target data.
- `type`: use the value from the `edn_type_id` column to query the `term` table and get the term label.
- `owner`: use the value from the `edn_owner_id` column to query the `usergroup` table. Concatenate the `ugr_given_name` and `ugr_family_name` columns to get the owner name. 
- `text`: the `edn_text_id` column as is.

For example:

```json
{
    "id": 1,
    "label": "Edition 1",
    "lines": [1, 2, 3],
    "type": "Reference",
    "owner": "John Doe",
    "text": 1
}
```

#### 7. Images

Get the list of images from the `image` table. Then add each image to the `images` array in the target data. Each image object should have the following properties:

- `id`: the `img_id` column as is.
- `label`: the `img_title` column as is.
- `url`: the `img_url` column as is.
- `type`: use the value from the `img_type_id` column to query the `term` table and get the term label.

For example:

```json
{
    "id": 1,
    "label": "Image 1",
    "url": "https://example.com/image.jpg",
    "type": "InscriptionRubbing"
}
```

#### 8. Texts

Get the list of texts from the `text` table. Then add each text to the `texts` array in the target data. Each text object should have the following properties:

- `id`: the `txt_id` column as is.
- `label`: the `txt_title` column as is.
- `ckn`: the `txt_ckn` column as is.
- `textRef`: the `txt_ref` column as is.
- `types`: the value from `txt_type_ids` column is an array of type IDs. For each ID, find the type label from the `term` table and add it to the `types` property array.
- `images`: the value from `txt_image_ids` column is an array of image IDs. Validate each ID in the array to make sure it exists in the `images` array in the target data. If it is valid, add it to the `images` property array. Otherwise, skip it.

For example:

```json
{
    "id": 1,
    "label": "Text 1",
    "ckn": "CKN 1",
    "textRef": "Text Ref 1",
    "types": ["Type 1", "Type 2"],
    "images": [1, 2]
}
```

### Notes

- When getting the value from a column in the source database, make sure to check if the value is null. If it is null, set the property value in the target data to `null`.
