<?php
// ===================================================================
//  usuario_form.php  —  Formulário de usuário
//  Módulo 1 · Sprint 2 · perfil administrador
//
//  UM formulário só serve para as duas coisas:
//    sem ?id  -> cadastrar um usuário novo
//    com ?id  -> editar um usuário que já existe
//
//  Ele não grava nada. Quem grava é o usuario_salvar.php.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'includes/perfis.php';
require 'config/conexao.php';

// Valores em branco, para o caso de ser um cadastro novo.
$id       = 0;
$nome     = '';
$login    = '';
$registro = '';
$perfil   = 'tecnico';
$editando = false;

// -------------------------------------------------------------------
//  MODO EDIÇÃO
//  Veio um id pela URL? Então busca esse usuário no banco.
// -------------------------------------------------------------------
if (isset($_GET['id'])) {

    // (int) força o valor a virar número inteiro. Se alguém digitar
    // "abc" ou um pedaço de SQL na URL, vira 0 e não acha ninguém.
    $id = (int) $_GET['id'];

    // COALESCE troca nulo por texto vazio ainda no banco. Administrador
    // e recepção não têm registro profissional, e mandar NULL para
    // htmlspecialchars() imprime um aviso na tela a partir do PHP 8.1.
    $sql  = "SELECT id, nome, login, perfil,
                    COALESCE(registro_profissional, '') AS registro_profissional
             FROM usuarios WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $usuario   = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if (!$usuario) {
        header('Location: usuario_listar.php?erro=nao_encontrado');
        exit;
    }

    $nome     = $usuario['nome'];
    $login    = $usuario['login'];
    $perfil   = $usuario['perfil'];
    $registro = $usuario['registro_profissional'];
    $editando = true;
}

// -------------------------------------------------------------------
//  VOLTOU DE UM ERRO?
//  O usuario_salvar.php devolve o que a pessoa tinha digitado pela
//  URL, para ela não precisar preencher tudo de novo.
//  Repare que a SENHA nunca volta por aqui — senha não anda na URL.
// -------------------------------------------------------------------
if (isset($_GET['nome']))     { $nome     = $_GET['nome']; }
if (isset($_GET['login']))    { $login    = $_GET['login']; }
if (isset($_GET['registro'])) { $registro = $_GET['registro']; }
if (isset($_GET['perfil']))   { $perfil   = $_GET['perfil']; }

mysqli_close($conexao);

$titulo    = ($editando ? 'Editar usuário' : 'Novo usuário');
$subtitulo = ($editando ? $login : 'Cadastro de acesso ao sistema');
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'login_repetido'  => 'Já existe um usuário com esse login. Escolha outro.',
    'campos'          => 'Preencha todos os campos obrigatórios.',
    'senha_curta'     => 'A senha precisa ter pelo menos 4 caracteres.',
    'perfil_invalido' => 'Perfil inválido.'
);

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php } ?>

<div class="row">
  <div class="col-lg-8">

    <form action="usuario_salvar.php" method="post">

      <!-- Em modo edição, o id viaja escondido dentro do formulário.
           É assim que o usuario_salvar.php sabe se é para inserir um
           registro novo ou atualizar um que já existe. -->
      <?php if ($editando) { ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php } ?>

      <div class="card">
        <div class="card-body">

          <div class="mb-3">
            <label class="form-label" for="nome">Nome completo *</label>
            <input type="text" class="form-control" id="nome" name="nome"
                   value="<?php echo htmlspecialchars($nome); ?>"
                   placeholder="ex.: Téc. Carlos Menezes" required>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="login">Login *</label>
              <input type="text" class="form-control" id="login" name="login"
                     value="<?php echo htmlspecialchars($login); ?>"
                     placeholder="ex.: carlos" required>
              <div class="form-text">Sem espaços. Não pode repetir.</div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label" for="perfil">Perfil *</label>
              <select class="form-select" id="perfil" name="perfil" required>
                <?php
                // O laço monta as quatro opções a partir do arquivo
                // includes/perfis.php. Se um dia entrar um perfil novo,
                // ele aparece aqui sozinho.
                foreach (listaDePerfis() as $chave => $rotulo) {
                    $marcado = ($chave == $perfil) ? 'selected' : '';
                    echo '<option value="' . $chave . '" ' . $marcado . '>'
                       . $rotulo . '</option>';
                }
                ?>
              </select>
              <div class="form-text">Define o que a pessoa pode fazer no sistema.</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="registro">Registro profissional</label>
            <input type="text" class="form-control" id="registro" name="registro"
                   value="<?php echo htmlspecialchars($registro); ?>"
                   placeholder="ex.: COREN-RS 000002">
            <div class="form-text">
              CRM para médico, COREN para técnico. Deixe em branco para administrador e recepção.
            </div>
          </div>

        </div>
      </div>

      <div class="card mt-4">
        <div class="card-body">

          <h6 class="mb-3">
            <?php echo ($editando ? 'Redefinir senha' : 'Senha de acesso'); ?>
          </h6>

          <div class="mb-3">
            <label class="form-label" for="senha">
              Senha <?php echo ($editando ? '' : '*'); ?>
            </label>
            <input type="password" class="form-control" id="senha" name="senha"
                   placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                   <?php echo ($editando ? '' : 'required'); ?>>
            <div class="form-text">
              <?php if ($editando) { ?>
                <strong>Deixe em branco para manter a senha atual.</strong>
                Preencha só se quiser trocá-la.
              <?php } else { ?>
                Mínimo de 4 caracteres. Depois de salva, nem o administrador
                consegue vê-la.
              <?php } ?>
            </div>
          </div>

        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary">
          <?php echo ($editando ? 'Salvar alterações' : 'Cadastrar usuário'); ?>
        </button>
        <a href="usuario_listar.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>

    </form>

  </div>

</div>

<?php require 'includes/rodape.php'; ?>
