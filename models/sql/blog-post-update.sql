UPDATE {TABLE}
SET excerpt = :excerpt,
    link_text = :link_text,
    body = :body,
    hero_image = :hero_image,
    social_image = :social_image,
    author_user_id = :author_user_id,
    is_published = :is_published,
    published_at = :published_at,
    updated_at = NOW()
WHERE id = :id
