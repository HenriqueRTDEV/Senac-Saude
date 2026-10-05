<?php
// ===================================================================
//  sinal_form.php  —  Aferir sinais vitais
//  Módulo 3 · Sprint 5 · perfil TÉCNICO
//
//  Uma aferição de um paciente. Nenhum campo é obrigatório sozinho —
//  não sempre se mede tudo — mas ao menos um precisa vir preenchido,
//  senão o registro não diz nada.
//
//  Repare que NÃO existe campo de data, de hora nem de autor. Os três
//  saem do relógio do servidor e da sessão, no sinal_salvar.php. É a
//  Regra de Ouro nº 2: todo registro tem autor e hora, e ninguém
//  escolhe os seus.
//
//  Os campos e as faixas não estão escritos aqui: eles vêm do
//  includes/faixas.php e o formulário é montado num laço. Entrar um
//  sinal novo naquele arquivo faz o campo aparecer nesta tela sozinho.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/faixas.php';

$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: sinal_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente existe E está internado?
//
//  Sinal vital é dado de internação. Aferir alguém que teve alta seria
//  gravar um dado clínico sem contexto — e o técnico chegaria aqui por
//  um endereço antigo, não por engano de digitação.
// -------------------------------------------------------------------
$sql = "SELECT p.id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '') AS setor
        FROM pacientes p
        JOIN internacoes i ON i.paciente_id = p.id
                          AND i.situacao = 'internado'
                          AND i.ativo = 1
        LEFT JOIN leitos  l ON l.id = i.leito_id
        LEFT JOIN setores s ON s.id = l.setor_id
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
//  A aferição anterior, para o técnico ter referência do que mudou.
// -------------------------------------------------------------------
$sql = "SELECT data_hora, pa_sistolica, pa_diastolica,
               frequencia_cardiaca, frequencia_respiratoria,
               temperatura, saturacao, glicemia, escala_dor
        FROM sinais_vitais
        WHERE paciente_id = ?
        ORDER BY data_hora DESC
        LIMIT 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$anterior = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// -------------------------------------------------------------------
//  Se o salvar recusou, ele devolve os valores digitados pela URL para
//  o técnico não perder o que já tinha escrito.
// -------------------------------------------------------------------
$digitado = array();
foreach (array_keys(listaDeSinais()) as $campo) {
    $digitado[$campo] = isset($_GET[$campo]) ? $_GET[$campo] : '';
}
$observacao = isset($_GET['observacao']) ? $_GET['observacao'] : '';

$titulo    = 'Aferir sinais vitais';
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'vazio'        => 'Preencha ao menos um sinal vital.',
    'impossivel'   => 'Algum valor não é um número válido ou está fora do que é fisicamente possível. Confira o que foi digitado.',
    'pa_invertida' => 'A pressão sistólica precisa ser maior que a diastólica.'
);

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php
}
?>

<!-- Cabeçalho do paciente, com a alergia em destaque -->
<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <h5 class="mb-1"><?php echo htmlspecialchars($paciente['nome']); ?></h5>
        <span class="text-body-secondary">
          <?php echo $paciente['idade']; ?> anos
          <?php if ($paciente['leito'] != '') { ?>
            · Leito <strong><?php echo htmlspecialchars($paciente['leito']); ?></strong>
            · <?php echo htmlspecialchars($paciente['setor']); ?>
          <?php } ?>
        </span>
      </div>
      <div class="col-md-4">
        <?php
        $texto_alergia = trim($paciente['alergias']);
        $nega = negaAlergias($texto_alergia);
        if (!$nega) {
        ?>
          <div class="alerta-alergia">
            <i class="icon-base bx bx-error-circle"></i>
            <span><?php echo htmlspecialchars($texto_alergia); ?></span>
          </div>
        <?php } else { ?>
          <span class="text-body-secondary small">Nega alergias</span>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-9">

    <form action="sinal_salvar.php" method="post">
      <input type="hidden" name="paciente_id" value="<?php echo $paciente['id']; ?>">

      <div class="card">
        <div class="card-body">

          <div class="row g-3">
          <?php
          // O formulário inteiro sai da lista do faixas.php.
          foreach (listaDeSinais() as $campo => $faixa) {

              // A pressão fica em duas colunas estreitas, lado a lado;
              // os outros em colunas de mesmo tamanho.
              $largura = 'col-sm-6 col-lg-3';
          ?>
            <div class="<?php echo $largura; ?>">
              <label class="form-label" for="<?php echo $campo; ?>">
                <?php echo $faixa['rotulo']; ?>
              </label>

              <div class="input-group">
                <input type="number"
                       class="form-control"
                       id="<?php echo $campo; ?>"
                       name="<?php echo $campo; ?>"
                       value="<?php echo htmlspecialchars($digitado[$campo]); ?>"
                       step="<?php echo ($faixa['decimais'] > 0 ? '0.1' : '1'); ?>"
                       min="<?php echo $faixa['possivel_min']; ?>"
                       max="<?php echo $faixa['possivel_max']; ?>">
                <span class="input-group-text"><?php echo $faixa['unidade']; ?></span>
              </div>

              <div class="form-text">
                Normal: <?php echo textoDaFaixa($campo); ?>
                <?php
                // O valor da aferição anterior, quando existe.
                if ($anterior && $anterior[$campo] !== null) {
                    echo '<br>Anterior: '
                       . number_format($anterior[$campo], $faixa['decimais'], ',', '');
                }
                ?>
              </div>
            </div>
          <?php } ?>
          </div>

          <hr class="my-4">

          <div class="mb-0">
            <label class="form-label" for="observacao">Observação</label>
            <textarea class="form-control" id="observacao" name="observacao" rows="2"
                      maxlength="255"
                      placeholder="Ex.: glicemia aferida antes do jantar"><?php echo htmlspecialchars($observacao); ?></textarea>
            <div class="form-text">Opcional. Até 255 caracteres.</div>
          </div>

        </div>

        <div class="card-body border-top d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="icon-base bx bx-pulse me-1"></i> Registrar aferição
          </button>
          <a href="sinal_listar.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </div>
    </form>

  </div>
</div>

<?php
mysqli_close($conexao);
require 'includes/rodape.php';
?>
