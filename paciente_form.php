<?php
// ===================================================================
//  paciente_form.php  —  Admissão e edição de paciente
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Sem ?id  -> cadastrar uma pessoa nova
//  Com ?id  -> corrigir um cadastro que já existe
//
//  ESTE FORMULÁRIO NÃO INTERNA NINGUÉM.
//  Ele cuida só de QUEM A PESSOA É: nome, nascimento, documento,
//  contato, acompanhante e alergias. Isso vale para a vida inteira
//  dela, e não muda de uma internação para outra.
//
//  Colocar em um leito é outra coisa, e fica na tela de
//  Movimentação. A separação é o que permite o mesmo paciente
//  internar, receber alta e voltar sem ser cadastrado de novo.
//
//  Também não tem diagnóstico nem alta: são do médico.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';
require 'includes/alergia.php';

// Valores em branco, para o caso de ser uma admissão nova.
$id          = 0;
$nome        = '';
$nascimento  = '';
$sexo        = '';
$cartao      = '';
$telefone    = '';
$endereco    = '';
$responsavel = '';
$alergias    = '';
$editando    = false;

// -------------------------------------------------------------------
//  MODO EDIÇÃO
// -------------------------------------------------------------------
if (isset($_GET['id'])) {

    $id = (int) $_GET['id'];

    // COALESCE troca nulo por texto vazio ainda no banco. Cartão SUS,
    // telefone, endereço e acompanhante aceitam nulo — e mandar NULL
    // para htmlspecialchars() imprime um aviso na tela a partir do
    // PHP 8.1. Resolvido aqui, o formulário fica limpo.
    $sql  = "SELECT id, nome, data_nascimento, sexo,
                    COALESCE(cartao_sus, '')  AS cartao_sus,
                    COALESCE(telefone, '')    AS telefone,
                    COALESCE(endereco, '')    AS endereco,
                    COALESCE(responsavel, '') AS responsavel,
                    COALESCE(alergias, '')    AS alergias
             FROM pacientes WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $paciente  = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if (!$paciente) {
        header('Location: paciente_listar.php?erro=nao_encontrado');
        exit;
    }

    $nome        = $paciente['nome'];
    $nascimento  = $paciente['data_nascimento'];
    $sexo        = $paciente['sexo'];
    $cartao      = $paciente['cartao_sus'];
    $telefone    = $paciente['telefone'];
    $endereco    = $paciente['endereco'];
    $responsavel = $paciente['responsavel'];
    $alergias    = $paciente['alergias'];
    $editando    = true;
}

// Voltou de um erro? O que a pessoa digitou vem de volta pela URL.
if (isset($_GET['nome']))        { $nome        = $_GET['nome']; }
if (isset($_GET['nascimento']))  { $nascimento  = $_GET['nascimento']; }
if (isset($_GET['sexo']))        { $sexo        = $_GET['sexo']; }
if (isset($_GET['cartao']))      { $cartao      = $_GET['cartao']; }
if (isset($_GET['telefone']))    { $telefone    = $_GET['telefone']; }
if (isset($_GET['endereco']))    { $endereco    = $_GET['endereco']; }
if (isset($_GET['responsavel'])) { $responsavel = $_GET['responsavel']; }

// -------------------------------------------------------------------
//  AS DUAS ESCOLHAS DA ALERGIA
//
//  A coluna do banco continua sendo uma só, de texto. O que mudou é a
//  PERGUNTA na tela: em vez de pedir que a pessoa acerte a redação
//  ("se não houver nenhuma, escreva Nega alergias"), o formulário
//  pergunta primeiro se há alergia, e só então pede quais.
//
//  Por que duas escolhas e não uma caixinha marcável: caixa marcável
//  tem um estado padrão — desmarcada — e desmarcada não distingue
//  "tem alergia" de "ninguém mexeu nisso ainda". É a mesma ambiguidade
//  do campo em branco, de outra roupa. Duas escolhas, nenhuma
//  pré-marcada, obrigam a responder.
//
//  Ao EDITAR, a escolha vem do que está gravado: negaAlergias() decide
//  qual das duas aparece marcada. Paciente antigo com "Nenhuma" abre
//  com "Nega alergias" marcado — e ao salvar, o texto se normaliza.
// -------------------------------------------------------------------
$tem_alergia    = '';
$alergias_texto = '';

if ($editando) {
    if (negaAlergias($alergias)) {
        $tem_alergia = 'nega';
    } else {
        $tem_alergia    = 'tem';
        $alergias_texto = $alergias;
    }
}

// Voltou de um erro: o que a pessoa respondeu vem de volta pela URL.
if (isset($_GET['tem_alergia']))    { $tem_alergia    = $_GET['tem_alergia']; }
if (isset($_GET['alergias_texto'])) { $alergias_texto = $_GET['alergias_texto']; }

mysqli_close($conexao);

$titulo    = ($editando ? 'Editar cadastro' : 'Cadastrar paciente');
$subtitulo = ($editando ? $nome : 'Novo cadastro');
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'campos'        => 'Preencha todos os campos obrigatórios.',
    'leito_ocupado' => 'Esse leito já está ocupado por outro paciente internado. Escolha outro.',
    'data_futura'   => 'A data de nascimento não pode estar no futuro.',
    'data_invalida' => 'Data de nascimento inválida.',
    'sexo_invalido' => 'Sexo inválido.',
    'alergia_sem_resposta' => 'Responda sobre alergia: escolha "Nega alergias" ou "Tem alergia".',
    'alergia_sem_texto'    => 'Você marcou que o paciente tem alergia. Escreva qual ou quais.',
    'alergia_contraditoria' => 'Você marcou que o paciente tem alergia, mas escreveu uma negação. Se ele não tem, marque "Nega alergias".'
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

    <form action="paciente_salvar.php" method="post">

      <?php if ($editando) { ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php } ?>

      <!-- ============================================================
           IDENTIFICAÇÃO
           ============================================================ -->
      <div class="card">
        <div class="card-body">
          <h6 class="mb-3">Identificação</h6>

          <div class="mb-3">
            <label class="form-label" for="nome">Nome completo *</label>
            <input type="text" class="form-control" id="nome" name="nome"
                   value="<?php echo htmlspecialchars($nome); ?>"
                   placeholder="Como está no documento" required>
          </div>

          <div class="row">
            <div class="col-md-5 mb-3">
              <label class="form-label" for="nascimento">Data de nascimento *</label>
              <!-- type="date" já entrega no formato que o MySQL espera
                   (aaaa-mm-dd) e abre o calendário do navegador. -->
              <input type="date" class="form-control" id="nascimento" name="nascimento"
                     value="<?php echo htmlspecialchars($nascimento); ?>" required>
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label" for="sexo">Sexo *</label>
              <select class="form-select" id="sexo" name="sexo" required>
                <option value="">—</option>
                <option value="F" <?php if ($sexo == 'F') { echo 'selected'; } ?>>Feminino</option>
                <option value="M" <?php if ($sexo == 'M') { echo 'selected'; } ?>>Masculino</option>
                <option value="O" <?php if ($sexo == 'O') { echo 'selected'; } ?>>Outro</option>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label" for="cartao">Cartão SUS</label>
              <input type="text" class="form-control" id="cartao" name="cartao"
                     value="<?php echo htmlspecialchars($cartao); ?>"
                     placeholder="000 0000 0000 0000">
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================
           CONTATO
           ============================================================ -->
      <div class="card mt-4">
        <div class="card-body">
          <h6 class="mb-3">Contato e acompanhante</h6>

          <div class="row">
            <div class="col-md-5 mb-3">
              <label class="form-label" for="telefone">Telefone</label>
              <input type="text" class="form-control" id="telefone" name="telefone"
                     value="<?php echo htmlspecialchars($telefone); ?>"
                     placeholder="(00) 00000-0000">
            </div>
            <div class="col-md-7 mb-3">
              <label class="form-label" for="responsavel">Acompanhante</label>
              <input type="text" class="form-control" id="responsavel" name="responsavel"
                     value="<?php echo htmlspecialchars($responsavel); ?>"
                     placeholder="Nome e parentesco — ex.: Cleusa Fontes (filha)">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="endereco">Endereço</label>
            <input type="text" class="form-control" id="endereco" name="endereco"
                   value="<?php echo htmlspecialchars($endereco); ?>">
          </div>
        </div>
      </div>

      <!-- ============================================================
           ALERGIAS E LEITO
           ============================================================ -->
      <div class="card mt-4">
        <div class="card-body">
          <h6 class="mb-3">Alergias</h6>

          <div class="mb-3">
            <label class="form-label">Alergias *</label>

            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="tem_alergia"
                     id="alergia_nega" value="nega"
                     <?php if ($tem_alergia == 'nega') { echo 'checked'; } ?>>
              <label class="form-check-label" for="alergia_nega">
                Nega alergias
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="tem_alergia"
                     id="alergia_tem" value="tem"
                     <?php if ($tem_alergia == 'tem') { echo 'checked'; } ?>>
              <label class="form-check-label" for="alergia_tem">
                Tem alergia
              </label>
            </div>

            <?php
            // O campo fica SEMPRE na tela, e não escondido pelo
            // JavaScript. Com o script desligado a tela continua
            // funcionando: a pessoa escolhe e digita, e quem decide se
            // as duas respostas combinam é o paciente_salvar.php.
            //
            // O script abaixo só desabilita o campo quando a resposta é
            // "nega" — é cortesia, não é a regra.
            ?>
            <textarea class="form-control" id="alergias_texto" name="alergias_texto"
                      rows="2" placeholder="Ex.: dipirona, penicilina"><?php echo htmlspecialchars($alergias_texto); ?></textarea>
            <div class="form-text">
              Marque uma das duas. Se tem alergia, escreva quais.
            </div>
          </div>

          <script>
          // Cortesia, não regra: apaga o campo de texto quando a resposta
          // é "nega alergias", para a tela não convidar a escrever num
          // campo que vai ser ignorado.
          //
          // Repare que ele DESABILITA e não esconde. Campo escondido
          // desaparece sem explicação; campo apagado continua visível e
          // mostra por que está fora de uso. E se o JavaScript estiver
          // desligado, a tela funciona igual — quem confere se as duas
          // respostas combinam é o paciente_salvar.php, no servidor.
          (function () {

              var nega  = document.getElementById('alergia_nega');
              var tem   = document.getElementById('alergia_tem');
              var texto = document.getElementById('alergias_texto');

              function ajustar() {
                  texto.disabled = nega.checked;
                  texto.style.opacity = (nega.checked ? '.5' : '1');
              }

              nega.addEventListener('change', ajustar);
              tem.addEventListener('change', ajustar);

              // Roda uma vez ao abrir, para o modo edição já chegar certo.
              ajustar();
          })();
          </script>
        </div>
      </div>

      <div class="alert alert-primary mt-4 d-flex align-items-center" role="alert">
        <i class="icon-base bx bx-bed me-2"></i>
        <div>
          <strong>O leito não fica aqui.</strong>
          Cadastrar a pessoa e colocá-la num leito são duas coisas separadas.
          Depois de salvar, use a tela <strong>Movimentação</strong> para internar.
        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary">
          <?php echo ($editando ? 'Salvar alterações' : 'Cadastrar paciente'); ?>
        </button>
        <a href="paciente_listar.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>

    </form>

  </div>

</div>

<?php require 'includes/rodape.php'; ?>
