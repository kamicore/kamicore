INSERT INTO plugin_acl(usergroup_id, plugin_id, handler)
SELECT ug.usergroup_id, p.plugin_id, 'view'
FROM usergroups ug
JOIN plugins p ON p.system_name = 'Notifications'
ON CONFLICT (usergroup_id, plugin_id, handler) DO NOTHING;
