<?php
// ===================================================================
//  internacao_alta.php  —  Alta médica
//  Módulo 2 · Sprint 4 · perfil MÉDICO
//
//  A tela que fecha o ciclo. Até aqui o paciente entrava no leito e
//  não saía mais pela tela — só por SQL.
//
//  Duas tarefas, as duas do médico:
//    · definir o diagnóstico (o CID) da internação
//    · dar alta
//
//  A alta NÃO apaga nada e NÃO solta o leito na mão. Ela grava
//  situacao = 'alta', a data e quem assinou. O leito volta a aparecer
//  como livre porque toda consulta de leito livre pergunta por
//  internação com situacao = 'internado' — mudando a situação, a cama
//  se libera sozinha, e a internação fica no histórico apontando para
//  o leito onde a pessoa esteve.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));

require 'config/conexao.php';
require 'includes/alergia.php';

// -------------------------------------------------------------------
//  O QUE FOI DIGITADO NA BUSCA
//
//  Com um valor vindo de fora, a consulta dos internados usa os
//  quatro passos do mysqli_prepare. Nome de paciente é texto livre,
//  e texto livre concatenado dentro do SQL é a porta da injeção.
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}

// O % de cada lado acha o pedaço em qualquer posição do nome.
// Busca vazia vira '%%', que casa com todos.
$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  1. QUEM ESTÁ INTERNADO
// -------------------------------------------------------------------
$sql = "SELECT i.id AS internacao_id, i.data_admissao, i.cid_id,
               TIMESTAMPDIFF(DAY, i.data_admissao, NOW()) AS dias,
               p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '')         AS setor,
               COALESCE(c.codigo, '')       AS cid_codigo,
               COALESCE(c.descricao, '')    AS cid_descricao
        FROM internacoes i
        JOIN pacientes p ON p.id = i.paciente_id
        LEFT JOIN leitos  l ON l.id = i.leito_id
        LEFT JOIN setores s ON s.id = l.setor_id
        LEFT JOIN cids    c ON c.id = i.cid_id
        WHERE i.situacao = 'internado' AND i.ativo = 1
          AND p.nome LIKE ?
        ORDER BY l.identificacao";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 's', $curinga);
mysqli_stmt_execute($stmt);
$internados = mysqli_stmt_get_result($stmt);

// O $stmt fica aberto até o fim da página, como nas outras
// listagens. O statement da confirmação abaixo tem nome próprio
// ($stmt_confirmar) justamente para não atropelar este.

// -------------------------------------------------------------------
//  2. O CATÁLOGO DE DIAGNÓSTICOS
//
//  Vai para um array porque a lista é usada uma vez por linha da
//  tabela. Um resultado do banco se lê uma vez; um array se percorre
//  quantas vezes quiser.
//
//  Este catálogo não tem tela de cadastro em nenhum perfil: manter a
//  tabela de CIDs é trabalho da farmácia e do faturamento, e o sistema
//  cobre enfermagem. O médico escolhe de uma lista que ele não edita.
// -------------------------------------------------------------------
$res_cids = mysqli_query($conexao,
    "SELECT id, codigo, descricao FROM cids WHERE ativo = 1 ORDER BY codigo");

$cids = array();
while ($c = mysqli_fetch_assoc($res_cids)) {
    $cids[] = $c;
}

// -------------------------------------------------------------------
//  3. A CONFIRMAÇÃO DA ALTA
//
//  Chegar aqui com ?alta=15 não dá alta em ninguém: só mostra o painel
//  de confirmação. Quem grava é o formulário desse painel, por POST.
//
//  A divisão é de propósito e vale a aula: endereço que se digita no
//  navegador (GET) só MOSTRA; quem MUDA o banco é o POST. Se a alta
//  fosse um link comum, bastaria alguém compartilhar o endereço — ou o
//  navegador adiantar a visita ao link — para dar alta sem ninguém
//  clicar em nada.
// -------------------------------------------------------------------
$confirmar = null;

if (isset($_GET['alta'])) {

    $id_confirmar = (int) $_GET['alta'];

    $sql = "SELECT i.id, i.data_admissao, i.cid_id,
                   TIMESTAMPDIFF(DAY, i.data_admissao, NOW()) AS dias,
                   p.nome,
                   TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
                   COALESCE(l.identificacao, '') AS leito,
                   COALESCE(c.codigo, '')    AS cid_codigo,
                   COALESCE(c.descricao, '') AS cid_descricao
            FROM internacoes i
            JOIN pacientes p ON p.id = i.paciente_id
            LEFT JOIN leitos l ON l.id = i.leito_id
            LEFT JOIN cids   c ON c.id = i.cid_id
            WHERE i.id = ? AND i.situacao = 'internado' AND i.ativo = 1";

    $stmt_confirmar = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt_confirmar, 'i', $id_confirmar);
    mysqli_stmt_execute($stmt_confirmar);
    $confirmar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_confirmar));
    mysqli_stmt_close($stmt_confirmar);
}

$titulo    = 'Alta médica';
$subtitulo = 'Diagnóstico e alta';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    // A mensagem da alta é montada mais abaixo, porque diz quantas
    // prescrições foram encerradas junto — número que varia.
    'alta'        => '',
    'diagnostico' => 'Diagnóstico salvo.'
);

$avisos_erro = array(
    'sem_cid'        => 'Defina o diagnóstico antes de dar alta.',
    'cid_invalido'   => 'Diagnóstico inválido ou fora de uso.',
    'nao_encontrado' => 'Internação não encontrada.',
    'ja_teve_alta'   => 'Esse paciente já recebeu alta. Atualize a tela.'
);

if (isset($_GET['ok']) && $_GET['ok'] == 'alta') {

    // Quantas prescrições a alta encerrou. Vem do arquivo que gravou.
    $encerradas = isset($_GET['encerradas']) ? (int) $_GET['encerradas'] : 0;
?>
  <div class="alert alert-success" role="alert">
    <div class="d-flex align-items-center">
      <i class="icon-base bx bx-check-circle me-2"></i>
      <div>
        Alta registrada. O leito voltou a ficar livre.
        <?php if ($encerradas > 0) { ?>
          <strong><?php echo $encerradas; ?></strong>
          <?php echo ($encerradas == 1 ? 'prescrição foi encerrada' : 'prescrições foram encerradas'); ?>
          junto, e as doses pendentes dela<?php echo ($encerradas == 1 ? '' : 's'); ?>
          saíram do turno.
        <?php } ?>
      </div>
    </div>
  </div>
<?php
} else if (isset($_GET['ok']) && isset($avisos_ok[$_GET['ok']]) && $avisos_ok[$_GET['ok']] != '') {
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

<?php
// -------------------------------------------------------------------
//  PAINEL DE CONFIRMAÇÃO
// -------------------------------------------------------------------
if (isset($_GET['alta']) && !$confirmar) {
?>
  <div class="alert alert-warning" role="alert">
    Internação não encontrada, ou o paciente já recebeu alta.
  </div>
<?php
} else if ($confirmar) {
?>
  <div class="card border border-warning mb-4">
    <div class="card-body">

      <h5 class="mb-3">Confirmar alta</h5>

      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <small class="text-body-secondary d-block">Paciente</small>
          <strong><?php echo htmlspecialchars($confirmar['nome']); ?></strong>,
          <?php echo $confirmar['idade']; ?> anos
        </div>
        <div class="col-sm-3">
          <small class="text-body-secondary d-block">Leito</small>
          <strong>
            <?php
            echo ($confirmar['leito'] == ''
                  ? '<span class="text-body-secondary">sem leito</span>'
                  : htmlspecialchars($confirmar['leito']));
            ?>
          </strong>
        </div>
        <div class="col-sm-3">
          <small class="text-body-secondary d-block">Internado há</small>
          <strong><?php echo $confirmar['dias']; ?>
            <?php echo ($confirmar['dias'] == 1 ? 'dia' : 'dias'); ?></strong>
        </div>
      </div>

      <div class="mb-3">
        <small class="text-body-secondary d-block">Diagnóstico</small>
        <?php if ($confirmar['cid_codigo'] == '') { ?>
          <span class="text-danger fw-bold">Não definido</span>
        <?php } else { ?>
          <span class="badge bg-label-info"><?php echo htmlspecialchars($confirmar['cid_codigo']); ?></span>
          <?php echo htmlspecialchars($confirmar['cid_descricao']); ?>
        <?php } ?>
      </div>

      <?php if ($confirmar['cid_id'] === null) { ?>

        <div class="alert alert-danger mb-0">
          Não é possível dar alta sem diagnóstico. Escolha o CID na lista abaixo
          e salve antes de encerrar a internação.
        </div>

      <?php } else { ?>

        <p class="text-body-secondary">
          A internação será encerrada com a data e a hora de agora, assinada com o seu
          nome, e o leito voltará a ficar livre. Se a alta for registrada por engano,
          o caminho é a recepção internar o paciente novamente — o que abre uma nova
          internação e mantém esta no histórico.
        </p>

        <form action="internacao_alta_salvar.php" method="post" class="d-flex gap-2">
          <input type="hidden" name="internacao_id" value="<?php echo $confirmar['id']; ?>">
          <button type="submit" class="btn btn-primary">
            <i class="icon-base bx bx-user-check me-1"></i> Confirmar alta
          </button>
          <a href="internacao_alta.php<?php if ($busca != '') { echo '?busca=' . urlencode($busca); } ?>" class="btn btn-outline-secondary">Cancelar</a>
        </form>

      <?php } ?>

    </div>
  </div>
<?php
}
?>

<?php
// Conta quantos ainda estão sem diagnóstico, para o cabeçalho da lista.
// O resultado do banco será percorrido no laço abaixo, então a contagem
// não pode consumi-lo — daí guardar as linhas num array primeiro.
$lista = array();
while ($i = mysqli_fetch_assoc($internados)) {
    $lista[] = $i;
}

$sem_diagnostico = 0;
foreach ($lista as $i) {
    if ($i['cid_id'] === null) {
        $sem_diagnostico = $sem_diagnostico + 1;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Pacientes internados</h5>
    <small class="text-body-secondary">
      <?php echo count($lista); ?>
      <?php echo (count($lista) == 1 ? 'internado' : 'internados'); ?>
      <?php if ($sem_diagnostico > 0) { ?>
        · <span class="text-danger fw-bold">
            <?php echo $sem_diagnostico; ?> sem diagnóstico
          </span>
      <?php } ?>
    </small>
  </div>
</div>

<form method="get" action="internacao_alta.php" class="mb-3">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por nome…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="internacao_alta.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito</th>
          <th>Paciente</th>
          <th>Desde</th>
          <th style="width:36%">Diagnóstico (CID)</th>
          <th>Alta</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if (count($lista) == 0) {
      ?>
        <tr><td colspan="5" class="text-center text-body-secondary py-4">
          <?php
          // "não há ninguém internado" e "a busca não achou" são
          // situações diferentes; dizer a errada faz a pessoa procurar
          // problema onde não tem.
          echo ($busca == ''
                ? 'Nenhum paciente internado no momento.'
                : 'Nenhum internado com esse nome.');
          ?>
        </td></tr>
      <?php
      }

      foreach ($lista as $i) {
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
            // REGRA DE OURO Nº 4 — alergia sempre em destaque.
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

          <td class="text-body-secondary text-nowrap">
            <?php echo date('d/m/Y', strtotime($i['data_admissao'])); ?><br>
            <small><?php echo $i['dias']; ?>
              <?php echo ($i['dias'] == 1 ? 'dia' : 'dias'); ?></small>
          </td>

          <td>
            <!-- Um formulário por linha, cada um com o id da sua
                 internação escondido. O <select> já vem marcado no
                 CID atual, então salvar de novo sem mexer não muda
                 nada — e trocar corrige o diagnóstico. -->
            <form action="internacao_cid_salvar.php" method="post" class="d-flex gap-2">
              <input type="hidden" name="internacao_id" value="<?php echo $i['internacao_id']; ?>">

              <select name="cid_id" class="form-select form-select-sm" required>
                <option value="">Escolha o diagnóstico…</option>
                <?php foreach ($cids as $c) { ?>
                  <option value="<?php echo $c['id']; ?>"
                    <?php if ($i['cid_id'] == $c['id']) { echo 'selected'; } ?>>
                    <?php echo htmlspecialchars($c['codigo']); ?>
                    — <?php echo htmlspecialchars($c['descricao']); ?>
                  </option>
                <?php } ?>
              </select>

              <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                Salvar
              </button>
            </form>
          </td>

          <td>
            <?php if ($i['cid_id'] === null) { ?>
              <button class="btn btn-sm btn-outline-secondary text-nowrap" disabled
                      title="Defina o diagnóstico antes de dar alta">
                Dar alta
              </button>
            <?php } else { ?>
              <a href="internacao_alta.php?alta=<?php echo $i['internacao_id']; ?><?php if ($busca != '') { echo '&busca=' . urlencode($busca); } ?>"
                 class="btn btn-sm btn-outline-primary text-nowrap">
                Dar alta
              </a>
            <?php } ?>
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
