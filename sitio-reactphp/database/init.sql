CREATE DATABASE IF NOT EXISTS sitio_reactphp;

USE sitio_reactphp;

CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO contacts (name, email, message) VALUES 
('Juan Pérez', 'juan@example.com', 'Consulta sobre productos'),
('María García', 'maria@example.com', 'Soporte técnico');