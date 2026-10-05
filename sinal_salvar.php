<?php
// ===================================================================
//  sinal_salvar.php  —  Grava uma aferição de sinais vitais
//  Módulo 3 · Sprint 5 · perfil TÉCNICO
//
//  Este arquivo mostra a diferença entre as duas conferências que o
//  faixas.php guarda, e vale a aula:
//
//    estaAlterado()  -> temperatura 39 °C: GRAVA e mostra em vermelho.
//                       Febre é dado verdadeiro, não erro.
//
//    ehPossivel()    -> temperatura 390 °C: RECUSA. Não é febre, é o
//                       dedo que escorregou no teclado.
//
//  Confundir as duas é o erro clássico aqui: um sistema que recusa
//  valor alterado obriga a enfermagem a não registrar a piora do
//  paciente, que é exatamente a informação mais importante.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('tecnico'));

require 'config/conexao.php';
require 'includes/faixas.php';

// -------------------------------------------------------------------
//  Só por POST, e com paciente.
// -------------------------------------------------------------------
if (!isset($_POST['paciente_id'])) {
    header('Location: sinal_listar.php?erro=nao_encontrado');
    exit;
}

$paciente_id = (int) $_POST['paciente_id'];

if ($paciente_id == 0) {
    header('Location: sinal_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente está internado AGORA?
//
//  A tela conferiu quando abriu, mas isso foi uma fotografia do
//  passado: entre abrir o formulário e enviar, o médico pode ter dado
//  alta. Pergunta-se de novo, no momento de gravar.
// -------------------------------------------------------------------
// Traz também o id da INTERNAÇÃO. A aferição pertence à estadia, não
// ao paciente em geral — é o que permite a ficha separar duas
// internações do mesmo paciente. E o id vem do banco, nunca do
// formulário.
$sql = "SELECT p.id, i.id AS internacao_id
        FROM pacientes p
        JOIN internacoes i ON i.paciente_id = p.id
                          AND i.situacao = 'internado'
                          AND i.ativo = 1
        WHERE p.id = ? AND p.ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$paciente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$paciente) {
    mysqli_close($conexao);
    header('Location: sinal_listar.php?erro=nao_internado');
    exit;
}

// -------------------------------------------------------------------
//  Recolhe os valores.
//
//  Campo em branco vira null, não zero. A diferença é clínica: zero é
//  uma medida, null é "não foi medido". Uma saturação de 0% seria uma
//  emergência; um campo vazio é uma linha que ninguém preencheu.
// -------------------------------------------------------------------
$valores    = array();
$preencheu  = false;
$impossivel = false;

foreach (array_keys(listaDeSinais()) as $campo) {

    $bruto = isset($_POST[$campo]) ? trim($_POST[$campo]) : '';

    if ($bruto === '') {
        $valores[$campo] = null;
        continue;
    }

    // Vírgula é o separador que a turma digita; o banco quer ponto.
    $bruto = str_replace(',', '.', $bruto);

    if (!is_numeric($bruto)) {
        $impossivel = true;
        $valores[$campo] = null;
        continue;
    }

    $numero = $bruto + 0;

    if (!ehPossivel($campo, $numero)) {
        $impossivel = true;
    }

    $valores[$campo] = $numero;
    $preencheu = true;
}

$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : '';
if ($observacao === '') {
    $observacao = null;
}

// -------------------------------------------------------------------
//  Devolve o técnico ao formulário sem perder o que ele digitou.
//  Os valores voltam pela URL, e o sinal_form.php os relê.
// -------------------------------------------------------------------
function voltarAoFormulario($paciente_id, $valores, $observacao, $erro)
{
    $partes = array('paciente_id=' . $paciente_id, 'erro=' . $erro);

    foreach ($valores as $campo => $valor) {
        if ($valor !== null) {
            $partes[] = $campo . '=' . urlencode($valor);
        }
    }

    if ($observacao !== null) {
        $partes[] = 'observacao=' . urlencode($observacao);
    }

    header('Location: sinal_form.php?' . implode('&', $partes));
    exit;
}

// -------------------------------------------------------------------
//  REGRA: valor impossível, ou que não é número, é erro de digitação.
//
//  A ORDEM DESTAS DUAS CONFERÊNCIAS IMPORTA, e é fácil errar.
//  Quem digitou "abc" não deixou o formulário vazio — digitou algo
//  inválido. Se a conferência de vazio viesse primeiro, o técnico
//  receberia "preencha ao menos um sinal vital" olhando para um campo
//  com "abc" escrito nele, e não entenderia a queixa.
//
//  Regra geral: a mensagem de erro tem que descrever o que a pessoa
//  fez, não o que sobrou depois que o sistema descartou o que ela fez.
// -------------------------------------------------------------------
if ($impossivel) {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $valores, $observacao, 'impossivel');
}

// -------------------------------------------------------------------
//  REGRA: uma aferição precisa aferir alguma coisa.
// -------------------------------------------------------------------
if (!$preencheu) {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $valores, $observacao, 'vazio');
}

// -------------------------------------------------------------------
//  REGRA: sistólica maior que diastólica.
//
//  Cada uma sozinha pode estar perfeita — 80 e 120 são valores normais
//  — e ainda assim o par estar trocado de lugar. Só se descobre
//  comparando os dois campos, e é por isso que esta conferência não
//  cabe no faixas.php.
// -------------------------------------------------------------------
if ($valores['pa_sistolica'] !== null && $valores['pa_diastolica'] !== null) {
    if ($valores['pa_sistolica'] <= $valores['pa_diastolica']) {
        mysqli_close($conexao);
        voltarAoFormulario($paciente_id, $valores, $observacao, 'pa_invertida');
    }
}

// -------------------------------------------------------------------
//  Grava.
//
//  data_hora vem de NOW() e usuario_id vem da sessão: nenhum dos dois
//  passa pelo formulário. Não existe como registrar aferição no nome
//  de outra pessoa nem em outro horário.
//
//  CONFIRA AS TRÊS CONTAGENS, e não duas: interrogações da consulta,
//  letras do bind e variáveis passadas. Aqui são 12 de cada. O 'd' é
//  a temperatura, que tem casa decimal; os outros são inteiros.
// -------------------------------------------------------------------
$quem_aferiu   = $_SESSION['usuario_id'];
$internacao_id = $paciente['internacao_id'];

$sql = "INSERT INTO sinais_vitais
          (paciente_id, internacao_id, usuario_id, data_hora,
           pa_sistolica, pa_diastolica,
           frequencia_cardiaca, frequencia_respiratoria,
           temperatura, saturacao, glicemia, escala_dor, observacao)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexao, $sql);

// Uma letra a mais no começo: com o internacao_id são QUATRO inteiros
// antes da temperatura. Ao acrescentar coluna, conferir a contagem de
// letras é a primeira coisa a fazer — o php -l não pega essa diferença,
// porque a sintaxe continua perfeita.
mysqli_stmt_bind_param($stmt, 'iiiiiiidiiis',
    $paciente_id,
    $internacao_id,
    $quem_aferiu,
    $valores['pa_sistolica'],
    $valores['pa_diastolica'],
    $valores['frequencia_cardiaca'],
    $valores['frequencia_respiratoria'],
    $valores['temperatura'],
    $valores['saturacao'],
    $valores['glicemia'],
    $valores['escala_dor'],
    $observacao
);

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

header('Location: sinal_historico.php?paciente_id=' . $paciente_id . '&ok=registrado');
exit;
