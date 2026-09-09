<?php
// ===================================================================
//  index.php  —  Tela de login do Prontuário Eletrônico TDS
//
//  É a porta de entrada do sistema. Repare que esta página NÃO usa o
//  includes/cabecalho.php: quem ainda não entrou não pode ver o menu.
//
//  O formulário envia usuário e senha por POST para autenticar.php,
//  que confere no banco e cria a sessão.
// ===================================================================

// Se autenticar.php recusou o login, ele volta para cá com ?erro=1
$deu_erro = isset($_GET['erro']);

// Se o usuário acabou de sair, volta para cá com ?saiu=1
$acabou_de_sair = isset($_GET['saiu']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Entrar · Prontuário TDS</title>

  <link rel="icon" type="image/x-icon" href="assets/img/favicon/prontus.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/prontus-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/img/prontus-180.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
    rel="stylesheet">

  <!-- Ícones e CSS do template Bootstrap -->
  <link rel="stylesheet" href="assets/vendor/fonts/iconify-icons.css">
  <link rel="stylesheet" href="assets/vendor/css/core.css">

  <!-- Nosso CSS: é ele que troca o roxo do template pelo verde -->
  <link rel="stylesheet" href="assets/css/prontuario.css">
</head>

<body>

<div class="container-fluid p-0">
  <div class="row g-0 min-vh-100">

    <!-- ============================================================
         COLUNA DA ESQUERDA — a marca
         d-none d-lg-flex = fica escondida no celular e aparece
         a partir de telas grandes.
         ============================================================ -->
    <div class="col-lg-6 login-marca d-none d-lg-flex flex-column justify-content-between p-5">

      <div class="fw-bold d-flex align-items-center gap-2">
        <img src="assets/img/prontus-64.png" alt="" class="marca-prontus">
        Prontuário TDS
      </div>

      <div>
        <span class="login-selo mb-3">Sistema didático</span>
        <h1 class="fw-bold mt-3 mb-3">Prontuário Eletrônico do laboratório de Enfermagem</h1>
        <p class="mb-0">
          Desenvolvido pela turma de Técnico em Desenvolvimento de Sistemas,
          para a turma de Técnico em Enfermagem praticar o registro assistencial.
        </p>
      </div>

      <div class="small opacity-75">PHP + MySQL · Turma 2025</div>
    </div>

    <!-- ============================================================
         COLUNA DA DIREITA — o formulário
         ============================================================ -->
    <div class="col-lg-6 d-flex align-items-center justify-content-center p-4">
      <div class="login-formulario w-100">

        <!-- Marca reduzida, só aparece no celular -->
        <div class="d-lg-none text-center mb-4">
          <img src="assets/img/prontus-64.png" alt="" class="marca-prontus">
          <span class="fw-bold ms-1">Prontuário TDS</span>
        </div>

        <h4 class="mb-1">Entrar</h4>
        <p class="text-body-secondary mb-4">
          Use o login e a senha cadastrados pelo administrador.
        </p>

        <?php if($deu_erro){ ?>
        <div class="alert alert-danger d-flex aling-itens-center" role="alert">
          <i class="icon-base bx bx-error-circle me-2"></i>
          <div>Usuário ou senha incorretos.</div>
        </div>
        <?php } ?>

        <?php if($acabou_de_sair){ ?>
        <div class="alert alert-success d-flex aling-itens-center" role="alert">
          <i class="icon-base bx bx-check-circle me-2"></i>
          <div>Você saiu do sistema com segurança.</div>
        </div>
        <?php } ?>
        <form action="autenticar.php" method="post">

          <div class="mb-3">
            <label class="rotulo-campo d-block" for="login">Usuário</label>
            <input
              type="text"
              class="form-control"
              id="login"
              name="login"
              placeholder="ex.: carlos"
              autofocus
              required>
          </div>

          <div class="mb-4">
            <label class="rotulo-campo d-block" for="senha">Senha</label>
            <div class="input-group">
              <input
                type="password"
                class="form-control"
                id="senha"
                name="senha"
                placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                required>
              <span class="input-group-text" id="verSenha" style="cursor:pointer">
                <i class="icon-base bx bx-hide" id="iconeOlho"></i>
              </span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">Entrar</button>

        </form>

        <!-- Exigência do documento de escopo: este aviso fica na tela de login -->
        <div class="alert alert-warning mt-4 mb-3 py-2 small" role="alert">
          <strong>Sistema didático — dados fictícios.</strong>
          Proibido cadastrar dados de pacientes reais.
        </div>

        <p class="small text-body-secondary mb-0">
          Usuários de teste:
          <code>fernanda</code> ·
          <code>ricardo</code> ·
          <code>carlos</code>
          — senha <code>123456</code>
        </p>

      </div>
    </div>

  </div>
</div>

<script>
  // Mostrar / esconder a senha.
  // Trocamos o type do campo entre "password" e "text".
  var botaoOlho = document.getElementById('verSenha');
  var campoSenha = document.getElementById('senha');
  var iconeOlho = document.getElementById('iconeOlho');

  botaoOlho.addEventListener('click', function () {
    if (campoSenha.type === 'password') {
      campoSenha.type = 'text';
      iconeOlho.className = 'icon-base bx bx-show';
    } else {
      campoSenha.type = 'password';
      iconeOlho.className = 'icon-base bx bx-hide';
    }
  });
</script>

</body>
</html>
