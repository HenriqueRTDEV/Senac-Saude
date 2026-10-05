<?php
// ===================================================================
//  internacao_movimentar.php  —  Movimentação de pacientes
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Uma tela, duas tarefas:
//
//    EM CIMA   quem está internado agora -> trocar de leito
//    EMBAIXO   quem está cadastrado mas não internado -> internar
//
//  A parte de baixo é o que resolve a reinternação. O paciente que
//  teve alta continua cadastrado; quando volta, a recepção o
//  encontra aqui e o interna de novo. Não se cadastra a mesma
//  pessoa duas vezes.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';
require 'includes/alergia.php';

// -------------------------------------------------------------------
//  1. QUEM ESTÁ INTERNADO AGORA
// -------------------------------------------------------------------
$sql = "SELECT 
  i.id AS internacao_id,
  i.data_admissao, 
  p.nome,
  COALESCE(p.alergias, '') AS alergias,
  TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
  l.id AS leito_id,
  l.identificacao AS leito,
  COALESCE(s.nome, '') AS setor
FROM internacoes i
JOIN pacientes p ON p.id = i.paciente_id
LEFT JOIN leitos l ON l.id = i.leito_id
LEFT JOIN setores s ON s.id = l.setor_id
WHERE i.situacao = 'internado' AND i.ativo = 1
ORDER BY l.identificacao";

$internados = mysqli_query($conexao, $sql);

// -------------------------------------------------------------------
//  2. OS LEITOS LIVRES
//     Ativos e que não aparecem em nenhuma internação em andamento.
//     Esta lista alimenta todos os <select> da tela.
// -------------------------------------------------------------------
$sql = "SELECT l.id, l.identificacao,
              COALESCE(s.nome, '') AS setor
              FROM leitos l
              LEFT JOIN setores s ON s.id = l.setor_id
              WHERE l.ativo = 1
                AND l.id NOT IN(
                    SELECT leito_id FROM internacoes
                    WHERE situacao = 'internado' AND ativo = 1 AND leito_id IS NOT NULL
                )
                ORDER BY l.identificacao";

$res_livres = mysqli_query($conexao, $sql);

// Guarda num array porque a lista será usada várias vezes na página —
// uma vez para cada linha da tabela. Um resultado do banco só se lê
// uma vez; um array se percorre quantas vezes quiser.
$leitos_livres = array();
while ($l = mysqli_fetch_assoc($res_livres)) {
    $leitos_livres[] = $l;
}

// -------------------------------------------------------------------
//  3. BUSCA DE PACIENTE NÃO INTERNADO
//     Só consulta se a recepção digitou alguma coisa — a lista
//     inteira de cadastrados não interessa aqui.
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}

$encontrados = null;

if ($busca != '') {

    $curinga = '%' . $busca . '%';

    // A condição "i.id IS NULL" depois do LEFT JOIN é o que seleciona
    // exatamente quem NÃO tem internação em andamento.
    $sql = "SELECT p.id, p.nome
              COALESCE(p.alergias, '') AS alergias,
              TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade
              (SELECT MAX(data_alta) FROM internacoes
              WHERE paciente_id = p.id) AS ultima_alta
          FROM pacientes p
          LEFT JOIN internacoes i ON i.paciente_id = p.id
            AND i.situacao = 'internado'
            AND i.ativo = 1
          WHERE p.ativo = 1
            AND i.id IS NULL
            AND p.nome LIKE ?
          ORDER BY p.nome";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 's', $curinga);
    mysqli_stmt_execute($stmt);
    $encontrados = mysqli_stmt_get_result($stmt);
}

$titulo    = 'Movimentação';
$subtitulo = 'Internar, transferir e reinternar';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'movido'    => 'Paciente transferido de leito.',
    'internado' => 'Paciente internado.'
);

$avisos_erro = array(
    'leito_ocupado'  => 'Esse leito já foi ocupado. Atualize a tela e escolha outro.',
    'leito_invalido' => 'Leito inválido ou fora de uso.',
    'ja_internado'   => 'Esse paciente já está internado.',
    'nao_encontrado' => 'Registro não encontrado.',
    'mesmo_leito'    => 'O paciente já está nesse leito.'
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

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="avatar"><span class="avatar-initial rounded bg-label-primary">
          <i class="icon-base bx bx-bed"></i></span></div>
        <div>
          <h5 class="mb-0"><?php echo mysqli_num_rows($internados); ?></h5>
          <small class="text-body-secondary">internados agora</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="avatar"><span class="avatar-initial rounded bg-label-success">
          <i class="icon-base bx bx-check"></i></span></div>
        <div>
          <h5 class="mb-0"><?php echo count($leitos_livres); ?></h5>
          <small class="text-body-secondary">leitos livres</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================================================================
     PARTE 1 — TROCAR DE LEITO
     ================================================================== -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">Internados — trocar de leito</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito atual</th>
          <th>Paciente</th>
          <th>Desde</th>
          <th style="width:38%">Mover para</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if (mysqli_num_rows($internados) == 0) {
      ?>
        <tr><td colspan="4" class="text-center text-body-secondary py-4">
          Nenhum paciente internado no momento.
        </td></tr>
      <?php
      }

      while ($i = mysqli_fetch_assoc($internados)) {
      ?>
        <tr>
          <td>
            <?php if ($i['leito'] == '') { ?>
              <span class="text-body-secondary">sem leito</span>
            <?php } else { ?>
              <strong><?php echo htmlspecialchars($i['leito']); ?></strong><br>
              <small class="text-body-secondary"><?php echo htmlspecialchars($i['setor']); ?></small>
            <?php } ?>
          </td>

          <td>
            <strong><?php echo htmlspecialchars($i['nome']); ?></strong>,
            <?php echo $i['idade']; ?> anos
            <?php
            $texto_alergia = trim($i['alergias']);
            $nega = negaAlergias($texto_alergia);
            if (!$nega) {
            ?>
              <br><span class="valor-alterado small">
                <i class="icon-base bx bx-error-circle"></i>
                <?php echo htmlspecialchars($texto_alergia); ?>
              </span>
            <?php } ?>
          </td>

          <td class="text-body-secondary">
            <?php echo date('d/m/Y', strtotime($i['data_admissao'])); ?>
          </td>

          <td>
            <!-- Um formulário por linha. Cada um leva escondido o id
                 da internação que vai mudar de leito. -->
            <form action="internacao_mover.php" method="post" class="d-flex gap-2">
              <input type="hidden" name="internacao_id" value="<?php echo $i['internacao_id']; ?>">

              <select name="leito_id" class="form-select form-select-sm" required>
                <option value="">Escolha o leito…</option>
                <?php foreach ($leitos_livres as $l) { ?>
                  <option value="<?php echo $l['id']; ?>">
                    <?php echo htmlspecialchars($l['identificacao']); ?>
                    — <?php echo htmlspecialchars($l['setor']); ?>
                  </option>
                <?php } ?>
              </select>

              <button type="submit" class="btn btn-sm btn-primary text-nowrap"
                      <?php if (count($leitos_livres) == 0) { echo 'disabled'; } ?>>
                Mover
              </button>
            </form>
          </td>
        </tr>
      <?php } ?>

      </tbody>
    </table>
  </div>
</div>

<!-- ==================================================================
     PARTE 2 — INTERNAR OU REINTERNAR
     ================================================================== -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-1">Internar um paciente já cadastrado</h5>
    <small class="text-body-secondary">
      Serve tanto para quem acabou de ser cadastrado quanto para quem já esteve
      internado antes e voltou. Em nenhum dos dois casos se cadastra de novo.
    </small>
  </div>

  <div class="card-body">
    <form method="get" action="internacao_movimentar.php" class="mb-3">
      <div class="input-group">
        <input type="text" name="busca" class="form-control"
               placeholder="Buscar paciente pelo nome…"
               value="<?php echo htmlspecialchars($busca); ?>">
        <button class="btn btn-outline-primary" type="submit">Buscar</button>
        <?php if ($busca != '') { ?>
          <a href="internacao_movimentar.php" class="btn btn-outline-secondary">Limpar</a>
        <?php } ?>
      </div>
      <div class="form-text">
        A busca mostra só quem <strong>não está internado</strong> no momento.
      </div>
    </form>

    <?php
    if ($encontrados === null) {
    ?>
      <p class="text-body-secondary mb-0">
        Digite um nome acima para encontrar o paciente.
      </p>
    <?php
    } else if (mysqli_num_rows($encontrados) == 0) {
    ?>
      <div class="alert alert-warning mb-0" role="alert">
        Nenhum paciente não internado com esse nome.
        Se a pessoa nunca esteve aqui,
        <a href="paciente_form.php" class="alert-link">cadastre primeiro</a>.
      </div>
    <?php
    } else {
    ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>Idade</th>
              <th>Última alta</th>
              <th style="width:38%">Internar em</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($p = mysqli_fetch_assoc($encontrados)) { ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($p['nome']); ?></strong>
                <?php
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

              <td><?php echo $p['idade']; ?> anos</td>

              <td class="text-body-secondary">
                <?php
                // Se já teve alta antes, é uma REINTERNAÇÃO.
                if ($p['ultima_alta'] === null) {
                    echo '<span class="badge bg-label-secondary">nunca internado</span>';
                } else {
                    echo date('d/m/Y', strtotime($p['ultima_alta']));
                    echo '<br><span class="badge bg-label-primary">reinternação</span>';
                }
                ?>
              </td>

              <td>
                <form action="internacao_admitir.php" method="post" class="d-flex gap-2">
                  <input type="hidden" name="paciente_id" value="<?php echo $p['id']; ?>">

                  <select name="leito_id" class="form-select form-select-sm" required>
                    <option value="">Escolha o leito…</option>
                    <?php foreach ($leitos_livres as $l) { ?>
                      <option value="<?php echo $l['id']; ?>">
                        <?php echo htmlspecialchars($l['identificacao']); ?>
                        — <?php echo htmlspecialchars($l['setor']); ?>
                      </option>
                    <?php } ?>
                  </select>

                  <button type="submit" class="btn btn-sm btn-primary text-nowrap"
                          <?php if (count($leitos_livres) == 0) { echo 'disabled'; } ?>>
                    Internar
                  </button>
                </form>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</div>

<?php if (count($leitos_livres) == 0) { ?>
  <div class="alert alert-warning mt-4" role="alert">
    <strong>Não há leito livre.</strong>
    Todos os leitos ativos estão ocupados. Dê alta a alguém ou
    <a href="leito_form.php" class="alert-link">cadastre um leito novo</a>.
  </div>
<?php } ?>

<?php
mysqli_close($conexao);
require 'includes/rodape.php';
?>
