<?php
// ===================================================================
//  prescricao_suspender.php  —  Suspende uma prescrição
//  Módulo 5 · Sprint 7 · perfil MÉDICO
//
//  Suspender é `ativo = 0`, como em todo o resto do sistema. A
//  prescrição continua no prontuário, com as doses que foram dadas
//  enquanto ela valia.
//
//  E ela leva consigo as doses PENDENTES: uma dose pendente é uma
//  ordem esperando execução, e ordem suspensa não deve ser executada.
//  Elas são apagadas de `administracoes`, e este é o ÚNICO DELETE do
//  sistema — vale entender por que ele é legítimo aqui:
//
//    · dose pendente nunca foi executada. Não é registro de um ato,
//      é um lembrete de algo a fazer
//    · dose administrada ou não administrada JAMAIS é tocada. Aquilo
//      aconteceu, tem hora e tem autor, e fica para sempre
//
//  A diferença é entre apagar uma tarefa da lista e apagar a história.
//  A primeira é limpeza; a segunda é falsificação.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));

require 'config/conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  A prescrição existe e está ativa?
// -------------------------------------------------------------------
$sql = "SELECT id, paciente_id, ativo FROM prescricoes WHERE id = ?";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$prescricao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$prescricao) {
    mysqli_close($conexao);
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

$paciente_id = $prescricao['paciente_id'];

if (!$prescricao['ativo']) {
    // Já estava suspensa: nada a fazer, e nada de errado.
    mysqli_close($conexao);
    header('Location: prescricao_historico.php?paciente_id=' . $paciente_id);
    exit;
}

// -------------------------------------------------------------------
//  1. Suspende a ordem
// -------------------------------------------------------------------
$stmt = mysqli_prepare($conexao,
    "UPDATE prescricoes SET ativo = 0 WHERE id = ? AND ativo = 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// -------------------------------------------------------------------
//  2. Retira as doses que ainda não foram executadas
//
//  O "AND status = 'pendente'" não é enfeite: é ele que garante que
//  nenhuma dose já checada seja alcançada por este DELETE.
// -------------------------------------------------------------------
$stmt = mysqli_prepare($conexao,
    "DELETE FROM administracoes
      WHERE prescricao_id = ? AND status = 'pendente'");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

header('Location: prescricao_historico.php?paciente_id=' . $paciente_id . '&ok=suspensa');
exit;
