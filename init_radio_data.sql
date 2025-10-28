-- Initialize radio data if tables exist but are empty

-- Add default stations if they don't exist
INSERT IGNORE INTO radio_station (id, name, description, port, folder_name, status, is_running, created_at, updated_at)
VALUES 
(1, 'Радио #1', 'Первая радиостанция сервера', 8081, '1', 1, 0, NOW(), NOW()),
(2, 'Радио #2', 'Вторая радиостанция сервера', 8082, '2', 1, 0, NOW(), NOW());

-- Enable radio section in settings
INSERT INTO site_settings (name, category, type, value, code) 
VALUES ('Раздел радиостанций', 'site', 'checkbox', '1', 'section_radio')
ON DUPLICATE KEY UPDATE value = '1';

-- Show results
SELECT 'Radio stations:' as info;
SELECT * FROM radio_station;

SELECT 'Settings:' as info;
SELECT * FROM site_settings WHERE code = 'section_radio';

