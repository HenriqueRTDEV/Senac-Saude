<?php

require 'config/conexao.php';

// Passo 1 - Identificar o paciente

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: paciente_listar2.php?erro=nao_encontrado');
    exit;
}

// Passo 2 - Descobrir como o cadastro está

$sql = "SELECT ativo FROM pacientes WHERE id = ?";

$stmt = mysqli_prepare($conexao, $sql);

if (!$stmt) {
    mysqli_close($conexao);
    header('Location: paciente_listar2.php?erro=erro_banco');
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $id);

// Executa a consulta
mysqli_stmt_execute($stmt);

// Obtém o resultado
$resultado = mysqli_stmt_get_result($stmt);

$paciente = mysqli_fetch_assoc($resultado);

// Fecha o statement
mysqli_stmt_close($stmt);

// Verifica se o paciente existe
if (!$paciente) {
    mysqli_close($conexao);

    header('Location: paciente_listar2.php?erro=nao_encontrado');
    exit;
}

// Passo 3 - Inverter o status do paciente

$novo_valor = ($paciente['ativo'] == 1) ? 0 : 1;

// Passo 4 - Atualizar o paciente

$sql = "UPDATE pacientes SET ativo = ? WHERE id = ?";

$stmt = mysqli_prepare($conexao, $sql);

if (!$stmt) {
    mysqli_close($conexao);

    header('Location: paciente_listar2.php?erro=erro_banco');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $novo_valor, $id);

// Executa a atualização
mysqli_stmt_execute($stmt);

// Fecha o statement
mysqli_stmt_close($stmt);

// Fecha a conexão
mysqli_close($conexao);

// Passo 5 - Criar mensagem para a página de listagem

if ($novo_valor == 0) {
    $aviso = 'inativo';
} else {
    $aviso = 'reativado';
}

// Volta para a lista de pacientes
header('Location: paciente_listar2.php?ok=' . $aviso);
exit;

?>
