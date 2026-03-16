-- Runs once when the data directory is first initialised.
-- The bookshop database already exists at this point (created by MYSQL_DATABASE).

ALTER DATABASE bookshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create the dedicated application user.
-- Using 'bookshop'@'%' so the user is reachable from any container IP.
-- Credentials here are dev defaults — override DB_USERNAME/DB_PASSWORD in .env
-- for production deployments.
CREATE USER IF NOT EXISTS 'bookshop'@'%' IDENTIFIED BY 'bookshop_secret';
GRANT ALL PRIVILEGES ON `bookshop`.* TO 'bookshop'@'%';
FLUSH PRIVILEGES;
