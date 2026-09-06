DELETE FROM global_settings a
USING global_settings b
WHERE a.varname = b.varname
  AND a.ctid < b.ctid;

ALTER TABLE global_settings
    DROP CONSTRAINT IF EXISTS global_settings_pkey;

ALTER TABLE global_settings
    ADD CONSTRAINT global_settings_pkey PRIMARY KEY (varname);

INSERT INTO global_settings(varname, value) VALUES
    ('cache_enabled', '1'),
    ('cache_ttl', '300')
ON CONFLICT (varname) DO NOTHING;
