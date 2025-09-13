USE heritage_db;

-- === CRUD Operations ===
INSERT INTO Visitors (name, nationality, email, phone)
VALUES ('John Doe', 'USA', 'john@example.com', '123456789');

SELECT * FROM Visitors;

UPDATE HeritageSites 
SET ticket_price = 200 
WHERE site_id = 1;

DELETE FROM Bookings WHERE booking_id = 2;

-- === ALTER TABLE ===
ALTER TABLE Guides ADD COLUMN experience_years INT;

-- === Constraints Example (already defined in schema) ===
-- (Primary Key, UNIQUE, NOT NULL, CHECK, DEFAULT)

-- === DISTINCT & ALL ===
SELECT DISTINCT nationality FROM Visitors;
SELECT ALL name, location FROM HeritageSites;

-- === Pattern Matching ===
SELECT * FROM Visitors WHERE name LIKE 'A%';
SELECT * FROM Guides WHERE language LIKE '%English%';

-- === Aggregate Functions ===
SELECT COUNT(*) AS total_sites FROM HeritageSites;
SELECT AVG(ticket_price) AS avg_ticket FROM Events;
SELECT MAX(rating) AS best_rating FROM Reviews;

-- === GROUP BY and HAVING ===
SELECT nationality, COUNT(*) AS total_visitors
FROM Visitors
GROUP BY nationality
HAVING COUNT(*) > 1;

-- === Subquery ===
SELECT name, location 
FROM HeritageSites
WHERE site_id IN (
    SELECT site_id FROM Events WHERE ticket_price > 100
);

-- === Set Operations ===
SELECT site_id AS id, name FROM HeritageSites WHERE ticket_price > 100
UNION
SELECT event_id, name FROM Events WHERE ticket_price > 100;

-- === Views ===
CREATE OR REPLACE VIEW TopRatedSites AS
SELECT s.name, AVG(r.rating) AS avg_rating
FROM HeritageSites s
JOIN Reviews r ON s.site_id = r.site_id
GROUP BY s.name
HAVING AVG(r.rating) > 4;

SELECT * FROM TopRatedSites;

-- === JOINs ===
-- Inner Join
SELECT v.name, b.booking_date 
FROM Visitors v
INNER JOIN Bookings b ON v.visitor_id = b.visitor_id;

-- Left Join
SELECT s.name, e.name AS event_name
FROM HeritageSites s
LEFT JOIN Events e ON s.site_id = e.site_id;

-- Right Join
SELECT s.name, e.name AS event_name
FROM HeritageSites s
RIGHT JOIN Events e ON s.site_id = e.site_id;

-- Full Outer Join (MySQL workaround with UNION)
SELECT s.name, e.name AS event_name
FROM HeritageSites s
LEFT JOIN Events e ON s.site_id = e.site_id
UNION
SELECT s.name, e.name
FROM HeritageSites s
RIGHT JOIN Events e ON s.site_id = e.site_id;

-- Natural Join
SELECT * FROM Bookings NATURAL JOIN Payments;

-- Cross Join
SELECT v.name, g.name AS guide_name
FROM Visitors v
CROSS JOIN Guides g;

-- Equi-Join
SELECT v.name, b.no_of_tickets
FROM Visitors v, Bookings b
WHERE v.visitor_id = b.visitor_id;

-- Non-Equi Join
SELECT v.name, e.name, e.ticket_price
FROM Visitors v
JOIN Bookings b ON v.visitor_id = b.visitor_id
JOIN Events e ON b.event_id = e.event_id
WHERE b.no_of_tickets * e.ticket_price > 200;

-- Self Join
SELECT g1.name AS guide1, g2.name AS guide2, g1.language
FROM Guides g1
JOIN Guides g2 ON g1.language = g2.language
AND g1.guide_id < g2.guide_id;
