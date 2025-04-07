CREATE DATABASE IF NOT EXISTS sitio_reactphp;
USE sitio_reactphp;

CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario con permisos limitados (seguridad)
CREATE USER IF NOT EXISTS 'app_user'@'localhost' IDENTIFIED BY 'password_seguro';
GRANT SELECT, INSERT ON sitio_reactphp.contacts TO 'app_user'@'localhost';
FLUSH PRIVILEGES;