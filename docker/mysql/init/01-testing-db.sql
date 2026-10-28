-- Pest runs against this database (phpunit.xml: DB_DATABASE=testing).
CREATE DATABASE IF NOT EXISTS `testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON `testing`.* TO 'laravel'@'%';
FLUSH PRIVILEGES;
