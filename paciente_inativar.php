<?php
// ===================================================================
//  paciente_inativar.php  —  Liga e desliga um cadastro de paciente
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Mesma ideia do usuario_desativar.php, e pelo mesmo motivo: aqui
//  também não existe DELETE.
//
//  No caso do paciente a razão é ainda mais forte. O cadastro está
//  amarrado a sinais vitais, anotações de enfermagem, evoluções
//  médicas e checagens de medicação. Apagar o paciente apagaria o
//  prontuário — e prontuário é documento legal.
//
//  Use isto para cadastro duplicado ou aberto por engano. NÃO use
//  para dar alta: alta é ação do médico e mexe em outra coluna.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: paciente_listar.php?erro=nao_encontrado');
    exit;
}

// Descobre a situação atual para saber para que lado virar a chave.
$sql  = "SELECT ativo FROM pacientes WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$paciente  = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if(!$paciente){
    header('Location: paciente_listar.php?erro=nao_encontrado');
}

// -------------------------------------------------------------------
//  REGRA: não se inativa o cadastro de quem está internado.
//  A pessoa está deitada num leito neste momento — sumir com o
//  cadastro dela deixaria o leito ocupado por um fantasma.
//  Primeiro dá-se alta (ação do médico), depois inativa-se.
// -------------------------------------------------------------------
if ($paciente['ativo']) {

    $sql  = "SELECT id FROM internacoes
             WHERE paciente_id = ? AND situacao = 'internado' AND ativo = 1";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $internado = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    
}

$novo_valor = ($paciente['ativo'] ? 0 : 1);

$sql  = "UPDATE pacientes SET ativo = ? WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $novo_valor, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

$aviso = ($novo_valor == 0 ? 'inativado' : 'reativado');

header('Location: paciente_listar.php?ok=' . $aviso);
exit;
