SELECT id, first_name, last_name
FROM {PREFIX}users
WHERE is_active = 1
ORDER BY first_name, last_name
