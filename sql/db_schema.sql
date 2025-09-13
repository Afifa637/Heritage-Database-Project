-- db_schema.sql
-- Heritage Management Database (MySQL 8.0+)

CREATE DATABASE IF NOT EXISTS heritage_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE heritage_db;

-- HeritageSites
CREATE TABLE IF NOT EXISTS HeritageSites (
  site_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(200),
  type VARCHAR(100),
  opening_hours VARCHAR(100),
  ticket_price DECIMAL(10,2) DEFAULT 0,
  unesco_status ENUM('None','Tentative','World Heritage') DEFAULT 'None',
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events
CREATE TABLE IF NOT EXISTS Events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  event_date DATE NOT NULL,
  event_time TIME NOT NULL,
  description TEXT,
  ticket_price DECIMAL(10,2) DEFAULT 0,
  capacity INT DEFAULT 0,
  CONSTRAINT fk_events_site FOREIGN KEY (site_id)
    REFERENCES HeritageSites(site_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visitors
CREATE TABLE IF NOT EXISTS Visitors (
  visitor_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  nationality VARCHAR(80),
  email VARCHAR(120) UNIQUE,
  phone VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings
CREATE TABLE IF NOT EXISTS Bookings (
  booking_id INT AUTO_INCREMENT PRIMARY KEY,
  visitor_id INT NOT NULL,
  site_id INT NULL,
  event_id INT NULL,
  booking_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  no_of_tickets INT NOT NULL,
  payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  CONSTRAINT fk_bookings_visitor FOREIGN KEY (visitor_id)
    REFERENCES Visitors(visitor_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_site FOREIGN KEY (site_id)
    REFERENCES HeritageSites(site_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_bookings_event FOREIGN KEY (event_id)
    REFERENCES Events(event_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments
CREATE TABLE IF NOT EXISTS Payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','card','mobile','bank_transfer','online') NOT NULL,
  status ENUM('initiated','successful','failed','refunded') NOT NULL DEFAULT 'initiated',
  paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id)
    REFERENCES Bookings(booking_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Guides
CREATE TABLE IF NOT EXISTS Guides (
  guide_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  language VARCHAR(80),
  specialization VARCHAR(120),
  salary DECIMAL(10,2) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assignments
CREATE TABLE IF NOT EXISTS Assignments (
  assign_id INT AUTO_INCREMENT PRIMARY KEY,
  guide_id INT NOT NULL,
  site_id INT NULL,
  event_id INT NULL,
  shift_time VARCHAR(100),
  CONSTRAINT fk_assign_guide FOREIGN KEY (guide_id)
    REFERENCES Guides(guide_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_assign_site FOREIGN KEY (site_id)
    REFERENCES HeritageSites(site_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_assign_event FOREIGN KEY (event_id)
    REFERENCES Events(event_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reviews
CREATE TABLE IF NOT EXISTS Reviews (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  visitor_id INT NOT NULL,
  site_id INT NULL,
  event_id INT NULL,
  rating TINYINT NOT NULL,
  comment TEXT,
  review_date DATE DEFAULT (CURRENT_DATE),
  CONSTRAINT fk_reviews_visitor FOREIGN KEY (visitor_id)
    REFERENCES Visitors(visitor_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_reviews_site FOREIGN KEY (site_id)
    REFERENCES HeritageSites(site_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_reviews_event FOREIGN KEY (event_id)
    REFERENCES Events(event_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admins (for admin login)
CREATE TABLE IF NOT EXISTS Admins (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CHECK constraints ensuring exactly one of site_id OR event_id is set
ALTER TABLE Bookings ADD CONSTRAINT chk_bookings_target CHECK ((site_id IS NOT NULL) XOR (event_id IS NOT NULL));
ALTER TABLE Assignments ADD CONSTRAINT chk_assignments_target CHECK ((site_id IS NOT NULL) XOR (event_id IS NOT NULL));
ALTER TABLE Reviews ADD CONSTRAINT chk_reviews_target CHECK ((site_id IS NOT NULL) XOR (event_id IS NOT NULL));

-- Indexes
CREATE INDEX idx_events_site ON Events(site_id);
CREATE INDEX idx_bookings_visitor ON Bookings(visitor_id);
CREATE INDEX idx_bookings_site ON Bookings(site_id);
CREATE INDEX idx_bookings_event ON Bookings(event_id);
CREATE INDEX idx_assignments_guide ON Assignments(guide_id);
CREATE INDEX idx_assignments_site ON Assignments(site_id);
CREATE INDEX idx_assignments_event ON Assignments(event_id);
CREATE INDEX idx_reviews_visitor ON Reviews(visitor_id);
CREATE INDEX idx_reviews_site ON Reviews(site_id);
CREATE INDEX idx_reviews_event ON Reviews(event_id);

-- Sample data
INSERT INTO HeritageSites (name, location, type, opening_hours, ticket_price, unesco_status, description) VALUES
('Lalbagh Fort','Dhaka, Bangladesh','Mughal Fort','09:00-17:00',100.00,'Tentative','17th-century fort'),
('Ahsan Manzil','Dhaka, Bangladesh','Palace Museum','10:00-17:00',50.00,'None','Pink Palace museum');

INSERT INTO Events (site_id, name, event_date, event_time, description, ticket_price, capacity) VALUES
(1,'Evening Light Show','2025-10-01','19:00:00','Light and sound show',200.00,200),
(2,'Heritage Walk','2025-10-05','09:00:00','Guided tour',150.00,30);

INSERT INTO Visitors (name, nationality, email, phone) VALUES
('Nadia Rahman','Bangladeshi','nadia@example.com','+8801700000000'),
('Arun Sen','Indian','arun@example.com','+919800000000');

INSERT INTO Guides (name, language, specialization, salary) VALUES
('Kamrul Hasan','Bangla, English','Mughal history',25000.00),
('Sara Ahmed','English','Colonial Dhaka',28000.00);

INSERT INTO Assignments (guide_id, site_id, event_id, shift_time) VALUES
(1,1,NULL,'Morning'),
(2,NULL,2,'Evening');

INSERT INTO Bookings (visitor_id, site_id, event_id, no_of_tickets, payment_status) VALUES
(1,1,NULL,2,'pending'),
(2,NULL,2,3,'pending');

INSERT INTO Payments (booking_id, amount, method, status) VALUES
(1,200.00,'online','successful'),
(2,450.00,'card','initiated');

INSERT INTO Reviews (visitor_id, site_id, event_id, rating, comment) VALUES
(1,1,NULL,5,'Beautiful fort!'),
(2,NULL,2,4,'Great walk.');

-- Admin user (username: admin, password: Admin@123)
INSERT INTO Admins (username, password_hash, full_name) VALUES
('admin', CONCAT('', SHA2('Admin@123', 256)), 'Site Administrator');
-- Note: SHA2 used for seed, but login uses password_verify with password_hash; You can update admin password using PHP script or replace above with a PHP-generated hash.
