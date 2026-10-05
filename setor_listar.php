<?php
// ===================================================================
//  setor_listar.php  —  Setores do hospital
//  Módulo 2 · Sprint 3 · perfil ADMINISTRADOR
//
//  Por que setor é do administrador e leito é da recepção:
//  setor é estrutura da instituição — cria-se uma ala nova a cada
//  poucos anos. Leito é realidade do dia a dia, e quem conhece a
//  ala é a recepção. Cargos diferentes, telas diferentes.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'config/conexao.php';

$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}
$curinga = '%' . $busca . '%';

// Traz cada setor e quantos leitos ele tem — total e ocupados.
// A subconsulta no SELECT roda uma vez por linha; com poucos
// setores isso é irrelevante, e deixa a consulta fácil de ler.
$sql = "SELECT s.id, s.nome, s.ativo,
               COALESCE(s.descricao, '') AS descricao,
               (SELECT COUNT(*) FROM leitos l
                 WHERE l.setor_id = s.id) AS leitos,
               (SELECT COUNT(*) FROM leitos l
                  JOIN internacoes i ON i.leito_id = l.id
                 WHERE l.setor_id = s.id
                   AND i.situacao = 'internado' AND i.ativo = 1) AS ocupados
        FROM setores s
        WHERE s.nome LIKE ? OR COALESCE(s.descricao, '') LIKE ?
        ORDER BY s.ativo DESC, s.nome";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ss', $curinga, $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$quantos   = mysqli_num_rows($resultado);

$titulo    = 'Setores';
$subtitulo = 'Alas do hospital';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'criado'     => 'Setor cadastrado.',
    'atualizado' => 'Setor atualizado.',
    'inativado'  => 'Setor inativado. Ele não aparece mais ao cadastrar leito.',
    'reativado'  => 'Setor reativado.'
);

$avisos_erro = array(
    'campos'         => 'Preencha o nome do setor.',
    'repetido'       => 'Já existe um setor com esse nome.',
    'tem_leito'      => 'Não dá para inativar um setor que ainda tem leito ativo. Mova ou inative os leitos antes.',
    'nao_encontrado' => 'Setor não encontrado.'
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
    <h5 class="mb-0">Setores</h5>
    <small class="text-body-secondary">
      <?php echo $quantos; ?> <?php echo ($quantos == 1 ? 'setor' : 'setores'); ?>
    </small>
  </div>
  <a href="setor_form.php" class="btn btn-primary">
    <i class="icon-base bx bx-plus me-1"></i> Novo setor
  </a>
</div>

<form method="get" action="setor_listar.php" class="mb-4">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por nome ou descrição…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="setor_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Setor</th>
          <th>Descrição</th>
          <th>Leitos</th>
          <th>Ocupação</th>
          <th>Situação</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if ($quantos == 0) {
      ?>
        <tr><td colspan="6" class="text-center text-body-secondary py-4">
          Nenhum setor encontrado.
        </td></tr>
      <?php
      }

      while ($s = mysqli_fetch_assoc($resultado)) {
      ?>
        <tr class="<?php echo ($s['ativo'] ? '' : 'opacity-50'); ?>">

          <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong></td>

          <td class="text-body-secondary">
            <?php
            echo ($s['descricao'] == '' ? '—' : htmlspecialchars($s['descricao']));
            ?>
          </td>

          <td><?php echo $s['leitos']; ?></td>

          <td>
            <?php echo $s['ocupados']; ?> de <?php echo $s['leitos']; ?>
            <?php if ($s['leitos'] > 0 && $s['ocupados'] == $s['leitos']) { ?>
              <span class="badge bg-label-danger ms-1">lotado</span>
            <?php } ?>
          </td>

          <td>
            <?php if ($s['ativo']) { ?>
              <span class="badge bg-label-primary">Ativo</span>
            <?php } else { ?>
              <span class="badge bg-label-secondary">Inativo</span>
            <?php } ?>
          </td>

          <td>
            <a href="setor_form.php?id=<?php echo $s['id']; ?>"
               class="btn btn-sm btn-outline-primary">Editar</a>

            <?php if ($s['ativo']) { ?>
              <a href="setor_inativar.php?id=<?php echo $s['id']; ?>"
                 class="btn btn-sm btn-outline-danger">Inativar</a>
            <?php } else { ?>
              <a href="setor_inativar.php?id=<?php echo $s['id']; ?>"
                 class="btn btn-sm btn-outline-primary">Reativar</a>
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
