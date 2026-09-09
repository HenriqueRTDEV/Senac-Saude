<?php
// ===================================================================
//  usuario_desativar.php  —  Liga e desliga o acesso de um usuário
//  Módulo 1 · Sprint 2 · perfil administrador
//
//  Este arquivo ocupa o lugar do "excluir_usuario.php" que existiria
//  num CRUD comum. NÃO existe DELETE neste sistema.
//
//  Duas razões, e as duas valem a aula:
//
//  1. LEGAL — prontuário é documento. Cada usuário assina sinais
//     vitais, anotações e checagens de medicação. Apagá-lo deixaria
//     registro clínico sem autor.
//
//  2. TÉCNICA — o banco nem deixaria. As chaves estrangeiras barram:
//     "Cannot delete or update a parent row: a foreign key constraint
//     fails". Teste no MySQL Workbench para ver acontecer.
//
//  Então o botão "Excluir" vira "Desativar", e isso é um UPDATE.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));
require 'includes/perfis.php';
require 'config/conexao.php';

// (int) força virar número. Se vier lixo na URL, vira 0
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if($id == 0){
    header('Location: usuario_listar.php?erro=nao_encontrado');
    exit;
}


// -------------------------------------------------------------------
//  REGRA: ninguém desativa a si mesmo.
//  Sem isto, o administrador se tranca do lado de fora e só volta
//  mexendo direto no banco.
//
//  O botão já vem desabilitado na tela — mas botão desabilitado é
//  enfeite: basta digitar o endereço com o id certo. A verificação
//  que vale é esta.
// -------------------------------------------------------------------
if($id == $_SESSION['usuario_id']){
    header('Location: usuario_listar.php?erro=auto_desativar');
    exit;
}

// Descobre a situação atual para saber para que lado virar a chave.
$sql = "SELECT ativo FROM usuarios WHERE = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if(!$usuario){
    header('Location: usuario_listar.php?erro=nao_encontrado');
    exit;
}

// Estava ativo? Vira inativo. Estava inativo? Vira ativo.
$novo_valor = ($usuario['ativo'] ? 0 : 1);

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $novo_valor, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

$aviso = ($novo_valor == 0 ? 'desativado' : 'reativado');

header('Location: usuario_listar.php?ok=' . $aviso);
exit;


