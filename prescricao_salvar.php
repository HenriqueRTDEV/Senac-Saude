<?php
// ===================================================================
//  prescricao_salvar.php  —  Grava a prescrição E GERA OS HORÁRIOS
//  Módulo 5 · Sprint 7 · perfil MÉDICO
//
//  O arquivo mais difícil do projeto, e vale entender por quê: ele
//  grava em DUAS tabelas. Uma linha em `prescricoes` — a ordem médica
//  — e várias em `administracoes`, uma para cada horário de cada dia,
//  todas nascendo com status `pendente`.
//
//  É a diferença entre a ORDEM e as EXECUÇÕES dela. O médico escreve
//  uma vez "8/8h"; o técnico vai checar três vezes por dia. A ordem
//  não se repete no banco; as execuções, sim.
//
//  PRESCRIÇÃO "SE NECESSÁRIO": horários em branco gera ZERO linhas em
//  `administracoes`, e isso é correto — não há hora marcada. O técnico
//  registra na tela do turno, no momento em que administrar.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));

require 'config/conexao.php';
require 'includes/prescricoes.php';
require 'includes/doses.php';

// -------------------------------------------------------------------
//  O que chegou
// -------------------------------------------------------------------
if (!isset($_POST['paciente_id'])) {
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

$paciente_id    = (int) $_POST['paciente_id'];
$medicamento_id = isset($_POST['medicamento_id']) ? (int) $_POST['medicamento_id'] : 0;
$dose           = isset($_POST['dose'])        ? trim($_POST['dose'])        : '';
$via            = isset($_POST['via'])         ? trim($_POST['via'])         : '';
$frequencia     = isset($_POST['frequencia'])  ? trim($_POST['frequencia'])  : '';
$horarios_texto = isset($_POST['horarios'])    ? trim($_POST['horarios'])    : '';
$data_inicio    = isset($_POST['data_inicio']) ? trim($_POST['data_inicio']) : '';
$data_fim       = isset($_POST['data_fim'])    ? trim($_POST['data_fim'])    : '';
$observacao     = isset($_POST['observacao'])  ? trim($_POST['observacao'])  : '';

// Devolve o médico ao formulário sem perder o que ele digitou.
function voltarAoFormulario($paciente_id, $dados, $erro)
{
    $partes = array('paciente_id=' . $paciente_id, 'erro=' . $erro);

    foreach ($dados as $campo => $valor) {
        if ($valor !== '' && $valor !== 0) {
            $partes[] = $campo . '=' . urlencode($valor);
        }
    }

    header('Location: prescricao_form.php?' . implode('&', $partes));
    exit;
}

$devolver = array(
    'medicamento_id' => $medicamento_id,
    'dose'           => $dose,
    'via'            => $via,
    'frequencia'     => $frequencia,
    'horarios'       => $horarios_texto,
    'data_inicio'    => $data_inicio,
    'data_fim'       => $data_fim,
    'observacao'     => $observacao
);

if ($paciente_id == 0) {
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente está internado AGORA?
// -------------------------------------------------------------------
// Traz também o id da INTERNAÇÃO em curso. A prescrição pertence a
// ela, não ao paciente em geral: uma ordem médica não sobrevive à
// alta. E o id vem do banco, nunca do formulário — se viesse de fora,
// daria para prescrever numa internação de outra pessoa.
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
    header('Location: prescricao_listar.php?erro=nao_internado');
    exit;
}

// -------------------------------------------------------------------
//  Os campos obrigatórios
// -------------------------------------------------------------------
if ($medicamento_id == 0 || $dose == '' || $via == '' || $frequencia == '') {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $devolver, 'campos');
}

// O medicamento existe e está ativo? (convenção 14)
$stmt = mysqli_prepare($conexao,
    "SELECT id FROM medicamentos WHERE id = ? AND ativo = 1");
mysqli_stmt_bind_param($stmt, 'i', $medicamento_id);
mysqli_stmt_execute($stmt);
$medicamento = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$medicamento) {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $devolver, 'medicamento');
}

// A via é uma das nove?
if (!viaExiste($via)) {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $devolver, 'via');
}

// -------------------------------------------------------------------
//  OS HORÁRIOS
//
//  separarHorarios() devolve false quando o texto está mal escrito, e
//  array vazio quando está em branco. São coisas diferentes: a
//  primeira é erro, a segunda é "se necessário".
//
//  Confundir as duas com um if ($horarios) daria erro em prescrição
//  válida — em PHP, array vazio é falso.
// -------------------------------------------------------------------
$horarios = separarHorarios($horarios_texto);

if ($horarios === false) {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $devolver, 'horarios');
}

// Guarda a versão normalizada: "6:00, 22:00" vira "06:00,22:00".
$horarios_gravar = (count($horarios) == 0 ? null : implode(',', $horarios));

// -------------------------------------------------------------------
//  AS DATAS
// -------------------------------------------------------------------
$partes = explode('-', $data_inicio);

if (count($partes) != 3
    || !checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
    mysqli_close($conexao);
    voltarAoFormulario($paciente_id, $devolver, 'data');
}

$data_fim_gravar = null;

if ($data_fim != '') {

    $pf = explode('-', $data_fim);

    if (count($pf) != 3 || !checkdate((int) $pf[1], (int) $pf[2], (int) $pf[0])) {
        mysqli_close($conexao);
        voltarAoFormulario($paciente_id, $devolver, 'data');
    }

    if ($data_fim < $data_inicio) {
        mysqli_close($conexao);
        voltarAoFormulario($paciente_id, $devolver, 'data_fim_antes');
    }

    $data_fim_gravar = $data_fim;
}

if ($observacao === '') {
    $observacao = null;
}

// -------------------------------------------------------------------
//  1. GRAVA A ORDEM MÉDICA
// -------------------------------------------------------------------
$quem_prescreveu = $_SESSION['usuario_id'];

$internacao_id = $paciente['internacao_id'];

$sql = "INSERT INTO prescricoes
          (paciente_id, internacao_id, usuario_id, medicamento_id, dose, via,
           frequencia, horarios, data_inicio, data_fim, observacao, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

// Agora são QUATRO inteiros e sete textos: 'iiii' + 'sssssss'.
// A quantidade de letras tem que bater exatamente com a de variáveis.
// Ao acrescentar uma coluna, é a primeira coisa a conferir — e é o
// tipo de defeito que o php -l não pega, porque só aparece quando a
// linha roda.
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'iiiisssssss',
    $paciente_id, $internacao_id, $quem_prescreveu, $medicamento_id, $dose, $via,
    $frequencia, $horarios_gravar, $data_inicio, $data_fim_gravar, $observacao);
mysqli_stmt_execute($stmt);

$prescricao_id = mysqli_insert_id($conexao);
mysqli_stmt_close($stmt);

// -------------------------------------------------------------------
//  2. GERA AS DOSES
//
//  O laço que faz isso mora no includes/doses.php, porque a MESMA
//  geração acontece em dois momentos: aqui, ao prescrever, e na tela do
//  turno, que completa o que falta enquanto o paciente segue internado.
//
//  Uma verdade, um lugar. Se a regra do primeiro dia mudar, muda lá.
//
//  A função lê a prescrição do banco em vez de receber os valores por
//  parâmetro. Parece rodeio — os valores estão logo acima — mas é o que
//  garante que os dois chamadores vejam exatamente o mesmo dado, e que
//  ela recuse sozinha prescrição suspensa ou paciente com alta.
// -------------------------------------------------------------------
$criados = gerarDosesFaltantes($conexao, $prescricao_id);

mysqli_close($conexao);

header('Location: prescricao_historico.php?paciente_id=' . $paciente_id
     . '&ok=lancada&criados=' . $criados);
exit;
