<?php
namespace App\Controllers;

use App\Models\Usuario;
use App\Models\PasswordReset;
use App\Services\EmailService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class AuthController
{
    public function login()
    {
        session_start();

        $erro = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $matricula = $_POST['matricula'] ?? '';
            $senha = $_POST['password'] ?? '';

            if (empty($matricula) || empty($senha)) {
                $erro = 'Matrícula e senha são obrigatórios';
            } else {
                try {
                    $usuario = Usuario::autenticarPorMatricula($matricula, $senha);

                    if ($usuario) {
                        $_SESSION['usuario_logado'] = [
                            'id' => $usuario->id,
                            'nome' => $usuario->nome,
                            'email' => $usuario->email,
                            'tipo_usuario' => $usuario->tipo_usuario,
                            'foto_perfil' => $usuario->foto_perfil,
                            'matricula' => $usuario->matricula,
                            'telefone' => $usuario->telefone
                        ];

                        // Redirecionar todos os usuários para o gestor
                        header('Location: /gestor');
                        exit;
                    } else {
                        $erro = 'Matrícula ou senha inválidos';
                    }
                } catch (\Exception $e) {
                    $erro = 'Erro ao fazer login: ' . $e->getMessage();
                }
            }
        }

        // Render login view using Twig
        $loader = new FilesystemLoader(__DIR__ . '/../Views');
        $twig = new Environment($loader);

        echo $twig->render('auth/login.html.twig', [
            'erro' => $erro,
            'matricula' => $_POST['matricula'] ?? ''
        ]);
    }

    public function logout()
    {
        session_start();
        session_destroy();
        header('Location: /login');
        exit;
    }

    /**
     * Processar solicitação de recuperação de senha
     */
    public function forgotPassword()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            return;
        }

        try {
            $email = $_POST['email'] ?? '';
            $matricula = $_POST['matricula'] ?? '';

            if (empty($email) || empty($matricula)) {
                echo json_encode(['success' => false, 'message' => 'Email e matrícula são obrigatórios']);
                return;
            }

            // Verificar se usuário existe
            $usuario = PasswordReset::validateUser($email, $matricula);
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado com estes dados']);
                return;
            }

            // Criar token de recuperação
            $resetData = PasswordReset::createResetToken($email, $matricula);
            
            // Enviar email via RabbitMQ
            $emailService = new EmailService();
            $emailSent = $emailService->sendPasswordResetEmail($email, $resetData['codigo'], $usuario->nome);

            if ($emailSent) {
                // Armazenar dados na sessão para próxima etapa
                session_start();
                $_SESSION['password_reset'] = [
                    'email' => $email,
                    'matricula' => $matricula,
                    'token' => $resetData['token'],
                    'step' => 'verify_code'
                ];

                echo json_encode([
                    'success' => true, 
                    'message' => 'Código de verificação enviado para seu email',
                    'next_step' => 'verify_code'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao enviar email. Tente novamente.']);
            }

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Verificar código de verificação
     */
    public function verifyCode()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            return;
        }

        session_start();

        try {
            $codigo = $_POST['codigo'] ?? '';
            
            if (empty($codigo)) {
                echo json_encode(['success' => false, 'message' => 'Código é obrigatório']);
                return;
            }

            // Verificar se há dados de reset na sessão
            if (!isset($_SESSION['password_reset'])) {
                echo json_encode(['success' => false, 'message' => 'Sessão expirada. Inicie o processo novamente.']);
                return;
            }

            $resetSession = $_SESSION['password_reset'];
            
            // Verificar código
            $resetData = PasswordReset::verifyCode($codigo, $resetSession['email']);
            
            if (!$resetData) {
                echo json_encode(['success' => false, 'message' => 'Código inválido ou expirado']);
                return;
            }

            // Atualizar sessão para próxima etapa
            $_SESSION['password_reset']['step'] = 'new_password';
            $_SESSION['password_reset']['verified'] = true;

            echo json_encode([
                'success' => true, 
                'message' => 'Código verificado com sucesso',
                'next_step' => 'new_password'
            ]);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Redefinir senha
     */
    public function resetPassword()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            return;
        }

        session_start();

        try {
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            
            if (empty($novaSenha) || empty($confirmarSenha)) {
                echo json_encode(['success' => false, 'message' => 'Nova senha e confirmação são obrigatórias']);
                return;
            }

            if ($novaSenha !== $confirmarSenha) {
                echo json_encode(['success' => false, 'message' => 'Senhas não coincidem']);
                return;
            }

            if (strlen($novaSenha) < 6) {
                echo json_encode(['success' => false, 'message' => 'Senha deve ter pelo menos 6 caracteres']);
                return;
            }

            // Verificar se há dados de reset verificados na sessão
            if (!isset($_SESSION['password_reset']) || !$_SESSION['password_reset']['verified']) {
                echo json_encode(['success' => false, 'message' => 'Sessão inválida. Inicie o processo novamente.']);
                return;
            }

            $resetSession = $_SESSION['password_reset'];
            
            // Verificar se token ainda é válido
            $resetData = PasswordReset::verifyToken($resetSession['token']);
            if (!$resetData) {
                echo json_encode(['success' => false, 'message' => 'Token expirado. Inicie o processo novamente.']);
                return;
            }

            // Buscar usuário
            $usuario = PasswordReset::validateUser($resetSession['email'], $resetSession['matricula']);
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                return;
            }

            // Atualizar senha
            $usuarioModel = new Usuario();
            $success = $usuarioModel->atualizar(
                $usuario->id,
                $usuario->nome,
                $usuario->email,
                null, // telefone
                null, // tipo_usuario (manter atual)
                $usuario->matricula,
                $novaSenha // nova senha
            );

            if ($success) {
                // Marcar token como usado
                PasswordReset::markTokenAsUsed($resetSession['token']);
                
                // Limpar sessão
                unset($_SESSION['password_reset']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Senha redefinida com sucesso! Você pode fazer login agora.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar senha']);
            }

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}
