-- Create the database
CREATE DATABASE IF NOT EXISTS barbing_db;
USE barbing_db;

-- Create users table (customers + barbers + admins)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'barber', 'admin') DEFAULT 'customer',
    loyalty_points INT DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL, -- Added to support profile avatar uploads
    bio TEXT DEFAULT NULL,            -- Added to support user bio in profiles
    location VARCHAR(255) DEFAULT NULL, -- Added to support user location in profiles
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create barbers table (extra info for barbers)
CREATE TABLE IF NOT EXISTS barbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bio TEXT,
    specialties VARCHAR(255),
    rating DECIMAL(2,1) DEFAULT 0,
    total_bookings INT DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create services table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_minutes INT NOT NULL,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    barber_id INT NOT NULL,
    service_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL,
    notes TEXT,
    admin_message TEXT DEFAULT NULL, -- Added to support custom admin messages/responses on appointments
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (barber_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample admin user (password is 'admin123' - hashed properly)
INSERT INTO users (name, email, phone, password, role) VALUES 
('Admin User', 'admin@barbing.com', '1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample services
INSERT INTO services (name, description, price, duration_minutes, category) VALUES
('Classic Haircut', 'Traditional scissor and clipper cut', 15.00, 30, 'Haircut'),
('Skin Fade', 'Modern fade with skin taper', 20.00, 45, 'Haircut'),
('Beard Trim', 'Shape and trim beard', 10.00, 20, 'Beard'),
('Clean Shave', 'Hot towel straight razor shave', 15.00, 30, 'Shave'),
('Hair + Beard Combo', 'Complete haircut and beard grooming', 30.00, 60, 'Combo');

-- Insert sample barber
INSERT INTO users (name, email, phone, password, role) VALUES 
('John Barber', 'john@barbing.com', '9876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'barber');

INSERT INTO barbers (user_id, bio, specialties, rating, commission_rate) VALUES
(2, 'Expert barber with 10 years experience', 'Fades, Designs, Beard', 4.8, 50.00);
