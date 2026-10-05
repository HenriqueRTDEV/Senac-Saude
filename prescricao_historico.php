<?php
// ===================================================================
//  prescricao_historico.php  —  As prescrições de um paciente
//  Módulo 5 · Sprint 7
//
//  As ativas primeiro, as suspensas depois. Cada uma mostra quem
//  prescreveu, quando, e quantas doses já foram administradas, não
//  administradas e estão pendentes.
//
//  PERMISSÃO: administrador, médico e técnico. Suspender é só do médico.
//
//  Prescrição suspensa NÃO é apagada — vira ativo = 0 e continua aqui,
//  com o histórico das doses que foram dadas enquanto valia.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador', 'medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/prescricoes.php';

$eh_medico   = ($_SESSION['usuario_perfil'] == 'medico');
$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente. Não se exige internação para LER — prescrição de quem
//  teve alta continua no prontuário. A internação decide só se o botão
//  de prescrever aparece.
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
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

$esta_internado = ($paciente['internacao_id'] !== null);

// -------------------------------------------------------------------
//  AS PRESCRIÇÕES, com a contagem de doses por estado.
//
//  Ordenadas pela INTERNAÇÃO, da mais recente para a mais antiga, e
//  dentro de cada uma as ativas primeiro. Num paciente que recebeu alta
//  e voltou, isso mantém as prescrições de cada estadia juntas em vez
//  de intercaladas — cada internação é um episódio clínico separado.
//
//  Cada cartão mostra o período da internação e o leito, e é isso que
//  identifica a estadia. O sistema não usa número de prontuário e não
//  precisa: "13/08 → 19/08, leito 107-B" não se confunde com outra.
// -------------------------------------------------------------------
$sql = "SELECT pr.id, pr.dose, pr.via, pr.frequencia,
               COALESCE(pr.horarios, '')   AS horarios,
               pr.data_inicio,
               COALESCE(pr.data_fim, '')   AS data_fim,
               COALESCE(pr.observacao, '') AS observacao,
               pr.ativo, pr.criado_em,
               m.nome AS medicamento,
               COALESCE(m.apresentacao, '') AS apresentacao,
               COALESCE(u.nome, '') AS prescrito_por,
               COALESCE(u.registro_profissional, '') AS registro,
               pr.internacao_id,
               DATE(i.data_admissao) AS internacao_de,
               COALESCE(DATE(i.data_alta), '') AS internacao_ate,
               i.situacao AS internacao_situacao,
               COALESCE(li.identificacao, '') AS internacao_leito,

               (SELECT COUNT(*) FROM administracoes
                 WHERE prescricao_id = pr.id AND status = 'administrado') AS dadas,
               (SELECT COUNT(*) FROM administracoes
                 WHERE prescricao_id = pr.id AND status = 'nao_administrado') AS nao_dadas,
               (SELECT COUNT(*) FROM administracoes
                 WHERE prescricao_id = pr.id AND status = 'pendente') AS pendentes

        FROM prescricoes pr
        JOIN medicamentos m ON m.id = pr.medicamento_id
        LEFT JOIN usuarios u ON u.id = pr.usuario_id
        JOIN internacoes  i ON i.id = pr.internacao_id
        LEFT JOIN leitos li ON li.id = i.leito_id
        WHERE pr.paciente_id = ?
        ORDER BY i.data_admissao DESC, pr.ativo DESC, pr.criado_em DESC";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$prescricoes = mysqli_stmt_get_result($stmt);
$quantas     = mysqli_num_rows($prescricoes);

$titulo    = 'Prescrições';
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';
?>

<?php
if (isset($_GET['ok']) && $_GET['ok'] == 'lancada') {
    $criados = isset($_GET['criados']) ? (int) $_GET['criados'] : 0;
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div>
      Prescrição lançada.
      <?php if ($criados > 0) { ?>
        <strong><?php echo $criados; ?></strong>
        <?php echo ($criados == 1 ? 'dose foi criada' : 'doses foram criadas'); ?>
        para o técnico checar.
      <?php } else { ?>
        Sem hora marcada: o técnico registra quando administrar.
      <?php } ?>
    </div>
  </div>
<?php
}

if (isset($_GET['ok']) && $_GET['ok'] == 'suspensa') {
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div>Prescrição suspensa. As doses pendentes dela saíram do turno.</div>
  </div>
<?php
}

if (isset($_GET['erro']) && $_GET['erro'] == 'nao_encontrado') {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div>Prescrição não encontrada.</div>
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
    <h5 class="mb-0">Prescrições</h5>
    <small class="text-body-secondary">
      <?php echo $quantas; ?>
      <?php echo ($quantas == 1 ? 'prescrição' : 'prescrições'); ?>
      · internação mais recente primeiro
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="prescricao_listar.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
    <?php if ($eh_medico && $esta_internado) { ?>
      <a href="prescricao_form.php?paciente_id=<?php echo $paciente['id']; ?>"
         class="btn btn-primary btn-sm">
        <i class="icon-base bx bx-capsule me-1"></i> Nova prescrição
      </a>
    <?php } ?>
  </div>
</div>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="badge bg-label-success">Administrado</span>
    <span class="badge bg-label-danger">Não administrado</span>
    <span class="badge bg-label-warning">Pendente</span>
    — as doses de cada prescrição.
  </small>
</div>

<?php
if ($quantas == 0) {
?>
  <div class="card">
    <div class="card-body text-center text-body-secondary py-5">
      Nenhuma prescrição para este paciente.
    </div>
  </div>
<?php
}

while ($p = mysqli_fetch_assoc($prescricoes)) {

    $sos = ($p['horarios'] == '');
?>
  <div class="card mb-3 <?php echo ($p['ativo'] ? '' : 'opacity-75'); ?>">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <h6 class="mb-1">
            <?php echo htmlspecialchars($p['medicamento']); ?>
            <?php if ($p['apresentacao'] != '') { ?>
              <small class="text-body-secondary fw-normal">
                <?php echo htmlspecialchars($p['apresentacao']); ?>
              </small>
            <?php } ?>
          </h6>
          <div>
            <strong><?php echo htmlspecialchars($p['dose']); ?></strong>
            · <?php echo htmlspecialchars($p['via']); ?>
            <small class="text-body-secondary">(<?php echo nomeDaVia($p['via']); ?>)</small>
            · <?php echo htmlspecialchars($p['frequencia']); ?>
          </div>
        </div>

        <div class="text-end">
          <?php
          // De que internação esta prescrição é. Numa reinternação, é
          // isso que separa uma estadia da outra — sem número de
          // prontuário, só com o período e o leito.
          ?>
          <div class="small text-body-secondary mb-1">
            <?php echo date('d/m/Y', strtotime($p['internacao_de'])); ?> →
            <?php echo ($p['internacao_ate'] == '' ? 'hoje' : date('d/m/Y', strtotime($p['internacao_ate']))); ?>
            <?php if ($p['internacao_leito'] != '') { ?>
              · leito <?php echo htmlspecialchars($p['internacao_leito']); ?>
            <?php } ?>
          </div>
          <?php if (!$p['ativo']) { ?>
            <span class="badge bg-label-secondary">Suspensa</span>
          <?php } else if ($sos) { ?>
            <span class="badge bg-label-info">Se necessário</span>
          <?php } else { ?>
            <span class="badge bg-label-primary">Ativa</span>
          <?php } ?>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-sm-4">
          <small class="text-body-secondary d-block">Horários</small>
          <?php
          if ($sos) {
              echo '<span class="text-body-secondary">sem hora marcada</span>';
          } else {
              echo htmlspecialchars(str_replace(',', '  ', $p['horarios']));
          }
          ?>
        </div>
        <div class="col-sm-4">
          <small class="text-body-secondary d-block">Período</small>
          <?php echo date('d/m/Y', strtotime($p['data_inicio'])); ?>
          <?php if ($p['data_fim'] != '') { ?>
            até <?php echo date('d/m/Y', strtotime($p['data_fim'])); ?>
          <?php } else { ?>
            <span class="text-body-secondary">— sem fim definido</span>
          <?php } ?>
        </div>
        <div class="col-sm-4">
          <small class="text-body-secondary d-block">Doses</small>
          <?php if ($p['dadas'] > 0) { ?>
            <span class="badge bg-label-success"><?php echo $p['dadas']; ?> dadas</span>
          <?php } ?>
          <?php if ($p['nao_dadas'] > 0) { ?>
            <span class="badge bg-label-danger"><?php echo $p['nao_dadas']; ?> não dadas</span>
          <?php } ?>
          <?php if ($p['pendentes'] > 0) { ?>
            <span class="badge bg-label-warning"><?php echo $p['pendentes']; ?> pendentes</span>
          <?php } ?>
          <?php if ($p['dadas'] == 0 && $p['nao_dadas'] == 0 && $p['pendentes'] == 0) { ?>
            <span class="text-body-secondary">—</span>
          <?php } ?>
        </div>
      </div>

      <?php if ($p['observacao'] != '') { ?>
        <div class="mt-3 text-body-secondary small">
          <i class="icon-base bx bx-message-square-dots me-1"></i>
          <?php echo htmlspecialchars($p['observacao']); ?>
        </div>
      <?php } ?>

      <div class="mt-3 d-flex justify-content-between align-items-end flex-wrap gap-2">
        <small class="text-body-secondary">
          Prescrito por <?php echo htmlspecialchars($p['prescrito_por']); ?>
          <?php if ($p['registro'] != '') { ?>
            · <?php echo htmlspecialchars($p['registro']); ?>
          <?php } ?>
          · <?php echo date('d/m/Y H:i', strtotime($p['criado_em'])); ?>
        </small>

        <?php if ($eh_medico && $p['ativo']) { ?>
          <a href="prescricao_suspender.php?id=<?php echo $p['id']; ?>"
             class="btn btn-sm btn-outline-danger">Suspender</a>
        <?php } ?>
      </div>

    </div>
  </div>
<?php } ?>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
