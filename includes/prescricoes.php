<?php
// ===================================================================
//  includes/prescricoes.php
//  Prontuário Eletrônico TDS
//
//  As vias de administração, os estados de uma checagem, e a leitura
//  dos horários. Escrito uma vez, como o perfis.php e o faixas.php.
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  QUEM FAZ O QUÊ                                              │
//  │    médico  prescreve e suspende — nunca administra            │
//  │    técnico administra e checa   — nunca prescreve             │
//  └──────────────────────────────────────────────────────────────┘
//
//  Essa separação é a razão de existir do módulo. Quem prescreve não
//  executa, e quem executa não decide. No sistema isso são telas
//  diferentes, com perfis diferentes, gravando em tabelas diferentes.
// ===================================================================


// -------------------------------------------------------------------
//  listaDeVias()
//  As vias que a coluna `via` da tabela aceita, com o nome que a
//  enfermagem usa. A chave é exatamente o valor do ENUM no banco.
// -------------------------------------------------------------------
function listaDeVias(){
    return array(
        'VO'        => 'Via Oral',
        'IV'        => 'Intravenosa',
        'IM'        => 'Intramuscular',
        'SC'        => 'Subcutânea',
        'SL'        => 'Sublingual',
        'TOP'       => 'Tópica',
        'INAL'      => 'Inalatória',
        'Retal'     => 'Retal',
        'OFT'       => 'Oftálmica'
    );
}

function nomeDaVia($via){
    $vias = listaDeVias();

    if(isset($vias[$via])){
        return $vias[$via];
    }

    return $via;
}

// O <select> oferecia só estas nove, mas o POST pode chegar sem passar
// por ele. Convenção 14 do projeto.

function viaExiste($via){
    $vias = listaDeVias();
    return isset($vias[$via]);
}

// -------------------------------------------------------------------
//  OS TRÊS ESTADOS DE UMA ADMINISTRAÇÃO
//
//  pendente          o horário chegou (ou vai chegar) e ninguém checou
//  administrado      foi dado, com quem deu e a hora real
//  nao_administrado  NÃO foi dado, e a justificativa é obrigatória
//
//  O terceiro é o mais importante do sistema. Medicação não dada é um
//  evento clínico que precisa de registro e de motivo — deixar a linha
//  simplesmente em branco esconderia a informação mais relevante do
//  plantão.
// -------------------------------------------------------------------
function nomeDoStatus($status){
    $nomes = array(
        'pendente'          => 'Pendente',
        'administrado'      => 'Administrado',
        'nao_administrado'  => 'Não Administrado'
    );

    if(isset($nomes[$status])){
        return $nomes[$status];
    }
    return $status;
}

function corDoStatus($status){
    $cores = array(
        'pendente'         => 'bg-label-warning',
        'adminstrado'      => 'bg-label-success',
        'nao_adminstrado' => 'bg-label-danger'
    );

    if(isset($cores[$status])){
        return $cores[$status];
    }
    return 'bg-label-secondary';
}

// -------------------------------------------------------------------
//  separarHorarios($texto)
//
//  O médico digita "06:00, 14:00,22:00" e o banco guarda essa string
//  inteira na coluna `horarios`. Esta função a transforma em lista,
//  conferindo cada pedaço.
//
//  Devolve um array de horários no formato HH:MM, sem repetição e em
//  ordem. Texto vazio devolve array vazio — e isso NÃO é erro: é a
//  prescrição "se necessário", que não tem horário marcado.
//
//  Repare que a conferência é do formato E do valor: "25:00" tem a
//  forma certa e não existe. É a mesma distinção do faixas.php entre
//  formato válido e valor possível.
// -------------------------------------------------------------------
function separarHorarios($texto)
{
    $texto = trim($texto);

    if ($texto === '') {
        return array();
    }

    $achados = array();

    foreach (explode(',', $texto) as $pedaco) {

        $pedaco = trim($pedaco);

        if ($pedaco === '') {
            continue;
        }

        // Aceita 6:00 e 06:00; recusa qualquer outra forma.
        if (!preg_match('/^([0-9]{1,2}):([0-9]{2})$/', $pedaco, $partes)) {
            return false;   // formato inválido
        }

        $hora   = (int) $partes[1];
        $minuto = (int) $partes[2];

        if ($hora > 23 || $minuto > 59) {
            return false;   // valor impossível
        }

        // Normaliza para HH:MM, para o banco guardar sempre igual
        $normalizado = sprintf('%02d:%02d', $hora, $minuto);

        if (!in_array($normalizado, $achados)) {
            $achados[] = $normalizado;
        }
    }

    sort($achados);

    return $achados;
}


// -------------------------------------------------------------------
//  diasParaGerar()
//
//  Quantos dias de horários são criados quando a prescrição não tem
//  data de fim.
//
//  POR QUE UM LIMITE. A geração acontece no momento em que a
//  prescrição é salva — num POST, de propósito: assim nenhuma tela
//  cria linha no banco só por ser aberta, o que quebraria a regra de
//  que GET só mostra (passo 16 do diário).
//
//  O preço é que a prescrição sem data de fim não pode gerar horário
//  para sempre: o laço precisa terminar. Sete dias cobrem qualquer
//  prática de turma com folga, e o reset entre turmas recomeça tudo.
//
//  Num sistema hospitalar de verdade isso seria uma rotina que roda
//  toda noite criando o dia seguinte. Vale dizer isso à turma: aqui a
//  geração é visível e finita porque é material de aula.
// -------------------------------------------------------------------
function diasParaGerar()
{
    return 7;
}


// -------------------------------------------------------------------
//  horasDoTurno()
//
//  Quanto tempo para a frente a tela de doses a checar enxerga.
//
//  POR QUE NÃO É "HOJE"
//
//  A tela se chama medicações DO TURNO, e turno atravessa a
//  meia-noite. Cortando por dia de calendário, o técnico do plantão
//  da noite abre a tela às 23:00 e não vê a dose que ele tem para
//  dar às 04:00 — ela é de "amanhã". E à 00:01 o dia seguinte
//  inteiro desaba na tela de uma vez, inclusive o que é do turno da
//  tarde.
//
//  Doze horas cobre um plantão. A escola que trabalhar com turnos de
//  seis horas troca este número — e só este.
// -------------------------------------------------------------------
function horasDoTurno()
{
    return 12;
}

// Sem a marca de fechar o PHP: espaço solto depois dela iria para o
// navegador e quebraria o header('Location: ...') de quem inclui.
