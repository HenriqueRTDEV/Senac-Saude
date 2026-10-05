<?php
require 'config/conexao.php';

// Passo 0 - Conferir se o formulário chegou
// se alguem digitar paciente_salvar2.php direto na barra de endereço
// mandar de volta para a lista

if(!isset($_POST['nome'])){
    header('Location: paciente_listar2.php');
    exit;
}

// Passo 1 - Pegar o que veio do formulário

$nome                 = trim($_POST['nome']);
$nascimento           = trim($_POST['nascimento']);
$sexo                 = $_POST['sexo'];
$telefone             = trim($_POST['telefone']);

// o id só existe quando foi uma edição - é o campo escondido
// do formulário. se não veio, vale 0 e o 0 quer dizer registro novo

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;


// Passo 2 - Conferir antes de gravar

if($nome == '' || $nascimento == '' || $sexo == ''){
    header('Location: paciente_form2.php?erro=campos');
}

// Passo 3 - Decidir: Insert ou Update?
if($id > 0){
    $sql = "UPDATE pacientes
    SET nome = ?, data_nascimento = ?, sexo = ?, telefone = ?
    WHERE id = ?";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssi', $nome, $nascimento, $sexo, $telefone, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $aviso = 'atualizado';

} else{

    $sql = "INSERT INTO pacientes (nome, data_nascimento, sexo, telefone, ativo)
    VALUES (?,?,?,?,1)";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ssss', $nome, $nascimento, $sexo, $telefone);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $aviso = 'cadastrado';
}

// Passo 4 - Fechar e mandar de volta para a lista

mysqli_close($conexao);

header('Location: paciente_listar2.php?ok='. $aviso);
exit;




?>