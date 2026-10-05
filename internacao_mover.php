<?php
// ===================================================================
//  internacao_mover.php  —  Troca o paciente de leito
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Muda uma coluna só: internacoes.leito_id.
//  O paciente continua internado, com a mesma data de admissão —
//  mudou de cama, não de internação.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';

if (!isset($_POST['internacao_id']) || !isset($_POST['leito_id'])) {
    header('Location: internacao_movimentar.php');
    exit;
}

$internacao_id = (int) $_POST['internacao_id'];
$leito_id      = (int) $_POST['leito_id'];

if ($internacao_id == 0 || $leito_id == 0) {
    header('Location: internacao_movimentar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  A internação existe e está mesmo em andamento?
// -------------------------------------------------------------------
$sql  = "SELECT id FROM internacoes
        WHERE leito_id = ? AND situacao = 'internado' AND ativo = 1";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$internacao = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$internacao) {
    header('Location: internacao_movimentar.php?erro=nao_encontrado');
    exit;
}

if ($internacao['leito_id'] == $leito_id) {
    header('Location: internacao_movimentar.php?erro=mesmo_leito');
    exit;
}

// -------------------------------------------------------------------
//  O leito de destino existe e está ativo?
// -------------------------------------------------------------------
$sql  = "UPDATE internacoes SET leito_id = ? WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $leito_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$leito = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$leito) {
    header('Location: internacao_movimentar.php?erro=leito_invalido');
    exit;
}

// -------------------------------------------------------------------
//  UM LEITO, UM PACIENTE.
//
//  A tela só ofereceu leitos livres — mas entre o momento em que a
//  página foi carregada e o clique no botão, outra pessoa da
//  recepção pode ter ocupado esse leito. Por isso confere de novo,
//  agora.
//
//  É a mesma lição do "esconder o botão não é segurança", em outra
//  forma: o que a tela mostrou é uma fotografia do passado.
// -------------------------------------------------------------------
$sql  = "";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $leito_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$ocupado = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($ocupado) {
    header('Location: internacao_movimentar.php?erro=leito_ocupado');
    exit;
}

// -------------------------------------------------------------------
//  A troca
// -------------------------------------------------------------------
$sql  = "";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $leito_id, $internacao_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

header('Location: internacao_movimentar.php?ok=movido');
exit;
