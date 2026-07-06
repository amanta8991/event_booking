-- Event Booking schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS event_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_booking;

CREATE TABLE IF NOT EXISTS events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255)  NOT NULL,
    description TEXT,
    category    VARCHAR(60)   NOT NULL DEFAULT 'General',
    event_date  DATE          NOT NULL,
    event_time  TIME          NOT NULL DEFAULT '09:00:00',
    location    VARCHAR(255)  NOT NULL,
    capacity    INT           NOT NULL,
    price       DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    image_emoji VARCHAR(10)   NOT NULL DEFAULT '🎟️',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    event_id     INT          NOT NULL,
    guest_name   VARCHAR(255) NOT NULL,
    guest_email  VARCHAR(255) NOT NULL,
    seats        INT          NOT NULL DEFAULT 1,
    booked_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO events (title, description, category, event_date, event_time, location, capacity, price, image_emoji) VALUES
('Frontend Dev Meetup Karlsruhe',
 'Monthly meetup for frontend developers in the Karlsruhe area. Lightning talks, networking, and live coding demos covering React, CSS, and modern tooling.',
 'Tech', '2026-07-22', '18:30:00', 'ZKM Media Centre, Karlsruhe', 60, 0.00, '💻'),

('KIT Startup Pitch Night',
 'Five student startup teams pitch their ideas to a panel of investors and industry experts. Q&A and networking dinner to follow.',
 'Business', '2026-07-28', '19:00:00', 'KIT Campus South, Building 11.40', 120, 5.00, '🚀'),

('React Advanced Workshop',
 'A full-day hands-on workshop covering React 18 patterns: concurrent features, server components, and performance optimisation. Bring your laptop.',
 'Tech', '2026-08-02', '09:00:00', 'SAS Institute, Heidelberg', 30, 49.00, '⚛️'),

('Karlsruhe Design Jam',
 'A 6-hour collaborative design sprint. Teams of 4 tackle a real UX problem and present their prototypes at the end of the day. All skill levels welcome.',
 'Design', '2026-08-09', '10:00:00', 'Perfekt Futur, Karlsruhe', 48, 12.00, '🎨'),

('Open Source Contribution Day',
 'Pick an open-source project, find a good first issue, and spend the day contributing with support from experienced maintainers. Lunch provided.',
 'Tech', '2026-08-16', '10:00:00', 'Coworking Space Central, Karlsruhe', 40, 0.00, '🔧'),

('Women in Tech Baden-Württemberg',
 'A half-day conference celebrating women in tech across the region. Panel discussions, mentorship speed-dating, and a keynote from a senior engineer at SAP.',
 'Networking', '2026-08-23', '13:00:00', 'IHK Karlsruhe', 200, 0.00, '👩‍💻');

-- Seed some existing bookings so capacity bars aren't all empty
INSERT INTO bookings (event_id, guest_name, guest_email, seats) VALUES
(1, 'Lena Schmidt',   'lena.s@example.com',   2),
(1, 'Tom Becker',     'tom.b@example.com',     1),
(1, 'Priya Nair',     'priya.n@example.com',   3),
(2, 'Jonas Weber',    'jonas.w@example.com',   4),
(2, 'Sara Müller',    'sara.m@example.com',    2),
(3, 'Ahmed Hassan',   'ahmed.h@example.com',   1),
(3, 'Emma Klein',     'emma.k@example.com',    1),
(3, 'Felix Braun',    'felix.b@example.com',   2),
(3, 'Mia Schulz',     'mia.s@example.com',     1),
(4, 'Luis Fernandez', 'luis.f@example.com',    4),
(5, 'Hannah Koch',    'hannah.k@example.com',  1),
(6, 'Yuki Tanaka',    'yuki.t@example.com',    1);
