-- Script para adicionar tabela de recuperação de senha
USE vaicomigo;

-- Criar tabela para tokens de recuperação de senha
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    matricula VARCHAR(20) NOT NULL,
    token VARCHAR(255) NOT NULL,
    codigo_verificacao VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_matricula (matricula),
    INDEX idx_token (token),
    INDEX idx_codigo (codigo_verificacao),
    INDEX idx_expires (expires_at)
);

-- Verificar se a tabela foi criada
DESCRIBE password_resets;
