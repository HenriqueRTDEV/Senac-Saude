<?php
// ===================================================================
//  prescricao_listar.php  —  Prescrições da ala
//  Módulo 5 · Sprint 7
//
//  Uma linha por internado, com quantas prescrições ativas ele tem e
//  quantas doses estão pendentes hoje.
//
//  PERMISSÃO: administrador, médico e técnico. A recepção não —
//  prescrição é dado clínico.
//
//  Quem PRESCREVE é só o médico. O técnico entra aqui para ler o que
//  foi prescrito, e checa na tela do turno.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador', 'medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/prescricoes.php';

$eh_medico = ($_SESSION['usuario_perfil'] == 'medico');

// -------------------------------------------------------------------
//  A busca por nome (convenção 19)
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}
$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  OS INTERNADOS, COM AS CONTAGENS
//
//  Três subconsultas, cada uma respondendo uma pergunta diferente
//  sobre o mesmo paciente. Elas ficam no SELECT, e não em JOIN,
//  justamente porque são números: um JOIN multiplicaria as linhas.
// -------------------------------------------------------------------
$sql = "SELECT p.id AS paciente_id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '')         AS setor,

               -- Contagem pela INTERNAÇÃO em curso, não pelo paciente.
               -- Antes era por paciente, e isso ressuscitava prescrição
               -- de estadia antiga em quem recebeu alta e voltou.
               (SELECT COUNT(*) FROM prescricoes
                 WHERE internacao_id = i.id AND ativo = 1) AS ativas,

               (SELECT COUNT(*) FROM administracoes a
                  JOIN prescricoes pr ON pr.id = a.prescricao_id
                 WHERE pr.internacao_id = i.id
                   AND a.status = 'pendente'
                   AND DATE(a.horario_previsto) <= CURDATE()) AS pendentes,

               (SELECT COUNT(*) FROM prescricoes
                 WHERE internacao_id = i.id AND ativo = 1
                   AND horarios IS NULL) AS se_necessario

        FROM internacoes i
        JOIN pacientes p ON p.id = i.paciente_id
        LEFT JOIN leitos  l ON l.id = i.leito_id
        LEFT JOIN setores s ON s.id = l.setor_id
        WHERE i.situacao = 'internado' AND i.ativo = 1
          AND p.nome LIKE ?
        ORDER BY l.identificacao";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 's', $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$lista = array();
while ($linha = mysqli_fetch_assoc($resultado)) {
    $lista[] = $linha;
}

$sem_prescricao = 0;
foreach ($lista as $linha) {
    if ($linha['ativas'] == 0) {
        $sem_prescricao = $sem_prescricao + 1;
    }
}

$titulo    = 'Prescrições';
$subtitulo = 'O que foi prescrito para cada paciente';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'lancada'   => 'Prescrição lançada.',
    'suspensa'  => 'Prescrição suspensa. As doses pendentes dela foram retiradas do turno.'
);

$avisos_erro = array(
    'nao_internado'  => 'Só se prescreve para paciente internado.',
    'nao_encontrado' => 'Paciente ou prescrição não encontrada.'
);

if (isset($_GET['ok']) && isset($avisos_ok[$_GET['ok']])) {
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div><?php echo $avisos_ok[$_GET['ok']]; ?></div>
  </div>
<?php
}

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Internados</h5>
    <small class="text-body-secondary">
      <?php echo count($lista); ?>
      <?php echo (count($lista) == 1 ? 'paciente' : 'pacientes'); ?>
      <?php if ($sem_prescricao > 0) { ?>
        · <span class="fw-bold"><?php echo $sem_prescricao; ?> sem prescrição</span>
      <?php } ?>
    </small>
  </div>
  <?php if ($eh_medico) { ?>
    <small class="text-body-secondary">Seu perfil prescreve e suspende</small>
  <?php } ?>
</div>

<form method="get" action="prescricao_listar.php" class="mb-3">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por nome…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="prescricao_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="badge bg-label-warning">Pendente</span> dose no horário e ainda não checada
    &nbsp;·&nbsp;
    <span class="badge bg-label-info">Se necessário</span> sem hora marcada, o técnico dá quando precisar
  </small>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito</th>
          <th>Paciente</th>
          <th>Prescrições ativas</th>
          <th>Doses pendentes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>

      <?php
      if (count($lista) == 0) {
      ?>
        <tr><td colspan="5" class="text-center text-body-secondary py-4">
          <?php
          echo ($busca == ''
                ? 'Nenhum paciente internado no momento.'
                : 'Nenhum internado com esse nome.');
          ?>
        </td></tr>
      <?php
      }

      foreach ($lista as $linha) {
      ?>
        <tr>
          <td>
            <?php if ($linha['leito'] == '') { ?>
              <span class="text-body-secondary">sem leito</span>
            <?php } else { ?>
              <strong><?php echo htmlspecialchars($linha['leito']); ?></strong><br>
              <small class="text-body-secondary"><?php echo htmlspecialchars($linha['setor']); ?></small>
            <?php } ?>
          </td>

          <td>
            <strong><?php echo htmlspecialchars($linha['nome']); ?></strong>,
            <?php echo $linha['idade']; ?> anos
            <?php
            // REGRA DE OURO Nº 4 — alergia sempre em destaque.
            $texto_alergia = trim($linha['alergias']);
            $nega = negaAlergias($texto_alergia);
            if (!$nega) {
            ?>
              <br><span class="valor-alterado small">
                <i class="icon-base bx bx-error-circle"></i>
                <?php echo htmlspecialchars($texto_alergia); ?>
              </span>
            <?php } ?>
          </td>

          <td>
            <?php if ($linha['ativas'] == 0) { ?>
              <span class="badge bg-label-secondary">Nenhuma</span>
            <?php } else { ?>
              <strong><?php echo $linha['ativas']; ?></strong>
              <?php echo ($linha['ativas'] == 1 ? 'prescrição' : 'prescrições'); ?>
              <?php if ($linha['se_necessario'] > 0) { ?>
                <br><span class="badge bg-label-info">
                  <?php echo $linha['se_necessario']; ?> se necessário
                </span>
              <?php } ?>
            <?php } ?>
          </td>

          <td>
            <?php if ($linha['pendentes'] == 0) { ?>
              <span class="text-body-secondary">—</span>
            <?php } else { ?>
              <span class="badge bg-label-warning">
                <?php echo $linha['pendentes']; ?> pendente<?php echo ($linha['pendentes'] == 1 ? '' : 's'); ?>
              </span>
            <?php } ?>
          </td>

          <td class="text-nowrap">
            <?php if ($eh_medico) { ?>
              <a href="prescricao_form.php?paciente_id=<?php echo $linha['paciente_id']; ?>"
                 class="btn btn-sm btn-primary">Prescrever</a>
            <?php } ?>
            <a href="prescricao_historico.php?paciente_id=<?php echo $linha['paciente_id']; ?>"
               class="btn btn-sm btn-outline-primary">Ver todas</a>
          </td>
        </tr>
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
