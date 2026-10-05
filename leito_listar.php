<?php
// ===================================================================
//  leito_listar.php  —  Os leitos da ala
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  O leito é a cama física. Ele existe independentemente de haver
//  alguém nela — por isso tem tabela própria, e não é mais um texto
//  digitado dentro do cadastro do paciente.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';

$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}
$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  Traz cada leito e, se houver, QUEM está nele agora.
//
//  O LEFT JOIN com a internação em andamento é o que responde
//  "livre ou ocupado?" sem precisar de duas consultas.
// -------------------------------------------------------------------
// COALESCE troca nulo por texto vazio ainda no banco. Sem isso, as
// colunas que aceitam nulo chegariam ao PHP como NULL, e a partir do
// PHP 8.1 mandar NULL para htmlspecialchars() imprime um aviso feio
// no meio da tabela. Resolver na consulta evita o problema em todos
// os lugares onde a coluna for usada.
$sql = "SELECT l.id, l.identificacao, l.ativo,
               COALESCE(s.nome, '')       AS setor,
               COALESCE(l.observacao, '') AS observacao,
               p.id AS paciente_id, p.nome AS paciente
        FROM leitos l
        LEFT JOIN setores s ON s.id = l.setor_id
        LEFT JOIN internacoes i
               ON i.leito_id = l.id
              AND i.situacao = 'internado'
              AND i.ativo = 1
        LEFT JOIN pacientes p ON p.id = i.paciente_id
        WHERE l.identificacao LIKE ? OR COALESCE(s.nome, '') LIKE ?
        ORDER BY l.ativo DESC, l.identificacao";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ss', $curinga, $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$quantos   = mysqli_num_rows($resultado);

$titulo    = 'Leitos';
$subtitulo = 'As camas da ala';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'criado'     => 'Leito cadastrado.',
    'atualizado' => 'Leito atualizado.',
    'inativado'  => 'Leito inativado. Ele não aparece mais na lista de leitos livres.',
    'reativado'  => 'Leito reativado.'
);

$avisos_erro = array(
    'campos'         => 'Preencha a identificação do leito.',
    'repetido'       => 'Já existe um leito com essa identificação.',
    'ocupado'        => 'Não dá para inativar um leito ocupado. Mova o paciente antes.',
    'nao_encontrado' => 'Leito não encontrado.'
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
    <h5 class="mb-0">Leitos</h5>
    <small class="text-body-secondary">
      <?php echo $quantos; ?> <?php echo ($quantos == 1 ? 'leito' : 'leitos'); ?>
    </small>
  </div>
  <a href="leito_form.php" class="btn btn-primary">
    <i class="icon-base bx bx-plus me-1"></i> Novo leito
  </a>
</div>

<form method="get" action="leito_listar.php" class="mb-4">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por identificação ou setor…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="leito_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito</th>
          <th>Setor</th>
          <th>Situação</th>
          <th>Ocupante</th>
          <th>Observação</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if ($quantos == 0) {
      ?>
        <tr><td colspan="6" class="text-center text-body-secondary py-4">
          Nenhum leito encontrado.
        </td></tr>
      <?php
      }

      while ($l = mysqli_fetch_assoc($resultado)) {
          $ocupado = ($l['paciente_id'] !== null);
      ?>
        <tr class="<?php echo ($l['ativo'] ? '' : 'opacity-50'); ?>">

          <td><strong><?php echo htmlspecialchars($l['identificacao']); ?></strong></td>

          <td>
            <?php
            echo ($l['setor'] == ''
                  ? '<span class="text-body-secondary">—</span>'
                  : htmlspecialchars($l['setor']));
            ?>
          </td>

          <td>
            <?php if (!$l['ativo']) { ?>
              <span class="badge bg-label-secondary">Fora de uso</span>
            <?php } else if ($ocupado) { ?>
              <span class="badge bg-label-primary">Ocupado</span>
            <?php } else { ?>
              <span class="badge bg-label-success">Livre</span>
            <?php } ?>
          </td>

          <td>
            <?php
            echo ($ocupado
                  ? htmlspecialchars($l['paciente'])
                  : '<span class="text-body-secondary">—</span>');
            ?>
          </td>

          <td class="text-body-secondary small">
            <?php echo htmlspecialchars($l['observacao']); ?>
          </td>

          <td>
            <a href="leito_form.php?id=<?php echo $l['id']; ?>"
               class="btn btn-sm btn-outline-primary">Editar</a>

            <?php if ($ocupado) { ?>
              <button class="btn btn-sm btn-outline-secondary" disabled
                      title="Mova o paciente antes de inativar">Inativar</button>
            <?php } else if ($l['ativo']) { ?>
              <a href="leito_inativar.php?id=<?php echo $l['id']; ?>"
                 class="btn btn-sm btn-outline-danger">Inativar</a>
            <?php } else { ?>
              <a href="leito_inativar.php?id=<?php echo $l['id']; ?>"
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
