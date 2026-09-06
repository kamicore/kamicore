DO $$
BEGIN
    IF to_regclass('public.pgm_recipes') IS NULL THEN
        RAISE EXCEPTION 'PageManager recipe storage is not installed';
    END IF;

    IF to_regclass('public.pm_recipes') IS NOT NULL THEN
        INSERT INTO pgm_recipes (
            recipe_id,
            recipe_uuid,
            recipe_key,
            name,
            description,
            payload,
            created_at,
            updated_at
        )
        SELECT
            recipe_id,
            recipe_uuid,
            recipe_key,
            name,
            description,
            payload,
            created_at,
            updated_at
        FROM pm_recipes
        ON CONFLICT (recipe_id) DO UPDATE SET
            recipe_uuid = EXCLUDED.recipe_uuid,
            recipe_key = EXCLUDED.recipe_key,
            name = EXCLUDED.name,
            description = EXCLUDED.description,
            payload = EXCLUDED.payload,
            created_at = EXCLUDED.created_at,
            updated_at = EXCLUDED.updated_at;
    END IF;
END
$$;

ALTER TABLE pm_setup_resources
    DROP CONSTRAINT IF EXISTS pm_setup_resources_recipe_id_fkey;

ALTER TABLE pm_setup_resources
    ADD CONSTRAINT pm_setup_resources_recipe_id_fkey
    FOREIGN KEY (recipe_id)
    REFERENCES pgm_recipes(recipe_id)
    ON DELETE SET NULL;

DROP TABLE IF EXISTS pm_recipes;

SELECT setval(
    pg_get_serial_sequence('pgm_recipes', 'recipe_id'),
    COALESCE((SELECT MAX(recipe_id) FROM pgm_recipes), 1),
    EXISTS (SELECT 1 FROM pgm_recipes)
);
