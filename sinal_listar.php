<?php
// ===================================================================
//  sinal_listar.php  —  Sinais vitais da ala
//  Módulo 3 · Sprint 5
//
//  Quem chega no plantão pergunta duas coisas: quem ainda não foi
//  aferido, e quem está com valor alterado. Esta tela responde as duas
//  de uma vez — uma linha por internado, com a última aferição.
//
//  PERMISSÃO — a linha mais importante da matriz do escopo:
//
//      administrador  ✔ vê
//      médico         ✔ vê
//      técnico        ✔ vê e AFERE
//      recepção       ✘ NÃO VÊ
//
//  A recepção cadastra o paciente e escolhe o leito, mas não vê um
//  sinal vital dele. Isso é acesso mínimo necessário, que a LGPD exige
//  de sistema de saúde: cada pessoa acessa só o que precisa para fazer
//  o próprio trabalho. Até agora toda tela era "de todos" ou "de um
//  perfil"; esta é a primeira de três, e é a que ensina privacidade.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador', 'medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/faixas.php';

$eh_tecnico = ($_SESSION['usuario_perfil'] == 'tecnico');

// -------------------------------------------------------------------
//  O QUE FOI DIGITADO NA BUSCA
//
//  A partir daqui esta consulta recebe um valor de FORA, e por isso
//  ela deixou de usar mysqli_query() e passou aos quatro passos do
//  mysqli_prepare. Não é opcional: nome de paciente é texto livre, e
//  texto livre concatenado dentro do SQL é a porta da injeção.
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}

// O % de cada lado faz o LIKE achar o pedaço em qualquer posição do
// nome. Busca vazia vira '%%', que casa com todos.
$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  OS INTERNADOS COM A ÚLTIMA AFERIÇÃO
//
//  O problema: `sinais_vitais` tem várias linhas por paciente, e aqui
//  interessa só a mais recente de cada um.
//
//  A saída é um LEFT JOIN com duas condições. A primeira liga paciente
//  e aferição; a segunda exige que não exista NENHUMA aferição mais
//  nova daquele paciente — o que sobra é, por definição, a última.
//
//  Repare que as duas condições estão no ON, não no WHERE. Precisa ser
//  assim: quem nunca foi aferido não tem par, e um WHERE apagaria essa
//  linha justamente quando ela mais importa.
// -------------------------------------------------------------------
$sql = "SELECT p.id AS paciente_id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '')         AS setor,
               sv.id AS afericao_id, sv.data_hora,
               TIMESTAMPDIFF(HOUR, sv.data_hora, NOW()) AS horas_atras,
               sv.pa_sistolica, sv.pa_diastolica,
               sv.frequencia_cardiaca, sv.frequencia_respiratoria,
               sv.temperatura, sv.saturacao, sv.glicemia, sv.escala_dor,
               COALESCE(u.nome, '') AS aferido_por
        FROM internacoes i
        JOIN pacientes p ON p.id = i.paciente_id
        LEFT JOIN leitos  l ON l.id = i.leito_id
        LEFT JOIN setores s ON s.id = l.setor_id
        LEFT JOIN sinais_vitais sv
               ON sv.paciente_id = p.id
              AND NOT EXISTS (
                  SELECT 1 FROM sinais_vitais mais_novo
                   WHERE mais_novo.paciente_id = p.id
                     AND mais_novo.data_hora > sv.data_hora
              )
        LEFT JOIN usuarios u ON u.id = sv.usuario_id
        WHERE i.situacao = 'internado' AND i.ativo = 1
          AND p.nome LIKE ?
        ORDER BY l.identificacao";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 's', $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$lista = array();
while ($linha = mysqli_fetch_assoc($resultado)) {
    $lista[] = $linha;
}

// Quantos ainda não foram aferidos e quantos têm valor alterado.
// Os dois números saem do array, sem voltar ao banco.
$sem_aferir  = 0;
$com_alterado = 0;
$campos = array_keys(listaDeSinais());

foreach ($lista as $linha) {

    if ($linha['afericao_id'] === null) {
        $sem_aferir = $sem_aferir + 1;
        continue;
    }

    foreach ($campos as $campo) {
        if (estaAlterado($campo, $linha[$campo])) {
            $com_alterado = $com_alterado + 1;
            break;   // um alterado já basta para contar o paciente
        }
    }
}

$titulo    = 'Sinais vitais';
$subtitulo = 'Última aferição de cada paciente';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'registrado' => 'Sinais vitais registrados.'
);

$avisos_erro = array(
    'nao_internado'  => 'Só se afere paciente internado.',
    'nao_encontrado' => 'Paciente não encontrado.',
    'vazio'          => 'Preencha ao menos um sinal vital.',
    'impossivel'     => 'Algum valor não é um número válido ou está fora do que é fisicamente possível. Confira o que foi digitado.',
    'pa_invertida'   => 'A pressão sistólica precisa ser maior que a diastólica.'
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
    <h5 class="mb-0">Internados</h5>
    <small class="text-body-secondary">
      <?php echo count($lista); ?>
      <?php echo (count($lista) == 1 ? 'paciente' : 'pacientes'); ?>
      <?php if ($sem_aferir > 0) { ?>
        · <span class="fw-bold"><?php echo $sem_aferir; ?> sem aferição</span>
      <?php } ?>
      <?php if ($com_alterado > 0) { ?>
        · <span class="valor-alterado"><?php echo $com_alterado; ?> com valor alterado</span>
      <?php } ?>
    </small>
  </div>
</div>

<form method="get" action="sinal_listar.php" class="mb-3">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por nome…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="sinal_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="valor-alterado">Em vermelho</span>: valor fora da faixa de referência.
  </small>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito</th>
          <th>Paciente</th>
          <th>Última aferição</th>
          <th>Valores</th>
          <th></th>
        </tr>
      </thead>
      <tbody>

      <?php
      if (count($lista) == 0) {
      ?>
        <tr><td colspan="5" class="text-center text-body-secondary py-4">
          <?php
          // A mensagem muda: "não há ninguém internado" e "a busca não
          // achou" são situações diferentes, e dizer a errada faz a
          // pessoa procurar problema onde não tem.
          echo ($busca == ''
                ? 'Nenhum paciente internado no momento.'
                : 'Nenhum internado com esse nome.');
          ?>
        </td></tr>
      <?php
      }

      foreach ($lista as $linha) {

          $nunca_aferido = ($linha['afericao_id'] === null);
      ?>
        <tr>
          <td>
            <?php if ($linha['leito'] == '') { ?>
              <span class="text-body-secondary">sem leito</span>
            <?php } else { ?>
              <strong><?php echo htmlspecialchars($linha['leito']); ?></strong><br>
              <small class="text-body-secondary"><?php echo htmlspecialchars($linha['setor']); ?></small>
            <?php } ?>
          </td>

          <td>
            <strong><?php echo htmlspecialchars($linha['nome']); ?></strong>,
            <?php echo $linha['idade']; ?> anos
            <?php
            // REGRA DE OURO Nº 4 — alergia sempre em destaque.
            $texto_alergia = trim($linha['alergias']);
            $nega = negaAlergias($texto_alergia);
            if (!$nega) {
            ?>
              <br><span class="valor-alterado small">
                <i class="icon-base bx bx-error-circle"></i>
                <?php echo htmlspecialchars($texto_alergia); ?>
              </span>
            <?php } ?>
          </td>

          <td class="text-nowrap">
            <?php if ($nunca_aferido) { ?>
              <span class="badge bg-label-secondary">Nunca aferido</span>
            <?php } else { ?>
              <?php echo date('d/m H:i', strtotime($linha['data_hora'])); ?><br>
              <small class="text-body-secondary">
                <?php
                // A unidade muda com o tamanho do intervalo. "há 109
                // horas" está certo e é ilegível: quem lê precisa
                // dividir por 24 de cabeça, e a coluna existe justamente
                // para responder de relance se a aferição é recente.
                $horas = $linha['horas_atras'];

                if ($horas < 1) {
                    echo 'há menos de 1 hora';
                } else if ($horas == 1) {
                    echo 'há 1 hora';
                } else if ($horas < 24) {
                    echo 'há ' . $horas . ' horas';
                } else {
                    $dias = (int) ($horas / 24);
                    echo 'há ' . $dias . ($dias == 1 ? ' dia' : ' dias');
                }
                ?>
              </small>
            <?php } ?>
          </td>

          <td>
            <?php
            if ($nunca_aferido) {
            ?>
              <span class="text-body-secondary">—</span>
            <?php
            } else {

                // Monta os valores medidos, pintando de vermelho os que
                // estão fora da faixa. Quem não foi medido nesta
                // aferição simplesmente não aparece.
                $pedacos = array();

                if ($linha['pa_sistolica'] !== null && $linha['pa_diastolica'] !== null) {
                    $pa_ruim = (estaAlterado('pa_sistolica', $linha['pa_sistolica'])
                             || estaAlterado('pa_diastolica', $linha['pa_diastolica']));
                    $pedacos[] = '<span class="' . ($pa_ruim ? 'valor-alterado' : '') . '">PA '
                               . (int) $linha['pa_sistolica'] . '×' . (int) $linha['pa_diastolica']
                               . '</span>';
                }

                $simples = array('frequencia_cardiaca', 'frequencia_respiratoria',
                                 'temperatura', 'saturacao', 'glicemia', 'escala_dor');

                foreach ($simples as $campo) {

                    if ($linha[$campo] === null) {
                        continue;
                    }

                    $faixa = faixaDoSinal($campo);
                    $classe = (estaAlterado($campo, $linha[$campo]) ? 'valor-alterado' : '');

                    $pedacos[] = '<span class="' . $classe . '">'
                               . $faixa['curto'] . ' '
                               . number_format($linha[$campo], $faixa['decimais'], ',', '')
                               . '</span>';
                }

                echo '<span class="small">' . implode(' &nbsp; ', $pedacos) . '</span>';

                if ($linha['aferido_por'] != '') {
                    echo '<br><small class="text-body-secondary">'
                       . htmlspecialchars($linha['aferido_por']) . '</small>';
                }
            }
            ?>
          </td>

          <td class="text-nowrap">
            <?php if ($eh_tecnico) { ?>
              <a href="sinal_form.php?paciente_id=<?php echo $linha['paciente_id']; ?>"
                 class="btn btn-sm btn-primary">Aferir</a>
            <?php } ?>
            <a href="sinal_historico.php?paciente_id=<?php echo $linha['paciente_id']; ?>"
               class="btn btn-sm btn-outline-primary">Histórico</a>
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
