<?php
// ===================================================================
//  leito_salvar.php  —  Grava o leito
//  Módulo 2 · Sprint 3 · perfil recepção
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';

function voltarComErro($erro, $id, $identificacao, $setor_id, $observacao)
{
    $url = 'leito_form.php?erro=' . $erro;
    if ($id > 0) { $url = $url . '&id=' . $id; }
    $url = $url . '&identificacao=' . urlencode($identificacao);
    $url = $url . '&setor_id='      . $setor_id;
    $url = $url . '&observacao='    . urlencode($observacao);
    header('Location: ' . $url);
    exit;
}

if (!isset($_POST['identificacao'])) {
    header('Location: leito_listar.php');
    exit;
}

$id            = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$identificacao = trim($_POST['identificacao']);
$setor_id      = isset($_POST['setor_id']) ? (int) $_POST['setor_id'] : 0;
$observacao    = trim($_POST['observacao']);

$editando = ($id > 0);

if ($identificacao == '' || $setor_id == 0) {
    voltarComErro('campos', $id, $identificacao, $setor_id, $observacao);
}

// O setor veio de um <select>, mas nada impede alguém de enviar um
// id inventado. Confere se existe e está ativo.
$stmt = mysqli_prepare($conexao, "SELECT id FROM setores WHERE id = ? AND ativo = 1");
mysqli_stmt_bind_param($stmt, 'i', $setor_id);
mysqli_stmt_execute($stmt);
$existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$existe) {
    voltarComErro('setor_invalido', $id, $identificacao, $setor_id, $observacao);
}

// -------------------------------------------------------------------
//  A identificação não pode repetir.
//  A coluna é UNIQUE no banco, então ele recusaria de qualquer jeito.
//  Conferir antes só troca uma mensagem técnica por uma clara.
//  Na edição, o próprio leito não conta: por isso o "AND id <> ?".
// -------------------------------------------------------------------
$sql  = "SELECT id FROM leitos WHERE identificacao = ? AND id <> ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'si', $identificacao, $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$repetido  = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if ($repetido) {
    voltarComErro('repetido', $id, $identificacao, $setor_id, $observacao);
}

if ($editando) {

    $sql  = "UPDATE leitos SET identificacao = ?, setor_id = ?, observacao = ? WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'sisi', $identificacao, $setor_id, $observacao, $id);
    $aviso = 'atualizado';

} else {

    $sql  = "INSERT INTO leitos (identificacao, setor_id, observacao, ativo) VALUES (?, ?, ?, 1)";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'sis', $identificacao, $setor_id, $observacao);
    $aviso = 'criado';
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conexao);

header('Location: leito_listar.php?ok=' . $aviso);
exit;
