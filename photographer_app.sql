-- Please make first a database on PHPMyAdmin named "photographer_app.sql" then import this file

-- Table: Album
CREATE TABLE album (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remote_id INT NOT NULL,
    date_add TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_over TIMESTAMP NULL,
    date_upd TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('live', 'longterm') DEFAULT 'live',
    venue_id INT NOT NULL,
    user_id INT NOT NULL,
    qr_code_path VARCHAR(255)
);

-- Table: User
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_add TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    album_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    log TEXT NULL
);

-- Table: Remote
CREATE TABLE remote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    token VARCHAR(255) NOT NULL
);

-- Table: Venue
CREATE TABLE venue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Table: Photobooth
CREATE TABLE photobooth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    name VARCHAR(100) NOT NULL
);

-- Table: Capture
CREATE TABLE capture (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    date_add TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT(11) NOT NULL,
    FOREIGN KEY (album_id) REFERENCES album(id) ON DELETE CASCADE
);


-- Foreign key relationships
ALTER TABLE album ADD CONSTRAINT fk_album_remote FOREIGN KEY (remote_id) REFERENCES remote(id);
ALTER TABLE album ADD CONSTRAINT fk_album_venue FOREIGN KEY (venue_id) REFERENCES venue(id);

ALTER TABLE user ADD CONSTRAINT fk_user_album FOREIGN KEY (album_id) REFERENCES album(id);

ALTER TABLE remote ADD CONSTRAINT fk_remote_venue FOREIGN KEY (venue_id) REFERENCES venue(id);

ALTER TABLE photobooth ADD CONSTRAINT fk_photobooth_venue FOREIGN KEY (venue_id) REFERENCES venue(id);

ALTER TABLE capture ADD CONSTRAINT fk_capture_album FOREIGN KEY (album_id) REFERENCES album(id);