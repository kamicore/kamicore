INSERT INTO plugin_acl(usergroup_id, plugin_id, handler)
SELECT ug.usergroup_id, p.plugin_id, 'account'
FROM usergroups ug
JOIN plugins p ON p.system_name = 'UserProfile'
WHERE ug.system_name = 'user'
ON CONFLICT (usergroup_id, plugin_id, handler) DO NOTHING;
