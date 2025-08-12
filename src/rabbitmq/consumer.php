<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Conexão com RabbitMQ
// Obtém o host da variável de ambiente, ou 'rabbitmq' como default
$rabbitmqHost = getenv('RABBITMQ_HOST') ?: 'rabbitmq';

$connection = new AMQPStreamConnection("host.docker.internal", 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('fila_redefinir_senha', false, false, true, false, false, false);

echo " [*] Serviço de Redefinição de Senha - Aguardando solicitações...\n";
echo " [*] Para sair pressione CTRL+C\n";

function enviarEmailReal($destinatario, $assunto, $corpo, $codigo) {
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER') ?: 'wemissonandrade23@gmail.com';
        $mail->Password   = getenv('SMTP_PASS') ?: 'romp ijlc qdhw pise';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        $mail->CharSet    = 'UTF-8';

        // Remetente
        $mail->setFrom(getenv('SMTP_USER') ?: 'wemissonandrade23@gmail.com', 'VaiComigo - Sistema');
        
        // Destinatário
        $mail->addAddress($destinatario);

        // Conteúdo do e-mail
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f8f9fa; }
                .code { font-size: 24px; font-weight: bold; color: #007bff; text-align: center; padding: 20px; background-color: white; border: 2px solid #007bff; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>VaiComigo - Redefinição de Senha</h1>
                </div>
                <div class='content'>
                    <h2>Código de Verificação</h2>
                    <p>Você solicitou a redefinição de sua senha. Use o código abaixo para continuar:</p>
                    <div class='code'>{$codigo}</div>
                    <p><strong>Importante:</strong> Este código expira em 15 minutos.</p>
                    <p>Se você não solicitou esta redefinição, ignore este e-mail.</p>
                </div>
                <div class='footer'>
                    <p>© 2024 VaiComigo - Sistema de Transporte</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->Body = $htmlBody;
        $mail->AltBody = "Seu código de verificação para redefinir a senha é: {$codigo}. Este código expira em 15 minutos.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new Exception("Erro ao enviar e-mail: {$mail->ErrorInfo}");
    }
}

$callback = function ($msg) {
    $dados = json_decode($msg->body, true);
    
    echo ' [x] Nova solicitação de redefinição recebida: ', $msg->body, "\n";
    
    // Processa especificamente redefinição de senha
    try {
        if ($dados['tipo'] === 'redefinir_senha') {
            echo " [x] Processando redefinição de senha para: {$dados['to']}\n";
            echo " [x] Código de verificação: {$dados['codigo_verificacao']}\n";
            echo " [x] Assunto: {$dados['subject']}\n";
            
            // ENVIO REAL DO E-MAIL
            echo " [x] Enviando e-mail REAL com código de verificação...\n";
            
            $emailEnviado = enviarEmailReal(
                $dados['to'],
                $dados['subject'],
                $dados['body'],
                $dados['codigo_verificacao']
            );
            
            if ($emailEnviado) {
                echo " [x] ✅ E-mail REAL enviado com sucesso para: {$dados['to']}\n";
                echo " [x] ✅ Código enviado: {$dados['codigo_verificacao']}\n";
                
                // Log de sucesso
                $logEntry = "[" . date('Y-m-d H:i:s') . "] REDEFINIÇÃO DE SENHA - E-mail: {$dados['to']} - Código: {$dados['codigo_verificacao']} - Status: ENVIADO REAL\n";
                file_put_contents('/var/www/rabbitmq/redefinir_senha_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
                
                echo " [x] Código {$dados['codigo_verificacao']} registrado no sistema para {$dados['to']}\n";
            }
            
        } else {
            echo " [!] Tipo de mensagem não reconhecido: {$dados['tipo']}\n";
        }
        
    } catch (Exception $e) {
        echo " [!] Erro ao processar redefinição de senha: {$e->getMessage()}\n";
        
        // Log de erro
        $errorLog = "[" . date('Y-m-d H:i:s') . "] ERRO - {$dados['to']} - {$e->getMessage()}\n";
        file_put_contents('/var/www/rabbitmq/redefinir_senha_log.txt', $errorLog, FILE_APPEND | LOCK_EX);
    }
    
    echo " [x] ✅ Processamento de redefinição concluído\n";
    echo " [x] ----------------------------------------\n";
    $msg->ack();
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume('fila_redefinir_senha', '', false, false, false, false, $callback);

// Loop infinito para processar solicitações de redefinição
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
