<?php
namespace App\Controllers;

use App\Models\Usuario;
use App\Models\BD;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class GestorController
{
    public function index()
    {
        session_start();

        // Verificar se usuário está logado
        if (!isset($_SESSION['usuario_logado'])) {
            header('Location: /login');
            exit;
        }

        $loggedInUserId = $_SESSION['usuario_logado']['id'];
        $usuarioLogado = Usuario::getById($loggedInUserId);
        if (!$usuarioLogado || $usuarioLogado->ativo != 1) {
            session_destroy();
            header('Location: /login');
            exit;
        }
       
        // Processar requisições AJAX
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handleAjaxRequest();
            return;
        }

    

        // Obter dados do usuário logado da sessão
        $user = $_SESSION['usuario_logado'] ?? null;

        // Carregar dados para a view
        try {
            $usuarios = Usuario::getAll();
            $estatisticas = [
                'total_usuarios' => Usuario::contarPorTipo(),
                'caronas_ativas' => 8, // Placeholder - implementar quando tiver tabela de caronas
                'caronas_concluidas' => 42, // Placeholder
                'avaliacoes' => 38 // Placeholder
            ];
        } catch (Exception $e) {
            $usuarios = [];
            $estatisticas = [
                'total_usuarios' => 0,
                'caronas_ativas' => 0,
                'caronas_concluidas' => 0,
                'avaliacoes' => 0
            ];
        }

        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../Views');
        $twig = new \Twig\Environment($loader);

        // JavaScript inline para funcionalidades do gestor
        $javascript = $this->getGestorJavaScript();

        echo $twig->render('gestor/index.html.twig', [
            'user' => $user,
            'usuarios' => $usuarios,
            'estatisticas' => $estatisticas,
            'javascript' => $javascript
        ]);
    }

    private function handleAjaxRequest()
    {
        header('Content-Type: application/json');
        
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'criar_usuario':
                    $this->criarUsuario();
                    break;
                case 'atualizar_usuario':
                    $this->atualizarUsuario();
                    break;
                case 'get_usuario':
                    $this->getUsuario();
                    break;
                case 'deletar_usuario':
                    $this->deletarUsuario();
                    break;
                case 'alterar_status_usuario':
                    $this->alterarStatusUsuario();
                    break;
                case 'buscar_usuarios':
                    $this->buscarUsuarios();
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Ação não encontrada']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function criarUsuario()
    {
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $telefone = $_POST['telefone'] ?? null;
        $tipo_usuario = $_POST['tipo_usuario'] ?? 'aluno';
        $matricula = $_POST['matricula'] ?? null;

        if (empty($nome) || empty($email) || empty($senha)) {
            throw new Exception('Nome, email e senha são obrigatórios');
        }

        $usuario = new Usuario();
        $id = $usuario->inserir($nome, $email, $senha, $telefone, $tipo_usuario, $matricula);

        echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso', 'id' => $id]);
    }

    private function atualizarUsuario()
    {
        $id = $_POST['usuario_id'] ?? '';
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? null;
        $telefone = $_POST['telefone'] ?? null;
        $tipo_usuario = $_POST['tipo_usuario'] ?? 'aluno';
        $matricula = $_POST['matricula'] ?? null;

        if (empty($id) || empty($nome) || empty($email)) {
            throw new Exception('ID, nome e email são obrigatórios');
        }

        $usuario = new Usuario();
        $success = $usuario->atualizar($id, $nome, $email, $telefone, $tipo_usuario, $matricula, $senha);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso']);
        } else {
            throw new Exception('Erro ao atualizar usuário');
        }
    }

    private function getUsuario()
    {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            throw new Exception('ID do usuário é obrigatório');
        }

        $usuario = Usuario::getById($id);
        
        if ($usuario) {
            echo json_encode(['success' => true, 'data' => $usuario]);
        } else {
            throw new Exception('Usuário não encontrado');
        }
    }

    private function deletarUsuario()
    {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            throw new Exception('ID do usuário é obrigatório');
        }

        $usuario = new Usuario();
        $success = $usuario->deletar($id);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Usuário deletado com sucesso']);
        } else {
            throw new Exception('Erro ao deletar usuário');
        }
    }

    private function alterarStatusUsuario()
    {
        $id = $_POST['id'] ?? '';
        $ativo = $_POST['ativo'] ?? '';
        
        if (empty($id) || $ativo === '') {
            throw new Exception('ID e status são obrigatórios');
        }

        $usuario = new Usuario();
        $success = $usuario->alterarStatus($id, (bool)$ativo);

        if ($success) {
            $status = $ativo ? 'ativado' : 'desativado';
            echo json_encode(['success' => true, 'message' => "Usuário $status com sucesso"]);
        } else {
            throw new Exception('Erro ao alterar status do usuário');
        }
    }

    private function buscarUsuarios()
    {
        $busca = $_POST['busca'] ?? '';
        $tipo = $_POST['tipo'] ?? '';

        $usuarios = Usuario::buscar($busca, $tipo);
        
        echo json_encode(['success' => true, 'data' => $usuarios]);
    }

    private function getGestorJavaScript()
    {
        return '
<script>
// Gestor JavaScript - Funcionalidades CRUD com Popups

// Variáveis globais
let modalUsuario, modalCarona;

// Inicialização
document.addEventListener("DOMContentLoaded", function() {
    modalUsuario = new bootstrap.Modal(document.getElementById("modalUsuario"));
    modalCarona = new bootstrap.Modal(document.getElementById("modalCarona"));
    
    // Event listeners para formulários
    document.getElementById("formUsuario").addEventListener("submit", salvarUsuario);
    document.getElementById("formCarona").addEventListener("submit", salvarCarona);
    
    // Inicializar gráficos
    inicializarGraficos();
});

// Funções para Usuários
function abrirModalUsuario(id = null) {
    const modal = document.getElementById("modalUsuario");
    const titulo = document.getElementById("modalUsuarioTitulo");
    const form = document.getElementById("formUsuario");
    
    // Limpar formulário
    form.reset();
    document.getElementById("usuario_id").value = "";
    
    if (id) {
        titulo.textContent = "Editar Usuário";
        carregarDadosUsuario(id);
    } else {
        titulo.textContent = "Novo Usuário";
        document.getElementById("usuario_senha").required = true;
    }
    
    modalUsuario.show();
}

function carregarDadosUsuario(id) {
    fetch("/gestor", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=get_usuario&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.data;
            document.getElementById("usuario_id").value = user.id;
            document.getElementById("usuario_nome").value = user.nome;
            document.getElementById("usuario_email").value = user.email;
            document.getElementById("usuario_telefone").value = user.telefone || "";
            document.getElementById("usuario_tipo").value = user.tipo_usuario;
            document.getElementById("usuario_matricula").value = user.matricula || "";
            document.getElementById("usuario_senha").required = false;
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        mostrarAlerta("Erro ao carregar dados do usuário", "danger");
    });
}

function salvarUsuario(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const id = document.getElementById("usuario_id").value;
    const action = id ? "atualizar_usuario" : "criar_usuario";
    formData.append("action", action);
    
    fetch("/gestor", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarAlerta(data.message, "success");
            modalUsuario.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarAlerta(data.message, "danger");
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        mostrarAlerta("Erro ao salvar usuário", "danger");
    });
}

function editarUsuario(id) {
    abrirModalUsuario(id);
}

function deletarUsuario(id) {
    if (confirm("Tem certeza que deseja deletar este usuário? Esta ação não pode ser desfeita.")) {
        fetch("/gestor", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=deletar_usuario&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarAlerta(data.message, "danger");
            }
        })
        .catch(error => {
            console.error("Erro:", error);
            mostrarAlerta("Erro ao deletar usuário", "danger");
        });
    }
}

function alterarStatusUsuario(id, ativo) {
    const acao = ativo ? "ativar" : "desativar";
    if (confirm(`Tem certeza que deseja ${acao} este usuário?`)) {
        fetch("/gestor", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=alterar_status_usuario&id=${id}&ativo=${ativo}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarAlerta(data.message, "danger");
            }
        })
        .catch(error => {
            console.error("Erro:", error);
            mostrarAlerta("Erro ao alterar status do usuário", "danger");
        });
    }
}

function filtrarUsuarios() {
    const busca = document.getElementById("filtro-usuario").value;
    const tipo = document.getElementById("filtro-tipo-usuario").value;
    
    fetch("/gestor", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=buscar_usuarios&busca=${encodeURIComponent(busca)}&tipo=${tipo}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            atualizarTabelaUsuarios(data.data);
        } else {
            mostrarAlerta("Erro ao filtrar usuários", "danger");
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        mostrarAlerta("Erro ao filtrar usuários", "danger");
    });
}

function atualizarTabelaUsuarios(usuarios) {
    const tbody = document.getElementById("tabela-usuarios");
    tbody.innerHTML = "";
    
    usuarios.forEach(user => {
        const tipoClass = user.tipo_usuario === "admin" ? "danger" : (user.tipo_usuario === "gestor" ? "warning" : "info");
        const statusClass = user.ativo == 1 ? "success" : "secondary";
        const statusText = user.ativo == 1 ? "Ativo" : "Inativo";
        const statusIcon = user.ativo == 1 ? "ban" : "check";
        const statusAction = user.ativo == 1 ? 0 : 1;
        const statusTitle = user.ativo == 1 ? "Desativar" : "Ativar";
        
        const row = `
            <tr>
                <td>${user.id}</td>
                <td>${escapeHtml(user.nome)}</td>
                <td>${escapeHtml(user.email)}</td>
                <td><span class="badge bg-${tipoClass}">${user.tipo_usuario.charAt(0).toUpperCase() + user.tipo_usuario.slice(1)}</span></td>
                <td>${user.matricula || "-"}</td>
                <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                <td>${formatarData(user.data_cadastro)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editarUsuario(${user.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-${user.ativo == 1 ? "warning" : "success"}" 
                                onclick="alterarStatusUsuario(${user.id}, ${statusAction})" 
                                title="${statusTitle}">
                            <i class="fas fa-${statusIcon}"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deletarUsuario(${user.id})" title="Deletar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// Funções Utilitárias
function mostrarAlerta(mensagem, tipo) {
    const alertContainer = document.createElement("div");
    alertContainer.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    alertContainer.style.cssText = "top: 20px; right: 20px; z-index: 9999; min-width: 300px;";
    alertContainer.innerHTML = `
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertContainer);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (alertContainer.parentNode) {
            alertContainer.remove();
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

function formatarData(data) {
    return new Date(data).toLocaleDateString("pt-BR");
}

function formatarDataHora(data, hora) {
    const dataObj = new Date(data + " " + hora);
    return dataObj.toLocaleDateString("pt-BR") + " " + dataObj.toLocaleTimeString("pt-BR", {hour: "2-digit", minute: "2-digit"});
}

// Funções de Gráficos
function inicializarGraficos() {
    // Verificar se os elementos existem antes de criar os gráficos
    const ctxUsuarios = document.getElementById("graficoUsuarios");
    const ctxCaronas = document.getElementById("graficoCaronas");
    
    if (ctxUsuarios) {
        // Contar badges por tipo usando uma abordagem mais segura
        const badges = document.querySelectorAll("#tabela-usuarios .badge");
        let alunos = 0, gestores = 0, admins = 0;
        
        badges.forEach(badge => {
            const text = badge.textContent.toLowerCase();
            if (text.includes("aluno")) alunos++;
            else if (text.includes("gestor")) gestores++;
            else if (text.includes("admin")) admins++;
        });
        
        new Chart(ctxUsuarios.getContext("2d"), {
            type: "doughnut",
            data: {
                labels: ["Alunos", "Gestores", "Administradores"],
                datasets: [{
                    data: [alunos, gestores, admins],
                    backgroundColor: ["#17a2b8", "#ffc107", "#dc3545"]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "bottom"
                    }
                }
            }
        });
    }
    
    if (ctxCaronas) {
        // Contar badges de caronas por status
        const caronaBadges = document.querySelectorAll("#tabela-caronas .badge");
        let ativas = 0, finalizadas = 0, canceladas = 0;
        
        caronaBadges.forEach(badge => {
            if (badge.classList.contains("bg-success")) ativas++;
            else if (badge.classList.contains("bg-primary")) finalizadas++;
            else if (badge.classList.contains("bg-danger")) canceladas++;
        });
        
        new Chart(ctxCaronas.getContext("2d"), {
            type: "bar",
            data: {
                labels: ["Ativas", "Finalizadas", "Canceladas"],
                datasets: [{
                    label: "Quantidade",
                    data: [ativas, finalizadas, canceladas],
                    backgroundColor: ["#28a745", "#007bff", "#dc3545"]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Event listeners para filtros em tempo real
document.addEventListener("DOMContentLoaded", function() {
    // Verificar se os elementos existem antes de adicionar event listeners
    const filtroUsuario = document.getElementById("filtro-usuario");
    const filtroTipoUsuario = document.getElementById("filtro-tipo-usuario");
    
    // Filtro de usuários em tempo real
    if (filtroUsuario) {
        filtroUsuario.addEventListener("input", function() {
            if (this.value.length >= 3 || this.value.length === 0) {
                filtrarUsuarios();
            }
        });
    }
    
    if (filtroTipoUsuario) {
        filtroTipoUsuario.addEventListener("change", filtrarUsuarios);
    }
});
</script>
        ';
    }
}
