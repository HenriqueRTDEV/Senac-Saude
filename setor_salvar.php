<?php
// ===================================================================
//  setor_salvar.php  —  Grava o setor
//  Módulo 2 · Sprint 3 · perfil ADMINISTRADOR
//
//  A permissão se repete aqui, no arquivo que GRAVA.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'config/conexao.php';

function voltarComErro($erro, $id, $nome, $descricao)
{
    $url = 'setor_form.php?erro=' . $erro;
    if ($id > 0) { $url = $url . '&id=' . $id; }
    $url = $url . '&nome='      . urlencode($nome);
    $url = $url . '&descricao=' . urlencode($descricao);
    header('Location: ' . $url);
    exit;
}

if (!isset($_POST['nome'])) {
    header('Location: setor_listar.php');
    exit;
}

$id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nome      = trim($_POST['nome']);
$descricao = trim($_POST['descricao']);

$editando = ($id > 0);

if ($nome == '') {
    voltarComErro('campos', $id, $nome, $descricao);
}

// O nome é UNIQUE no banco. Conferir antes só troca a mensagem
// técnica por uma clara. Na edição, o próprio setor não conta.
$stmt = mysqli_prepare($conexao, "SELECT id FROM setores WHERE nome = ? AND id <> ?");
mysqli_stmt_bind_param($stmt, 'si', $nome, $id);
mysqli_stmt_execute($stmt);
$repetido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($repetido) {
    voltarComErro('repetido', $id, $nome, $descricao);
}

if ($editando) {

    $sql  = "UPDATE setores SET nome = ?, descricao = ? WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ssi', $nome, $descricao, $id);
    $aviso = 'atualizado';

} else {

    $sql  = "INSERT INTO setores (nome, descricao, ativo) VALUES (?, ?, 1)";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $nome, $descricao);
    $aviso = 'criado';
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conexao);

header('Location: setor_listar.php?ok=' . $aviso);
exit;
