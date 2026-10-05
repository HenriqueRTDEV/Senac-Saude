<?php
// ===================================================================
//  setor_inativar.php  —  Tira e devolve um setor de circulação
//  Módulo 2 · Sprint 3 · perfil ADMINISTRADOR
//
//  Um setor inativado some da lista de opções ao cadastrar leito,
//  mas continua existindo — e os leitos que já pertencem a ele
//  continuam apontando para ele. Aqui também não existe DELETE.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'config/conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: setor_listar.php?erro=nao_encontrado');
    exit;
}

$stmt = mysqli_prepare($conexao, "SELECT ativo FROM setores WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$setor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$setor) {
    header('Location: setor_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  REGRA: setor com leito ativo não se inativa.
//  Senão a recepção ficaria com leitos órfãos, apontando para uma
//  ala que o sistema considera fora de uso. Primeiro se resolve o
//  destino dos leitos, depois se fecha a ala.
// -------------------------------------------------------------------
if ($setor['ativo']) {

    $stmt = mysqli_prepare($conexao,
        "SELECT id FROM leitos WHERE setor_id = ? AND ativo = 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $temLeito = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($temLeito) {
        mysqli_close($conexao);
        header('Location: setor_listar.php?erro=tem_leito');
        exit;
    }
}

$novo_valor = ($setor['ativo'] ? 0 : 1);

$stmt = mysqli_prepare($conexao, "UPDATE setores SET ativo = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $novo_valor, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

$aviso = ($novo_valor == 0 ? 'inativado' : 'reativado');

header('Location: setor_listar.php?ok=' . $aviso);
exit;
