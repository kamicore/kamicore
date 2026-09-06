<?php
declare(strict_types=1);

namespace Core;

class Content
{
    public static array $known_types = [];
    public static array $known_fields = [];
    public static array $known_items = [];
    public static ?array $field_map = null;

    private static array $known_field_types = [];
    private static ?array $content_type_map = null;

    /**
     * Clear request-local caches for content structure metadata.
     * Persistent cache keys are invalidated by ContentStructure.
     */
    public static function resetStructureCache(): void
    {
        self::$known_types = [];
        self::$known_fields = [];
        self::$field_map = null;
        self::$known_field_types = [];
        self::$content_type_map = null;
    }

    public static function create(int|string $contentType, array $data = []): int
    {
        $contentTypeId = (int) self::getContentType($contentType)['ct_id'];
        $itemData = ['ct_id' => $contentTypeId];

        foreach (['author_id', 'plugin_id', 'item_slug', 'parent_id', 'domain_scope', 'domains', 'item_settings'] as $column) {
            if (array_key_exists($column, $data)) {
                $itemData[$column] = $data[$column];
            }
        }

        if (!array_key_exists('author_id', $itemData) && defined('USER_ID')) {
            $itemData['author_id'] = USER_ID;
        }

        if (isset($itemData['domains']) && is_array($itemData['domains'])) {
            $itemData['domains'] = self::pgIntArray($itemData['domains']);
        }

        if (isset($itemData['item_settings']) && is_array($itemData['item_settings'])) {
            $itemData['item_settings'] = self::encodeJson($itemData['item_settings']);
        }

        $itemId = \DB::insert('content_items', $itemData, 'item_id');

        if (!is_numeric($itemId)) {
            throw new \RuntimeException('Failed to create content item.');
        }

        return (int) $itemId;
    }

    public static function update(
        int $id,
        array $data,
        ?string $lang = null,
        array $syncLanguages = []
    ): bool {
        $lang ??= LANG;
        $syncLanguages = array_values(array_unique(array_filter(
            array_map(static fn(mixed $value): string => trim((string) $value), $syncLanguages),
            static fn(string $value): bool => $value !== '' && $value !== $lang
        )));
        $data = self::normalizeFieldKeys($data);

        \DB::beginTransaction();

        try {
            $item = \DB::getRow(
                'SELECT * FROM content_items WHERE item_id=$1 FOR UPDATE',
                [$id]
            );

            if (!$item) {
                throw new \OutOfBoundsException("Unknown content item: {$id}");
            }

            $contentType = self::getContentType((int) $item['ct_id'], $lang);
            $structure = $contentType['schema']['fields'] ?? [];
            $commonData = self::decodeJson($item['common_data'] ?? null);
            $translatedData = self::loadExactTranslation($item['item_uuid'], $lang);
            unset($translatedData['title']);

            $indexRows = [
                'item_texts' => [],
                'item_nums' => [],
                'item_bools' => [],
                'item_dates' => [],
            ];
            $translationChanged = false;

            foreach ($structure as $fieldName => $schemaField) {
                if (!array_key_exists($fieldName, $data)) {
                    continue;
                }

                $field = self::getField($fieldName);
                $settings = array_replace(
                    $field['type_settings'],
                    $field['field_settings'],
                    $schemaField['settings'] ?? []
                );
                $multiple = !empty($settings['multiple']);
                $translatable = !empty($settings['translatable']);
                $value = self::normalizeStoredValue($data[$fieldName], $multiple);

                if ($field['root_type_name'] === 'boolean') {
                    $value = self::normalizeBooleanFieldValue($value, $multiple);
                }

                if (!empty($settings['unique'])) {
                    self::assertUniqueFieldValue(
                        $field,
                        $value,
                        $multiple,
                        $id,
                        $translatable ? $lang : null
                    );
                }

                if ($translatable) {
                    self::storeJsonValue($translatedData, $fieldName, $value);
                    $translationChanged = true;
                } else {
                    self::storeJsonValue($commonData, $fieldName, $value);
                }

                if (empty($settings['indexed'])) {
                    continue;
                }

                $table = self::indexTable($field['root_type_name']);
                if ($translatable && $table !== 'item_texts') {
                    throw new \LogicException(
                        "Indexed translatable field '{$fieldName}' requires a language-aware index table."
                    );
                }

                self::clearFieldIndex(
                    $table,
                    $id,
                    (int) $field['field_id'],
                    $translatable ? $lang : null
                );

                foreach (self::indexValues($value, $multiple) as $indexValue) {
                    $row = self::makeIndexRow(
                        $table,
                        $id,
                        (int) $field['field_id'],
                        $indexValue,
                        $translatable ? $lang : null
                    );

                    if ($row !== null) {
                        $indexRows[$table][] = $row;
                    }
                }
            }

            $slug = $item['item_slug'] ?? null;
            $titleField = $contentType['schema']['title_field'] ?? null;

            if (!empty($contentType['has_slug'])) {
                if (array_key_exists('item_slug', $data) && trim((string) $data['item_slug']) !== '') {
                    $slug = trim((string) $data['item_slug']);
                } elseif ($titleField && array_key_exists($titleField, $data)) {
                    $titleValue = $data[$titleField];
                    $slug = is_scalar($titleValue) && (string) $titleValue !== ''
                        ? createSlug((string) $titleValue)
                        : null;
                }
            }

            $parentId = array_key_exists('parent_id', $data)
                ? self::nullablePositiveInt($data['parent_id'])
                : self::nullablePositiveInt($item['parent_id'] ?? null);

            \DB::query(
                'UPDATE content_items
                 SET item_slug=$1, parent_id=$2, common_data=$3::jsonb, updated_at=NOW()
                 WHERE item_id=$4',
                [$slug, $parentId, self::encodeJson($commonData), $id]
            );

            if ($translationChanged) {
                self::saveExactTranslation($item['item_uuid'], $lang, $translatedData);
            }

            if ($syncLanguages !== []) {
                \DB::query(
                    'UPDATE translations
                     SET updated_at=NOW()
                     WHERE entity_uuid=$1 AND lang_code=ANY($2::text[])',
                    [$item['item_uuid'], self::pgTextArray($syncLanguages)]
                );
            }

            foreach ($indexRows as $table => $rows) {
                if ($rows !== []) {
                    \DB::bulk_insert($table, $rows);
                }
            }

            \DB::commit();
            self::invalidateItemCache($id, $item['item_uuid'], $lang, $syncLanguages);

            return true;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Patch translatable fields without marking the content item as edited.
     *
     * Existing translation timestamps are preserved. A translation created by
     * this patch inherits the current content item timestamp.
     */
    public static function patchTranslation(
        int $id,
        array $data,
        string $lang
    ): bool {
        $lang = trim($lang);
        if ($lang === '') {
            throw new \InvalidArgumentException('Translation language is required.');
        }

        $data = self::normalizeFieldKeys($data);

        \DB::beginTransaction();

        try {
            $item = \DB::getRow(
                'SELECT * FROM content_items WHERE item_id=$1 FOR UPDATE',
                [$id]
            );

            if (!$item) {
                throw new \OutOfBoundsException("Unknown content item: {$id}");
            }

            $contentType = self::getContentType((int) $item['ct_id'], $lang);
            $structure = $contentType['schema']['fields'] ?? [];
            $translatedData = self::loadExactTranslation($item['item_uuid'], $lang);
            unset($translatedData['title']);

            $indexRows = [];
            $changed = false;

            foreach ($structure as $fieldName => $schemaField) {
                if (!array_key_exists($fieldName, $data)) {
                    continue;
                }

                $field = self::getField($fieldName);
                $settings = array_replace(
                    $field['type_settings'],
                    $field['field_settings'],
                    $schemaField['settings'] ?? []
                );

                if (empty($settings['translatable'])) {
                    continue;
                }

                $multiple = !empty($settings['multiple']);
                $value = self::normalizeStoredValue($data[$fieldName], $multiple);

                if (!empty($settings['unique'])) {
                    self::assertUniqueFieldValue($field, $value, $multiple, $id, $lang);
                }

                self::storeJsonValue($translatedData, $fieldName, $value);
                $changed = true;

                if (empty($settings['indexed'])) {
                    continue;
                }

                $table = self::indexTable($field['root_type_name']);
                if ($table !== 'item_texts') {
                    throw new \LogicException(
                        "Indexed translatable field '{$fieldName}' requires a language-aware index table."
                    );
                }

                self::clearFieldIndex(
                    $table,
                    $id,
                    (int) $field['field_id'],
                    $lang
                );

                foreach (self::indexValues($value, $multiple) as $indexValue) {
                    $row = self::makeIndexRow(
                        $table,
                        $id,
                        (int) $field['field_id'],
                        $indexValue,
                        $lang
                    );

                    if ($row !== null) {
                        $indexRows[] = $row;
                    }
                }
            }

            if (!$changed) {
                \DB::commit();
                return true;
            }

            self::savePatchedTranslation(
                $item['item_uuid'],
                $lang,
                $translatedData,
                (string) $item['updated_at']
            );

            if ($indexRows !== []) {
                \DB::bulk_insert('item_texts', $indexRows);
            }

            \DB::commit();
            self::invalidateItemCache($id, $item['item_uuid'], $lang);

            return true;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public static function reindex(int $itemId): void
    {
        \DB::beginTransaction();

        try {
            $item = \DB::getRow(
                'SELECT * FROM content_items WHERE item_id=$1 FOR UPDATE',
                [$itemId]
            );

            if (!$item) {
                throw new \OutOfBoundsException("Unknown content item: {$itemId}");
            }

            $contentType = self::getContentType((int) $item['ct_id']);
            $structure = $contentType['schema']['fields'] ?? [];
            $commonData = self::decodeJson($item['common_data'] ?? null);
            $translations = [];
            $translationRows = \DB::query(
                'SELECT lang_code, translated_data
                 FROM translations
                 WHERE entity_uuid=$1',
                [$item['item_uuid']]
            );

            while ($row = \DB::fetchRow($translationRows)) {
                $translations[$row['lang_code']] = self::decodeJson($row['translated_data']);
            }

            foreach (['item_texts', 'item_nums', 'item_bools', 'item_dates'] as $table) {
                \DB::delete($table, 'item_id=$1', [$itemId]);
            }

            $indexRows = [
                'item_texts' => [],
                'item_nums' => [],
                'item_bools' => [],
                'item_dates' => [],
            ];

            foreach ($structure as $fieldName => $schemaField) {
                $field = self::getField($fieldName);
                $settings = array_replace(
                    $field['type_settings'],
                    $field['field_settings'],
                    $schemaField['settings'] ?? []
                );

                if (empty($settings['indexed'])) {
                    continue;
                }

                $table = self::indexTable($field['root_type_name']);
                $multiple = !empty($settings['multiple']);
                $translatable = !empty($settings['translatable']);

                if ($translatable && $table !== 'item_texts') {
                    throw new \LogicException(
                        "Indexed translatable field '{$fieldName}' requires a language-aware index table."
                    );
                }

                $sources = $translatable ? $translations : [null => $commonData];

                foreach ($sources as $sourceLang => $sourceData) {
                    if (!array_key_exists($fieldName, $sourceData)) {
                        continue;
                    }

                    if (!empty($settings['unique'])) {
                        self::assertUniqueFieldValue(
                            $field,
                            $sourceData[$fieldName],
                            $multiple,
                            $itemId,
                            $translatable ? (string)$sourceLang : null
                        );
                    }

                    foreach (self::indexValues($sourceData[$fieldName], $multiple) as $value) {
                        $row = self::makeIndexRow(
                            $table,
                            $itemId,
                            (int) $field['field_id'],
                            $value,
                            $translatable ? (string) $sourceLang : null
                        );

                        if ($row !== null) {
                            $indexRows[$table][] = $row;
                        }
                    }
                }
            }

            foreach ($indexRows as $table => $rows) {
                if ($rows !== []) {
                    \DB::bulk_insert($table, $rows);
                }
            }

            \DB::commit();
            self::invalidateItemCache($itemId, $item['item_uuid']);
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool
    {
        \DB::beginTransaction();

        try {
            $item = \DB::getRow(
                'SELECT item_uuid FROM content_items WHERE item_id=$1 FOR UPDATE',
                [$id]
            );

            if (!$item) {
                \DB::rollBack();
                return false;
            }
            $cachedLanguages = \DB::getArr(
                'SELECT lang_code FROM translations WHERE entity_uuid=$1',
                [$item['item_uuid']]
            );

            foreach (['item_texts', 'item_nums', 'item_bools', 'item_dates'] as $table) {
                \DB::delete($table, 'item_id=$1', [$id]);
            }

            \DB::delete('translations', 'entity_uuid=$1', [$item['item_uuid']]);
            \DB::delete('content_items', 'item_id=$1', [$id]);
            \DB::commit();

            self::invalidateItemCache($id, $item['item_uuid'], null, $cachedLanguages);
            return true;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public static function fieldMap(int|string $field): ?int
    {
        if (self::$field_map === null) {
            self::$field_map = \Cache::get('globals:field_map');

            if (!is_array(self::$field_map)) {
                self::$field_map = [];
                $rows = \DB::query('SELECT field_id, system_name FROM fields');

                while ($row = \DB::fetchRow($rows)) {
                    self::$field_map[$row['system_name']] = (int) $row['field_id'];
                    self::$field_map[(int) $row['field_id']] = (int) $row['field_id'];
                }

                \Cache::set('globals:field_map', self::$field_map);
            }
        }

        $key = self::identifierKey($field);
        if (isset(self::$field_map[$key])) {
            return (int) self::$field_map[$key];
        }

        $row = self::isNumericIdentifier($field)
            ? \DB::getRow(
                'SELECT field_id, system_name FROM fields WHERE field_id=$1',
                [(int) $field]
            )
            : \DB::getRow(
                'SELECT field_id, system_name FROM fields WHERE system_name=$1',
                [(string) $field]
            );

        if (!$row) {
            return null;
        }

        $fieldId = (int) $row['field_id'];
        self::$field_map[$fieldId] = $fieldId;
        self::$field_map[$row['system_name']] = $fieldId;
        \Cache::set('globals:field_map', self::$field_map);

        return $fieldId;
    }

    public static function getField(int|string $field): array
    {
        $fieldId = self::fieldMap($field);

        if ($fieldId === null) {
            throw new \InvalidArgumentException("Unknown field: {$field}");
        }

        if (isset(self::$known_fields[$fieldId])) {
            return self::$known_fields[$fieldId];
        }

        $cached = \Cache::get("globals:fields:f_{$fieldId}");
        if (is_array($cached)) {
            self::$known_fields[$fieldId] = $cached;
            self::$known_fields[$cached['system_name']] = $cached;
            return $cached;
        }

        $row = \DB::getRow('SELECT * FROM fields WHERE field_id=$1', [$fieldId]);
        if (!$row) {
            throw new \InvalidArgumentException("Unknown field: {$field}");
        }

        $fieldType = self::getFieldType((int) $row['type_id']);
        $row['field_id'] = (int) $row['field_id'];
        $row['type_id'] = (int) $row['type_id'];
        $row['field_settings'] = self::decodeJson($row['field_settings'] ?? null);
        $row['type_settings'] = $fieldType['type_settings'];
        $row['root_type_id'] = $fieldType['root_type_id'];
        $row['root_type_name'] = $fieldType['root_type_name'];

        self::$known_fields[$fieldId] = $row;
        self::$known_fields[$row['system_name']] = $row;
        \Cache::set("globals:fields:f_{$fieldId}", $row);

        return $row;
    }

    public static function getFieldType(int|string $fieldType): array
    {
        $cacheKey = self::identifierKey($fieldType);

        if (isset(self::$known_field_types[$cacheKey])) {
            return self::$known_field_types[$cacheKey];
        }

        $row = self::isNumericIdentifier($fieldType)
            ? \DB::getRow('SELECT * FROM field_types WHERE type_id=$1', [(int) $fieldType])
            : \DB::getRow('SELECT * FROM field_types WHERE system_name=$1', [(string) $fieldType]);

        if (!$row) {
            throw new \InvalidArgumentException("Unknown field type: {$fieldType}");
        }

        $chain = [];
        $current = $row;

        while ($current) {
            $chain[] = $current;
            $parentId = (int) ($current['parent_id'] ?? 0);
            $current = $parentId > 0
                ? \DB::getRow('SELECT * FROM field_types WHERE type_id=$1', [$parentId])
                : false;
        }

        $settings = [];
        $parameters = [];
        foreach (array_reverse($chain) as $part) {
            $partSettings = self::decodeJson($part['type_settings'] ?? null);
            $partParameters = is_array($partSettings['parameters'] ?? null)
                ? $partSettings['parameters']
                : [];
            unset($partSettings['parameters']);

            $settings = array_replace($settings, $partSettings);
            foreach ($partParameters as $name => $definition) {
                if (!is_string($name) || $name === '') {
                    continue;
                }
                if ($definition === null || $definition === false) {
                    unset($parameters[$name]);
                    continue;
                }
                if (!is_array($definition)) {
                    continue;
                }
                $parameters[$name] = array_replace(
                    is_array($parameters[$name] ?? null) ? $parameters[$name] : [],
                    $definition
                );
            }
        }
        if ($parameters !== []) {
            $settings['parameters'] = $parameters;
        }

        $root = end($chain);
        $row['type_id'] = (int) $row['type_id'];
        $row['parent_id'] = (int) ($row['parent_id'] ?? 0);
        $row['type_settings'] = $settings;
        $row['root_type_id'] = (int) $root['type_id'];
        $row['root_type_name'] = $root['system_name'];

        self::$known_field_types[(int) $row['type_id']] = $row;
        self::$known_field_types[$row['system_name']] = $row;

        return $row;
    }

    public static function findByField(
        int|string $field,
        mixed $value,
        int|string|array|null $contentTypes = null,
        ?string $lang = null
    ): array {
        $lang ??= LANG;
        $definition = self::getField($field);
        $settings = array_replace($definition['type_settings'], $definition['field_settings']);

        if (empty($settings['indexed'])) {
            throw new \RuntimeException('Field is not indexed: ' . $definition['system_name']);
        }

        $table = self::indexTable($definition['root_type_name']);
        if ($table === 'item_bools') {
            $value = self::normalizeBooleanScalar($value);
        }

        $params = [(int) $definition['field_id'], $value];
        $where = ['idx.field_id=$1', 'idx.value=$2'];

        if ($table === 'item_texts') {
            $where[] = 'md5(idx.value)=md5($2::text)';

            if (!empty($settings['translatable'])) {
                $params[] = $lang;
                $where[] = 'idx.lang_code=$3';
            } else {
                $where[] = 'idx.lang_code IS NULL';
            }
        }

        $contentTypeIds = self::resolveContentTypeIds($contentTypes);
        if ($contentTypeIds !== []) {
            $params[] = self::pgIntArray($contentTypeIds);
            $where[] = 'ci.ct_id=ANY($' . count($params) . '::int[])';
        }

        return array_map(
            'intval',
            \DB::getArr(
                'SELECT idx.item_id
                 FROM ' . $table . ' idx
                 JOIN content_items ci USING(item_id)
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY idx.item_id',
                $params
            )
        );
    }

    public static function exists(
        int|string $contentType,
        int|string $field,
        mixed $value,
        ?string $lang = null
    ): ?int {
        $ids = self::findByField($field, $value, $contentType, $lang);
        return $ids[0] ?? null;
    }

    public static function getContentType(int|string $contentType, ?string $lang = null): array
    {
        $lang ??= LANG;
        $key = self::identifierKey($contentType);

        if (isset(self::$known_types[$lang][$key])) {
            return self::$known_types[$lang][$key];
        }

        $contentTypeId = self::resolveContentTypeIds($contentType)[0] ?? null;
        if ($contentTypeId === null) {
            throw new \InvalidArgumentException("Unknown content type: {$contentType}");
        }

        $cached = \Cache::get("globals:content_types:{$contentTypeId}_{$lang}");
        if (is_array($cached)) {
            self::rememberContentType($cached, $lang);
            return $cached;
        }

        $data = \DB::getRow('SELECT * FROM content_types WHERE ct_id=$1', [$contentTypeId]);
        if (!$data) {
            throw new \InvalidArgumentException("Unknown content type: {$contentType}");
        }

        $data['ct_id'] = (int) $data['ct_id'];
        $data['parent_id'] = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        $data['schema'] = self::decodeJson($data['schema'] ?? null);
        $translation = getTranslation($data['uuid'], $lang) ?? [];
        $data = array_replace_recursive($data, $translation);
        $data['schema'] = self::resolveContentTypeSchema($data['schema'], $lang);
        $data['title'] ??= ucwords(str_replace(['_', '-'], ' ', $data['system_name']));
        $data['description'] ??= null;

        self::rememberContentType($data, $lang);
        \Cache::set("globals:content_types:{$contentTypeId}_{$lang}", $data);

        return $data;
    }

    public static function getStructure(int|string $contentType, ?string $lang = null): array
    {
        return self::getContentType($contentType, $lang)['schema']['fields'] ?? [];
    }

    public static function getItem(int $id, ?string $lang = null): array
    {
        if ($id <= 0) {
            return [];
        }

        $lang ??= LANG;

        if (isset(self::$known_items[$lang][$id])) {
            return self::$known_items[$lang][$id];
        }

        $item = \Cache::get("globals:content_items:{$id}_{$lang}");

        if (!is_array($item)) {
            $item = \DB::getRow('SELECT content_items.*, content_types.system_name as content_type_name FROM content_items left join content_types using(ct_id) WHERE item_id=$1', [$id]) ?: [];

            if ($item !== []) {
                $item['item_id'] = (int) $item['item_id'];
                $item['ct_id'] = (int) $item['ct_id'];

                $translation = getTranslation($item['item_uuid'], $lang) ?? [];
                $structure = self::getContentType($item['ct_id'], $lang)['schema']['fields'] ?? [];
                $translatedData = [];

                foreach ($translation as $fieldName => $value) {
                    if (
                        isset($structure[$fieldName])
                        && !empty($structure[$fieldName]['settings']['translatable'])
                    ) {
                        $translatedData[$fieldName] = $value;
                    }
                }

                $item['data'] = array_replace(
                    self::decodeJson($item['common_data'] ?? null),
                    $translatedData
                );
                $item = self::addDisplayFields($item, $lang);
                \Cache::set("globals:content_items:{$id}_{$lang}", $item);
            }
        }

        self::$known_items[$lang][$id] = $item;
        return $item;
    }

    public static function findByTitle(
        string $title,
        ?array $contentTypes = null,
        bool $exact = true,
        int $limit = 0,
        ?string $lang = null
    ): array {
        $lang ??= LANG;
        $contentTypeIds = self::resolveContentTypeIds($contentTypes);
        $params = [$lang, $exact ? $title : "%{$title}%"];
        if ($contentTypeIds !== []) {
            $titleFields = [];

            foreach ($contentTypeIds as $contentTypeId) {
                $titleField = self::getContentType($contentTypeId, $lang)['schema']['title_field'] ?? null;
                if ($titleField !== null) {
                    $titleFields[] = $titleField;
                }
            }

            $titleFields = array_values(array_unique($titleFields));
            if (count($titleFields) === 1) {
                $field = self::getField($titleFields[0]);
                $settings = array_replace($field['type_settings'], $field['field_settings']);

                if (!empty($settings['indexed']) && self::indexTable($field['root_type_name']) === 'item_texts') {
                    $result = self::search(
                        $contentTypeIds,
                        'substr',
                        null,
                        [[
                            'field_id' => (int) $field['field_id'],
                            'mode' => $exact ? 'eq' : 'substr',
                            'value' => $title,
                        ]],
                        null,
                        0,
                        $limit,
                        $lang
                    );

                    return $result['ids'];
                }
            }
        }

        $operator = $exact ? '=' : 'ILIKE';
        $typeSql = '';

        if ($contentTypeIds !== []) {
            $params[] = self::pgIntArray($contentTypeIds);
            $typeSql = 'AND ci.ct_id=ANY($3::int[])';
        }

        $limitSql = $limit > 0 ? 'LIMIT ' . $limit : '';
        $idValue = ltrim($title, '#');
        $params[] = $exact ? $idValue : "%{$idValue}%";
        $idParam = '$' . count($params);

        $sql = "SELECT DISTINCT ci.item_id
                FROM content_items ci
                JOIN content_types content_type USING(ct_id)
                LEFT JOIN translations translation
                  ON translation.entity_uuid=ci.item_uuid
                 AND translation.lang_code=$1
                WHERE (
                    COALESCE(
                        translation.translated_data->>(content_type.schema->>'title_field'),
                        ci.common_data->>(content_type.schema->>'title_field')
                    ) {$operator} $2
                    OR (
                        content_type.schema->>'title_field' IS NULL
                        AND (
                            ci.item_id::text {$operator} {$idParam}
                            OR COALESCE(ci.item_slug, '') {$operator} $2
                        )
                    )
                )
                {$typeSql}
                ORDER BY ci.item_id
                {$limitSql}";

        return array_map('intval', \DB::getArr($sql, $params));
    }

    public static function search(
        array $contentTypes,
        string $mode = 'substr',
        ?string $query = null,
        ?array $filter = null,
        ?array $order = null,
        int $offset = 0,
        int $limit = 0,
        ?string $lang = null
    ): array {
        $lang ??= LANG;
        $plan = self::buildSearchPlan($contentTypes, $mode, $query, $filter, $order, $lang);

        if ($plan === null) {
            return ['ids' => [], 'totals' => 0];
        }

        $limitSql = $limit > 0 ? 'LIMIT ' . $limit : '';
        $offset = max(0, $offset);
        $ids = \DB::getArr(
            'SELECT ci.item_id' . $plan['select'] . '
             FROM content_items ci
             ' . $plan['joins'] . '
             WHERE ' . $plan['where'] . '
             ' . $plan['order'] . '
             OFFSET ' . $offset . ' ' . $limitSql,
            $plan['params']
        );

        $totals = \DB::getOne(
            'SELECT count(*)
             FROM content_items ci
             WHERE ' . $plan['where'],
            array_slice($plan['params'], 0, $plan['where_param_count'])
        );

        return [
            'ids' => array_map('intval', $ids),
            'totals' => (int) $totals,
        ];
    }

    public static function searchItems(
        array $contentTypes,
        string $mode = 'substr',
        ?string $query = null,
        ?array $filter = null,
        ?array $order = null,
        int $offset = 0,
        int $limit = 0,
        ?string $lang = null
    ): array {
        $lang ??= LANG;
        $result = self::search(
            $contentTypes,
            $mode,
            $query,
            $filter,
            $order,
            $offset,
            $limit,
            $lang
        );

        if ($result['ids'] === []) {
            return [
                'items' => \DB::query('SELECT * FROM content_items WHERE false'),
                'totals' => $result['totals'],
            ];
        }

        $idArray = self::pgIntArray($result['ids']);
        $items = \DB::query(
            'SELECT ci.*, translation.translated_data
             FROM content_items ci
             LEFT JOIN translations translation
               ON translation.entity_uuid=ci.item_uuid
              AND translation.lang_code=$1
             WHERE ci.item_id=ANY($2::bigint[])
             ORDER BY array_position($2::bigint[], ci.item_id)',
            [$lang, $idArray]
        );

        return ['items' => $items, 'totals' => $result['totals']];
    }

    public static function prepareItem(array $item, ?string $lang = null): array
    {
        $item['data'] = array_replace(
            self::decodeJson($item['common_data'] ?? null),
            self::decodeJson($item['translated_data'] ?? null)
        );

        return self::addDisplayFields($item, $lang ?? LANG);
    }

    private static function buildSearchPlan(
        array $contentTypes,
        string $mode,
        ?string $query,
        ?array $filters,
        ?array $order,
        string $lang
    ): ?array {
        $contentTypeIds = self::resolveContentTypeIds($contentTypes);
        if ($contentTypeIds === []) {
            return null;
        }

        $params = [];
        $where = [];
        $addParam = static function (mixed $value) use (&$params): string {
            $params[] = $value;
            return '$' . count($params);
        };

        $typeParam = $addParam(self::pgIntArray($contentTypeIds));
        $where[] = "ci.ct_id=ANY({$typeParam}::int[])";

        foreach ($filters ?? [] as $filterIndex => $part) {
            $fieldKeys = $part['field_ids']
                ?? $part['fields']
                ?? [$part['field_id'] ?? $part['field_name'] ?? $part['field'] ?? null];
            $fieldKeys = array_values(array_filter((array) $fieldKeys, static fn($value): bool => $value !== null && $value !== ''));

            if ($fieldKeys === []) {
                throw new \InvalidArgumentException("Search filter {$filterIndex} has no field.");
            }

            $fields = array_map(static fn($field): array => self::getField($field), $fieldKeys);
            $tables = array_unique(array_map(
                static fn(array $field): string => self::indexTable($field['root_type_name']),
                $fields
            ));

            if (count($tables) !== 1) {
                throw new \InvalidArgumentException("Search filter {$filterIndex} mixes incompatible field types.");
            }

            $hasValue = array_key_exists('value', $part);
            $hasValues = array_key_exists('values', $part);
            if (!$hasValue && !$hasValues) {
                continue;
            }

            $table = reset($tables);
            $fieldIds = array_map(static fn(array $field): int => (int) $field['field_id'], $fields);
            $fieldParam = $addParam(self::pgIntArray($fieldIds));
            $conditions = ["idx.field_id=ANY({$fieldParam}::int[])"];
            $filterMode = strtolower((string) ($part['mode'] ?? ($hasValues ? 'in' : 'eq')));

            if ($filterMode === 'in') {
                $values = array_values(array_filter(
                    (array) ($part['values'] ?? $part['value'] ?? []),
                    static fn($value): bool => $value !== null && $value !== ''
                ));

                if ($values === []) {
                    $where[] = 'false';
                    continue;
                }

                if ($table === 'item_bools') {
                    $values = array_map([self::class, 'normalizeBooleanScalar'], $values);
                }

                $cast = match ($table) {
                    'item_nums' => 'numeric[]',
                    'item_bools' => 'boolean[]',
                    'item_dates' => 'timestamptz[]',
                    default => 'text[]',
                };
                $valueParam = $addParam(
                    $table === 'item_bools'
                        ? self::pgBoolArray($values)
                        : self::pgTextArray($values)
                );
                $conditions[] = "idx.value=ANY({$valueParam}::{$cast})";
            } else {
                $value = $part['value'] ?? null;

                if ($table === 'item_bools') {
                    if (!in_array($filterMode, ['eq', 'neq'], true)) {
                        throw new \InvalidArgumentException(
                            'Boolean fields support only eq, neq and in search modes.'
                        );
                    }
                    $value = self::normalizeBooleanScalar($value);
                }

                $valueParam = $addParam($filterMode === 'substr' ? "%{$value}%" : $value);
                $operator = match ($filterMode) {
                    'substr' => 'ILIKE',
                    'gt' => '>',
                    'gte' => '>=',
                    'lt' => '<',
                    'lte' => '<=',
                    'neq' => '<>',
                    'eq' => '=',
                    default => throw new \InvalidArgumentException("Unsupported search mode: {$filterMode}"),
                };

                if ($filterMode === 'substr' && $table !== 'item_texts') {
                    throw new \InvalidArgumentException('Substring search is available only for text fields.');
                }

                $conditions[] = "idx.value {$operator} {$valueParam}";
                if ($table === 'item_texts' && $filterMode === 'eq') {
                    $conditions[] = "md5(idx.value)=md5({$valueParam}::text)";
                }
            }

            if ($table === 'item_texts') {
                $translationModes = array_values(array_unique(array_map(
                    static fn(array $field): bool => !empty(array_replace(
                        $field['type_settings'],
                        $field['field_settings']
                    )['translatable']),
                    $fields
                )));

                if ($translationModes === [true]) {
                    $langParam = $addParam($lang);
                    $conditions[] = "idx.lang_code={$langParam}";
                } elseif ($translationModes === [false]) {
                    $conditions[] = 'idx.lang_code IS NULL';
                } else {
                    $langParam = $addParam($lang);
                    $conditions[] = "(idx.lang_code={$langParam} OR idx.lang_code IS NULL)";
                }
            }

            $where[] = 'EXISTS (
                SELECT 1 FROM ' . $table . ' idx
                WHERE idx.item_id=ci.item_id
                  AND ' . implode(' AND ', $conditions) . '
            )';
        }

        if ($query !== null && $query !== '') {
            $queryParam = $addParam($mode === 'substr' ? "%{$query}%" : $query);
            $langParam = $addParam($lang);

            if ($mode === 'fulltext') {
                $config = \DB::getOne(
                    "SELECT COALESCE(cfg_name, 'simple') FROM languages WHERE lang_code=$1",
                    [$lang]
                ) ?? 'simple';
                $configParam = $addParam($config);
                $queryCondition = "(
                    (search_text.lang_code={$langParam}
                     AND search_text.tsv @@ websearch_to_tsquery({$configParam}::regconfig, {$queryParam}))
                    OR
                    (search_text.lang_code IS NULL
                     AND search_text.tsv @@ websearch_to_tsquery('simple', {$queryParam}))
                )";
            } elseif ($mode === 'substr') {
                $queryCondition = "search_text.value ILIKE {$queryParam}";
            } else {
                throw new \InvalidArgumentException("Unsupported search mode: {$mode}");
            }

            $where[] = "EXISTS (
                SELECT 1 FROM item_texts search_text
                WHERE search_text.item_id=ci.item_id
                  AND (search_text.lang_code={$langParam} OR search_text.lang_code IS NULL)
                  AND {$queryCondition}
            )";
        }

        $whereParamCount = count($params);
        $select = '';
        $joins = '';
        $orderParts = [];

        foreach ($order ?? [] as $sortIndex => $part) {
            $fieldKey = $part['field_id'] ?? $part['field_name'] ?? $part['field'] ?? null;
            if ($fieldKey === null) {
                throw new \InvalidArgumentException("Sort rule {$sortIndex} has no field.");
            }

            $field = self::getField($fieldKey);
            $table = self::indexTable($field['root_type_name']);
            $direction = strtolower((string) ($part['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $aggregate = $direction === 'DESC' ? 'MAX' : 'MIN';
            $valueExpression = $table === 'item_bools'
                ? 'sort_value.value::int'
                : 'sort_value.value';
            $fieldParam = $addParam((int) $field['field_id']);
            $langSql = '';

            if ($table === 'item_texts') {
                $langParam = $addParam($lang);
                $langSql = "AND (sort_value.lang_code={$langParam} OR sort_value.lang_code IS NULL)";
            }

            $alias = 'sort_' . $sortIndex;
            $joins .= " LEFT JOIN LATERAL (
                SELECT {$aggregate}({$valueExpression}) AS value
                FROM {$table} sort_value
                WHERE sort_value.item_id=ci.item_id
                  AND sort_value.field_id={$fieldParam}
                  {$langSql}
            ) {$alias} ON true\n";
            $select .= ", {$alias}.value AS {$alias}";
            $orderParts[] = "{$alias}.value {$direction} NULLS LAST";
        }

        if ($orderParts === []) {
            $orderParts[] = 'ci.item_id ASC';
        } else {
            $orderParts[] = 'ci.item_id ASC';
        }

        return [
            'select' => $select,
            'joins' => $joins,
            'where' => implode(' AND ', $where),
            'order' => 'ORDER BY ' . implode(', ', $orderParts),
            'params' => $params,
            'where_param_count' => $whereParamCount,
        ];
    }

    private static function resolveContentTypeIds(int|string|array|null $contentTypes): array
    {
        if ($contentTypes === null) {
            return [];
        }

        if (self::$content_type_map === null) {
            self::$content_type_map = \Cache::get('globals:content_type_map');

            if (!is_array(self::$content_type_map)) {
                self::$content_type_map = [];
                $rows = \DB::query('SELECT ct_id, system_name FROM content_types');

                while ($row = \DB::fetchRow($rows)) {
                    self::$content_type_map[(int) $row['ct_id']] = (int) $row['ct_id'];
                    self::$content_type_map[$row['system_name']] = (int) $row['ct_id'];
                }

                \Cache::set('globals:content_type_map', self::$content_type_map);
            }
        }

        $ids = [];
        foreach ((array) $contentTypes as $contentType) {
            $key = self::identifierKey($contentType);

            if (!isset(self::$content_type_map[$key])) {
                $row = self::isNumericIdentifier($contentType)
                    ? \DB::getRow(
                        'SELECT ct_id, system_name FROM content_types WHERE ct_id=$1',
                        [(int) $contentType]
                    )
                    : \DB::getRow(
                        'SELECT ct_id, system_name FROM content_types WHERE system_name=$1',
                        [(string) $contentType]
                    );

                if (!$row) {
                    throw new \InvalidArgumentException("Unknown content type: {$contentType}");
                }

                $contentTypeId = (int) $row['ct_id'];
                self::$content_type_map[$contentTypeId] = $contentTypeId;
                self::$content_type_map[$row['system_name']] = $contentTypeId;
                \Cache::set('globals:content_type_map', self::$content_type_map);
            }

            $ids[] = (int) self::$content_type_map[$key];
        }

        return array_values(array_unique($ids));
    }

    private static function rememberContentType(array $contentType, string $lang): void
    {
        self::$known_types[$lang][(int) $contentType['ct_id']] = $contentType;
        self::$known_types[$lang][$contentType['system_name']] = $contentType;
    }

    private static function addDisplayFields(array $item, string $lang): array
    {
        $contentType = self::getContentType((int) $item['ct_id'], $lang);
        $titleField = $contentType['schema']['title_field'] ?? null;
        $summaryField = $contentType['schema']['summary_field'] ?? null;
        $title = $titleField ? ($item['data'][$titleField] ?? null) : null;
        $summary = $summaryField ? ($item['data'][$summaryField] ?? null) : null;

        $item['title'] = is_scalar($title) && (string) $title !== ''
            ? strip_tags((string) $title)
            : "#{$item['item_id']}";
        $item['summary'] = is_scalar($summary) ? strip_tags((string) $summary) : null;

        return $item;
    }

    private static function normalizeFieldKeys(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if ((is_int($key) || (is_string($key) && ctype_digit($key))) && self::fieldMap((int) $key) !== null) {
                $fieldName = self::getField((int) $key)['system_name'];
                $data[$fieldName] = $data[$key];
                unset($data[$key]);
            }
        }

        return $data;
    }

    private static function loadExactTranslation(string $uuid, string $lang): array
    {
		debug_step('************** trans');
        return self::decodeJson(
            \DB::getOne(
                'SELECT translated_data FROM translations WHERE entity_uuid=$1 AND lang_code=$2',
                [$uuid, $lang]
            )
        );
    }

    private static function saveExactTranslation(string $uuid, string $lang, array $data): void
    {
        if ($data === []) {
            \DB::delete('translations', 'entity_uuid=$1 AND lang_code=$2', [$uuid, $lang]);
            return;
        }

        \DB::query(
            'INSERT INTO translations(entity_uuid, lang_code, translated_data, updated_at)
             VALUES($1, $2, $3::jsonb, NOW())
             ON CONFLICT (entity_uuid, lang_code)
             DO UPDATE SET
                 translated_data=EXCLUDED.translated_data,
                 updated_at=EXCLUDED.updated_at',
            [$uuid, $lang, self::encodeJson($data)]
        );
    }

    private static function savePatchedTranslation(
        string $uuid,
        string $lang,
        array $data,
        string $itemUpdatedAt
    ): void {
        if ($data === []) {
            \DB::delete('translations', 'entity_uuid=$1 AND lang_code=$2', [$uuid, $lang]);
            return;
        }

        \DB::query(
            'INSERT INTO translations(entity_uuid, lang_code, translated_data, updated_at)
             VALUES($1, $2, $3::jsonb, $4::timestamptz)
             ON CONFLICT (entity_uuid, lang_code)
             DO UPDATE SET translated_data=EXCLUDED.translated_data',
            [$uuid, $lang, self::encodeJson($data), $itemUpdatedAt]
        );
    }

    private static function assertUniqueFieldValue(
        array $field,
        mixed $value,
        bool $multiple,
        int $itemId,
        ?string $lang
    ): void {
        $table = self::indexTable((string)$field['root_type_name']);
        $fieldId = (int)$field['field_id'];

        foreach (self::indexValues($value, $multiple) as $candidate) {
            $row = self::makeIndexRow($table, $itemId, $fieldId, $candidate, $lang);
            if ($row === null) {
                continue;
            }

            $lockKey = $fieldId . "\0" . ($lang ?? '') . "\0" . (string)$row['value'];
            \DB::query(
                'SELECT pg_advisory_xact_lock(hashtextextended($1, 0))',
                [$lockKey]
            );

            $params = [$fieldId, $itemId, $row['value']];
            $where = ['field_id=$1', 'item_id<>$2', 'value=$3'];
            if ($table === 'item_texts') {
                $where[] = 'md5(value)=md5($3::text)';
                if ($lang === null) {
                    $where[] = 'lang_code IS NULL';
                } else {
                    $params[] = $lang;
                    $where[] = 'lang_code=$4';
                }
            }

            $duplicate = \DB::getOne(
                'SELECT item_id FROM ' . $table
                . ' WHERE ' . implode(' AND ', $where)
                . ' LIMIT 1',
                $params
            );
            if ($duplicate) {
                throw new \DomainException(
                    "Field '{$field['system_name']}' requires unique values; "
                    . "the value is already used by item #{$duplicate}."
                );
            }
        }
    }

    private static function clearFieldIndex(
        string $table,
        int $itemId,
        int $fieldId,
        ?string $lang
    ): void {
        if ($table === 'item_texts') {
            if ($lang === null) {
                \DB::delete(
                    $table,
                    'item_id=$1 AND field_id=$2 AND lang_code IS NULL',
                    [$itemId, $fieldId]
                );
            } else {
                \DB::delete(
                    $table,
                    'item_id=$1 AND field_id=$2 AND lang_code=$3',
                    [$itemId, $fieldId, $lang]
                );
            }
            return;
        }

        \DB::delete($table, 'item_id=$1 AND field_id=$2', [$itemId, $fieldId]);
    }

    private static function makeIndexRow(
        string $table,
        int $itemId,
        int $fieldId,
        mixed $value,
        ?string $lang
    ): ?array {
        if ($value === null || $value === '' || is_array($value) || is_object($value)) {
            return null;
        }

        if ($table === 'item_texts') {
            return [
                'item_id' => $itemId,
                'field_id' => $fieldId,
                'lang_code' => $lang,
                'value' => strip_tags((string) $value),
            ];
        }

        if ($table === 'item_bools') {
            $value = self::normalizeBooleanScalar($value);
        }

        if ($table === 'item_nums') {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            if (!is_numeric($value)) {
                return null;
            }
        }

        return [
            'item_id' => $itemId,
            'field_id' => $fieldId,
            'value' => $value,
        ];
    }

    private static function indexValues(mixed $value, bool $multiple): array
    {
        if ($value === null) {
            return [];
        }

        return $multiple ? (is_array($value) ? $value : [$value]) : [$value];
    }

    private static function normalizeStoredValue(mixed $value, bool $multiple): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$multiple) {
            return is_array($value) ? ($value[0] ?? null) : $value;
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_filter(
            $values,
            static fn($item): bool => $item !== null && $item !== ''
        ));

        return array_values(array_unique($values, SORT_REGULAR));
    }

    private static function normalizeBooleanFieldValue(mixed $value, bool $multiple): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$multiple) {
            return self::normalizeBooleanScalar($value);
        }

        if ($value === []) {
            return [];
        }

        return array_values(array_unique(array_map(
            [self::class, 'normalizeBooleanScalar'],
            (array) $value
        ), SORT_REGULAR));
    }

    private static function normalizeBooleanScalar(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return match ($value) {
                1, 1.0 => true,
                0, 0.0 => false,
                default => throw new \InvalidArgumentException('Invalid boolean value.'),
            };
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 't', 'yes', 'y', 'on' => true,
                '', '0', 'false', 'f', 'no', 'n', 'off' => false,
                default => throw new \InvalidArgumentException('Invalid boolean value.'),
            };
        }

        throw new \InvalidArgumentException('Invalid boolean value.');
    }

    private static function storeJsonValue(array &$data, string $fieldName, mixed $value): void
    {
        if ($value === null || $value === []) {
            unset($data[$fieldName]);
            return;
        }

        $data[$fieldName] = $value;
    }

    private static function indexTable(string $rootType): string
    {
        return match ($rootType) {
            'number' => 'item_nums',
            'boolean' => 'item_bools',
            'date' => 'item_dates',
            default => 'item_texts',
        };
    }

    private static function invalidateItemCache(
        int $itemId,
        string $uuid,
        ?string $lang = null,
        array $additionalLanguages = []
    ): void
    {
        $languages = \DB::getArr(
            'SELECT lang_code FROM translations WHERE entity_uuid=$1',
            [$uuid]
        );
        $languages = array_merge($languages, $additionalLanguages);

        if ($lang !== null) {
            $languages[] = $lang;
        }
        if (defined('LANG')) {
            $languages[] = LANG;
        }
        if (defined('DOMAIN_CONFIG') && !empty(DOMAIN_CONFIG['default_language'])) {
            $languages[] = DOMAIN_CONFIG['default_language'];
        }

        foreach (array_unique($languages) as $language) {
            \Cache::del("globals:content_items:{$itemId}_{$language}");
            \Cache::del("globals:{$uuid}_{$language}");
        }

        foreach (array_keys(self::$known_items) as $language) {
            unset(self::$known_items[$language][$itemId]);
        }
    }

    private static function resolveContentTypeSchema(array $schema, string $lang): array
    {
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $resolvedFields = [];

        foreach ($fields as $fieldName => $local) {
            if (!is_string($fieldName) || !is_array($local)) {
                continue;
            }

            $field = self::getField($fieldName);
            $fieldType = self::getFieldType((int)$field['type_id']);
            $globalTranslation = getTranslation((string)$field['uuid'], $lang) ?? [];

            $typeSettings = is_array($field['type_settings'] ?? null)
                ? $field['type_settings']
                : [];
            $parameterDefinitions = is_array($typeSettings['parameters'] ?? null)
                ? $typeSettings['parameters']
                : [];
            unset($typeSettings['parameters']);

            $globalSettings = is_array($field['field_settings'] ?? null)
                ? $field['field_settings']
                : [];
            $globalParams = is_array($globalSettings['params'] ?? null)
                ? $globalSettings['params']
                : [];
            unset($globalSettings['params']);

            $localSettings = is_array($local['settings'] ?? null)
                ? $local['settings']
                : [];
            foreach (['indexed', 'unique', 'translatable'] as $globalOnly) {
                unset($localSettings[$globalOnly]);
            }

            $resolved = $local;
            unset($resolved['params']);
            $resolved['type'] = (string)$fieldType['system_name'];
            $resolved['settings'] = array_replace(
                $typeSettings,
                $globalSettings,
                $localSettings
            );
            $resolved['title'] = (string)(
                $local['title']
                ?? $globalTranslation['title']
                ?? $fieldName
            );
            $resolved['description'] = (string)(
                $local['description']
                ?? $globalTranslation['description']
                ?? ''
            );

            $params = [];
            foreach ($parameterDefinitions as $name => $definition) {
                if (
                    is_string($name)
                    && is_array($definition)
                    && array_key_exists('default', $definition)
                ) {
                    $params[$name] = $definition['default'];
                }
            }
            $params = array_replace($params, $globalParams);

            if (is_array($params['options'] ?? null)) {
                $params['options'] = self::translateFieldOptions(
                    $params['options'],
                    is_array($globalTranslation['options'] ?? null)
                        ? $globalTranslation['options']
                        : [],
                    is_array($local['options'] ?? null)
                        ? $local['options']
                        : []
                );
            }
            unset($resolved['options']);

            if ($params !== []) {
                $resolved['params'] = $params;
            }

            $resolvedFields[$fieldName] = $resolved;
        }

        $schema['fields'] = $resolvedFields;
        return $schema;
    }

    private static function translateFieldOptions(array $options, array ...$translations): array
    {
        $titles = [];
        foreach ($translations as $translation) {
            foreach ($translation as $index => $option) {
                if (!is_array($option) || !isset($option['title'])) {
                    continue;
                }
                $value = array_key_exists('value', $option)
                    ? (string)$option['value']
                    : (isset($options[$index]) && is_array($options[$index])
                        ? (string)($options[$index]['value'] ?? '')
                        : '');
                if ($value !== '') {
                    $titles[$value] = (string)$option['title'];
                }
            }
        }

        foreach ($options as &$option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            $value = (string)$option['value'];
            if (isset($titles[$value])) {
                $option['title'] = $titles[$value];
            }
        }
        unset($option);

        return $options;
    }

    private static function nullablePositiveInt(mixed $value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private static function identifierKey(int|string $identifier): int|string
    {
        return self::isNumericIdentifier($identifier) ? (int) $identifier : (string) $identifier;
    }

    private static function isNumericIdentifier(int|string $identifier): bool
    {
        return is_int($identifier) || ctype_digit((string) $identifier);
    }

    private static function decodeJson(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        if (!is_string($json) || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private static function encodeJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function pgIntArray(array $values): string
    {
        return '{' . implode(',', array_map('intval', $values)) . '}';
    }

    private static function pgTextArray(array $values): string
    {
        return '{' . implode(',', array_map(
            static fn($value): string => '"' . str_replace(
                ['\\', '"'],
                ['\\\\', '\\"'],
                (string) $value
            ) . '"',
            $values
        )) . '}';
    }

    private static function pgBoolArray(array $values): string
    {
        return '{' . implode(',', array_map(
            static fn(bool $value): string => $value ? 'true' : 'false',
            $values
        )) . '}';
    }
}
