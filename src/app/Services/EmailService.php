<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    
    private $mail;
    
    public function __construct() {
        $this->setupMailer();
    }
    
    private function setupMailer() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Configurações do servidor SMTP
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'dheyf.silva@estudante.ifto.edu.br';
            $this->mail->Password   = 'dsuf upbc buca emwf';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 587;
            $this->mail->CharSet    = 'UTF-8';
            
            // Configurações do remetente
            $this->mail->setFrom('dheyfdheyf.silva@estudante.ifto.edu.br', 'VaiComigo - Sistema');
            
        } catch (Exception $e) {
            error_log('Erro ao configurar PHPMailer: ' . $e->getMessage());
        }
    }
    
    /**
     * Enviar email de recuperação de senha diretamente
     * @param string $email
     * @param string $codigo
     * @param string $nome
     * @return bool
     */
    public function sendPasswordResetEmail($email, $codigo, $nome = '') {
        try {
            // Limpar destinatários anteriores
            $this->mail->clearAddresses();
            
            // Configurar destinatário
            $this->mail->addAddress($email);
            
            // Configurar conteúdo
            $this->mail->isHTML(true);
            $this->mail->Subject = 'VaiComigo - Código de Verificação';
            $this->mail->Body = $this->buildEmailBody($codigo, $nome);
            $this->mail->AltBody = "Seu código de verificação para redefinir a senha é: {$codigo}. Este código expira em 15 minutos.";
            
            // Enviar email
            $result = $this->mail->send();
            
            if ($result) {
                error_log("Email enviado com sucesso para: {$email} - Código: {$codigo}");
                return true;
            } else {
                error_log("Falha ao enviar email para: {$email}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log('Erro ao enviar email: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Construir corpo do email HTML
     * @param string $codigo
     * @param string $nome
     * @return string
     */
    private function buildEmailBody($codigo, $nome = '') {
        $saudacao = $nome ? "Olá, {$nome}!" : "Olá!";
        
        return "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #6c5ce7; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .code { background: #007bff; color: white; font-size: 24px; font-weight: bold; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0; letter-spacing: 3px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚗 VaiComigo</h1>
                    <p>Sistema de Caronas - IFTO</p>
                </div>
                <div class='content'>
                    <h2>{$saudacao}</h2>
                    <p>Você solicitou a redefinição de sua senha no sistema VaiComigo.</p>
                    <p>Use o código abaixo para continuar o processo:</p>
                    
                    <div class='code'>{$codigo}</div>
                    
                    <div class='warning'>
                        <strong>⚠️ Importante:</strong>
                        <ul>
                            <li>Este código expira em <strong>15 minutos</strong></li>
                            <li>Use apenas se você solicitou a redefinição</li>
                            <li>Não compartilhe este código com ninguém</li>
                        </ul>
                    </div>
                    
                    <p>Se você não solicitou esta redefinição, ignore este email. Sua senha permanecerá inalterada.</p>
                    
                    <hr>
                    <p><strong>Como usar:</strong></p>
                    <ol>
                        <li>Volte à tela de login</li>
                        <li>Clique em 'Esqueceu sua senha?'</li>
                        <li>Digite este código quando solicitado</li>
                        <li>Defina sua nova senha</li>
                    </ol>
                </div>
                <div class='footer'>
                    <p>VaiComigo - Sistema de Caronas<br>
                    IFTO - Campus Colinas do Tocantins<br>
                    Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
}
