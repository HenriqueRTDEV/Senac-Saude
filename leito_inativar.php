<?php
// ===================================================================
//  leito_inativar.php  —  Tira e devolve um leito de circulação
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Serve para leito em reforma, quebrado ou desativado.
//  Ele some da lista de leitos livres, mas continua existindo — e as
//  internações antigas que aconteceram nele continuam apontando para
//  ele. Aqui também não existe DELETE.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: leito_listar.php?erro=nao_encontrado');
    exit;
}

$sql  = "SELECT ativo FROM leitos WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$leito     = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$leito) {
    header('Location: leito_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  REGRA: leito ocupado não se inativa.
//  Há uma pessoa deitada nele agora. Some com o leito e o paciente
//  fica sem lugar.
//
//  Na tela o botão já vem desabilitado — mas botão desabilitado é
//  enfeite: basta digitar o endereço. A verificação que vale é esta.
// -------------------------------------------------------------------
if ($leito['ativo']) {

    $sql  = "SELECT id FROM internacoes
             WHERE leito_id = ? AND situacao = 'internado' AND ativo = 1";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ocupado = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($ocupado) {
        mysqli_close($conexao);
        header('Location: leito_listar.php?erro=ocupado');
        exit;
    }
}

$novo_valor = ($leito['ativo'] ? 0 : 1);

$sql  = "UPDATE leitos SET ativo = ? WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $novo_valor, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

$aviso = ($novo_valor == 0 ? 'inativado' : 'reativado');

header('Location: leito_listar.php?ok=' . $aviso);
exit;
