-- ================================================================
--  Online Library Management System  v3.0
--  KEY CONCEPT: Each PHYSICAL copy gets its own unique LIB number
--
--  Example: Computer Networks qty=3 generates:
--    LIB015 -> copy 1 (available or issued to Student A)
--    LIB016 -> copy 2 (available or issued to Student B)
--    LIB017 -> copy 3 (available or issued to Student C)
-- ================================================================

CREATE DATABASE IF NOT EXISTS library_system;
USE library_system;

-- Drop old tables if re-importing
DROP TABLE IF EXISTS issued_books;
DROP TABLE IF EXISTS book_copies;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS users;

-- ----------------------------------------------------------------
-- USERS
-- ----------------------------------------------------------------
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    admin_id   VARCHAR(50)  DEFAULT NULL,
    reg_no     VARCHAR(50)  DEFAULT NULL,
    name       VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','member') NOT NULL DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------
-- BOOKS  (catalogue record — title / author / publisher only)
-- ----------------------------------------------------------------
CREATE TABLE books (
    book_id    INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    author     VARCHAR(100) NOT NULL,
    publisher  VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------
-- BOOK_COPIES  (one row per physical copy on the shelf)
-- Each copy carries its own unique LIB number (stickered on spine)
-- ----------------------------------------------------------------
CREATE TABLE book_copies (
    copy_id     INT AUTO_INCREMENT PRIMARY KEY,
    book_id     INT NOT NULL,
    book_number VARCHAR(10) NOT NULL UNIQUE,   -- e.g. LIB001
    status      ENUM('available','issued') NOT NULL DEFAULT 'available',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    INDEX idx_book_number (book_number),
    INDEX idx_copy_status (book_id, status)
);

-- ----------------------------------------------------------------
-- ISSUED_BOOKS  (links a member to a specific physical copy)
-- ----------------------------------------------------------------
CREATE TABLE issued_books (
    issue_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT  NOT NULL,
    copy_id     INT  NOT NULL,                 -- the exact physical copy
    issue_date  DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status      ENUM('issued','returned') DEFAULT 'issued',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id)
);

-- ----------------------------------------------------------------
-- SEED DATA
-- ----------------------------------------------------------------
INSERT INTO users (admin_id, name, username, password, role) VALUES
('ADM001', 'Administrator', 'admin', 'admin123', 'admin');

INSERT INTO users (reg_no, name, username, password, role) VALUES
('REG001', 'Alice Johnson',  'alice',  'alice123',  'member'),
('REG002', 'Bob Williams',   'bob',    'bob123',    'member'),
('REG003', 'Carol Martinez', 'carol',  'carol123',  'member');

-- Catalogue entries
INSERT INTO books (book_id, title, author, publisher) VALUES
(1, 'Introduction to Programming',    'John Smith',           'Tech Press'),
(2, 'Database Management Systems',    'C.J. Date',            'Pearson'),
(3, 'Data Structures and Algorithms', 'Thomas Cormen',        'MIT Press'),
(4, 'Operating Systems Concepts',     'Abraham Silberschatz', 'Wiley'),
(5, 'Computer Networks',              'Andrew Tanenbaum',     'Prentice Hall');

-- Introduction to Programming  qty 5  -> LIB001 … LIB005
INSERT INTO book_copies (book_id, book_number) VALUES
(1,'LIB001'),(1,'LIB002'),(1,'LIB003'),(1,'LIB004'),(1,'LIB005');

-- Database Management Systems  qty 3  -> LIB006 … LIB008
INSERT INTO book_copies (book_id, book_number) VALUES
(2,'LIB006'),(2,'LIB007'),(2,'LIB008');

-- Data Structures and Algorithms  qty 4  -> LIB009 … LIB012
INSERT INTO book_copies (book_id, book_number) VALUES
(3,'LIB009'),(3,'LIB010'),(3,'LIB011'),(3,'LIB012');

-- Operating Systems Concepts  qty 2  -> LIB013 … LIB014
INSERT INTO book_copies (book_id, book_number) VALUES
(4,'LIB013'),(4,'LIB014');

-- Computer Networks  qty 3  -> LIB015 … LIB017
INSERT INTO book_copies (book_id, book_number) VALUES
(5,'LIB015'),(5,'LIB016'),(5,'LIB017');
