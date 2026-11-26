-- Sunset Brunch: OPTIONAL demo bookings for trying the back-office.
-- Fictional guests on the next two Sundays (dates are computed from today).
--
-- Import:  mysql -u root -p sunset_brunch < database/demo-data.sql
-- Remove:  DELETE FROM reservations WHERE ip_hash = REPEAT('d', 64);

-- Send this file as UTF-8, so names such as "Lucía Fernández" are stored correctly.
SET NAMES utf8mb4;

-- Next Sunday (a week ahead if today is Sunday) and the Sunday after.
-- DAYOFWEEK(): 1 = Sunday ... 7 = Saturday.
SET @sunday1 = CURDATE() + INTERVAL (8 - DAYOFWEEK(CURDATE())) DAY;
SET @sunday2 = @sunday1 + INTERVAL 7 DAY;
SET @demo = REPEAT('d', 64);

INSERT INTO reservations (full_name, phone, email, booking_date, time_slot, guests, message, status, ip_hash, created_at) VALUES
-- Sunday 1, 12:00: 28 active guests (the cancelled booking does not count)
('Yasmine Alaoui',    '0661234501',    'yasmine.alaoui@example.com', @sunday1, '12:00',  8, 'Birthday brunch for my mother.', 'confirmed', @demo, NOW() - INTERVAL 6 DAY),
('Karim Bennani',     '0662234502',    'karim.bennani@example.com',  @sunday1, '12:00',  6, NULL,                             'new',       @demo, NOW() - INTERVAL 2 DAY),
('Sophie Martin',     '+33612345678',  'sophie.martin@example.com',  @sunday1, '12:00', 10, 'Two vegetarians in the group.',  'confirmed', @demo, NOW() - INTERVAL 5 DAY),
('Omar El Fassi',     '0663234504',    'omar.elfassi@example.com',   @sunday1, '12:00',  4, NULL,                             'new',       @demo, NOW() - INTERVAL 20 HOUR),
('Lucía Fernández',   '+34612345678',  'lucia.fernandez@example.com', @sunday1, '12:00', 5, 'Change of plans, sorry!',        'cancelled', @demo, NOW() - INTERVAL 4 DAY),
-- Sunday 1, 13:30: full (40 guests)
('Hassan Tazi',       '0664234506',    'hassan.tazi@example.com',    @sunday1, '13:30', 12, 'Company team lunch.',            'confirmed', @demo, NOW() - INTERVAL 9 DAY),
('Nadia Chraibi',     '0665234507',    'nadia.chraibi@example.com',  @sunday1, '13:30', 12, NULL,                             'confirmed', @demo, NOW() - INTERVAL 8 DAY),
('James Walker',      '+447911123456', 'james.walker@example.com',   @sunday1, '13:30', 10, 'A high chair, please.\nWe arrive by taxi.', 'new', @demo, NOW() - INTERVAL 3 HOUR),
('Salma Idrissi',     '0666234509',    'salma.idrissi@example.com',  @sunday1, '13:30',  6, NULL,                             'confirmed', @demo, NOW() - INTERVAL 1 DAY),
-- Sunday 1, 15:00: quiet
('Mehdi Berrada',     '0667234510',    'mehdi.berrada@example.com',  @sunday1, '15:00',  2, 'Table on the terrace if possible.', 'new',    @demo, NOW() - INTERVAL 1 HOUR),
('Anna Schmidt',      '+4915112345678','anna.schmidt@example.com',   @sunday1, '15:00',  3, NULL,                             'new',       @demo, NOW() - INTERVAL 30 MINUTE),
-- Sunday 2
('Rachid Amrani',     '0668234512',    'rachid.amrani@example.com',  @sunday2, '12:00',  4, NULL,                             'new',       @demo, NOW() - INTERVAL 10 MINUTE),
('Fatima Zahra Lahlou','0669234513',   'fz.lahlou@example.com',      @sunday2, '13:30',  2, 'Allergic to nuts.',              'confirmed', @demo, NOW() - INTERVAL 2 HOUR);
