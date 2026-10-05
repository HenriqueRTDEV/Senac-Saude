<?php
// ===================================================================
//  registro_salvar.php  —  Grava um registro no prontuário
//  Módulo 4 · Sprint 6 · perfis MÉDICO e TÉCNICO
//
//  O arquivo mais curto do módulo, e o que carrega a regra mais séria
//  do sistema inteiro.
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  NÃO EXISTE registro_editar.php.                             │
//  │  NÃO EXISTE registro_apagar.php.                             │
//  │  NÃO EXISTE nenhum UPDATE nem DELETE na tabela `registros`.   │
//  └──────────────────────────────────────────────────────────────┘
//
//  Isso não é uma tela que faltou fazer: é a regra. Registro clínico
//  assinado não se altera. Se a pessoa errou, ela escreve um registro
//  NOVO corrigindo o anterior — e os dois ficam, com hora e autor de
//  cada um.
//
//  É exatamente assim no prontuário de papel, onde não se usa corretivo
//  nem se arranca folha, e é assim no prontuário eletrônico de verdade.
//  A auditoria de um caso clínico precisa poder ler o que se pensava em
//  cada momento, não só a conclusão final.
//
//  O TIPO NÃO VEM DO FORMULÁRIO. Ele vem do perfil da sessão. Um
//  técnico não consegue gravar uma evolução médica nem trocando o que
//  é enviado, porque não existe campo de tipo para trocar.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/registros.php';

// -------------------------------------------------------------------
//  Só por POST, e com paciente.
// -------------------------------------------------------------------
if (!isset($_POST['paciente_id']) || !isset($_POST['texto'])) {
    header('Location: registro_listar.php?erro=nao_encontrado');
    exit;
}

$paciente_id = (int) $_POST['paciente_id'];

if ($paciente_id == 0) {
    header('Location: registro_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O TIPO — do perfil, nunca do formulário.
//
//  Se alguém enviar um campo "tipo" junto, ele é simplesmente ignorado:
//  o valor gravado é decidido aqui, pela sessão.
// -------------------------------------------------------------------
$meu_tipo = tipoDoPerfil($_SESSION['usuario_perfil']);

if ($meu_tipo === null) {
    mysqli_close($conexao);
    header('Location: registro_listar.php?erro=sem_permissao');
    exit;
}

// -------------------------------------------------------------------
//  O paciente está internado AGORA?
//  A tela conferiu ao abrir, mas isso foi uma fotografia do passado.
// -------------------------------------------------------------------
// Traz também o id da INTERNAÇÃO. O registro pertence à estadia, não ao
// paciente em geral — é o que permite a ficha separar duas internações
// do mesmo paciente. E o id vem do banco, nunca do formulário.
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
    header('Location: registro_listar.php?erro=nao_internado');
    exit;
}

// -------------------------------------------------------------------
//  O TEXTO
//
//  trim() tira espaço e quebra de linha das pontas, mas guarda as
//  quebras do meio: parágrafo de anotação clínica tem sentido, e o
//  histórico as mostra como foram digitadas.
// -------------------------------------------------------------------
$texto = trim($_POST['texto']);

if ($texto === '') {
    mysqli_close($conexao);
    header('Location: registro_form.php?paciente_id=' . $paciente_id . '&erro=vazio');
    exit;
}

// O mínimo recusa o clique acidental, não julga o conteúdo.
if (strlen($texto) < minimoDoTexto()) {
    mysqli_close($conexao);
    header('Location: registro_form.php?paciente_id=' . $paciente_id
         . '&erro=curto&texto=' . urlencode($texto));
    exit;
}

// -------------------------------------------------------------------
//  Grava.
//
//  INSERT e mais nada. Não há UPDATE aqui nem em arquivo nenhum do
//  projeto — a tabela `registros` só cresce.
//
//  data_hora de NOW() e usuario_id da sessão: Regra de Ouro nº 2.
// -------------------------------------------------------------------
$quem_escreveu = $_SESSION['usuario_id'];

$internacao_id = $paciente['internacao_id'];

$sql = "INSERT INTO registros
          (paciente_id, internacao_id, usuario_id, tipo, data_hora, texto)
        VALUES (?, ?, ?, ?, NOW(), ?)";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'iiiss',
    $paciente_id, $internacao_id, $quem_escreveu, $meu_tipo, $texto);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

header('Location: registro_historico.php?paciente_id=' . $paciente_id . '&ok=gravado');
exit;
