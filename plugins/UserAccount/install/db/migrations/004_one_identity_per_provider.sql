ALTER TABLE user_auth_identities
    ADD CONSTRAINT user_auth_identities_user_provider_unique
    UNIQUE (user_id, provider);
