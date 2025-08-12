<?php

namespace App\Models;

use PDO;
use Exception;

class PasswordReset {
    
    /**
     * Criar um novo token de recuperação de senha
     * @param string $email
     * @param string $matricula
     * @return array
     */
    public static function createResetToken($email, $matricula) {
        $conn = BD::getConnection();
        
        // Gerar token único e código de verificação
        $token = bin2hex(random_bytes(32));
        $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Token expira em 15 minutos
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Invalidar tokens anteriores para este email
        $sqlInvalidate = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0");
        $sqlInvalidate->bindValue(':email', $email);
        $sqlInvalidate->execute();
        
        // Inserir novo token
        $sql = $conn->prepare("INSERT INTO password_resets (email, matricula, token, codigo_verificacao, expires_at) VALUES (:email, :matricula, :token, :codigo, :expires_at)");
        $sql->bindValue(':email', $email);
        $sql->bindValue(':matricula', $matricula);
        $sql->bindValue(':token', $token);
        $sql->bindValue(':codigo', $codigo);
        $sql->bindValue(':expires_at', $expiresAt);
        
        if ($sql->execute()) {
            return [
                'token' => $token,
                'codigo' => $codigo,
                'expires_at' => $expiresAt
            ];
        }
        
        throw new Exception('Erro ao criar token de recuperação');
    }
    
    /**
     * Verificar se um código de verificação é válido
     * @param string $codigo
     * @param string $email
     * @return array|false
     */
    public static function verifyCode($codigo, $email) {
        $conn = BD::getConnection();
        
        $sql = $conn->prepare("
            SELECT * FROM password_resets 
            WHERE codigo_verificacao = :codigo 
            AND email = :email 
            AND used = 0 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $sql->bindValue(':codigo', $codigo);
        $sql->bindValue(':email', $email);
        $sql->execute();
        
        return $sql->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verificar se um token é válido
     * @param string $token
     * @return array|false
     */
    public static function verifyToken($token) {
        $conn = BD::getConnection();
        
        $sql = $conn->prepare("
            SELECT * FROM password_resets 
            WHERE token = :token 
            AND used = 0 
            AND expires_at > NOW()
            LIMIT 1
        ");
        $sql->bindValue(':token', $token);
        $sql->execute();
        
        return $sql->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marcar token como usado
     * @param string $token
     * @return bool
     */
    public static function markTokenAsUsed($token) {
        $conn = BD::getConnection();
        
        $sql = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
        $sql->bindValue(':token', $token);
        
        return $sql->execute();
    }
    
    /**
     * Limpar tokens expirados (executar periodicamente)
     * @return int
     */
    public static function cleanExpiredTokens() {
        $conn = BD::getConnection();
        
        $sql = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
        $sql->execute();
        
        return $sql->rowCount();
    }
    
    /**
     * Verificar se usuário existe com email e matrícula
     * @param string $email
     * @param string $matricula
     * @return object|false
     */
    public static function validateUser($email, $matricula) {
        $conn = BD::getConnection();
        
        $sql = $conn->prepare("
            SELECT id, nome, email, matricula 
            FROM usuarios 
            WHERE email = :email 
            AND matricula = :matricula 
            AND ativo = 1
        ");
        $sql->bindValue(':email', $email);
        $sql->bindValue(':matricula', $matricula);
        $sql->execute();
        
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
