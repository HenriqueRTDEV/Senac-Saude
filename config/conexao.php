<?php
// ===================================================================
//  config/conexao.php
//  Prontuário Eletrônico TDS
//
//  A ÚNICA conexão com o banco em todo o projeto. Nenhum outro arquivo
//  chama mysqli_connect(). Toda página que precisa do banco faz:
//
//      require 'config/conexao.php';
//
//  e já recebe a variável $conexao pronta para usar.
//
//  Repare que aqui NÃO existe new nem seta. É tudo função solta, no
//  padrão procedural, coerente com programação estruturada.
// ===================================================================

// Os nomes começam com "bd_" de propósito: o formulário de login também
// tem variáveis chamadas $login e $senha. Se usássemos os mesmos nomes
// aqui, uma sobrescreveria a outra e o erro seria dificílimo de achar.
$bd_servidor = 'localhost';
$bd_usuario  = 'root';
$bd_senha    = '123456';
$bd_banco    = 'prontuario_tds';

$conexao = mysqli_connect($bd_servidor, $bd_usuario, $bd_senha, $bd_banco);

// Se o banco não respondeu, para tudo aqui mesmo.
if (!$conexao) {
    die('Erro ao conectar no banco: ' . mysqli_connect_error());
}

// O banco foi criado em utf8mb4. A conexão precisa falar a mesma
// língua, senão acento e cedilha chegam trocados.
mysqli_set_charset($conexao, 'utf8mb4');

// ===================================================================
//  OS DOIS RELÓGIOS PRECISAM MARCAR A MESMA HORA
//
//  O sistema tem duas fontes de hora, e elas são independentes:
//
//    NOW() do MySQL   grava data_hora de aferição, registro e checagem
//    date() do PHP    decide "já passou?", "há quantas horas?"
//
//  O PHP vem configurado em UTC de fábrica. O MySQL usa o fuso do
//  sistema operacional. No laboratório isso dá TRÊS HORAS de diferença,
//  e o efeito é silencioso: nada dá erro, só sai errado.
//
//  Foi assim que uma prescrição feita às 17:11 com horário para as
//  18:00 não gerou a dose daquele dia — o PHP achava que já eram 20:11,
//  então "18:00 já passou". O médico prescreveu e a dose não existiu.
//
//  Uma linha, aqui, e todas as telas passam a concordar com o banco.
//  Este arquivo é incluído por todas elas — é o lugar certo.
//
//  Se a escola mudar de cidade ou de fuso, muda aqui e em nenhum outro
//  lugar. A lista de nomes válidos está em php.net/timezones.
// ===================================================================
date_default_timezone_set('America/Sao_Paulo');

// ATENÇÃO: este arquivo termina SEM a marca de fechar o PHP.
// Se sobrar um espaço ou uma linha em branco depois dela, esse espaço
// é enviado ao navegador e o header('Location: ...') das outras páginas
// para de funcionar, com o erro "headers already sent".
