<?php

declare(strict_types=1);

namespace Core;

if (!defined('IN_KAMI')) die();

final class User
{
    private static ?string $sessionId = null;
    private static ?string $uahash = null;

    public static ?array $session = null;
    public static ?array $user = null;
    public static ?array $group = null;

    private const ACL_CACHE_TTL = 300;
    private const ACL_CACHE_NS = 'acl_bag_v3:';

    public static function init(): void
    {
        self::$sessionId = Request::cookie()['session_id'] ?? null;
        self::$uahash = hash('sha256', normalizeUAgent());

        if (!self::$sessionId) {
            self::createSession();
        } else {
            self::$session = \Cache::get('d_' . DOMAIN_ID . ':sessions:' . self::$sessionId);

            if (
                self::$session
                && (
                    self::$session['ua_hash'] !== self::$uahash
                    || (
                        strtotime((string)self::$session['updated_at']) < TIME_NOW - GLOBAL_SETTINGS['session_timeout']
                        && empty(self::$session['is_persistent'])
                    )
                )
            ) {
                self::createSession();
            } elseif (!self::$session) {
                self::$session = \DB::getRow(
                    "SELECT session_id, user_id, ua_hash, is_persistent, updated_at
                     FROM sessions
                     WHERE domain_id=$1
                       AND session_id=$2
                       AND ua_hash=$3
                       AND (is_persistent OR updated_at>=NOW()-INTERVAL '" . (int)GLOBAL_SETTINGS['session_timeout'] . " seconds')
                     LIMIT 1",
                    [DOMAIN_ID, self::$sessionId, self::$uahash]
                );

                if (!self::$session) {
                    self::createSession();
                }
            }
        }

        self::$user = self::getUser();
        debug_step('get User');

        if (!self::$user) {
            self::createSession();
            self::$user = self::getUser(0);
        }

        self::$group = self::getGroup();
        debug_step('get group');

        $updatedAt = date('Y-m-d H:i:s.uP');
        self::$session['updated_at'] = $updatedAt;

        \Cache::set('d_' . DOMAIN_ID . ':sessions:' . self::$sessionId, self::$session);
        \DB::query(
            'UPDATE sessions SET updated_at=$1 WHERE domain_id=$2 AND session_id=$3',
            [$updatedAt, DOMAIN_ID, self::$sessionId]
        );

        if (!defined('USER_ID')) {
            define('USER_ID', (int)self::$session['user_id']);
        }
        if (!defined('USERGROUP_ID')) {
            define('USERGROUP_ID', (int)self::$user['usergroup_id']);
        }
    }

    public static function createSession(): string
    {
        $sessionId = self::$sessionId = generateSessionId();
        $sessionData = [
            'domain_id' => DOMAIN_ID,
            'session_id' => $sessionId,
            'user_id' => 0,
            'ua_hash' => self::$uahash,
            'is_persistent' => false,
            'updated_at' => date('Y-m-d H:i:s.uP'),
        ];

        \DB::insert('sessions', $sessionData);
        Response::addCookie('session_id', $sessionId, 0, true);

        self::$session = $sessionData;
        \Cache::set('d_' . DOMAIN_ID . ':sessions:' . $sessionId, self::$session);

        return $sessionId;
    }

    public static function authorize(int $userId, bool $remember): void
    {
        \DB::query(
            'UPDATE sessions SET user_id=$1, is_persistent=$2 WHERE session_id=$3',
            [$userId, $remember, self::$sessionId]
        );
        \Cache::del('d_' . DOMAIN_ID . ':sessions:' . self::$sessionId);
    }

    public static function logout(): void
    {
        if (!self::$sessionId) {
            return;
        }

        \DB::query(
            'UPDATE sessions SET user_id=0, is_persistent=false WHERE domain_id=$1 AND session_id=$2',
            [DOMAIN_ID, self::$sessionId]
        );

        \Cache::del('d_' . DOMAIN_ID . ':sessions:' . self::$sessionId);

        if (is_array(self::$session)) {
            self::$session['user_id'] = 0;
            self::$session['is_persistent'] = false;
        }
    }

    /**
     * Invalidate every authenticated session for one user across all domains.
     *
     * @return list<array{domain_id:int, session_id:string}>
     */
    public static function invalidateSessions(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $result = \DB::query(
            'SELECT domain_id, session_id FROM sessions WHERE user_id=$1',
            [$userId]
        );
        if ($result === false) {
            throw new \RuntimeException('Failed to load user sessions for invalidation.');
        }

        $sessions = [];
        while ($row = \DB::fetchRow($result)) {
            $sessions[] = [
                'domain_id' => (int)$row['domain_id'],
                'session_id' => (string)$row['session_id'],
            ];
        }

        if ($sessions === []) {
            return [];
        }

        if (\DB::query(
            'UPDATE sessions SET user_id=0, is_persistent=false WHERE user_id=$1',
            [$userId]
        ) === false) {
            throw new \RuntimeException('Failed to invalidate user sessions.');
        }

        foreach ($sessions as $session) {
            \Cache::del(
                'd_' . $session['domain_id'] . ':sessions:' . $session['session_id']
            );
        }

        return $sessions;
    }

    public static function getUser(?int $userId = null): ?array
    {
        $userId ??= (int)(self::$session['user_id'] ?? 0);
        $cacheKey = 'users:v2:' . $userId;

        $user = \Cache::get($cacheKey);
        if (!is_array($user)) {
            $user = \DB::getRow(
                'SELECT user_id, user_uuid, username, email, usergroup_id, is_active, email_verified_at, created_at, last_login
                 FROM users
                 WHERE user_id=$1
                 LIMIT 1',
                [$userId]
            ) ?: null;

            debug_step('User DB');

            if ($user) {
                $user['user_id'] = (int)$user['user_id'];
                $user['usergroup_id'] = (int)$user['usergroup_id'];
                $user['is_active'] = (bool)$user['is_active'];
                \Cache::set($cacheKey, $user);
            }
        }

        return $user;
    }

    public static function getGroup(?int $usergroupId = null): ?array
    {
        $usergroupId ??= (int)(self::$user['usergroup_id'] ?? 0);
        if ($usergroupId < 1) {
            return null;
        }

        $cacheKey = 'usergroups:' . $usergroupId;
        $group = \Cache::get($cacheKey);

        if (
            !is_array($group)
            || !isset($group['uuid'], $group['system_name'], $group['has_api'])
        ) {
            $group = \DB::getRow(
                'SELECT usergroup_id, uuid, system_name, is_system, has_api
                 FROM usergroups
                 WHERE usergroup_id=$1
                 LIMIT 1',
                [$usergroupId]
            ) ?: null;

            if ($group) {
                $group['usergroup_id'] = (int)$group['usergroup_id'];
                $group['is_system'] = (bool)$group['is_system'];
                $group['has_api'] = (bool)$group['has_api'];
                \Cache::set($cacheKey, $group);
            }
        }

        if (!$group) {
            return null;
        }

        $translation = Translation::get((string)$group['uuid']) ?? [];
        $group['title'] = (string)($translation['title'] ?? $group['system_name']);
        $group['description'] = (string)($translation['description'] ?? '');

        return $group;
    }

    public static function getId(): int
    {
        return (int)(self::$session['user_id'] ?? 0);
    }

    public static function isGuest(): bool
    {
        return self::getId() === 0;
    }

    public static function isRoot(?int $groupId = null): bool
    {
        $groupId ??= self::currentGroupId();
        return $groupId === (int)(GLOBAL_SETTINGS['usergroup_root'] ?? -1);
    }

    public static function getSessionId(): ?string
    {
        return self::$session['session_id'] ?? null;
    }

    /**
     * Check access to one exact page. Pages already belong to one domain, so
     * page ACL needs no additional scope.
     */
    public static function canPage(int $pageId, ?int $groupId = null): bool
    {
        if ($pageId < 1) {
            return false;
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return true;
        }

        $bag = self::getAclBag($groupId);
        return isset($bag['pages_by_id'][$pageId]);
    }

    /**
     * Check access to one exact plugin handler.
     */
    public static function canPlugin(
        int|string $plugin,
        string $handler,
        ?int $groupId = null
    ): bool {
        $handler = trim($handler);
        if ($handler === '') {
            return false;
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return true;
        }

        $bag = self::getAclBag($groupId);
        $bucket = is_int($plugin) || ctype_digit((string)$plugin)
            ? $bag['plugins_by_id'][(int)$plugin] ?? null
            : $bag['plugins_by_name'][(string)$plugin] ?? null;

        return is_array($bucket) && isset($bucket[$handler]);
    }

    /**
     * Check one exact content capability.
     * ACL permission describes a user operation, not a low-level data write.
     */
    public static function canContent(
        int|string $contentType,
        string $handler,
        ?int $groupId = null
    ): bool {
        $handler = trim($handler);
        if ($handler === '') {
            return false;
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return true;
        }

        $bag = self::getAclBag($groupId);
        $bucket = is_int($contentType) || ctype_digit((string)$contentType)
            ? $bag['content_types_by_id'][(int)$contentType] ?? null
            : $bag['content_types_by_name'][(string)$contentType] ?? null;

        return is_array($bucket) && isset($bucket[$handler]);
    }

    /**
     * Return content type IDs available for one exact capability.
     * Root returns every registered content type.
     *
     * @return int[]
     */
    public static function getAllowedContentTypeIds(
        string $handler,
        ?int $groupId = null
    ): array {
        $handler = trim($handler);
        if ($handler === '') {
            return [];
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return array_map('intval', \DB::getArr('SELECT ct_id FROM content_types ORDER BY ct_id'));
        }

        $bag = self::getAclBag($groupId);
        $ids = [];
        foreach ($bag['content_types_by_id'] as $contentTypeId => $handlers) {
            if (isset($handlers[$handler])) {
                $ids[] = (int)$contentTypeId;
            }
        }

        return $ids;
    }

    public static function clearAclCache(?int $groupId = null): void
    {
        $groupIds = $groupId !== null
            ? [$groupId]
            : array_map('intval', \DB::getArr('SELECT usergroup_id FROM usergroups'));

        foreach ($groupIds as $id) {
            \Cache::del(self::aclCacheKey($id));
        }
    }

    public static function clearUserCache(int $userId): void
    {
        \Cache::del('users:v2:' . $userId);
        \Cache::del('users:' . $userId);
    }

    public static function clearGroupCache(int $groupId): void
    {
        \Cache::del('usergroups:' . $groupId);
        self::clearAclCache($groupId);
    }

    private static function currentGroupId(): int
    {
        if (self::$user && isset(self::$user['usergroup_id'])) {
            return (int)self::$user['usergroup_id'];
        }
        if (defined('USERGROUP_ID')) {
            return (int)USERGROUP_ID;
        }
        return (int)(GLOBAL_SETTINGS['usergroup_guest'] ?? 0);
    }

    private static function compileAclBag(int $groupId): array
    {
        $bag = [
            'meta' => [
                'group_id' => $groupId,
                'generated_at' => date('Y-m-d H:i:s'),
            ],
            'plugins_by_id' => [],
            'plugins_by_name' => [],
            'content_types_by_id' => [],
            'content_types_by_name' => [],
            'pages_by_id' => [],
            'pages_by_name' => [],
        ];

        $plugins = \DB::query(
            'SELECT pa.plugin_id, p.system_name AS plugin_name, pa.handler
             FROM plugin_acl pa
             JOIN plugins p USING(plugin_id)
             WHERE pa.usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($plugins)) {
            $pluginId = (int)$row['plugin_id'];
            $pluginName = (string)$row['plugin_name'];
            $handler = (string)$row['handler'];
            $bag['plugins_by_id'][$pluginId][$handler] = true;
            $bag['plugins_by_name'][$pluginName][$handler] = true;
        }

        $content = \DB::query(
            'SELECT ca.ct_id, ct.system_name AS content_type_name, ca.handler
             FROM content_acl ca
             JOIN content_types ct USING(ct_id)
             WHERE ca.usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($content)) {
            $contentTypeId = (int)$row['ct_id'];
            $contentTypeName = (string)$row['content_type_name'];
            $handler = (string)$row['handler'];
            $bag['content_types_by_id'][$contentTypeId][$handler] = true;
            $bag['content_types_by_name'][$contentTypeName][$handler] = true;
        }

        $pages = \DB::query(
            'SELECT pa.page_id, p.system_name AS page_name
             FROM page_acl pa
             JOIN pages p USING(page_id)
             WHERE pa.usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($pages)) {
            $pageId = (int)$row['page_id'];
            $pageName = (string)$row['page_name'];
            $bag['pages_by_id'][$pageId] = true;
            $bag['pages_by_name'][$pageName] = true;
        }

        return $bag;
    }

    private static function aclCacheKey(int $groupId): string
    {
        return self::ACL_CACHE_NS . $groupId;
    }

    private static function getAclBag(int $groupId): array
    {
        $key = self::aclCacheKey($groupId);
        $cached = \Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $bag = self::compileAclBag($groupId);
        \Cache::set($key, $bag, self::ACL_CACHE_TTL);
        return $bag;
    }

}
