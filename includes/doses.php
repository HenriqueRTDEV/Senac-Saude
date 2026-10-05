<?php
// ===================================================================
//  includes/doses.php
//  Prontuário Eletrônico TDS
//
//  Gera as doses que faltam de uma prescrição — escrito UMA vez só e
//  chamado de dois lugares.
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  A REGRA CLÍNICA                                             │
//  │  Prescrição ativa de paciente internado tem que continuar     │
//  │  aparecendo no turno ATÉ a alta ou até ser suspensa.          │
//  └──────────────────────────────────────────────────────────────┘
//
//  POR QUE ESTE ARQUIVO EXISTE
//
//  Antes, as doses eram geradas uma vez só, no momento de prescrever,
//  para diasParaGerar() dias. Ninguém as repunha. O efeito:
//
//    Paciente internado mais de uma semana PARAVA DE TER DOSE, em
//    silêncio. A prescrição continuava marcada como ativa, e a tela do
//    turno simplesmente não a mostrava mais.
//
//  Aconteceu no banco da escola: a Dipirona da Helena, ativa desde
//  14/08, com horários 06:00 · 12:00 · 18:00 · 00:00, ficou com zero
//  doses futuras e desapareceu do turno.
//
//  Agora a geração acontece nos dois momentos:
//
//    ao PRESCREVER          prescricao_salvar.php   (POST)
//    ao ABRIR O TURNO       medicacao_turno.php     (completa o que falta)
//
//  DUAS COISAS QUE FAZEM ISSO SER SEGURO
//
//  1. É IDEMPOTENTE. Antes de inserir, confere se aquela dose já
//     existe. Rodar dez vezes seguidas cria as mesmas doses uma vez.
//     Sem isso, cada vez que alguém abrisse a tela do turno nasceriam
//     doses duplicadas.
//
//  2. SÓ GERA PARA A FRENTE. Nunca cria dose de horário que já passou.
//     Uma prescrição de 14/08 que ficou sem dose até hoje NÃO recebe
//     as doses dos dias perdidos — elas apareceriam como uma pilha de
//     atrasadas que ninguém teve como administrar, culpando a equipe
//     por uma falha do sistema.
//
//  A HORA VEM DO BANCO, NÃO DO PHP
//
//  O `SELECT NOW()` lá embaixo parece desnecessário — o PHP tem
//  date(). Ele está ali de propósito: as duas fontes de hora já
//  divergiram três horas neste projeto, e uma prescrição perdeu a dose
//  do dia por causa disso. Perguntando a hora ao banco, a comparação
//  usa o mesmo relógio que gravou os dados. Ver a convenção 24.
// ===================================================================


// -------------------------------------------------------------------
//  gerarDosesFaltantes()
//
//  Recebe a conexão e o id da prescrição. Devolve quantas doses criou.
//
//  Devolve 0 — sem reclamar — quando não é o caso de gerar: prescrição
//  suspensa, paciente com alta, ou prescrição "se necessário" (que não
//  tem horário e por isso não tem dose esperando).
// -------------------------------------------------------------------
function gerarDosesFaltantes($conexao, $prescricao_id)
{
    // ---------------------------------------------------------------
    //  1. A prescrição, e o estado da internação dela
    //
    //  O JOIN com internacoes é o que garante a regra: prescrição de
    //  paciente que recebeu alta não gera nada, porque a linha não
    //  volta da consulta.
    // ---------------------------------------------------------------
    $sql = "SELECT pr.id, pr.data_inicio,
                   pr.data_fim,
                   COALESCE(pr.horarios, '') AS horarios
            FROM prescricoes pr
            JOIN internacoes i ON i.id = pr.internacao_id
                              AND i.situacao = 'internado'
                              AND i.ativo = 1
            WHERE pr.id = ? AND pr.ativo = 1";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $prescricao_id);
    mysqli_stmt_execute($stmt);
    $pr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$pr) {
        return 0;   // suspensa, ou paciente não está mais internado
    }

    // "Se necessário" não tem horário, então não tem dose esperando.
    $horarios = separarHorarios($pr['horarios']);

    if ($horarios === false || count($horarios) == 0) {
        return 0;
    }

    // ---------------------------------------------------------------
    //  2. A hora de agora, perguntada ao BANCO
    // ---------------------------------------------------------------
    $agora = mysqli_fetch_row(mysqli_query($conexao, 'SELECT NOW()'))[0];
    $hoje  = substr($agora, 0, 10);

    // ---------------------------------------------------------------
    //  3. De quando até quando
    //
    //  Começa hoje, ou no início da prescrição se ela ainda não
    //  começou. Termina na data_fim, ou numa janela finita quando não
    //  há data_fim — o laço precisa terminar.
    // ---------------------------------------------------------------
    $primeiro_dia = ($pr['data_inicio'] > $hoje ? $pr['data_inicio'] : $hoje);

    if ($pr['data_fim'] === null) {
        $ultimo_dia = date('Y-m-d', strtotime($primeiro_dia . ' + '
                          . (diasParaGerar() - 1) . ' day'));
    } else {
        $ultimo_dia = $pr['data_fim'];
    }

    // ---------------------------------------------------------------
    //  4. O laço: dia por fora, horário por dentro
    // ---------------------------------------------------------------
    $sql_existe = "SELECT id FROM administracoes
                    WHERE prescricao_id = ? AND horario_previsto = ?";

    $sql_criar = "INSERT INTO administracoes
                    (prescricao_id, usuario_id, horario_previsto,
                     data_hora_checagem, status, justificativa)
                  VALUES (?, NULL, ?, NULL, 'pendente', NULL)";

    $stmt_existe = mysqli_prepare($conexao, $sql_existe);
    $stmt_criar  = mysqli_prepare($conexao, $sql_criar);

    $criadas = 0;
    $dia     = $primeiro_dia;

    while ($dia <= $ultimo_dia) {

        foreach ($horarios as $hora) {

            $quando = $dia . ' ' . $hora . ':00';

            // SÓ PARA A FRENTE. Vale tanto para o primeiro dia de uma
            // prescrição nova quanto para a reposição de uma antiga.
            if ($quando <= $agora) {
                continue;
            }

            // JÁ EXISTE? É o que torna a função repetível.
            mysqli_stmt_bind_param($stmt_existe, 'is', $prescricao_id, $quando);
            mysqli_stmt_execute($stmt_existe);
            $ja = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_existe));

            if ($ja) {
                continue;
            }

            mysqli_stmt_bind_param($stmt_criar, 'is', $prescricao_id, $quando);
            mysqli_stmt_execute($stmt_criar);
            $criadas = $criadas + 1;
        }

        $dia = date('Y-m-d', strtotime($dia . ' + 1 day'));
    }

    mysqli_stmt_close($stmt_existe);
    mysqli_stmt_close($stmt_criar);

    return $criadas;
}


// -------------------------------------------------------------------
//  reporDosesDoTurno()
//
//  Passa por todas as prescrições ativas de pacientes internados e
//  completa o que falta. É o que a tela do turno chama ao abrir.
//
//  Devolve quantas doses criou no total — a tela não mostra esse
//  número, mas ele serve para conferir em teste.
// -------------------------------------------------------------------
function reporDosesDoTurno($conexao)
{
    // Só as que têm horário e ainda estão dentro do período. A lista é
    // pequena — um hospital-escola tem poucas prescrições ativas — e a
    // função chamada em seguida recusa sozinha o que não é caso.
    $sql = "SELECT pr.id
            FROM prescricoes pr
            JOIN internacoes i ON i.id = pr.internacao_id
                              AND i.situacao = 'internado'
                              AND i.ativo = 1
            WHERE pr.ativo = 1
              AND pr.horarios IS NOT NULL
              AND pr.horarios <> ''
              AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())";

    $res = mysqli_query($conexao, $sql);

    $ids = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $ids[] = $linha['id'];
    }

    // Os ids são lidos ANTES de gerar. Gerar dentro do while faria a
    // consulta e a escrita disputarem a mesma tabela — ver a convenção
    // 20, sobre não fechar statement antes de ler o resultado.
    $total = 0;

    foreach ($ids as $id) {
        $total = $total + gerarDosesFaltantes($conexao, $id);
    }

    return $total;
}
