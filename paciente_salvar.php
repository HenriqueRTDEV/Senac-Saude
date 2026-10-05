<?php
// ===================================================================
//  paciente_salvar.php  —  Grava o cadastro do paciente
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Sem id  -> INSERT (pessoa nova)
//  Com id  -> UPDATE (correção de cadastro)
//
//  NÃO interna ninguém. Este arquivo mexe só na tabela `pacientes`,
//  que guarda quem a pessoa é. Colocar num leito é gravar em
//  `internacoes`, e isso acontece no internacao_admitir.php.
//
//  A permissão se repete aqui, no arquivo que GRAVA.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';
require 'includes/alergia.php';


function voltarComErro($erro, $id, $dados)
{
    $url = 'paciente_form.php?erro=' . $erro;

    if ($id > 0) {
        $url = $url . '&id=' . $id;
    }

    foreach ($dados as $campo => $valor) {
        $url = $url . '&' . $campo . '=' . urlencode($valor);
    }

    header('Location: ' . $url);
    exit;
}


if (!isset($_POST['nome'])) {
    header('Location: paciente_listar.php');
    exit;
}

$id          = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nome        = trim($_POST['nome']);
$nascimento  = trim($_POST['nascimento']);
$sexo        = $_POST['sexo'];
$cartao      = trim($_POST['cartao']);
$telefone    = trim($_POST['telefone']);
$endereco    = trim($_POST['endereco']);
$responsavel = trim($_POST['responsavel']);

// A alergia vem em DUAS partes: a resposta e, quando for o caso, o
// texto. A coluna do banco continua sendo uma só — ela é montada mais
// abaixo, depois de as duas partes serem conferidas.
$tem_alergia    = isset($_POST['tem_alergia']) ? $_POST['tem_alergia'] : '';
$alergias_texto = isset($_POST['alergias_texto']) ? trim($_POST['alergias_texto']) : '';

$editando = ($id > 0);

$digitado = array(
    'nome'        => $nome,
    'nascimento'  => $nascimento,
    'sexo'        => $sexo,
    'cartao'      => $cartao,
    'telefone'    => $telefone,
    'endereco'    => $endereco,
    'responsavel'    => $responsavel,
    'tem_alergia'    => $tem_alergia,
    'alergias_texto' => $alergias_texto
);


// ===================================================================
//  VALIDAÇÃO
//  O required do HTML ajuda quem usa a tela, mas não protege o
//  sistema: o formulário pode chegar sem passar por ela.
// ===================================================================

if ($nome == '' || $nascimento == '' || $sexo == '') {
    voltarComErro('campos', $id, $digitado);
}

// -------------------------------------------------------------------
//  A ALERGIA — três recusas, e só então o texto é montado
//
//  Antes, o campo era texto livre obrigatório, e a tela pedia que a
//  pessoa escrevesse a frase "Nega alergias" quando não houvesse
//  alergia. Quem escrevia "Nenhuma" não desobedecia: respondia a
//  pergunta que foi feita. E aparecia com tarja vermelha de alergia
//  em dezessete telas.
//
//  Agora não existe frase para interpretar na entrada.
// -------------------------------------------------------------------

// 1. Nenhuma das duas respostas. É a recusa mais importante: campo em
//    branco não distingue "não tem" de "ninguém perguntou", e essa
//    diferença é clínica.
if ($tem_alergia != 'nega' && $tem_alergia != 'tem') {
    voltarComErro('alergia_sem_resposta', $id, $digitado);
}

// 2. Disse que tem, e não disse qual.
if ($tem_alergia == 'tem' && $alergias_texto == '') {
    voltarComErro('alergia_sem_texto', $id, $digitado);
}

// 3. Disse que tem, e escreveu uma negação. A mesma função que decide
//    o alerta nas telas serve para pegar a contradição aqui — se ela
//    reconhece o texto como negação, as duas respostas não combinam.
if ($tem_alergia == 'tem' && negaAlergias($alergias_texto)) {
    voltarComErro('alergia_contraditoria', $id, $digitado);
}

// Agora sim: uma coluna, montada a partir de uma resposta sem ambiguidade.
if ($tem_alergia == 'nega') {
    $alergias = 'Nega alergias';
} else {
    $alergias = $alergias_texto;
}

if ($sexo != 'F' && $sexo != 'M' && $sexo != 'O') {
    voltarComErro('sexo_invalido', $id, $digitado);
}

// A data precisa ser uma data de verdade. checkdate() recusa
// 31 de fevereiro, por exemplo.
$partes = explode('-', $nascimento);   // formato aaaa-mm-dd

if (count($partes) != 3) {
    voltarComErro('data_invalida', $id, $digitado);
}

$ano = (int) $partes[0];
$mes = (int) $partes[1];
$dia = (int) $partes[2];

if (!checkdate($mes, $dia, $ano) || $ano < 1900) {
    voltarComErro('data_invalida', $id, $digitado);
}

// REGRA DE OURO Nº 7 — data futura não entra.
if ($nascimento > date('Y-m-d')) {
    voltarComErro('data_futura', $id, $digitado);
}


// ===================================================================
//  GRAVAÇÃO
// ===================================================================

if ($editando) {

    $sql = "UPDATE pacientes
            SET nome = ?, data_nascimento = ?, sexo = ?, cartao_sus = ?,
                telefone = ?, endereco = ?, responsavel = ?, alergias = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssssi',
        $nome, $nascimento, $sexo, $cartao, $telefone,
        $endereco, $responsavel, $alergias, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $aviso = 'atualizado';

} else {

    // ------------------------------------------------------------
    //  CADASTRO NOVO
    //
    //  `cadastrado_por` sai da sessão, nunca do formulário. Regra
    //  de Ouro nº 2: todo registro tem autor, e o autor não se
    //  digita.
    //
    //  Repare que o paciente nasce SEM internação. Ele existe no
    //  sistema, mas não está em leito nenhum — e é assim que deve
    //  ser: cadastrar não é internar.
    // ------------------------------------------------------------
    $quem_cadastrou = $_SESSION['usuario_id'];

    $sql = "INSERT INTO pacientes
              (nome, data_nascimento, sexo, cartao_sus, telefone,
               endereco, responsavel, alergias, cadastrado_por, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssssi',
        $nome, $nascimento, $sexo, $cartao, $telefone,
        $endereco, $responsavel, $alergias, $quem_cadastrou);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $aviso = 'cadastrado';
}

mysqli_close($conexao);

header('Location: paciente_listar.php?ok=' . $aviso);
exit;
