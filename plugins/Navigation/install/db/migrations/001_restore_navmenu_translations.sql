WITH source_values AS (
    SELECT
        item.item_uuid,
        COALESCE(NULLIF(plugin.default_language, ''), 'en') AS lang_code,
        jsonb_strip_nulls(jsonb_build_object(
            'menu_title', item.common_data->'menu_title',
            'menu_description', item.common_data->'menu_description'
        )) AS translated_data
    FROM content_items item
    JOIN content_types content_type ON content_type.ct_id=item.ct_id
    LEFT JOIN plugins plugin ON plugin.plugin_id=content_type.plugin_id
    WHERE content_type.system_name='navmenu'
)
INSERT INTO translations (
    entity_uuid,
    lang_code,
    translated_data,
    translation_status,
    is_default
)
SELECT
    item_uuid,
    lang_code,
    translated_data,
    'draft',
    false
FROM source_values
WHERE translated_data <> '{}'::jsonb
ON CONFLICT (entity_uuid, lang_code) DO UPDATE
SET translated_data=COALESCE(translations.translated_data, '{}'::jsonb) || EXCLUDED.translated_data,
    updated_at=NOW();

UPDATE content_items item
SET common_data=COALESCE(item.common_data, '{}'::jsonb) - 'menu_title' - 'menu_description'
FROM content_types content_type
WHERE item.ct_id=content_type.ct_id
  AND content_type.system_name='navmenu'
  AND COALESCE(item.common_data, '{}'::jsonb) ?| ARRAY['menu_title', 'menu_description'];

UPDATE fields
SET field_settings=jsonb_set(
    COALESCE(field_settings, '{}'::jsonb),
    '{translatable}',
    'true'::jsonb,
    true
)
WHERE system_name IN ('menu_title', 'menu_description');
