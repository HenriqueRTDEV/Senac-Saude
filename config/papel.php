<?php
// ===================================================================
//  config/papel.php
//  Prontuário Eletrônico TDS
//
//  O ADMINISTRADOR VÊ O SISTEMA COMO OUTRO PERFIL
//
//  Recurso permanente, não um andaime de construção. Serve para duas
//  coisas do dia a dia:
//
//    testar    o caminho inteiro numa sessão só — a recepção internar,
//              o médico prescrever, o técnico checar a dose
//    apresentar  mostrar o sistema à turma sem trocar de login na
//                frente de todo mundo
//
//  Repare no que isto NÃO é: não é um perfil com acesso a tudo. É
//  empréstimo de papel. O sistema passa a se comportar exatamente como
//  se comportaria para aquele perfil, nem mais nem menos — nenhuma
//  tranca é afrouxada, nenhum botão a mais aparece.
//
//  QUEM ASSINA CONTINUA SENDO QUEM ENTROU. O $_SESSION['usuario_id']
//  não muda. Então uma evolução escrita com o papel de médico fica
//  gravada com o administrador como autor. É a combinação certa — o
//  TIPO do registro segue o papel, o AUTOR é real, e nada mente sobre
//  quem fez. Nos dados de teste isso aparece, e o reset_turma.sql
//  limpa.
//
//  QUEM PODE: só quem entrou com perfil administrador. Não é o perfil
//  em uso, é o de verdade — ver papel_trocar.php, que é onde a tranca
//  mora. Consequência a ter em mente: promover um aluno a
//  administrador para ensinar a tela de usuários dá a ele este recurso
//  também. Promova de propósito.
//
//  PARA DESLIGAR: troque true por false. Uma palavra. O item sai do
//  menu do avatar, o papel_trocar.php recusa todo mundo, e as
//  verificações de permissão voltam a valer sem exceção nenhuma.
//
//  Por que define() e não uma variável: constante vale em todo o
//  programa e ninguém consegue mudar depois. É a diferença que
//  interessa aqui — uma chave de permissão não deve poder ser
//  reescrita no meio do caminho por outra tela qualquer.
// ===================================================================
define('ADMIN_TROCA_PAPEL', true);

// ATENÇÃO: este arquivo termina SEM a marca de fechar o PHP, pelo mesmo
// motivo do config/conexao.php. Um espaço sobrando depois dela seria
// enviado ao navegador e quebraria todo header('Location: ...') do
// sistema, com o erro "headers already sent".
