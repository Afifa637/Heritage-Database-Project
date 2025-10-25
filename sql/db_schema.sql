-- ===============================
-- Heritage Management Database (MySQL 8.0+)
-- ===============================

CREATE DATABASE IF NOT EXISTS heritage_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE heritage_db;

-- Heritage Sites
CREATE TABLE IF NOT EXISTS HeritageSites (
  site_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(200),
  type VARCHAR(100),
  opening_hours VARCHAR(100),
  ticket_price DECIMAL(10,2) DEFAULT 0,
  unesco_status ENUM('None','Tentative','World Heritage') DEFAULT 'None',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
DROP TABLE IF EXISTS Visitors;
CREATE TABLE Visitors (
  visitor_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  nationality VARCHAR(80),
  email VARCHAR(120) UNIQUE NOT NULL,
  phone VARCHAR(30),
  password_hash VARCHAR(255) NOT NULL,  -- for login authentication
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings
CREATE TABLE IF NOT EXISTS Bookings (
  booking_id INT AUTO_INCREMENT PRIMARY KEY,
  visitor_id INT NOT NULL,
  site_id INT NULL,
  event_id INT NULL,
  booking_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  no_of_tickets INT NOT NULL,
  booked_ticket_price DECIMAL(10,2) NOT NULL DEFAULT 0,
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
    ON DELETE SET NULL,
  CONSTRAINT chk_bookings_target CHECK ((site_id IS NOT NULL) XOR (event_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE IF NOT EXISTS Payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('bkash','nagad','rocket','card','bank_transfer') NOT NULL,
  status ENUM('initiated','successful','failed','refunded') NOT NULL DEFAULT 'initiated',
  paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id)
    REFERENCES Bookings(booking_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guides
CREATE TABLE IF NOT EXISTS Guides (
  guide_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
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
    ON DELETE SET NULL,
  CONSTRAINT chk_assignments_target CHECK ((site_id IS NOT NULL) XOR (event_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT chk_reviews_target CHECK ((site_id IS NOT NULL) XOR (event_id IS NOT NULL)),
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins (separate from Users if needed)
CREATE TABLE IF NOT EXISTS Admins (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_events_site ON Events(site_id);
CREATE INDEX IF NOT EXISTS idx_bookings_visitor ON Bookings(visitor_id);
CREATE INDEX IF NOT EXISTS idx_bookings_site ON Bookings(site_id);
CREATE INDEX IF NOT EXISTS idx_bookings_event ON Bookings(event_id);
CREATE INDEX IF NOT EXISTS idx_assignments_guide ON Assignments(guide_id);
CREATE INDEX IF NOT EXISTS idx_assignments_site ON Assignments(site_id);
CREATE INDEX IF NOT EXISTS idx_assignments_event ON Assignments(event_id);
CREATE INDEX IF NOT EXISTS idx_reviews_visitor ON Reviews(visitor_id);
CREATE INDEX IF NOT EXISTS idx_reviews_site ON Reviews(site_id);
CREATE INDEX IF NOT EXISTS idx_reviews_event ON Reviews(event_id);
ALTER TABLE HeritageSites
  ADD INDEX IF NOT EXISTS idx_location (location),
  ADD INDEX IF NOT EXISTS idx_type (type),
  ADD INDEX IF NOT EXISTS idx_ticket_price (ticket_price),
  ADD INDEX IF NOT EXISTS idx_unesco_status (unesco_status);

-- Optional fulltext index for description searches (MySQL 5.6+)
ALTER TABLE HeritageSites
    ADD FULLTEXT INDEX IF NOT EXISTS ft_description (description);

-- Heritage Sites
INSERT INTO `heritagesites` (`site_id`, `name`, `location`, `type`, `opening_hours`, `ticket_price`, `unesco_status`, `description`, `created_at`) VALUES
(1, 'Lalbagh Fort', 'Dhaka, Bangladesh', 'Mughal Fort', '09:00-17:00', 100.00, 'Tentative', '17th-century Mughal fort in Dhaka', '2025-09-28 23:22:05'),
(2, 'Ahsan Manzil', 'Dhaka, Bangladesh', 'Palace Museum', '10:00-17:00', 50.00, 'None', 'The Pink Palace museum of Dhaka', '2025-09-28 23:22:05'),
(3, 'Mahasthangarh', 'Bogura, Bangladesh', 'Ancient City', '08:00-18:00', 80.00, 'World Heritage', 'Ancient archaeological site', '2025-09-28 23:22:05'),
(4, 'Sixty Dome Mosque', 'Bagerhat, Bangladesh', 'Mosque', '09:00-18:00', 120.00, 'World Heritage', 'Historic mosque built in 15th century', '2025-09-28 23:22:05'),
(5, 'Jatiyo Sangsad Bhaban', 'Dhaka, Bangladesh', 'Parliament Building', '09:00-17:00', 30.00, 'None', 'Iconic national parliament building designed by Louis Kahn', '2025-09-28 23:39:33'),
(6, 'Kantajew Temple', 'Dinajpur, Bangladesh', 'Hindu Temple', '08:00-17:00', 60.00, 'None', '18th-century terracotta temple with intricate carvings', '2025-09-28 23:39:33'),
(7, 'Panam City', 'Sonargaon, Narayanganj', 'Ancient City', '08:00-18:00', 40.00, 'Tentative', 'Ruins of a historic city along the river', '2025-09-28 23:39:33'),
(8, 'Bagha Mosque', 'Rajshahi, Bangladesh', 'Mosque', '08:00-17:00', 70.00, 'Tentative', 'Historic 16th-century mosque with terracotta decorations', '2025-09-28 23:39:33'),
(9, 'Lalmai Hills Monastery', 'Comilla, Bangladesh', 'Monastery', '09:00-16:00', 50.00, 'None', 'Ancient Buddhist monastery on Lalmai Hills', '2025-09-28 23:39:33'),
(10, 'Paharpur Buddhist Monastery', 'Naogaon, Bangladesh', 'Monastery', '08:00-18:00', 120.00, 'World Heritage', 'Ruins of the largest Buddhist vihara in South Asia', '2025-09-28 23:39:33'),
(11, 'Mahasthangarh Museum', 'Bogura, Bangladesh', 'Museum', '09:00-17:00', 60.00, 'None', 'Museum displaying artifacts from Mahasthangarh archaeological site', '2025-09-28 23:39:33'),
(12, 'Bagerhat Museum', 'Bagerhat, Bangladesh', 'Museum', '09:00-17:00', 40.00, 'None', 'Museum for Bagerhat historic mosque city', '2025-09-28 23:39:33'),
(13, 'Chhota Sona Mosque', 'Pabna, Bangladesh', 'Mosque', '08:00-17:00', 90.00, 'Tentative', 'Small golden mosque from 15th century', '2025-09-28 23:39:33'),
(14, 'Kantajew Temple Park', 'Dinajpur, Bangladesh', 'Temple Complex', '08:00-17:00', 45.00, 'None', 'Gardens and smaller shrines adjacent to Kantajew Temple', '2025-09-29 00:00:00'),
(15, 'Shat Gombuj Mosque Complex', 'Bagerhat, Bangladesh', 'Mosque Complex', '09:00-18:00', 80.00, 'World Heritage', 'Expanded area around the Sixty Dome Mosque', '2025-09-29 00:05:00'),
(16, 'Historic Rickshaw Museum', 'Dhaka, Bangladesh', 'Museum', '10:00-17:00', 20.00, 'None', 'Exhibition of traditional rickshaws and designs', '2025-09-29 00:10:00'),
(17, 'Old Dhaka Courtyard', 'Dhaka, Bangladesh', 'Historic District', '09:00-17:00', 0.00, 'None', 'Cluster of old merchant houses and courtyards', '2025-09-29 00:15:00'),
(18, 'Bhawal National Park Ruins', 'Gazipur, Bangladesh', 'Ancient Ruins', '08:00-16:00', 35.00, 'None', 'Old ruins in forested park', '2025-09-29 00:20:00'),
(19, 'Ethnographic Village', 'Sylhet, Bangladesh', 'Open Air Museum', '09:00-17:00', 60.00, 'None', 'Traditional village buildings and crafts', '2025-09-29 00:25:00'),
(20, 'Rangamati Tribal Heritage Centre', 'Rangamati, Bangladesh', 'Cultural Centre', '10:00-16:00', 50.00, 'None', 'Displays and performances by hill-tribes', '2025-09-29 00:30:00');

-- Events
INSERT INTO Events (site_id, name, event_date, event_time, description, ticket_price, capacity) VALUES
(1,'Evening Light Show','2025-10-01','19:00:00','Light and sound show inside Lalbagh Fort',200.00,200),
(2,'Heritage Walk','2025-10-05','09:00:00','Guided tour through Ahsan Manzil area',150.00,30),
(3,'Cultural Festival','2025-11-10','17:00:00','Music and dance festival at Mahasthangarh',300.00,500),
(4,'Islamic Architecture Talk','2025-12-01','15:00:00','Lecture on mosque architecture at Bagerhat',100.00,100),
(5, 1, 'Heritage Photography Walk', '2025-10-08','08:30:00','Photo tour focusing on architectural details', 120.00, 25),
(6, 2, 'Ahsan Manzil Evening Concert', '2025-10-12','18:00:00','Classical music on the palace lawns', 180.00, 150),
(7, 3, 'Archaeology Workshop', '2025-10-20','10:00:00','Hands-on digs and talks', 250.00, 40),
(8, 5, 'Parliament Architecture Guided Tour', '2025-10-25','11:00:00','Guided tour of the parliament spaces', 50.00, 60),
(9, 6, 'Temple Terracotta Demonstration', '2025-11-03','14:00:00','Ceramic and terracotta carving demos', 70.00, 30),
(10, 7, 'Panam City Heritage Walk', '2025-11-12','09:30:00','Walking tour of old merchant houses', 60.00, 40),
(11, 8, 'Bagha Mosque Cultural Evening', '2025-11-18','18:30:00','Sufi music and discussion', 100.00, 80),
(12, 9, 'Monastery Pilgrimage Day', '2025-11-25','07:30:00','Pilgrimage routes and meditation session', 40.00, 100),
(13, 10, 'Paharpur Archaeology Lecture', '2025-12-05','15:00:00','Lecture and guided site tour', 150.00, 120),
(14, 11, 'Mahasthangarh Museum Night', '2025-12-12','19:00:00','Night exhibits and curator talk', 90.00, 80),
(15, 12, 'Bagerhat Museum Local Crafts', '2025-12-18','10:00:00','Local crafts market inside museum grounds', 30.00, 60),
(16, 13, 'Chhota Sona Mosque Festival', '2026-01-05','10:00:00','Community festival and guided tours', 70.00, 200),
(17, 14, 'Kantajew Temple Music', '2026-01-15','11:00:00','Bhajan and classical performances', 60.00, 100),
(18, 15, 'Bagerhat Heritage Marathon', '2026-02-01','06:00:00','Charity run through the historic city', 20.00, 500),
(19, 16, 'Rickshaw Art Competition', '2026-02-10','12:00:00','Painting and rickshaw design competition', 25.00, 80),
(20, 17, 'Old Dhaka Street Food Tour', '2026-02-20','17:30:00','Guided street-food tasting and history', 40.00, 30);

-- Visitors
INSERT INTO Visitors (visitor_id, name, nationality, email, phone, password_hash) VALUES
(1, 'Ayesha Rahman', 'Bangladeshi', 'ayesha.rahman@example.com', '+8801711111111', '$2y$10$demoHash12345678901234'),
(2, 'Arun Sen', 'Indian', 'arun.sen@example.com', '+919800000000', '$2y$10$demoHash12345678901234'),
(3, 'Sophia Lee', 'American', 'sophia.lee@example.com', '+14155550000', '$2y$10$demoHash12345678901234'),
(4, 'Ahmed Khan', 'Pakistani', 'ahmed.khan@example.com', '+923001112222', '$2y$10$demoHash12345678901234'),
(5, 'Fatima Noor', 'Pakistani', 'fatima.noor@example.com', '+923001234567', '$2y$10$demoHash12345678901234'),
(6, 'Mehedi Hasan', 'Bangladeshi', 'mehedi.hasan@example.com', '+8801711222333', '$2y$10$demoHash12345678901234'),
(7, 'Sadia Karim', 'Bangladeshi', 'sadia.karim@example.com', '+8801911445566', '$2y$10$demoHash12345678901234'),
(8, 'Nita Rahman', 'Bangladeshi', 'nita.rahman@example.com', '+8801711112222', '$2y$10$demoHash12345678901234'),
(9, 'Puja Sen', 'Indian', 'puja.sen@example.com', '+919812345679', '$2y$10$demoHash12345678901234'),
(10, 'David Smith', 'British', 'david.smith@example.com', '+447911123456', '$2y$10$demoHash12345678901234'),
(11, 'Rina Choudhury', 'Bangladeshi', 'rina.choudhury@example.com', '+8801712223334', '$2y$10$demoHash22222222222222'),
(12, 'Kamal Hossain', 'Bangladeshi', 'kamal.hossain@example.com', '+8801713334445', '$2y$10$demoHash33333333333333'),
(13, 'Lily Fernandes', 'Sri Lankan', 'lily.fernandes@example.com', '+94712345678', '$2y$10$demoHash44444444444444'),
(14, 'John Doe', 'American', 'john.doe@example.com', '+12025550123', '$2y$10$demoHash55555555555555'),
(15, 'Mary Johnson', 'British', 'mary.johnson@example.com', '+447700900123', '$2y$10$demoHash66666666666666'),
(16, 'Bilal Sheikh', 'Bangladeshi', 'bilal.sheikh@example.com', '+8801714445556', '$2y$10$demoHash77777777777777'),
(17, 'Anita Roy', 'Bangladeshi', 'anita.roy@example.com', '+8801715556667', '$2y$10$demoHash88888888888888'),
(18, 'Carlos Mendes', 'Portuguese', 'carlos.mendes@example.com', '+351912345678', '$2y$10$demoHash99999999999999'),
(19, 'Min-Jae Kim', 'Korean', 'minjae.kim@example.com', '+821012345678', '$2y$10$demoHash10101010101010'),
(20, 'Hannah Becker', 'German', 'h.becker@example.com', '+4915123456789', '$2y$10$demoHash11111111111111');

-- Bookings
INSERT INTO Bookings (visitor_id, site_id, event_id, no_of_tickets, payment_status) VALUES
(1, 1, NULL, 2, 'pending'),
(2, NULL, 2, 3, 'paid'),
(3, 3, NULL, 4, 'pending'),
(4, NULL, 4, 1, 'failed'),
(5, 5, 1, NULL, '2025-09-29 09:00:00', 2, 'paid', 100.00),
(6, 6, NULL, 2, '2025-09-29 10:00:00', 3, 'paid', 150.00),
(7, 7, 3, NULL, '2025-09-30 11:00:00', 1, 'pending', 80.00),
(8, 8, NULL, 3, '2025-09-30 12:00:00', 4, 'paid', 300.00),
(9, 9, 4, NULL, '2025-10-01 08:00:00', 2, 'paid', 120.00),
(10, 10, NULL, 5, '2025-10-02 09:30:00', 1, 'pending', 120.00),
(11, 11, 6, NULL, '2025-10-03 10:00:00', 2, 'paid', 60.00),
(12, 12, NULL, 7, '2025-10-04 11:00:00', 2, 'paid', 100.00),
(13, 13, 8, NULL, '2025-10-05 08:30:00', 3, 'paid', 70.00),
(14, 14, NULL, 9, '2025-10-06 14:00:00', 1, 'paid', 70.00),
(15, 15, 10, NULL, '2025-10-07 09:15:00', 4, 'paid', 120.00),
(16, 16, NULL, 10, '2025-10-08 14:00:00', 2, 'pending', 60.00),
(17, 17, 11, NULL, '2025-10-09 18:00:00', 1, 'paid', 90.00),
(18, 18, NULL, 11, '2025-10-10 19:00:00', 2, 'paid', 100.00),
(19, 19, 12, NULL, '2025-10-11 10:30:00', 1, 'paid', 40.00),
(20, 20, NULL, 13, '2025-10-12 19:00:00', 5, 'paid', 150.00);
-- Payments
INSERT INTO Payments (booking_id, amount, method, status) VALUES
(1, 200.00, 'online', 'successful'),
(2, 450.00, 'card', 'successful'),
(3, 320.00, 'mobile', 'initiated'),
(4, 100.00, 'cash', 'failed'),
(5, 5, 200.00, 'card', 'successful', '2025-09-29 09:30:00'),
(6, 6, 450.00, 'bkash', 'successful', '2025-09-29 10:20:00'),
(7, 7, 80.00, 'cash', 'initiated', '2025-09-30 11:10:00'),
(8, 8, 1200.00, 'card', 'successful', '2025-09-30 12:40:00'),
(9, 9, 240.00, 'online', 'successful', '2025-10-01 09:15:00'),
(10, 10, 120.00, 'nagad', 'initiated', '2025-10-02 09:50:00'),
(11, 11, 120.00, 'card', 'successful', '2025-10-03 10:25:00'),
(12, 12, 200.00, 'bkash', 'successful', '2025-10-04 11:45:00'),
(13, 13, 210.00, 'card', 'successful', '2025-10-05 09:00:00'),
(14, 14, 70.00, 'cash', 'successful', '2025-10-06 14:20:00'),
(15, 15, 480.00, 'card', 'successful', '2025-10-07 09:40:00'),
(16, 16, 120.00, 'nagad', 'initiated', '2025-10-08 14:30:00'),
(17, 17, 90.00, 'bkash', 'successful', '2025-10-09 18:20:00'),
(18, 18, 200.00, 'card', 'successful', '2025-10-10 19:30:00'),
(19, 19, 40.00, 'cash', 'successful', '2025-10-11 10:45:00'),
(20, 20, 750.00, 'card', 'successful', '2025-10-12 19:40:00');
-- Guides
INSERT INTO Guides (name, language, specialization, salary) VALUES
(1,'Kamrul Hasan','Bangla, English','Mughal history',25000.00),
(2,'Sara Ahmed','English','Colonial Dhaka',28000.00),
(3,'Rafiq Ullah','Bangla, Arabic','Islamic architecture',30000.00),
(4, 'Tarek Aziz', 'Bangla, English', 'Mughal Art', 22000.00),
(5, 'Nusrat Jahan', 'Bangla, Hindi', 'Textile History', 24000.00),
(6, 'Omar Faruq', 'Bangla, English', 'Archaeology', 26000.00),
(7, 'Priya Chakraborty', 'Bengali, English', 'Religious Sites', 23000.00),
(8, 'Michael Brown', 'English', 'Colonial Architecture', 35000.00),
(9, 'Sultana Begum', 'Bangla', 'Folk Arts', 21000.00),
(10, 'Rashid Khan', 'Bangla, Arabic', 'Islamic Calligraphy', 28000.00),
(11, 'Zara Ali', 'English', 'Conservation', 30000.00),
(12, 'Hossain Molla', 'Bangla', 'Local Histories', 20000.00),
(13, 'Farhana Islam', 'Bangla, English', 'Museum Studies', 27500.00),
(14, 'Imran Siddique', 'Bangla, English', 'Ancient Scripts', 29000.00),
(15, 'Leila Ahmed', 'Arabic, English', 'Religious Studies', 32000.00),
(16, 'Rony Gomes', 'Bangla, Portuguese', 'Maritime Heritage', 25000.00),
(17, 'Saeed Khan', 'Urdu, Bangla', 'Islamic Architecture', 26500.00),
(18, 'Emily Carter', 'English', 'Cultural Tourism', 33000.00),
(19, 'Nadia Rahman', 'Bangla, Chakma', 'Tribal Culture', 24000.00),
(20, 'Victor Silva', 'Portuguese, English', 'Colonial Trade', 34000.00);
-- Assignments
INSERT INTO Assignments (guide_id, site_id, event_id, shift_time) VALUES
(1, 1, NULL, 'Morning'),
(2, NULL, 2, 'Evening'),
(3, 4, NULL, 'Afternoon'),
(4, 4, 1, NULL, 'Morning'),
(5, 5, NULL, 6, 'Evening'),
(6, 6, 3, NULL, 'Afternoon'),
(7, 7, NULL, 8, 'Morning'),
(8, 8, 4, NULL, 'Afternoon'),
(9, 9, NULL, 10, 'Evening'),
(10, 10, 5, NULL, 'Morning'),
(11, 11, NULL, 11, 'Afternoon'),
(12, 12, 7, NULL, 'Morning'),
(13, 13, NULL, 13, 'Evening'),
(14, 14, 14, NULL, 'Afternoon'),
(15, 15, NULL, 15, 'Morning'),
(16, 16, 16, NULL, 'Morning'),
(17, 17, NULL, 18, 'Evening'),
(18, 18, 19, NULL, 'Afternoon'),
(19, 19, NULL, 20, 'Morning'),
(20, 20, 2, NULL, 'Evening');
-- Reviews
INSERT INTO Reviews (visitor_id, site_id, event_id, rating, comment) VALUES
(1, 1, NULL, 5, 'Beautiful Mughal fort!'),
(2, NULL, 2, 4, 'Great walk and well organized.'),
(3, 3, NULL, 5, 'Amazing historical site!'),
(4, NULL, 4, 3, 'Lecture was informative but too long.'),
(5, 5, 1, NULL, 4, 'Loved the evening ambiance at Lalbagh Fort.', '2025-10-01'),
(6, 6, NULL, 2, 5, 'Ahsan Manzil tour was insightful!', '2025-10-06'),
(7, 7, 3, NULL, 5, 'Mahasthangarh is breathtaking.', '2025-11-11'),
(8, 8, NULL, 4, 4, 'Good lecture at Bagerhat.', '2025-12-02'),
(9, 9, 5, NULL, 3, 'Parliament tour interesting but strict rules.', '2025-10-26'),
(10, 10, NULL, 6, 5, 'Excellent concert evening.', '2025-10-13'),
(11, 11, 7, NULL, 4, 'Panam City definitely worth a morning.', '2025-11-13'),
(12, 12, NULL, 7, 5, 'Great learning at the workshop.', '2025-10-21'),
(13, 13, 8, NULL, 4, 'Bagha Mosque is peaceful.', '2025-11-19'),
(14, 14, NULL, 9, 5, 'Kantajew demo was superb!', '2025-11-04'),
(15, 15, 10, NULL, 5, 'Paharpur was a highlight.', '2025-12-06'),
(16, 16, NULL, 11, 4, 'Museum night was magical.', '2025-12-13'),
(17, 17, 12, NULL, 4, 'Bagerhat Museum has charming exhibits.', '2025-12-19'),
(18, 18, NULL, 13, 5, 'Festival at Chhota Sona was vibrant.', '2026-01-06'),
(19, 19, 14, NULL, 4, 'Temple music was beautiful.', '2026-01-16'),
(20, 20, NULL, 20, 5, 'Street food tour in Old Dhaka—delicious!', '2026-02-21');

-- Admin user (default admin)
-- password = Admin@123
INSERT INTO Admins (email, password_hash, full_name) VALUES
('admin@gmail.com', '$2y$10$0Z4K2LgQDLKfHjSKhTCh4OHkMtkOCPoE.X3nWgbbUIEMh9D3Q0hXy', 'Site Administrator');
