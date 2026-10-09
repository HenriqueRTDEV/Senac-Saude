<?php

require 'includes/protege.php';
require 'includes/permissoes.php';
exigirPerfil (array('medico'));

require 'config/conexao.php';

if (!isset($_POST['id_internacao'])) {
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$internacao_id = $_POST['id_internacao'];

if($internacao_id == ''){
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}
$sql = "SELECT id, cid_id, situacao FROM internacoes WHERE id =? AND ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, "i", $internacao_id);
msqli_stmt_execute($stmt);

$internacao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$internacao){
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

if($internacao['situacao'] != 'alta'){
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=ja_teve_alta');
    exit;
}
if($internacao['cid_id'] == null){
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=sem_cid');
    exit;
}

$sql = " update internacoes set situacao = 'alta,
 data_alta = now(),
 alta_usuario_id = ?,
 where id = ? and situacao = 'internado' and ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, "ii", $alta_usuario_id, $internacao_id);
msqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);