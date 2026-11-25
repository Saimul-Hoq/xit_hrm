-- =====================================================================
-- Fixed department list. Import AFTER schema.sql, into the same database.
-- The name is always "Digital Marketing" (never "Marketing").
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO departments (name) VALUES
    ('Development'),
    ('Sales'),
    ('Digital Marketing'),
    ('Data'),
    ('Graphics');