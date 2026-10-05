<?php
// ===================================================================
//  sinal_historico.php  —  Histórico de sinais vitais de um paciente
//  Módulo 3 · Sprint 5
//
//  Do mais recente para o mais antigo, como pede o escopo. É nesta
//  tela que a evolução aparece: dá para ver a febre subindo às 6h e
//  cedendo às 18h, lendo de baixo para cima.
//
//  PERMISSÃO: administrador, médico e técnico. A recepção não entra —
//  mesma regra do sinal_listar.php, e o mesmo motivo.
//
//  Só o técnico afere, mas o médico precisa LER: é do que ele registra
//  a evolução, no Módulo 4. Ver e escrever são permissões diferentes,
//  e esta tela é o exemplo mais claro disso no sistema.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador', 'medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/faixas.php';

$eh_tecnico  = ($_SESSION['usuario_perfil'] == 'tecnico');
$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: sinal_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente, e se está internado agora.
//
//  Aqui NÃO se exige internação: o histórico de quem teve alta continua
//  existindo e continua podendo ser lido. O que a internação decide é
//  só se o botão "Aferir" aparece.
// -------------------------------------------------------------------
$sql = "SELECT p.id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               i.id AS internacao_id
        FROM pacientes p
        LEFT JOIN internacoes i ON i.paciente_id = p.id
                               AND i.situacao = 'internado'
                               AND i.ativo = 1
        LEFT JOIN leitos l ON l.id = i.leito_id
        WHERE p.id = ? AND p.ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$paciente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$paciente) {
    mysqli_close($conexao);
    header('Location: sinal_listar.php?erro=nao_encontrado');
    exit;
}

$esta_internado = ($paciente['internacao_id'] !== null);

// -------------------------------------------------------------------
//  As aferições, da mais nova para a mais velha.
// -------------------------------------------------------------------
$sql = "SELECT sv.id, sv.data_hora,
               sv.pa_sistolica, sv.pa_diastolica,
               sv.frequencia_cardiaca, sv.frequencia_respiratoria,
               sv.temperatura, sv.saturacao, sv.glicemia, sv.escala_dor,
               COALESCE(sv.observacao, '') AS observacao,
               COALESCE(u.nome, '') AS aferido_por,
               COALESCE(u.registro_profissional, '') AS registro
        FROM sinais_vitais sv
        LEFT JOIN usuarios u ON u.id = sv.usuario_id
        WHERE sv.paciente_id = ?
        ORDER BY sv.data_hora DESC";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$afericoes = mysqli_stmt_get_result($stmt);
$quantas   = mysqli_num_rows($afericoes);

$titulo    = 'Sinais vitais';
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';
?>

<?php
if (isset($_GET['ok']) && $_GET['ok'] == 'registrado') {
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div>Sinais vitais registrados.</div>
  </div>
<?php
}
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-7">
        <h5 class="mb-1"><?php echo htmlspecialchars($paciente['nome']); ?></h5>
        <span class="text-body-secondary">
          <?php echo $paciente['idade']; ?> anos
          <?php if ($esta_internado && $paciente['leito'] != '') { ?>
            · Leito <strong><?php echo htmlspecialchars($paciente['leito']); ?></strong>
          <?php } else if (!$esta_internado) { ?>
            · <span class="badge bg-label-secondary">Não internado</span>
          <?php } ?>
        </span>
      </div>
      <div class="col-md-5">
        <?php
        $texto_alergia = trim($paciente['alergias']);
        $nega = negaAlergias($texto_alergia);
        if (!$nega) {
        ?>
          <div class="alerta-alergia">
            <i class="icon-base bx bx-error-circle"></i>
            <span><?php echo htmlspecialchars($texto_alergia); ?></span>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Aferições</h5>
    <small class="text-body-secondary">
      <?php echo $quantas; ?>
      <?php echo ($quantas == 1 ? 'registro' : 'registros'); ?>
      · da mais recente para a mais antiga
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="sinal_listar.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
    <?php if ($eh_tecnico && $esta_internado) { ?>
      <a href="sinal_form.php?paciente_id=<?php echo $paciente['id']; ?>"
         class="btn btn-primary btn-sm">
        <i class="icon-base bx bx-pulse me-1"></i> Nova aferição
      </a>
    <?php } ?>
  </div>
</div>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="valor-alterado">Em vermelho</span>: valor fora da faixa de referência.
    Passe o mouse no título da coluna para ver a faixa.
  </small>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Data e hora</th>
          <?php
          // Os títulos das colunas saem da mesma lista que monta o
          // formulário. Uma verdade, um lugar.
          foreach (listaDeSinais() as $campo => $faixa) {
          ?>
            <th class="text-nowrap" title="Normal: <?php echo textoDaFaixa($campo); ?>">
              <?php echo $faixa['curto']; ?>
            </th>
          <?php } ?>
          <th>Aferido por</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if ($quantas == 0) {
      ?>
        <tr><td colspan="10" class="text-center text-body-secondary py-4">
          Nenhuma aferição registrada para este paciente.
        </td></tr>
      <?php
      }

      while ($a = mysqli_fetch_assoc($afericoes)) {
      ?>
        <tr>
          <td class="text-nowrap">
            <?php echo date('d/m/Y', strtotime($a['data_hora'])); ?><br>
            <strong><?php echo date('H:i', strtotime($a['data_hora'])); ?></strong>
          </td>

          <?php
          foreach (listaDeSinais() as $campo => $faixa) {

              $valor   = $a[$campo];
              $alterado = estaAlterado($campo, $valor);
          ?>
            <td class="text-nowrap <?php echo ($alterado ? 'valor-alterado' : ''); ?>">
              <?php
              if ($valor === null) {
                  echo '<span class="text-body-secondary">—</span>';
              } else {
                  echo number_format($valor, $faixa['decimais'], ',', '');
                  if ($alterado) {
                      echo ' <i class="icon-base bx bx-error-circle"></i>';
                  }
              }
              ?>
            </td>
          <?php } ?>

          <td class="text-nowrap">
            <small>
              <?php echo htmlspecialchars($a['aferido_por']); ?>
              <?php if ($a['registro'] != '') { ?>
                <br><span class="text-body-secondary"><?php echo htmlspecialchars($a['registro']); ?></span>
              <?php } ?>
            </small>
          </td>
        </tr>

        <?php if ($a['observacao'] != '') { ?>
          <tr>
            <td colspan="10" class="pt-0 text-body-secondary small">
              <i class="icon-base bx bx-message-square-dots me-1"></i>
              <?php echo htmlspecialchars($a['observacao']); ?>
            </td>
          </tr>
        <?php } ?>

      <?php } ?>

      </tbody>
    </table>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
