UPDATE plugins
SET settings = settings - 'password_reset_page'
WHERE system_name = 'UserAccount'
  AND settings ? 'password_reset_page';

UPDATE plugin_domains pd
SET local_settings = pd.local_settings - 'password_reset_page'
FROM plugins p
WHERE p.plugin_id = pd.plugin_id
  AND p.system_name = 'UserAccount'
  AND pd.local_settings ? 'password_reset_page';
