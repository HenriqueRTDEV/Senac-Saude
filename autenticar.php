<?php
// ===================================================================
//  autenticar.php
//  Prontuário Eletrônico TDS
//
//  Recebe o formulário do index.php, confere no banco e cria a sessão.
//  Esta página NÃO desenha nada: ela decide e redireciona.
//
//  Deu certo  -> painel.php
//  Deu errado -> index.php?erro=1  (a tela de login mostra o alerta)
// ===================================================================

// session_start() tem que vir antes de qualquer saída na tela.
session_start();

require 'config/conexao.php';

// Ninguém deve abrir este arquivo digitando o endereço no navegador.
// Se não veio formulário, devolve para o login.
if (!isset($_POST['login']) || !isset($_POST['senha'])) {
    header('Location: index.php');
    exit;
}

$login = trim($_POST['login']);
$senha = $_POST['senha'];

// -------------------------------------------------------------------
//  OS QUATRO PASSOS DO PREPARED STATEMENT
//  É o mesmo padrão do projeto inteiro, do login até a checagem
//  de medicação. Nunca se escreve $_POST dentro do SQL.
//
//  O "ativo = 1" é regra de negócio: usuário desativado não entra.
//  Como o sistema nunca apaga ninguém, é assim que se tira o acesso.
// -------------------------------------------------------------------
$sql = "SELECT id, nome, senha, perfil, registro_profissional
        FROM usuarios
        WHERE login = ? AND ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);          // 1. prepara
mysqli_stmt_bind_param($stmt, 's', $login);      // 2. amarra o valor
mysqli_stmt_execute($stmt);                      // 3. executa
$resultado = mysqli_stmt_get_result($stmt);      // 4. lê o resultado

$usuario = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);
mysqli_close($conexao);

// password_verify compara a senha digitada com o hash guardado.
// O hash não tem volta: não existe "descriptografar" — só comparar.
if ($usuario && password_verify($senha, $usuario['senha'])) {

    // Troca o número da sessão ao entrar. Uma linha que evita que
    // alguém reaproveite um número de sessão antigo.
    session_regenerate_id(true);

    // O que fica guardado enquanto a pessoa estiver logada.
    // O id é o mais importante: é ele que vai assinar cada sinal vital,
    // cada anotação e cada checagem de medicação.
    $_SESSION['usuario_id']       = $usuario['id'];
    $_SESSION['usuario_nome']     = $usuario['nome'];
    $_SESSION['usuario_perfil']   = $usuario['perfil'];

    // O MESMO perfil, guardado numa segunda chave — e as duas existem
    // por um motivo.
    //
    // O usuario_perfil pode ser EMPRESTADO: no modo construção o
    // administrador assume outro papel para testar, e aí esta chave
    // passa a valer 'medico' ou 'tecnico'. As 60 verificações de
    // permissão do sistema leem ela, então todas obedecem de uma vez.
    //
    // O usuario_perfil_real nunca muda. Vem da tabela usuarios, é
    // escrito aqui e em nenhum outro lugar, e é ele que responde "quem
    // esta pessoa é de verdade". É com ele que o papel_trocar.php
    // decide se pode emprestar o papel — se comparasse com o de cima,
    // quem assumisse 'tecnico' ficaria preso nesse papel para sempre.
    $_SESSION['usuario_perfil_real'] = $usuario['perfil'];
    $_SESSION['usuario_registro'] = $usuario['registro_profissional'];

    header('Location: painel.php');
    exit;
}

// Uma mensagem só para os dois casos — login errado e senha errada.
// Dizer "este usuário não existe" entregaria quais logins são válidos.
header('Location: index.php?erro=1');
exit;
