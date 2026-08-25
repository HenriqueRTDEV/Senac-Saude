<?php
// ===================================================================
//  usuario_listar.php  —  Listagem de usuários
//  Módulo 1 · Sprint 2 · perfil administrador
//
//  É o "R" do CRUD: ler o banco e mostrar na tela.
// ===================================================================

// A tranca vem sempre primeiro, antes de qualquer outra coisa.


// E logo depois, quem pode abrir esta tela.
// Esconder o item no menu não basta: sem estas duas linhas, qualquer
// pessoa logada abre esta página digitando o endereço no navegador.


// -------------------------------------------------------------------
//  A BUSCA
//  Se o formulário não foi usado, $busca fica vazio.
// -------------------------------------------------------------------


// O % é o curinga do LIKE: quer dizer "qualquer coisa aqui".
// Repare no truque: se a busca está vazia, o curinga vira '%%',
// que casa com tudo. Assim uma consulta só serve para os dois casos
// e não precisamos de um if em volta do SQL.

?>

<?php
// -------------------------------------------------------------------
//  MENSAGENS
//  As outras páginas voltam para cá com ?ok=... ou ?erro=...
// -------------------------------------------------------------------




?>
 
<?php

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Usuários cadastrados</h5>
    <small class="text-body-secondary">
      <?php ?>
      <?php ?>
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
      value="<?php ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php  ?>
      <a href="usuario_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php  ?>
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
      
      ?>
        <tr>
          <td colspan="6" class="text-center text-body-secondary py-4">
            Nenhum usuário encontrado para essa busca.
          </td>
        </tr>
      <?php
      

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
