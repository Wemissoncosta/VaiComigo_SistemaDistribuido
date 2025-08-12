<?php
require_once '/var/www/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Conexão com RabbitMQ
// Obtém o host da variável de ambiente, ou 'rabbitmq' como default
$rabbitmqHost = getenv('RABBITMQ_HOST') ?: 'rabbitmq';

$connection = new AMQPStreamConnection($rabbitmqHost, 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('fila_redefinir_senha', false, false, true, false, false, false);

echo "Sistema de Redefinição de Senha - Enviando códigos de verificação\n";

// Simula solicitações de redefinição de senha
$usuarios = [
    'usuario1@exemplo.com',
    'usuario2@exemplo.com', 
    'admin@vaicomigo.com',
    'teste@gmail.com',
    'user@hotmail.com'
];

foreach ($usuarios as $index => $email) {
    $dados = json_encode([
        'tipo' => 'redefinir_senha',
        'to' => $email,
        'codigo_verificacao' => '101010',
        'subject' => 'Redefinição de Senha - VaiComigo',
        'body' => 'Seu código de verificação para redefinir a senha é: 101010. Este código expira em 15 minutos.',
        'timestamp' => date('Y-m-d H:i:s'),
        'tentativa' => $index + 1
    ]);
    
    $msg = new AMQPMessage($dados, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    
    $channel->basic_publish($msg, '', 'fila_redefinir_senha');
    
    echo " [x] Código de redefinição enviado para: {$email} - Código: 101010\n";
    
    sleep(2); // Aguarda 2 segundos entre envios
}

echo " [x] Todos os códigos de redefinição foram enviados!\n";

$channel->close();
$connection->close();
?>
