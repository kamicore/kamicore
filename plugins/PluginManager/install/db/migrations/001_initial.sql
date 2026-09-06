CREATE TABLE pm_recipes (
    recipe_id SERIAL PRIMARY KEY,
    recipe_uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    recipe_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now()
);

CREATE TABLE pm_setup_history (
    setup_id SERIAL PRIMARY KEY,
    plugin_id INTEGER
        REFERENCES plugins(plugin_id) ON DELETE SET NULL,
    plugin_system_name TEXT NOT NULL,
    domain_id INTEGER
        REFERENCES domains(domain_id) ON DELETE SET NULL,
    action TEXT NOT NULL CHECK (
        action IN ('install', 'update', 'setup', 'remove_from_site', 'uninstall')
    ),
    status TEXT NOT NULL CHECK (status IN ('success', 'failed')),
    preset_name TEXT,
    config JSONB NOT NULL DEFAULT '{}'::jsonb,
    error TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now()
);

CREATE INDEX pm_setup_history_plugin_idx
    ON pm_setup_history (plugin_system_name, created_at DESC);

CREATE INDEX pm_setup_history_domain_idx
    ON pm_setup_history (domain_id, created_at DESC);

CREATE TABLE pm_setup_resources (
    setup_resource_id SERIAL PRIMARY KEY,
    setup_id INTEGER NOT NULL
        REFERENCES pm_setup_history(setup_id) ON DELETE CASCADE,
    resource_key TEXT NOT NULL,
    resource_type TEXT NOT NULL,
    resource_id BIGINT,
    resource_uuid UUID,
    ownership TEXT NOT NULL,
    recipe_id INTEGER
        REFERENCES pm_recipes(recipe_id) ON DELETE SET NULL,
    recipe_snapshot JSONB,
    config JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    UNIQUE (setup_id, resource_key)
);

CREATE INDEX pm_setup_resources_resource_idx
    ON pm_setup_resources (resource_type, resource_id);
