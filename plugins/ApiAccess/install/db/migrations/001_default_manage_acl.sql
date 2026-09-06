INSERT INTO plugin_acl(usergroup_id, plugin_id, handler)
SELECT ug.usergroup_id, p.plugin_id, 'manage'
FROM usergroups ug
JOIN plugins p ON p.system_name = 'ApiAccess'
WHERE ug.has_api = true
ON CONFLICT (usergroup_id, plugin_id, handler) DO NOTHING;
