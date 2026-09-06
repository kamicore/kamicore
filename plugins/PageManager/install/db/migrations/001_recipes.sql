CREATE TABLE IF NOT EXISTS pgm_recipes (
    recipe_id SERIAL PRIMARY KEY,
    recipe_uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    recipe_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now()
);

DO $$
BEGIN
    IF to_regclass('public.pm_recipes') IS NOT NULL THEN
        EXECUTE '
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
                updated_at = EXCLUDED.updated_at';
    END IF;
END
$$;

SELECT setval(
    pg_get_serial_sequence('pgm_recipes', 'recipe_id'),
    COALESCE((SELECT MAX(recipe_id) FROM pgm_recipes), 1),
    EXISTS (SELECT 1 FROM pgm_recipes)
);
