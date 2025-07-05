-- FusionAuth Database Initialization Script
-- Creates a fusionadmin user with full database privileges

-- Create the fusionadmin user with the specified password
CREATE USER IF NOT EXISTS 'fusionadmin'@'%' IDENTIFIED BY 'FusionAdminPass123!';

-- Grant all privileges on all databases (including ability to create databases)
GRANT ALL PRIVILEGES ON *.* TO 'fusionadmin'@'%' WITH GRANT OPTION;

-- Grant specific privileges needed for FusionAuth
GRANT CREATE ON *.* TO 'fusionadmin'@'%';
GRANT DROP ON *.* TO 'fusionadmin'@'%';
GRANT ALTER ON *.* TO 'fusionadmin'@'%';
GRANT INDEX ON *.* TO 'fusionadmin'@'%';
GRANT LOCK TABLES ON *.* TO 'fusionadmin'@'%';
GRANT REFERENCES ON *.* TO 'fusionadmin'@'%';
GRANT CREATE TEMPORARY TABLES ON *.* TO 'fusionadmin'@'%';
GRANT EXECUTE ON *.* TO 'fusionadmin'@'%';
GRANT CREATE VIEW ON *.* TO 'fusionadmin'@'%';
GRANT SHOW VIEW ON *.* TO 'fusionadmin'@'%';
GRANT CREATE ROUTINE ON *.* TO 'fusionadmin'@'%';
GRANT ALTER ROUTINE ON *.* TO 'fusionadmin'@'%';
GRANT EVENT ON *.* TO 'fusionadmin'@'%';
GRANT TRIGGER ON *.* TO 'fusionadmin'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;

-- Display confirmation
SELECT 'FusionAuth admin user created successfully' AS Status; 