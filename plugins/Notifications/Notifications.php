<?php

declare(strict_types=1);

namespace Plugins\Notifications;

use Core\User;

if (!defined('IN_KAMI')) die();

final class Notifications extends \Core\BasePlugin
{
    private const STYLES = [
        'default',
        'success',
        'alert',
        'danger',
    ];

    private const CLEANUP_ONE_IN = 500;
    private const CLEANUP_BATCH = 1000;

    public function view(array $instanceParams = []): string
    {
        return $this->render('notifications', [
            'endpoint' => '/ajax/Notifications/get',
        ]);
    }

    public function store(
        string $sessionId,
        ?int $userId,
        string $text,
        string $style = 'default'
    ): void {
        $sessionId = trim($sessionId);
        $text = trim($text);
        $style = strtolower(trim($style));

        if ($sessionId === '') {
            throw new \InvalidArgumentException('Notification session ID cannot be empty.');
        }
        if ($userId !== null && $userId < 1) {
            throw new \InvalidArgumentException('Notification user ID must be positive or null.');
        }
        if ($text === '') {
            throw new \InvalidArgumentException('Notification text cannot be empty.');
        }
        if (!in_array($style, self::STYLES, true)) {
            throw new \InvalidArgumentException("Unsupported notification style: {$style}.");
        }

        $expire = max(0, (int)($this->settings['expire'] ?? 0));

        $result = \DB::query(
            "INSERT INTO notification_messages (
                session_id,
                user_id,
                text,
                style,
                expires_at
             ) VALUES (
                $1,
                $2,
                $3,
                $4,
                CASE
                    WHEN $5::integer > 0
                    THEN CURRENT_TIMESTAMP + ($5::integer * INTERVAL '1 second')
                    ELSE NULL
                END
             )",
            [$sessionId, $userId, $text, $style, $expire]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to store notification.');
        }
    }

    public function get(): string
    {
        \Core\Response::addHeader('Content-Type: application/json; charset=utf-8');
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');

        $sessionId = User::getSessionId();
        if (!$sessionId) {
            return $this->jsonResponse([]);
        }

        $guestOnly = User::isGuest();
        $where = $guestOnly
            ? 'session_id=$1 AND user_id IS NULL'
            : 'session_id=$1';

        $result = \DB::query(
            "WITH consumed AS (
                DELETE FROM notification_messages
                WHERE {$where}
                RETURNING notification_id, text, style, created_at, expires_at
             )
             SELECT notification_id, text, style, created_at
             FROM consumed
             WHERE expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP
             ORDER BY created_at, notification_id",
            [$sessionId]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to consume notifications.');
        }

        $messages = [];
        while ($row = \DB::fetchRow($result)) {
            $messages[] = [
                'id' => (int)$row['notification_id'],
                'text' => (string)$row['text'],
                'style' => (string)$row['style'],
                'created_at' => (string)$row['created_at'],
            ];
        }

        $this->maybeCleanupExpired();

        return $this->jsonResponse($messages);
    }

    public function clearAuthenticated(string $sessionId): void
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return;
        }

        $result = \DB::query(
            'DELETE FROM notification_messages
             WHERE session_id=$1
               AND user_id IS NOT NULL',
            [$sessionId]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to clear authenticated notifications.');
        }
    }

    private function maybeCleanupExpired(): void
    {
        if (mt_rand(1, self::CLEANUP_ONE_IN) !== 1) {
            return;
        }

        \DB::query(
            'DELETE FROM notification_messages target
             USING (
                 SELECT notification_id
                 FROM notification_messages
                 WHERE expires_at IS NOT NULL
                   AND expires_at <= CURRENT_TIMESTAMP
                 ORDER BY expires_at, notification_id
                 LIMIT ' . self::CLEANUP_BATCH . '
             ) expired
             WHERE target.notification_id=expired.notification_id'
        );
    }

    private function jsonResponse(array $messages): string
    {
        return json_encode(
            [
                'status' => 'ok',
                'messages' => $messages,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
