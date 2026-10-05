<?php
// ===================================================================
//  leito_form.php  —  Cadastro e edição de leito
//  Módulo 2 · Sprint 3 · perfil recepção
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';

$id            = 0;
$identificacao = '';
$setor_id      = 0;
$observacao    = '';
$editando      = false;

// A lista de setores alimenta o <select>. Só os ativos: um setor
// desativado não deve aparecer como opção para leito novo.
$setores = array();
$res = mysqli_query($conexao, "SELECT id, nome FROM setores WHERE ativo = 1 ORDER BY nome");
while ($s = mysqli_fetch_assoc($res)) {
    $setores[] = $s;
}

if (isset($_GET['id'])) {

    $id = (int) $_GET['id'];

    // COALESCE troca nulo por texto vazio ainda no banco — senão o
    // htmlspecialchars() do formulário recebe NULL e reclama.
    $sql  = "SELECT id, identificacao,
                    COALESCE(setor_id, 0)    AS setor_id,
                    COALESCE(observacao, '') AS observacao
             FROM leitos WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $leito     = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if (!$leito) {
        header('Location: leito_listar.php?erro=nao_encontrado');
        exit;
    }

    $identificacao = $leito['identificacao'];
    $setor_id      = $leito['setor_id'];
    $observacao    = $leito['observacao'];
    $editando      = true;
}

if (isset($_GET['identificacao'])) { $identificacao = $_GET['identificacao']; }
if (isset($_GET['setor_id']))      { $setor_id      = (int) $_GET['setor_id']; }
if (isset($_GET['observacao']))    { $observacao    = $_GET['observacao']; }

mysqli_close($conexao);

$titulo    = ($editando ? 'Editar leito' : 'Novo leito');
$subtitulo = ($editando ? $identificacao : 'Cadastro de leito');
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'campos'        => 'Preencha a identificação e escolha o setor.',
    'repetido'      => 'Já existe um leito com essa identificação.',
    'setor_invalido'=> 'Setor inválido ou desativado.'
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
    <form action="leito_salvar.php" method="post">

      <?php if ($editando) { ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php } ?>

      <div class="card">
        <div class="card-body">

          <div class="mb-3">
            <label class="form-label" for="identificacao">Identificação *</label>
            <input type="text" class="form-control" id="identificacao" name="identificacao"
                   value="<?php echo htmlspecialchars($identificacao); ?>"
                   placeholder="ex.: 101-A" style="max-width:220px"
                   maxlength="10" required>
            <div class="form-text">
              Como o leito é chamado na ala. Não pode repetir.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="setor_id">Setor *</label>
            <select class="form-select" id="setor_id" name="setor_id"
                    style="max-width:320px" required>
              <option value="">Escolha o setor…</option>
              <?php foreach ($setores as $s) { ?>
                <option value="<?php echo $s['id']; ?>"
                  <?php if ($s['id'] == $setor_id) { echo 'selected'; } ?>>
                  <?php echo htmlspecialchars($s['nome']); ?>
                </option>
              <?php } ?>
            </select>
            <div class="form-text">
              Os setores são cadastrados pelo administrador.
              Falta algum na lista? Peça para ele incluir.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="observacao">Observação</label>
            <input type="text" class="form-control" id="observacao" name="observacao"
                   value="<?php echo htmlspecialchars($observacao); ?>"
                   placeholder="ex.: leito de isolamento" maxlength="200">
          </div>

        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary">
          <?php echo ($editando ? 'Salvar alterações' : 'Cadastrar leito'); ?>
        </button>
        <a href="leito_listar.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>

    </form>
  </div>

</div>

<?php require 'includes/rodape.php'; ?>
