SELECT p.id, p.page_id
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id
