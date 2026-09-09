<?php
// ===================================================================
//  usuario_salvar.php  —  Grava o formulário no banco
//  Módulo 1 · Sprint 2 · perfil administrador
//
//  É o "C" e o "U" do CRUD. Esta página não desenha nada: recebe o
//  POST, confere, grava e manda o navegador para outro lugar.
//
//  Sem id  -> INSERT (usuário novo)
//  Com id  -> UPDATE (usuário que já existe)
//
//  ATENÇÃO — a verificação de permissão se repete AQUI.
//  É neste arquivo que o estrago aconteceria: quem conseguisse enviar
//  um formulário direto para cá criaria usuários à vontade, mesmo sem
//  nunca ter aberto a tela. Esconder o botão não protege nada.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'includes/perfis.php';
require 'config/conexao.php';


// -------------------------------------------------------------------
//  Manda de volta ao formulário levando o erro e o que foi digitado,
//  para a pessoa não ter que preencher tudo outra vez.
//  A senha NUNCA vai junto — senha não anda na barra de endereço.
// -------------------------------------------------------------------
function voltarComErro($erro, $id, $nome, $login, $registro, $perfil)
{
    $url = 'usuario_form.php?erro=' . $erro;

    if ($id > 0) {
        $url = $url . '&id=' . $id;
    }

    // urlencode troca espaços e acentos por um código que a URL aceita.
    $url = $url . '&nome='     . urlencode($nome);
    $url = $url . '&login='    . urlencode($login);
    $url = $url . '&registro=' . urlencode($registro);
    $url = $url . '&perfil='   . urlencode($perfil);

    header('Location: ' . $url);
    exit;
}


// Ninguém deve abrir este arquivo digitando o endereço no navegador.
if (!isset($_POST['nome'])) {
    header('Location: usuario_listar.php');
    exit;
}

$id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nome     = trim($_POST['nome']);
$login    = trim($_POST['login']);
$registro = trim($_POST['registro']);
$perfil   = $_POST['perfil'];
$senha    = $_POST['senha'];

$editando = ($id > 0);


// ===================================================================
//  VALIDAÇÃO
//  O required do HTML ajuda o usuário distraído, mas não protege o
//  sistema: qualquer um envia um formulário sem passar pela tela.
//  A validação que vale é esta, no PHP.
// ===================================================================

if ($nome == '' || $login == '') {
    voltarComErro('campos', $id, $nome, $login, $registro, $perfil);
}

// Nunca confie no que chega do navegador: o select tinha quatro
// opções, mas nada impede alguém de mandar uma quinta.
if (!perfilExiste($perfil)) {
    voltarComErro('perfil_invalido', $id, $nome, $login, $registro, $perfil);
}

// Senha: obrigatória ao cadastrar; ao editar, em branco significa
// "não quero trocar".
$trocar_senha = ($senha != '');

if (!$editando && $senha == '') {
    voltarComErro('campos', $id, $nome, $login, $registro, $perfil);
}

if ($trocar_senha && strlen($senha) < 4) {
    voltarComErro('senha_curta', $id, $nome, $login, $registro, $perfil);
}


// ===================================================================
//  O LOGIN NÃO PODE REPETIR
//  A coluna é UNIQUE no banco, então ele recusaria de qualquer jeito.
//  Mas o erro do banco é feio e técnico. Conferir antes deixa a
//  mensagem clara para quem está usando o sistema.
//
//  Ao editar, o próprio usuário não conta: ele pode manter o login
//  dele. Por isso o "AND id <> ?".
// ===================================================================
$sql  = "SELECT id FROM usuarios WHERE login = ? AND id <> ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'si', $login, $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$repetido  = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if ($repetido) {
    voltarComErro('login_repetido', $id, $nome, $login, $registro, $perfil);
}


// ===================================================================
//  GRAVAÇÃO
// ===================================================================

if ($editando) {

    // ------------------------------------------------------------
    //  UPDATE — dois caminhos, conforme trocou a senha ou não
    // ------------------------------------------------------------
    if ($trocar_senha) {

        // password_hash embaralha a senha de um jeito sem volta.
        // O banco nunca vê o texto digitado.
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "UPDATE usuarios
                SET nome = ?, login = ?, perfil = ?,
                    registro_profissional = ?, senha = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssi',
            $nome, $login, $perfil, $registro, $hash, $id);

        $aviso = 'senha';

    } else {

        $sql = "UPDATE usuarios
                SET nome = ?, login = ?, perfil = ?,
                    registro_profissional = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssi',
            $nome, $login, $perfil, $registro, $id);

        $aviso = 'atualizado';
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Se o administrador editou o próprio nome, a barra de cima
    // continuaria mostrando o nome antigo até ele sair e entrar de
    // novo. Atualizamos a sessão junto.
    if ($id == $_SESSION['usuario_id']) {
        $_SESSION['usuario_nome']     = $nome;
        $_SESSION['usuario_perfil']   = $perfil;
        $_SESSION['usuario_registro'] = $registro;

        // As duas chaves de perfil andam juntas AQUI, e só aqui.
        //
        // Este é o único lugar do sistema onde o perfil de alguém muda
        // de verdade. O outro lugar que escreve usuario_perfil — o
        // papel_trocar.php — está apenas emprestando um papel.
        //
        // Sem esta linha, o administrador que editasse a si mesmo
        // acabaria com as duas chaves discordando, e o empréstimo de
        // papel terminaria em silêncio no meio de um teste.
        $_SESSION['usuario_perfil_real'] = $perfil;
    }

} else {

    // ------------------------------------------------------------
    //  INSERT — usuário novo, sempre nasce ativo
    // ------------------------------------------------------------
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios
              (nome, login, senha, registro_profissional, perfil, ativo)
            VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'sssss',
        $nome, $login, $hash, $registro, $perfil);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $aviso = 'criado';
}

mysqli_close($conexao);

header('Location: usuario_listar.php?ok=' . $aviso);
exit;
