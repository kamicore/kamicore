UPDATE plugins
SET settings = settings - 'verification_page'
WHERE system_name = 'UserAccount'
  AND settings ? 'verification_page';

UPDATE plugin_domains pd
SET local_settings = pd.local_settings - 'verification_page'
FROM plugins p
WHERE p.plugin_id = pd.plugin_id
  AND p.system_name = 'UserAccount'
  AND pd.local_settings ? 'verification_page';
