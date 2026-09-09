<?php
// ===================================================================
//  usuario_listar.php  —  Listagem de usuários
//  Módulo 1 · Sprint 2 · perfil administrador
//
//  É o "R" do CRUD: ler o banco e mostrar na tela.
// ===================================================================

// A tranca vem sempre primeiro, antes de qualquer outra coisa.
require 'includes/protege.php';

// E logo depois, quem pode abrir esta tela.
// Esconder o item no menu não basta: sem estas duas linhas, qualquer
// pessoa logada abre esta página digitando o endereço no navegador.
require 'includes/permissao.php';
exigirPerfil(array('administrador'));

require 'includes/perfis.php';
require 'config/conexao.php';

// -------------------------------------------------------------------
//  A BUSCA
//  Se o formulário não foi usado, $busca fica vazio.
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}

// O % é o curinga do LIKE: quer dizer "qualquer coisa aqui".
// Repare no truque: se a busca está vazia, o curinga vira '%%',
// que casa com tudo. Assim uma consulta só serve para os dois casos
// e não precisamos de um if em volta do SQL.
$curinga = '%' . $busca . '%';

$sql = "SELECT id, nome, login, perfil, registro_profissional, ativo
        FROM usuarios
        WHERE nome LIKE ? OR login LIKE ?
        ORDER BY ativo DESC, nome";

$stmt = mysqli_prepare($conexao, $sql);              // 1. prepara
mysqli_stmt_bind_param($stmt, 'ss', $curinga, $curinga);  // 2. amarra
mysqli_stmt_execute($stmt);                          // 3. executa
$resultado = mysqli_stmt_get_result($stmt);          // 4. lê

$quantos = mysqli_num_rows($resultado);

$titulo    = 'Usuários';
$subtitulo = 'Quem tem acesso ao sistema';
require 'includes/cabecalho.php';
?>

<?php
// -------------------------------------------------------------------
//  MENSAGENS
//  As outras páginas voltam para cá com ?ok=... ou ?erro=...
// -------------------------------------------------------------------
$avisos_ok = array(
    'criado'      => 'Usuário cadastrado com sucesso.',
    'atualizado'  => 'Dados do usuário atualizados.',
    'senha'       => 'Senha redefinida.',
    'desativado'  => 'Usuário desativado. Ele continua no sistema, mas não entra mais.',
    'reativado'   => 'Usuário reativado.'
);

$avisos_erro = array(
    'login_repetido' => 'Já existe um usuário com esse login. Escolha outro.',
    'campos'         => 'Preencha todos os campos obrigatórios.',
    'nao_encontrado' => 'Usuário não encontrado.',
    'auto_desativar' => 'Você não pode desativar a si mesmo — ficaria sem acesso ao sistema.',
    'perfil_invalido'=> 'Perfil inválido.'
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
    <h5 class="mb-0">Usuários cadastrados</h5>
    <small class="text-body-secondary">
      <?php echo $quantos; ?>
      <?php echo ($quantos == 1 ? 'usuário encontrado' : 'usuários encontrados'); ?>
    </small>
  </div>
  <a href="usuario_form.php" class="btn btn-primary">
    <i class="icon-base bx bx-plus me-1"></i> Novo usuário
  </a>
</div>

<!-- Busca. Vai por GET de propósito: assim o endereço guarda o que
     foi procurado e a página pode ser recarregada ou compartilhada. -->
<form method="get" action="usuario_listar.php" class="mb-4">
  <div class="input-group">
    <input
      type="text"
      name="busca"
      class="form-control"
      placeholder="Buscar por nome ou login…"
      value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="usuario_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Login</th>
          <th>Perfil</th>
          <th>Registro profissional</th>
          <th>Situação</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if ($quantos == 0) {
      ?>
        <tr>
          <td colspan="6" class="text-center text-body-secondary py-4">
            Nenhum usuário encontrado para essa busca.
          </td>
        </tr>
      <?php
      }

      // O laço: repete uma vez para CADA linha que veio do banco.
      while ($u = mysqli_fetch_assoc($resultado)) {

          // Quem está logado não pode desativar a si mesmo.
          $sou_eu = ($u['id'] == $_SESSION['usuario_id']);
      ?>
        <tr class="<?php echo ($u['ativo'] ? '' : 'opacity-50'); ?>">

          <td>
            <!-- htmlspecialchars protege a tela: se alguém cadastrar
                 um nome com sinais de HTML, eles aparecem como texto
                 em vez de virar código na página. -->
            <strong><?php echo htmlspecialchars($u['nome']); ?></strong>
            <?php if ($sou_eu) { ?>
              <span class="badge bg-label-primary ms-1">você</span>
            <?php } ?>
          </td>

          <td><code><?php echo htmlspecialchars($u['login']); ?></code></td>

          <td>
            <span class="selo-perfil perfil-<?php echo $u['perfil']; ?>">
              <?php echo nomeDoPerfil($u['perfil']); ?>
            </span>
          </td>

          <td class="<?php echo ($u['registro_profissional'] == '' ? 'text-body-secondary' : ''); ?>">
            <?php
            echo ($u['registro_profissional'] == ''
                  ? '—'
                  : htmlspecialchars($u['registro_profissional']));
            ?>
          </td>

          <td>
            <?php if ($u['ativo']) { ?>
              <span class="badge bg-label-primary">Ativo</span>
            <?php } else { ?>
              <span class="badge bg-label-secondary">Inativo</span>
            <?php } ?>
          </td>

          <td>
            <a href="usuario_form.php?id=<?php echo $u['id']; ?>"
               class="btn btn-sm btn-outline-primary">Editar</a>

            <?php if ($sou_eu) { ?>
              <button class="btn btn-sm btn-outline-secondary" disabled
                      title="Você não pode desativar a si mesmo">Desativar</button>

            <?php } else if ($u['ativo']) { ?>
              <a href="usuario_desativar.php?id=<?php echo $u['id']; ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Desativar <?php echo htmlspecialchars($u['nome']); ?>? Ele deixa de entrar no sistema, mas continua no prontuário.');">
                Desativar</a>

            <?php } else { ?>
              <a href="usuario_desativar.php?id=<?php echo $u['id']; ?>"
                 class="btn btn-sm btn-outline-primary">Reativar</a>
            <?php } ?>
          </td>

        </tr>
      <?php
      } // fim do while
      ?>

      </tbody>
    </table>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>