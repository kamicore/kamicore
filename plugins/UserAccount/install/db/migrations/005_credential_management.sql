ALTER TABLE tokens
    ADD COLUMN token_data jsonb;

CREATE UNIQUE INDEX users_email_lower_unique
    ON users (lower(email))
    WHERE email IS NOT NULL;
