---
name: generating-im-iiif
description: Use this skill when the user wants to convert the extracted IM data to IIIF format, or the agent wants to understand how the IIIF format is structured. The skill documents the specification of mapping from the extracted IM data to IIIF format.
---

# Generating IM IIIF Skill

## When to use this skill

Use this skill if the agent wants to understand the IIIF format or convert the extracted IM data to IIIF format. This skill have the specification how to convert the extracted IM data to IIIF resources such as manifest, canvas, annotation, etc.

## General rules

- The output IIIF resources should comply with the IIIF Presentation API 3.0. Annotations should comply with the Web Annotation Data Model.
- For IIIF resource IDs, unless specified, generate a dummy URI based on the entity type and ID from the extracted IM data. For example, if the entity type is `text`, the ID is `1`, then the URI is `https://example.com/text/1`.
- For IIIF resource `label`, `summary`, `metadata` values, use the language code `en` by default.
- Don't add other properties for IIIF resources unless specified.
- When generating the HTML markups in the annotation body, make sure to escape the property value for the element text and attributes.
- The output IIIF resources should be stored under the `output` directory in the workspace by default.
- For convenience, in this skill, the word "resource" refers to the IIIF resources such as manifest, canvas, annotation, etc. The word "entity" refers to the entities in the extracted IM data such as text, image, edition, grapheme, etc.
- Assume there is only one text entity, one image entity, and one edition entity in the extracted IM data.
- If a property value is null in the entity, don't use it at all for the IIIF resource.

## How to use it

### 1. Preparation

Firstly, the agent needs to know where the extracted IM data is stored. This would normally be a JSON file. If it's not clear where to find the file, the agent can ask the user to provide the file path.

Then, the agent should know the database name which will be used to construct the output filenames. Ask the user if it's not clear.

If it is necessary for the agent to understand the extracted IM data structure, refer to the `extracting-im-data` skill.

### 2. Text

Convert the text entity from the IM extracted data into IIIF "Manifest" resource. For the "Manifest" resource:

- Generate the URI for the `id` property in the pattern of `https://example.com/{database_name}_manifest.json`. For example, `https://example.com/RS22_02_manifest.json`.
- Set the `label` property of the resource to the `label` property of text entity in language `en`.
- Add the `ckn`, `textRef`, `types` of the entity as the `metadata` entries `CKM`, `Text Ref`, `Types` of the manifest resource.
- For each ID in the `images` property array of the text entity, get the image entity from the IM extracted data. Refer to the "3. Images" section for how to convert the image entity and add the converted resource into the `items` property of the manifest resource.

### 3. Image

Convert the image entity to "Canvas" resource. For the "Canvas" resource:

- Generate the URI for the `id` property.
- Set the `label` property of the resource to the `label` property of image entity in language `en`.
- Add the `type` property of the entity as the `metadata` entry of the resource.
- Do the following for the `url` property of the image entity:
  - Fetch the image from its URL and get the image's width and height to set the `width` and `height` properties of the canvas resource.
  - Create a `AnnotationPage` resource and add it to the `items` property of the canvas resource. Set the `id` property based on the `id` of the canvas resource. For example, `https://example.com/image/1/page/1`.
  - Create a `Annotation` resource and add it to the `items` property of the annotation page resource. Set the `id` property based on the `id` of the annotation page resource. For example, `https://example.com/image/1/page/1/annotation/1`. Set the `motivation` property to `"painting"`. Set the `target` property to the `id` of the canvas resource.
  - Create a `Image` resource and add it to the `body` property of the annotation resource. Use the `url` of the image entity as the `id` property of the image resource. Set the `format` property to `image/jpeg`. Set the `height` and `width` properties to the image's height and width.
- Set the `annotations` property of the canvas resource to an array which contains the annotation page resource created from the segment entities. The full content of the annotation page should be embedded. See the "6. Segments" section for more details about the annotation page.


### 4. Edition

Convert the edition entity to `AnnotationCollection` resource. Note that this resource will be saved in a separate file later. For the `AnnotationCollection` resource:

- Generate the URI for the `id` property in the pattern of `https://example.com/{database_name}_annotation_collection.json`. For example, `https://example.com/RS22_02_annotation_collection.json`.
- Set the `label` property of the resource to the `label` property of edition entity in language `en`.
- Add the `type` and `owner` properties of the entity as the `metadata` entries of the resource.
- Count the total number of annotation resources created from the line, segment, token entities and set the `total` property of the resource to the count.
- Set the `first` property of the resource to an object with the following properties:
  - `id`: The `id` property of the `AnnotationPage` resource created from the list of segment entities. See the "6. Segments" section for the ID pattern of the annotation page.
  - `type`: Set to `AnnotationPage`.

### 5. Lines

For each line entity, create an "Annotation" resource with the following properties:

- `id`: Generate a URI based on the `id` of the line entity.
- `type`: Set to `Annotation`.
- `motivation`: Set to `linking`.
- `generator`: Set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6QDRZGTAD862RMZA73AZJF`.
- `body`: Set to an array contains the following objects:
  - Object created from the `label` property of the line entity with the following properties:
    - `type`: set to `TextualBody`.
    - `language`: set to `en`.
    - `format`: set to `text/html`.
    - `value`: use the HTML pattern of `<p><span class="field-label"><b>Label</b></span>: <span class="field-value">{VALUE}</span></p>` where `{VALUE}` is the value of the `label` property of the line entity.
    - `generator`: set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6QDRZGTAD862RMZA73AZJF/field/70`.
- `target`: use the `segments` property of the line entity to get the list of `id` of annotation resources generated from the segment entities. See the "6. Segments" section for the ID pattern of the annotation resource.

Once all annotation resources are created for line entities, create an `AnnotationPage` resource and add all line annotation resources to the `items` property of the annotation page resource. The annotation page resource should have the following properties:

- `id`: Generate the URI for the `id` property in the pattern of `https://example.com/{database_name}_annotation_page_lines.json`. For example, `https://example.com/RS22_02_annotation_page_lines.json`.
- `type`: Set to `AnnotationPage`.
- `partOf`: Set to the `AnnotationCollection` resource created from the edition entity. Remember to only include the `id`, `type`, and `label` properties of the annotation collection resource.
- `prev`: Set to the `AnnotationPage` resource created from the tokens. Only include the `id` and `type` properties of the annotation page resource. See the "8. Tokens" section for the annotation page resource created from the tokens.
- `items`: Set to the array of line annotation resources.

### 6. Segments

For each segment entity, create an "Annotation" resource with the following properties:

- `id`: Generate the URI for the `id` property in the pattern of `https://example.com/segment/{segment}`. For example, `https://example.com/segment/1`.
- `type`: Set to `Annotation`.
- `motivation`: Set to `"describing"`.
- `generator`: Set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK`.
- `target`: Set to an object with the following properties:
  - `source`: the `id` of the canvas resource created from the image entity.
  - `selector`: Set to an object with the following properties:
    - `type`: Set to `SvgSelector`.
    - `value`: Get the `(x,y)` coordinates pairs from the `coordinates` property of the segment entity. Then set the value to a SVG polygon based on the coordinates. For example, if the `coordinates` property value is `{"((2347,241),(2455,241),(2455,449),(2347,449))"}`, then the `value` property should be `<svg><polygon points="2347,241 2455,241 2455,449 2347,449" /></svg>`.
- `body`: Set to an array contains the following objects:
  - An object created from the `clarity` property of the segment entity with the following properties:
    - `type`: set to `TextualBody`.
    - `language`: set to `en`.
    - `format`: set to `text/html`.
    - `value`: use the HTML pattern of `<p><span class="field-label"><b>Clarity</b></span>: <span class="field-value">{VALUE}</span></p>` where `{VALUE}` is the value of the `clarity` property of the segment entity.
    - `generator`: set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/67`.
  - An object created from the `obscurations` property of the segment entity with the following properties:
    - `type`: set to `TextualBody`.
    - `language`: set to `en`.
    - `format`: set to `text/html`.
    - `value`: use the HTML pattern of `<p><span class="field-label"><b>Obscurations</b></span>: <span class="field-value">{VALUE}</span></p>` where `{VALUE}` is the value of the `obscurations` property of the segment entity.
    - `generator`: set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/68`.
  - For each ID in the `graphemes` property of the segment entity, create an object with the following properties:
    - `type`: set to `TextualBody`.
    - `language`: set to `en`.
    - `format`: set to `text/html`.
    - `value`: use the ID in the `grapheme` property of the segment entity to located the annotated grapheme in the `annotatedGraphemes` list of the IM extracted data. Firstly, get the value of the `textCriticalMark` property of the annotated grapheme. Then use the `grapheme` property value of the annotated grapheme to find the grapheme entity and get the grapheme URI and label (from the `grapheme` property of the grapheme entity) created in the "7. Graphemes" section. Then generate the HTML markup in this pattern to use as the value of the `value` property: `<p><span class="field-label"><b>Grapheme</b></span>: <span class="field-value"><a data-tcm="{TEXT_CRITICAL_MARK}" href="{GRAPHEME_URI}">{GRAPHEME_LABEL}</a></span></p>` where `{TEXT_CRITICAL_MARK}` is the value of the `textCriticalMark` property of the annotated grapheme, `{GRAPHEME_URI}` is the URI of the grapheme entity and `{GRAPHEME_LABEL}` is the value of the `grapheme` property of the grapheme entity. Ignore the `data-tcm` attribute if the `textCriticalMark` property value is empty.
    - `generator`: set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/66`.

Once all annotation resources are created for the segments, create an `AnnotationPage` resource and add the annotation resources to the `items` property of the `AnnotationPage` resource. The `AnnotationPage` resource should have the following properties:

- `id`: Generate the URI for the `id` property in the pattern of `https://example.com/{database_name}_annotation_page_segments.json`. For example, `https://example.com/RS22_02_annotation_page_segments.json`.
- `type`: Set to `AnnotationPage`.
- `partOf`: Set to the `AnnotationCollection` resource created from the edition entity. Remember to only include the `id`, `type`, and `label` properties of the annotation collection resource.
- `next`: Set to the `AnnotationPage` resource created from the tokens. Only include the `id` and `type` properties of the annotation page resource. See the "8. Tokens" section for the annotation page resource created from the tokens.
- `items`: Set to the array of segment annotation resources.

### 7. Graphemes

There is no need to create resources for grapheme entities. Instead, use the pattern of `https://example.com/grapheme/{grapheme}` to generate a list of grapheme URIs which can be referenced in the segment annotations. The `{grapheme}` placeholder in the pattern should be the `grapheme` property value of the grapheme entity. For example, if the `grapheme` property value is `a`, then the URI should be `https://example.com/grapheme/a`. Remember to escape any special characters from the property value for the URI.

### 8. Tokens

For each token entity, create an "Annotation" resource with the following properties:

- `id`: Generate a URI based on the `id` of the token entity.
- `type`: Set to `Annotation`.
- `motivation`: Set to `linking`.
- `generator`: Set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6PTSE4R77Y5KWEBH0RZ7SA`.
- `body`: Set to an array contains the following objects:
  - Refer to the "How to find the segments and offset of a token" section to get the offset value. Then use the value to create an object with the following properties:
    - `type`: set to `TextualBody`.
    - `language`: set to `en`.
    - `format`: set to `text/html`.
    - `value`: use the HTML pattern of `<p><span class="field-label"><b>Offset</b></span>: <span class="field-value">{VALUE}</span></p>` where `{VALUE}` is the offset value of the token.
    - `generator`: set to `https://w3id.org/iaw/data/publications/annotation-template/01KG6PTSE4R77Y5KWEBH0RZ7SA/field/69`.
- `target`: Refer to the "How to find the segments and offset of a token" section to get the segments of the token. Then set the `target` property to the `id` of the annotation resources created from the segments. See the "6. Segments" section for the ID pattern of the annotation resource.

Once all annotation resources are created for token entities, create an `AnnotationPage` resource and add all token annotation resources to the `items` property of the annotation page resource. The annotation page resource should have the following properties:

- `id`: Generate the URI for the `id` property in the pattern of `https://example.com/{database_name}_annotation_page_tokens.json`. For example, `https://example.com/RS22_02_annotation_page_tokens.json`.
- `type`: Set to `AnnotationPage`.
- `partOf`: Set to the `AnnotationCollection` resource created from the edition entity. Remember to only include the `id`, `type`, and `label` properties of the annotation collection resource.
- `prev`: Set to the `AnnotationPage` resource created from the segments. Only include the `id` and `type` properties of the annotation page resource. See the "6. Segments" section for the annotation page resource created from the segments.
- `next`: Set to the `AnnotationPage` resource created from the lines. Only include the `id` and `type` properties of the annotation page resource. See the "7. Lines" section for the annotation page resource created from the lines.
- `items`: Set to the array of token annotation resources.

#### How to find the segments and offset of a token

Use the `graphemes` property of the token entity to get the list grapheme entity IDs. Then match the `graphemes` property of the segment entity to get the segment entities. For example, a token entity has the `graphemes` of `[1, 2, 3, 4]`. Segment 1 has the `graphemes` of `[1, 2]`, segment 2 has the `graphemes` of `[3, 4]`. Then the token's segments are `[1, 2]` with no offset.

The offset is used when a segment is split across multiple tokens. For example, token 1 has the `graphemes` of `[1, 2, 3]`. token 2 has the `graphemes` of `[4, 5, 6]`. Segment 1 has the `graphemes` of `[1, 2]`, segment 2 has the `graphemes` of `[3, 4]`, segment 3 has the `graphemes` of `[5, 6]`. Then the token 1's segments are `[1, 2]` with an offset of `-1`, which means it trims off the last one grapheme of the last segment. The token 2's segments are `[2, 3]` with an offset of `1`, which means it trims off the first one grapheme of the first segment.

### 9. Saving the files

- Save the `Manifest` resource created from the text entity to a file with the name in the pattern of `{database_name}_manifest.json`. For example, `RS22_02_manifest.json`.
- Save the `AnnotationCollection` resource created from the edition entity to a file with the name in the pattern of `{database_name}_annotation_collection.json`. For example, `RS22_02_annotation_collection.json`.
- Save the `AnnotationPage` resource created from the segments to a file with the name in the pattern of `{database_name}_annotation_page_segments.json`. For example, `RS22_02_annotation_page_segments.json`.
- Save the `AnnotationPage` resource created from the lines to a file with the name in the pattern of `{database_name}_annotation_page_lines.json`. For example, `RS22_02_annotation_page_lines.json`.
- Save the `AnnotationPage` resource created from the tokens to a file with the name in the pattern of `{database_name}_annotation_page_tokens.json`. For example, `RS22_02_annotation_page_tokens.json`.

Remember to add the `@context` property with the value of `http://iiif.io/api/presentation/3/context.json` to the top level resource before saving the file.
