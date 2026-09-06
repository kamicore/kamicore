CREATE UNLOGGED TABLE IF NOT EXISTS notification_messages (
    notification_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    session_id TEXT NOT NULL,
    user_id BIGINT,
    text TEXT NOT NULL,
    style VARCHAR(16) NOT NULL DEFAULT 'default'
        CHECK (style IN ('default', 'success', 'alert', 'danger')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS notification_messages_recipient_idx
    ON notification_messages (session_id, user_id, created_at, notification_id);

CREATE INDEX IF NOT EXISTS notification_messages_expires_idx
    ON notification_messages (expires_at, notification_id)
    WHERE expires_at IS NOT NULL;

ALTER TABLE notification_messages SET (
    autovacuum_vacuum_scale_factor = 0.02,
    autovacuum_vacuum_threshold = 200,
    autovacuum_analyze_scale_factor = 0.05,
    autovacuum_analyze_threshold = 200
);
