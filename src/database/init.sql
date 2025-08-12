-- Criação do banco de dados
DROP DATABASE IF EXISTS vaicomigo;
CREATE DATABASE vaicomigo;
USE vaicomigo;

-- Tabela de usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    matricula VARCHAR(20) UNIQUE,
    tipo_usuario ENUM('admin', 'gestor', 'aluno') DEFAULT 'aluno',
    foto_perfil VARCHAR(255),
    avaliacao DECIMAL(3,2) DEFAULT 0.00,
    ultimo_login TIMESTAMP NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de caronas
CREATE TABLE caronas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    origem VARCHAR(255) NOT NULL,
    destino VARCHAR(255) NOT NULL,
    data_viagem DATE NOT NULL,
    hora_viagem TIME NOT NULL,
    vagas_disponiveis INT NOT NULL,
    vagas_ocupadas INT DEFAULT 0,
    observacoes TEXT,
    status ENUM('ativa', 'cancelada', 'finalizada') DEFAULT 'ativa',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (motorista_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de solicitações de carona
CREATE TABLE solicitacoes_carona (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carona_id INT NOT NULL,
    passageiro_id INT NOT NULL,
    status ENUM('pendente', 'aceita', 'recusada', 'cancelada') DEFAULT 'pendente',
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carona_id) REFERENCES caronas(id) ON DELETE CASCADE,
    FOREIGN KEY (passageiro_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_solicitacao (carona_id, passageiro_id)
);

-- Tabela de mensagens do chat
CREATE TABLE mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carona_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carona_id) REFERENCES caronas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de notificações
CREATE TABLE notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo ENUM('solicitacao', 'aceite', 'recusa', 'cancelamento', 'mensagem', 'sistema') NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de avaliações
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliador_id INT NOT NULL,
    avaliado_id INT NOT NULL,
    carona_id INT NOT NULL,
    nota INT CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    data_avaliacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (avaliado_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (carona_id) REFERENCES caronas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_avaliacao (avaliador_id, avaliado_id, carona_id)
);

-- View para listar caronas com informações do motorista
CREATE VIEW vw_caronas_completas AS
SELECT 
    c.id,
    c.origem,
    c.destino,
    c.data_viagem,
    c.hora_viagem,
    c.vagas_disponiveis,
    c.vagas_ocupadas,
    (c.vagas_disponiveis - c.vagas_ocupadas) AS vagas_livres,
    c.observacoes,
    c.status,
    c.data_criacao,
    u.id AS motorista_id,
    u.nome AS motorista_nome,
    u.telefone AS motorista_telefone,
    u.avaliacao AS motorista_avaliacao
FROM caronas c
JOIN usuarios u ON c.motorista_id = u.id
WHERE u.ativo = 1;

-- View para estatísticas de caronas
CREATE VIEW vw_estatisticas_caronas AS
SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) AS ativas,
    SUM(CASE WHEN status = 'finalizada' THEN 1 ELSE 0 END) AS finalizadas,
    SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) AS canceladas
FROM caronas;

-- =====================================================
-- ÍNDICES PARA PERFORMANCE
-- =====================================================

CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_matricula ON usuarios(matricula);
CREATE INDEX idx_usuarios_tipo ON usuarios(tipo_usuario);
CREATE INDEX idx_caronas_motorista ON caronas(motorista_id);
CREATE INDEX idx_caronas_data ON caronas(data_viagem);
CREATE INDEX idx_caronas_status ON caronas(status);
CREATE INDEX idx_solicitacoes_carona ON solicitacoes_carona(carona_id);
CREATE INDEX idx_solicitacoes_passageiro ON solicitacoes_carona(passageiro_id);
CREATE INDEX idx_mensagens_carona ON mensagens(carona_id);
CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id);

-- Usuário administrador padrão
INSERT INTO usuarios (nome, email, senha, telefone, matricula, tipo_usuario, foto_perfil, avaliacao, ultimo_login, ativo)
VALUES (
    'Administrador',
    'admin@vaicomigo.local',
    '$2y$12$AwDq/yy0MPqtlTVxFbBOOuVLlC0nj2TS5Nld1nwAOajRWukV1rJdq',
    '(63) 90000-0000',
    'ADMIN00000001',
    'admin',
    NULL,
    0.00,
    NOW(),
    1
);

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
