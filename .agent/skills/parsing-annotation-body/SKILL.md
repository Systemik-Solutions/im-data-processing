---
name: parsing-annotation-body
description: Use this skill when the agent how to parse the IIIF annotation body based on the annotation template system.
---

# Parsing Annotation Body

Tha annotation template system extends the Web Annotation Data Model to support structured annotation data. It breaks the body of an annotation into multiple fields which are represented by the HTML markups in the `TextualBody` value.

## Body items

The body of an annotation is an array of `TexualBody` resources. Each `TextualBody` resource is corresponding to a field value in the annotation template system. Note that a field can have multiple values where each value is represented by a single `TextualBody` resource with the same field label.

The following is an example of an annotation with a single field called `Grapheme` and two values:

```json
{
    "id": "https://systemik-solutions.github.io/im-data-processing/output/RS22_02/segment/1",
    "type": "Annotation",
    "motivation": "describing",
    "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK",
    "target": {
        "source": "https://systemik-solutions.github.io/im-data-processing/output/RS22_02/canvas/1",
        "selector": {
            "type": "SvgSelector",
            "value": "<svg><polygon points=\"2347,241 2455,241 2455,449 2347,449\" /></svg>"
        }
    },
    "body": [
        {
            "type": "TextualBody",
            "language": "en",
            "format": "text/html",
            "value": "<p><span class=\"field-label\"><b>Grapheme</b></span>: <span class=\"field-value\"><a href=\"https://systemik-solutions.github.io/im-data-processing/output/RS22_02/grapheme/%CA%94\">ʔ</a></span></p>",
            "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/66"
        },
        {
            "type": "TextualBody",
            "language": "en",
            "format": "text/html",
            "value": "<p><span class=\"field-label\"><b>Grapheme</b></span>: <span class=\"field-value\"><a href=\"https://systemik-solutions.github.io/im-data-processing/output/RS22_02/grapheme/e\">e</a></span></p>",
            "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/66"
        }
    ]
}
```

### Parsing the field label

In the `value` of the `TextualBody` resource, find the element with the class name `field-label` and get the text content of the element as the field label.

For example, in the annotation:

```json
{
    "id": "https://systemik-solutions.github.io/im-data-processing/output/RS22_02/token/2",
    "type": "Annotation",
    "motivation": "linking",
    "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6PTSE4R77Y5KWEBH0RZ7SA",
    "body": [
        {
            "type": "TextualBody",
            "language": "en",
            "format": "text/html",
            "value": "<p><span class=\"field-label\"><b>Offset</b></span>: <span class=\"field-value\">0</span></p>",
            "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6PTSE4R77Y5KWEBH0RZ7SA/field/69"
        }
    ],
    "target": "https://systemik-solutions.github.io/im-data-processing/output/RS22_02/segment/3"
}
```

The field label is `Offset`.

### Parsing the text field value

In the `value` of the `TextualBody` resource, find the element with the class name `field-value` and get the text content of the element as the field value.

In the above example, the field value is `0`.

### Parsing the grapheme field value

The `Grapheme` field value is different from other field values. It is a HTML link element with the class name `field-value` and the text content of the element as the grapheme value. The `href` attribute of the link element is the URL of the grapheme resource. And optionally, the `data-tcm` attribute of the link element is the Text Critical Mark of the grapheme.

For example, in the annotation:

```json
{
    "id": "https://systemik-solutions.github.io/im-data-processing/output/RS22_02/segment/3",
    "type": "Annotation",
    "motivation": "describing",
    "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK",
    "target": {
        "source": "https://systemik-solutions.github.io/im-data-processing/output/RS22_02/canvas/1",
        "selector": {
            "type": "SvgSelector",
            "value": "<svg><polygon points=\"2215,233 2300,233 2300,335 2215,335\" /></svg>"
        }
    },
    "body": [
        {
            "type": "TextualBody",
            "language": "en",
            "format": "text/html",
            "value": "<p><span class=\"field-label\"><b>Grapheme</b></span>: <span class=\"field-value\"><a data-tcm=\"U\" href=\"https://systemik-solutions.github.io/im-data-processing/output/RS22_02/grapheme/m\">m</a></span></p>",
            "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/66"
        },
        {
            "type": "TextualBody",
            "language": "en",
            "format": "text/html",
            "value": "<p><span class=\"field-label\"><b>Grapheme</b></span>: <span class=\"field-value\"><a href=\"https://systemik-solutions.github.io/im-data-processing/output/RS22_02/grapheme/e\">e</a></span></p>",
            "generator": "https://w3id.org/iaw/data/publications/annotation-template/01KG6P5F6TJR44JXPPFFPVHAFK/field/66"
        }
    ]
}
```

The first `Grapheme` field value is `m` with the Text Critical Mark `U`, and the URL of the grapheme resource is `https://systemik-solutions.github.io/im-data-processing/output/RS22_02/grapheme/m`.

The second `Grapheme` field value is `e` with no Text Critical Mark, and the URL of the grapheme resource is `https://systemik-solutions.github.io/im-data-processing/output/RS22_02/grapheme/e`.
