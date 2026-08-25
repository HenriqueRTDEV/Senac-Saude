<?php
// ===================================================================
//  sair.php
//  Prontuário Eletrônico TDS
//
//  Encerra a sessão e volta para o login.
// ===================================================================

session_start();

// São dois passos, e a ordem importa.
// 1) Esvazia o array $_SESSION — sem isso os dados continuam
//    disponíveis até o fim deste script.
$_SESSION = array();

// 2) Destrói a sessão no servidor.
session_destroy();

// O ?saiu=1 faz a tela de login mostrar o aviso verde de despedida.
header('Location: index.php?saiu=1');
exit;
