<?php
// ===================================================================
//  includes/protege.php
//  Prontuário Eletrônico TDS
//
//  A tranca da área logada. É a PRIMEIRA linha de toda página interna:
//
//      require 'includes/protege.php';
//
//  Tem que vir antes de qualquer outra coisa — antes do cabeçalho e
//  antes de mexer no banco. De nada adianta conferir a permissão
//  depois de a página já ter salvado alguma coisa.
// ===================================================================

// Só inicia a sessão se ela ainda não estiver aberta. Chamar
// session_start() duas vezes gera um aviso feio na tela.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sem usuário na sessão, não passa.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}