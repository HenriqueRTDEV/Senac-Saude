<?php
// ===================================================================
//  includes/faixas.php
//  Prontuário Eletrônico TDS
//
//  As faixas de referência dos sinais vitais, escritas UMA vez.
//
//  Mesma ideia do includes/perfis.php: se a faixa da temperatura
//  estivesse copiada no formulário, no histórico e na ficha, bastaria
//  alguém corrigir num lugar para o sistema dizer duas coisas
//  diferentes sobre o mesmo paciente. Aqui é o único lugar onde esses
//  números existem.
//
//  ┌───────────────────────────────────────────────────────────────┐
//  │ CONFIRMAR COM A PROFESSORA DE ENFERMAGEM ANTES DE USAR EM AULA │
//  └───────────────────────────────────────────────────────────────┘
//
//  Os valores abaixo são faixas de adulto de uso corrente, e servem
//  para o sistema funcionar. Mas quem define faixa de referência é a
//  Enfermagem, não a TI — e o documento de escopo pede essa validação
//  em letras claras. Duas em especial merecem conversa:
//
//    · glicemia — a faixa de jejum é bem mais estreita do que a de
//      paciente internado em uso de insulina
//    · escala de dor — dor 3 não é "valor alterado" como febre é;
//      aqui ela é destacada de 4 para cima, que é o ponto em que
//      costuma exigir conduta
//
//  DUAS COISAS DIFERENTES, e é importante não confundir:
//
//    FAIXA DE REFERÊNCIA  diz se o valor é preocupante.
//                         Temperatura 39 °C é fora da faixa — e é um
//                         dado verdadeiro, que precisa ser gravado e
//                         mostrado em vermelho.
//
//    LIMITE DE PLAUSIBILIDADE  diz se o valor é possível.
//                         Temperatura 390 °C não é febre, é erro de
//                         digitação. Isso o sistema RECUSA.
//
//  A primeira pinta de vermelho. A segunda impede de salvar.
// ===================================================================


// -------------------------------------------------------------------
//  listaDeSinais()
//  Tudo o que o sistema sabe sobre cada sinal vital: como se chama na
//  tela, em que unidade, qual a faixa normal e que valores são
//  fisicamente possíveis.
//
//  O formulário e o histórico são montados a partir desta lista. Se um
//  dia entrar um sinal novo, ele aparece nas telas sozinho.
// -------------------------------------------------------------------
function listaDeSinais()
{
    return array(

        'pa_sistolica' => array(
            'rotulo'   => 'PA sistólica',
            'curto'    => 'PA sist.',
            'unidade'  => 'mmHg',
            'min'      => 90,      'max'      => 139,   // faixa normal
            'possivel_min' => 40,  'possivel_max' => 300,
            'decimais' => 0
        ),

        'pa_diastolica' => array(
            'rotulo'   => 'PA diastólica',
            'curto'    => 'PA diast.',
            'unidade'  => 'mmHg',
            'min'      => 60,      'max'      => 89,
            'possivel_min' => 20,  'possivel_max' => 200,
            'decimais' => 0
        ),

        'frequencia_cardiaca' => array(
            'rotulo'   => 'Frequência cardíaca',
            'curto'    => 'FC',
            'unidade'  => 'bpm',
            'min'      => 60,      'max'      => 100,
            'possivel_min' => 20,  'possivel_max' => 250,
            'decimais' => 0
        ),

        'frequencia_respiratoria' => array(
            'rotulo'   => 'Frequência respiratória',
            'curto'    => 'FR',
            'unidade'  => 'irpm',
            'min'      => 12,      'max'      => 20,
            'possivel_min' => 4,   'possivel_max' => 80,
            'decimais' => 0
        ),

        'temperatura' => array(
            'rotulo'   => 'Temperatura axilar',
            'curto'    => 'T',
            'unidade'  => '°C',
            'min'      => 35.5,    'max'      => 37.7,
            'possivel_min' => 30,  'possivel_max' => 43,
            'decimais' => 1
        ),

        'saturacao' => array(
            'rotulo'   => 'Saturação de O₂',
            'curto'    => 'SpO₂',
            'unidade'  => '%',
            'min'      => 92,      'max'      => 100,
            'possivel_min' => 50,  'possivel_max' => 100,
            'decimais' => 0
        ),

        'glicemia' => array(
            'rotulo'   => 'Glicemia capilar',
            'curto'    => 'Glicemia',
            'unidade'  => 'mg/dL',
            'min'      => 70,      'max'      => 180,
            'possivel_min' => 20,  'possivel_max' => 600,
            'decimais' => 0
        ),

        'escala_dor' => array(
            'rotulo'   => 'Escala de dor',
            'curto'    => 'Dor',
            'unidade'  => '0 a 10',
            'min'      => 0,       'max'      => 3,
            'possivel_min' => 0,   'possivel_max' => 10,
            'decimais' => 0
        )
    );
}


// -------------------------------------------------------------------
//  faixaDoSinal($campo)
//  Devolve os dados de um sinal só, ou null se o nome não existir.
// -------------------------------------------------------------------
function faixaDoSinal($campo)
{
    $todos = listaDeSinais();

    if (isset($todos[$campo])) {
        return $todos[$campo];
    }

    return null;
}


// -------------------------------------------------------------------
//  estaAlterado($campo, $valor)
//  O valor está FORA da faixa de referência?
//
//  Campo em branco devolve false: não medir não é o mesmo que medir e
//  dar alterado. Quem não foi aferido aparece como traço na tela, não
//  como valor normal e nem como alerta.
// -------------------------------------------------------------------
function estaAlterado($campo, $valor)
{
    if ($valor === null || $valor === '') {
        return false;
    }

    $faixa = faixaDoSinal($campo);

    if (!$faixa) {
        return false;
    }

    return ($valor < $faixa['min'] || $valor > $faixa['max']);
}


// -------------------------------------------------------------------
//  ehPossivel($campo, $valor)
//  O valor é fisicamente possível? Serve para recusar erro de digitação
//  ANTES de gravar. Nada a ver com estar alterado.
// -------------------------------------------------------------------
function ehPossivel($campo, $valor)
{
    if ($valor === null || $valor === '') {
        return true;   // não preenchido é permitido
    }

    $faixa = faixaDoSinal($campo);

    if (!$faixa) {
        return false;
    }

    return ($valor >= $faixa['possivel_min'] && $valor <= $faixa['possivel_max']);
}


// -------------------------------------------------------------------
//  textoDaFaixa($campo)
//  A faixa escrita como se lê na tela: "90 a 139 mmHg".
// -------------------------------------------------------------------
function textoDaFaixa($campo)
{
    $faixa = faixaDoSinal($campo);

    if (!$faixa) {
        return '';
    }

    $min = number_format($faixa['min'], $faixa['decimais'], ',', '');
    $max = number_format($faixa['max'], $faixa['decimais'], ',', '');

    return $min . ' a ' . $max . ' ' . $faixa['unidade'];
}


// -------------------------------------------------------------------
//  valorNaTela($campo, $valor)
//  O número formatado para leitura, já com a unidade. Traço quando não
//  foi aferido.
// -------------------------------------------------------------------
function valorNaTela($campo, $valor)
{
    if ($valor === null || $valor === '') {
        return '—';
    }

    $faixa = faixaDoSinal($campo);

    if (!$faixa) {
        return $valor;
    }

    return number_format($valor, $faixa['decimais'], ',', '') . ' ' . $faixa['unidade'];
}

// Este arquivo termina sem a marca de fechar o PHP, de propósito: um
// espaço solto depois dela seria enviado ao navegador e quebraria o
// header('Location: ...') das páginas que o incluem.
