<?php
// ===================================================================
//  medicacao_historico.php  —  Histórico de checagem por paciente
//  Módulo 5 · Sprint 7 · perfil TÉCNICO
//
//  A segunda metade do submenu "Medicações". A tela do turno responde
//  "o que eu tenho que fazer agora?"; esta responde "o que já foi
//  feito?". Eram a mesma página, e a segunda crescia todo dia até
//  empurrar a primeira para fora da tela.
//
//  AGRUPADO POR INTERNAÇÃO, e é aqui que está o cuidado do módulo.
//
//  Um paciente pode ter estado internado, recebido alta e voltado. As
//  doses das duas estadias são do mesmo paciente e NÃO podem aparecer
//  numa lista só, misturadas: são episódios clínicos diferentes.
//
//  O sistema não usa número de prontuário. Não precisa: a própria
//  internação tem id, data de admissão e data de alta — e isso
//  identifica a estadia sem ambiguidade nenhuma. "Internação 1,
//  13/08 a 19/08, leito 107-B" não se confunde com nenhuma outra.
//
//  Isso só é exato porque `prescricoes` guarda `internacao_id`. Antes
//  da migração v3 a prescrição pertencia só ao paciente, e o
//  agrupamento teria que ser adivinhado comparando datas — o que
//  erraria justamente na dose checada com atraso, depois da alta.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/prescricoes.php';

// -------------------------------------------------------------------
//  Qual paciente
//
//  Esta tela precisa de um paciente escolhido: o histórico de todos ao
//  mesmo tempo não responde pergunta nenhuma. Enquanto ninguém for
//  escolhido, ela mostra a busca e a lista de quem tem checagem.
// -------------------------------------------------------------------
$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}
$curinga = '%' . $busca . '%';

$paciente   = null;
$internacoes = array();

if ($paciente_id > 0) {

    // ---------------------------------------------------------------
    //  O paciente escolhido
    // ---------------------------------------------------------------
    $sql = "SELECT p.id, p.nome,
                   COALESCE(p.alergias, '') AS alergias,
                   TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade
            FROM pacientes p
            WHERE p.id = ? AND p.ativo = 1";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
    mysqli_stmt_execute($stmt);
    $paciente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$paciente) {
        mysqli_close($conexao);
        header('Location: medicacao_historico.php?erro=nao_encontrado');
        exit;
    }

    // ---------------------------------------------------------------
    //  AS INTERNAÇÕES DELE, da mais recente para a mais antiga.
    //
    //  Cada uma traz a contagem de doses checadas naquela estadia. O
    //  caminho é administracoes -> prescricoes -> internacao_id, e é a
    //  coluna nova que torna isso possível.
    // ---------------------------------------------------------------
    $sql = "SELECT i.id, i.data_admissao, i.data_alta, i.situacao,
                   COALESCE(l.identificacao, '') AS leito,
                   COALESCE(s.nome, '')          AS setor,
                   COALESCE(c.codigo, '')        AS cid_codigo,
                   COALESCE(c.descricao, '')     AS cid_descricao,

                   (SELECT COUNT(*) FROM administracoes a
                      JOIN prescricoes pr ON pr.id = a.prescricao_id
                     WHERE pr.internacao_id = i.id
                       AND a.status <> 'pendente') AS checadas,
                   (SELECT COUNT(*) FROM administracoes a
                      JOIN prescricoes pr ON pr.id = a.prescricao_id
                     WHERE pr.internacao_id = i.id
                       AND a.status = 'administrado') AS dadas,
                   (SELECT COUNT(*) FROM administracoes a
                      JOIN prescricoes pr ON pr.id = a.prescricao_id
                     WHERE pr.internacao_id = i.id
                       AND a.status = 'nao_administrado') AS nao_dadas

            FROM internacoes i
            LEFT JOIN leitos  l ON l.id = i.leito_id
            LEFT JOIN setores s ON s.id = l.setor_id
            LEFT JOIN cids    c ON c.id = i.cid_id
            WHERE i.paciente_id = ? AND i.ativo = 1
            ORDER BY i.data_admissao DESC";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($linha = mysqli_fetch_assoc($res)) {
        $internacoes[] = $linha;
    }
    mysqli_stmt_close($stmt);

    // ---------------------------------------------------------------
    //  AS DOSES CHECADAS, todas de uma vez, agrupadas em array pelo
    //  id da internação.
    //
    //  Uma consulta só para todas as estadias, e a separação acontece
    //  no PHP. O contrário — uma consulta por internação, dentro do
    //  laço que desenha — daria o mesmo resultado e faria N vezes mais
    //  viagens ao banco.
    // ---------------------------------------------------------------
    $sql = "SELECT pr.internacao_id,
                   a.id, a.horario_previsto, a.data_hora_checagem,
                   a.status, COALESCE(a.justificativa, '') AS justificativa,
                   pr.dose, pr.via, pr.frequencia,
                   m.nome AS medicamento,
                   COALESCE(u.nome, '') AS checado_por,
                   COALESCE(u.registro_profissional, '') AS registro
            FROM administracoes a
            JOIN prescricoes  pr ON pr.id = a.prescricao_id
            JOIN medicamentos m  ON m.id = pr.medicamento_id
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE pr.paciente_id = ?
              AND a.status <> 'pendente'
            ORDER BY a.data_hora_checagem DESC";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $doses_por_internacao = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $doses_por_internacao[$linha['internacao_id']][] = $linha;
    }
    mysqli_stmt_close($stmt);

} else {

    // ---------------------------------------------------------------
    //  Ninguém escolhido: lista quem tem checagem, para escolher.
    //
    //  Aqui NÃO se exige internação. Histórico de quem recebeu alta
    //  continua existindo e é justamente o que se vem consultar.
    // ---------------------------------------------------------------
    $sql = "SELECT p.id, p.nome,
                   TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
                   COALESCE(p.alergias, '') AS alergias,
                   (SELECT COUNT(*) FROM administracoes a
                      JOIN prescricoes pr ON pr.id = a.prescricao_id
                     WHERE pr.paciente_id = p.id
                       AND a.status <> 'pendente') AS checadas,
                   (SELECT COUNT(*) FROM internacoes
                     WHERE paciente_id = p.id AND ativo = 1) AS internacoes,
                   (SELECT COUNT(*) FROM internacoes
                     WHERE paciente_id = p.id AND ativo = 1
                       AND situacao = 'internado') AS internado_agora
            FROM pacientes p
            WHERE p.ativo = 1
              AND p.nome LIKE ?
            HAVING checadas > 0
            ORDER BY internado_agora DESC, p.nome";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 's', $curinga);
    mysqli_stmt_execute($stmt);
    $lista_pacientes = mysqli_stmt_get_result($stmt);
}

$titulo    = 'Histórico checado';
$subtitulo = ($paciente ? $paciente['nome'] : 'Escolha o paciente');
require 'includes/cabecalho.php';
?>

<?php
if (isset($_GET['erro']) && $_GET['erro'] == 'nao_encontrado') {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div>Paciente não encontrado.</div>
  </div>
<?php
}
?>

<?php
// ===================================================================
//  MODO 1 — nenhum paciente escolhido: escolher
// ===================================================================
if (!$paciente) {
?>

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Escolha o paciente</h5>
      <small class="text-body-secondary">
        <?php echo mysqli_num_rows($lista_pacientes); ?>
        com checagem registrada · internados primeiro
      </small>
    </div>
  </div>

  <form method="get" action="medicacao_historico.php" class="mb-3">
    <div class="input-group">
      <input type="text" name="busca" class="form-control"
             placeholder="Buscar por nome…"
             value="<?php echo htmlspecialchars($busca); ?>">
      <button class="btn btn-outline-primary" type="submit">Buscar</button>
      <?php if ($busca != '') { ?>
        <a href="medicacao_historico.php" class="btn btn-outline-secondary">Limpar</a>
      <?php } ?>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Internações</th>
            <th>Doses checadas</th>
            <th></th>
          </tr>
        </thead>
        <tbody>

        <?php
        if (mysqli_num_rows($lista_pacientes) == 0) {
        ?>
          <tr><td colspan="4" class="text-center text-body-secondary py-4">
            <?php
            echo ($busca == ''
                  ? 'Nenhuma dose checada ainda.'
                  : 'Nenhum paciente com esse nome tem dose checada.');
            ?>
          </td></tr>
        <?php
        }

        while ($p = mysqli_fetch_assoc($lista_pacientes)) {
        ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars($p['nome']); ?></strong>,
              <?php echo $p['idade']; ?> anos
              <?php
              // REGRA DE OURO Nº 4 — alergia sempre em destaque.
              $texto_alergia = trim($p['alergias']);
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
              <?php echo $p['internacoes']; ?>
              <?php echo ($p['internacoes'] == 1 ? 'internação' : 'internações'); ?>
              <?php if ($p['internado_agora'] > 0) { ?>
                <br><span class="badge bg-label-primary">internado agora</span>
              <?php } else { ?>
                <br><span class="badge bg-label-secondary">não internado</span>
              <?php } ?>
            </td>
            <td><strong><?php echo $p['checadas']; ?></strong></td>
            <td class="text-nowrap">
              <a href="medicacao_historico.php?paciente_id=<?php echo $p['id']; ?>"
                 class="btn btn-sm btn-primary">Ver histórico</a>
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
    exit;
}
?>

<?php
// ===================================================================
//  MODO 2 — paciente escolhido: o histórico por internação
// ===================================================================
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-7">
        <h5 class="mb-1"><?php echo htmlspecialchars($paciente['nome']); ?></h5>
        <span class="text-body-secondary"><?php echo $paciente['idade']; ?> anos</span>
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
    <h5 class="mb-0">Histórico de checagem</h5>
    <small class="text-body-secondary">
      <?php echo count($internacoes); ?>
      <?php echo (count($internacoes) == 1 ? 'internação' : 'internações'); ?>
      · da mais recente para a mais antiga
    </small>
  </div>
  <a href="medicacao_historico.php" class="btn btn-outline-secondary btn-sm">
    Trocar de paciente
  </a>
</div>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="badge bg-label-success">Administrado</span>
    <span class="badge bg-label-danger">Não administrado</span>
    — cada internação é um episódio separado, identificado pelo período e pelo leito.
  </small>
</div>

<?php
if (count($internacoes) == 0) {
?>
  <div class="card">
    <div class="card-body text-center text-body-secondary py-5">
      Este paciente não tem internação registrada.
    </div>
  </div>
<?php
}

foreach ($internacoes as $numero => $i) {

    // As internações vêm da mais nova para a mais velha, então a
    // numeração de leitura é ao contrário do índice do array.
    $ordem = count($internacoes) - $numero;

    $doses = isset($doses_por_internacao[$i['id']])
           ? $doses_por_internacao[$i['id']]
           : array();
?>

  <div class="card mb-4">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h6 class="mb-1">
            Internação <?php echo $ordem; ?>
            <?php if ($i['situacao'] == 'internado') { ?>
              <span class="badge bg-label-primary">internado</span>
            <?php } else { ?>
              <span class="badge bg-label-secondary">alta</span>
            <?php } ?>
          </h6>
          <small class="text-body-secondary">
            <?php echo date('d/m/Y', strtotime($i['data_admissao'])); ?>
            →
            <?php
            if ($i['data_alta'] === null) {
                echo 'hoje';
            } else {
                echo date('d/m/Y', strtotime($i['data_alta']));
            }
            ?>
            <?php if ($i['leito'] != '') { ?>
              · leito <strong><?php echo htmlspecialchars($i['leito']); ?></strong>
              <?php if ($i['setor'] != '') { ?>
                · <?php echo htmlspecialchars($i['setor']); ?>
              <?php } ?>
            <?php } ?>
            <?php if ($i['cid_codigo'] != '') { ?>
              · <?php echo htmlspecialchars($i['cid_codigo']); ?>
              <?php echo htmlspecialchars($i['cid_descricao']); ?>
            <?php } ?>
          </small>
        </div>

        <div class="text-end">
          <?php if ($i['dadas'] > 0) { ?>
            <span class="badge bg-label-success">
              <?php echo $i['dadas']; ?>
              <?php echo ($i['dadas'] == 1 ? 'administrada' : 'administradas'); ?>
            </span>
          <?php } ?>
          <?php if ($i['nao_dadas'] > 0) { ?>
            <span class="badge bg-label-danger">
              <?php echo $i['nao_dadas']; ?>
              <?php echo ($i['nao_dadas'] == 1 ? 'não administrada' : 'não administradas'); ?>
            </span>
          <?php } ?>
          <?php if ($i['checadas'] == 0) { ?>
            <span class="badge bg-label-secondary">nenhuma checagem</span>
          <?php } ?>
        </div>
      </div>
    </div>

    <?php
    if (count($doses) == 0) {
    ?>
      <div class="card-body text-body-secondary text-center py-4">
        Nenhuma dose checada nesta internação.
      </div>
    <?php
    } else {
    ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Checado às</th>
              <th>Previsto</th>
              <th>Medicamento</th>
              <th>Situação</th>
              <th>Por</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $dia_anterior = '';

          foreach ($doses as $d) {

              // Separador de dia, como na linha do tempo dos registros.
              $dia = date('d/m/Y', strtotime($d['data_hora_checagem']));

              if ($dia != $dia_anterior) {
                  $dia_anterior = $dia;
          ?>
            <tr>
              <td colspan="5" class="text-uppercase text-body-secondary fw-bold py-2"
                  style="font-size:.72rem; letter-spacing:.06em; background:#fbfbfc;">
                <?php echo $dia; ?>
              </td>
            </tr>
          <?php
              }
          ?>
            <tr>
              <td class="text-nowrap">
                <strong><?php echo date('H:i', strtotime($d['data_hora_checagem'])); ?></strong>
              </td>
              <td class="text-nowrap text-body-secondary">
                <?php echo date('d/m H:i', strtotime($d['horario_previsto'])); ?>
              </td>
              <td>
                <strong><?php echo htmlspecialchars($d['medicamento']); ?></strong><br>
                <small class="text-body-secondary">
                  <?php echo htmlspecialchars($d['dose']); ?>
                  · <?php echo htmlspecialchars($d['via']); ?>
                  · <?php echo htmlspecialchars($d['frequencia']); ?>
                </small>
              </td>
              <td>
                <span class="badge <?php echo corDoStatus($d['status']); ?>">
                  <?php echo nomeDoStatus($d['status']); ?>
                </span>
                <?php if ($d['justificativa'] != '') { ?>
                  <br><small class="text-body-secondary">
                    <?php echo htmlspecialchars($d['justificativa']); ?>
                  </small>
                <?php } ?>
              </td>
              <td class="text-nowrap">
                <small>
                  <?php echo htmlspecialchars($d['checado_por']); ?>
                  <?php if ($d['registro'] != '') { ?>
                    <br><span class="text-body-secondary"><?php echo htmlspecialchars($d['registro']); ?></span>
                  <?php } ?>
                </small>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>

  </div>
<?php } ?>

<?php
mysqli_close($conexao);
require 'includes/rodape.php';
?>
