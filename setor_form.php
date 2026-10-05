<?php
// ===================================================================
//  setor_form.php  —  Cadastro e edição de setor
//  Módulo 2 · Sprint 3 · perfil ADMINISTRADOR
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'config/conexao.php';

$id        = 0;
$nome      = '';
$descricao = '';
$editando  = false;

if (isset($_GET['id'])) {

    $id = (int) $_GET['id'];

    $sql  = "SELECT id, nome, COALESCE(descricao, '') AS descricao
             FROM setores WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $setor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$setor) {
        header('Location: setor_listar.php?erro=nao_encontrado');
        exit;
    }

    $nome      = $setor['nome'];
    $descricao = $setor['descricao'];
    $editando  = true;
}

if (isset($_GET['nome']))      { $nome      = $_GET['nome']; }
if (isset($_GET['descricao'])) { $descricao = $_GET['descricao']; }

mysqli_close($conexao);

$titulo    = ($editando ? 'Editar setor' : 'Novo setor');
$subtitulo = ($editando ? $nome : 'Cadastro de setor');
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'campos'   => 'Preencha o nome do setor.',
    'repetido' => 'Já existe um setor com esse nome.'
);

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php } ?>

<div class="row">
  <div class="col-lg-7">
    <form action="setor_salvar.php" method="post">

      <?php if ($editando) { ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php } ?>

      <div class="card">
        <div class="card-body">

          <div class="mb-3">
            <label class="form-label" for="nome">Nome *</label>
            <input type="text" class="form-control" id="nome" name="nome"
                   value="<?php echo htmlspecialchars($nome); ?>"
                   placeholder="ex.: Clínica médica" maxlength="60"
                   style="max-width:340px" required>
            <div class="form-text">Não pode repetir.</div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="descricao">Descrição</label>
            <input type="text" class="form-control" id="descricao" name="descricao"
                   value="<?php echo htmlspecialchars($descricao); ?>"
                   placeholder="ex.: Internação clínica geral" maxlength="200">
          </div>

        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary">
          <?php echo ($editando ? 'Salvar alterações' : 'Cadastrar setor'); ?>
        </button>
        <a href="setor_listar.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>

    </form>
  </div>

</div>

<?php require 'includes/rodape.php'; ?>
