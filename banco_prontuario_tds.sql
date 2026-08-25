-- ============================================================
-- PRONTUÁRIO ELETRÔNICO TDS - Banco de dados
-- Curso Técnico em Desenvolvimento de Sistemas - Turma 2025
-- MySQL / MariaDB
--
-- ESTE É O SCRIPT COMPLETO. É o único que a turma precisa rodar.
--
-- Ele já traz tudo o que as três migrações fizeram: setores, leitos,
-- internações, e o vínculo do dado clínico com a internação. Os
-- arquivos migracao_v2, v3 e v4 desta pasta são HISTÓRICO — servem
-- para mostrar à turma como o banco cresceu, e NÃO se rodam depois
-- deste. Se alguém rodar, dá erro, e o erro assusta sem motivo:
--
--   v2  ERROR 1054  Unknown column 'setor'
--   v3  ERROR 1060  Duplicate column name 'internacao_id'
--   v4  ERROR 1060  Duplicate column name 'internacao_id'
--
-- Não é defeito: é o banco dizendo que a coluna já existe.
--
-- PERFIS: administrador (TI) | recepcao | medico | tecnico (de enfermagem)
--
-- ATENÇÃO 1: todos os dados são FICTÍCIOS. Nunca cadastre
--            pacientes reais neste sistema.
-- ATENÇÃO 2: este script APAGA E RECRIA o banco inteiro.
--            Rode uma vez, no sprint 1. Para limpar os dados
--            entre as turmas de Enfermagem sem perder os
--            usuários, use o arquivo reset_turma.sql.
--
-- AS ONZE TABELAS E QUEM ALIMENTA CADA UMA
--
--   usuarios        quem acessa o sistema          administrador
--   setores         alas do hospital               administrador
--   leitos          as camas                       recepção
--   cids            catálogo de diagnósticos       -- só pelo banco --
--   medicamentos    catálogo da farmácia           -- só pelo banco --
--   pacientes       quem a pessoa é                recepção
--   internacoes     cada passagem pelo hospital    recepção / médico
--   sinais_vitais   cada aferição                  técnico
--   registros       anotações e evoluções          técnico / médico
--   prescricoes     o que foi prescrito            médico
--   administracoes  cada horário e sua checagem    técnico
--
-- POR QUE CIDS E MEDICAMENTOS NÃO TÊM TELA DE CADASTRO
--
--   O médico ESCOLHE de uma lista; ninguém digita o nome de um
--   remédio nem o texto de um diagnóstico. Mas manter esses dois
--   catálogos é trabalho da FARMÁCIA e do faturamento, e este
--   sistema é de enfermagem — não cobre nenhum dos dois.
--
--   Então eles entram prontos por este script e ficam só de
--   leitura no sistema. Quem precisar acrescentar um item mexe
--   direto no banco, pelo Workbench.
--
--   É uma decisão de escopo, não uma limitação técnica: todo
--   sistema real conversa com outros sistemas, e nem tudo o que
--   ele lê precisa ser gerenciado por ele.
--
-- POR QUE SETOR, LEITO E INTERNAÇÃO SÃO TABELAS SEPARADAS
--
--   Setor e leito existem mesmo quando não há ninguém neles —
--   por isso não podem ser um texto digitado dentro do cadastro
--   do paciente. Sem tabela própria, um leito vago seria
--   invisível para o sistema, e não haveria como oferecer a
--   lista de leitos livres na hora de internar.
--
--   E a pessoa é uma coisa; a internação dela é outra. O mesmo
--   paciente recebe alta e volta meses depois. Guardando as duas
--   coisas juntas, a segunda internação apagaria a primeira.
--   Separadas, o cadastro é reaproveitado e o histórico fica.
-- ============================================================

DROP DATABASE IF EXISTS prontuario_tds;
CREATE DATABASE prontuario_tds
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE prontuario_tds;

-- ------------------------------------------------------------
-- 1. USUÁRIOS  (gerenciados pelo ADMINISTRADOR)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  nome                   VARCHAR(120) NOT NULL,
  login                  VARCHAR(50)  NOT NULL UNIQUE,
  senha                  VARCHAR(255) NOT NULL,   -- guardar SEMPRE com password_hash()
  registro_profissional  VARCHAR(30)  NULL,       -- CRM (médico) ou COREN (técnico) - fictício
  perfil                 ENUM('administrador','recepcao','medico','tecnico') NOT NULL DEFAULT 'tecnico',
  ativo                  TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. SETORES  (gerenciados pelo ADMINISTRADOR)
--    A ala do hospital: clínica médica, cirúrgica, isolamento.
--    É estrutura da instituição, não do dia a dia do plantão —
--    por isso fica com a TI, junto dos usuários.
-- ------------------------------------------------------------
CREATE TABLE setores (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(60)  NOT NULL UNIQUE,
  descricao  VARCHAR(200) NULL,
  ativo      TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. CIDS  (catálogo — SEM tela de cadastro)
--    Classificação Internacional de Doenças. O médico escolhe um
--    código da lista em vez de digitar o diagnóstico por extenso.
--
--    Por que isso importa: "pneumonia", "Pneumonia", "pneumonia
--    comunitária" e "PNM" são a mesma doença escritas de quatro
--    jeitos. Digitadas à mão, viram quatro diagnósticos diferentes
--    para o sistema, e nenhum relatório fecha. Com código, não há
--    ambiguidade.
-- ------------------------------------------------------------
CREATE TABLE cids (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(10)  NOT NULL UNIQUE,   -- "J18.9"
  descricao  VARCHAR(200) NOT NULL,          -- "Pneumonia não especificada"
  ativo      TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX idx_cids_descricao (descricao)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. MEDICAMENTOS  (catálogo da farmácia — SEM tela de cadastro)
--    O médico escolhe da lista; a dose, a via e os horários ele
--    define na prescrição, porque mudam de paciente para paciente.
-- ------------------------------------------------------------
CREATE TABLE medicamentos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  apresentacao  VARCHAR(60)  NULL,   -- "500 mg comprimido"
  via_padrao    ENUM('VO','IV','IM','SC','SL','TOP','INAL','RETAL','OFT') NULL,
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX idx_medicamentos_nome (nome)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. LEITOS  (gerenciados pela RECEPÇÃO)
--    A cama física. Existe esteja ela ocupada ou vaga.
-- ------------------------------------------------------------
CREATE TABLE leitos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  identificacao  VARCHAR(10)  NOT NULL UNIQUE,   -- "101-A"
  setor_id       INT          NULL,
  observacao     VARCHAR(200) NULL,              -- "leito de isolamento"
  ativo          TINYINT(1)   NOT NULL DEFAULT 1,-- 0 = fora de uso (manutenção)
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_le_setor FOREIGN KEY (setor_id) REFERENCES setores(id),
  INDEX idx_leitos_setor (setor_id, ativo)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. PACIENTES  (cadastrados pela RECEPÇÃO)
--    Só o que é permanente: quem a pessoa é.
--    Leito, diagnóstico e datas de internação NÃO ficam aqui —
--    mudam a cada internação, e vivem na tabela `internacoes`.
-- ------------------------------------------------------------
CREATE TABLE pacientes (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nome             VARCHAR(120) NOT NULL,
  data_nascimento  DATE         NOT NULL,
  sexo             ENUM('F','M','O') NOT NULL,
  cartao_sus       VARCHAR(20)  NULL,
  telefone         VARCHAR(20)  NULL,
  endereco         VARCHAR(200) NULL,
  responsavel      VARCHAR(120) NULL,          -- acompanhante / contato
  alergias         VARCHAR(255) NULL,          -- "nega alergias" também é informação
  cadastrado_por   INT          NULL,          -- quem da recepção criou o cadastro
  ativo            TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pa_cadastro FOREIGN KEY (cadastrado_por) REFERENCES usuarios(id),
  INDEX idx_pacientes_nome (nome)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. INTERNAÇÕES
--    Cada passagem do paciente pelo hospital é uma linha.
--    O mesmo paciente pode ter várias ao longo dos anos.
--
--    Repare em quem assina o quê:
--      admitido_por     -> recepção, na chegada
--      cid_id           -> médico, ao avaliar
--      alta_usuario_id  -> médico, na saída
--
--    O diagnóstico é um cid_id, não um texto: o médico ESCOLHE
--    da lista de CIDs. Fica sem ambiguidade e permite contar
--    quantas pneumonias houve no mês.
-- ------------------------------------------------------------
CREATE TABLE internacoes (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id      INT      NOT NULL,
  leito_id         INT      NULL,
  situacao         ENUM('internado','alta') NOT NULL DEFAULT 'internado',
  cid_id           INT      NULL,          -- diagnóstico, definido pelo médico
  data_admissao    DATETIME NOT NULL,
  admitido_por     INT      NOT NULL,
  data_alta        DATETIME NULL,
  alta_usuario_id  INT      NULL,
  ativo            TINYINT(1) NOT NULL DEFAULT 1,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_in_paciente FOREIGN KEY (paciente_id)     REFERENCES pacientes(id),
  CONSTRAINT fk_in_leito    FOREIGN KEY (leito_id)        REFERENCES leitos(id),
  CONSTRAINT fk_in_cid      FOREIGN KEY (cid_id)          REFERENCES cids(id),
  CONSTRAINT fk_in_admitiu  FOREIGN KEY (admitido_por)    REFERENCES usuarios(id),
  CONSTRAINT fk_in_alta     FOREIGN KEY (alta_usuario_id) REFERENCES usuarios(id),
  INDEX idx_in_paciente (paciente_id),
  INDEX idx_in_situacao (situacao, ativo),
  INDEX idx_in_leito (leito_id, situacao)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. SINAIS VITAIS  (registrados pelo TÉCNICO)
--
--    SIMPLIFICAÇÃO CONSCIENTE: liga em `paciente_id`, não em
--    `internacao_id`. Num sistema hospitalar de verdade cada
--    internação teria seu próprio conjunto de registros. Aqui a
--    prática da turma dura um plantão, e amarrar tudo à
--    internação dobraria a complexidade de cada consulta sem
--    ganho didático proporcional. Fica como assunto de aula.
-- ------------------------------------------------------------
CREATE TABLE sinais_vitais (
  id                       INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id              INT      NOT NULL,
  internacao_id            INT      NOT NULL,   -- de QUAL estadia é a aferição
  usuario_id               INT      NOT NULL,   -- quem aferiu
  data_hora                DATETIME NOT NULL,
  pa_sistolica             SMALLINT NULL,       -- mmHg
  pa_diastolica            SMALLINT NULL,       -- mmHg
  frequencia_cardiaca      SMALLINT NULL,       -- bpm
  frequencia_respiratoria  SMALLINT NULL,       -- irpm
  temperatura              DECIMAL(4,1) NULL,   -- °C
  saturacao                SMALLINT NULL,       -- SpO2 %
  glicemia                 SMALLINT NULL,       -- mg/dL
  escala_dor               TINYINT  NULL,       -- 0 a 10 (validar no PHP)
  observacao               VARCHAR(255) NULL,
  criado_em                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sv_paciente   FOREIGN KEY (paciente_id)   REFERENCES pacientes(id),
  CONSTRAINT fk_sv_internacao FOREIGN KEY (internacao_id) REFERENCES internacoes(id),
  CONSTRAINT fk_sv_usuario    FOREIGN KEY (usuario_id)    REFERENCES usuarios(id),
  INDEX idx_sv_paciente_data (paciente_id, data_hora)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. REGISTROS
--    'anotacao' = anotação de enfermagem, escrita pelo TÉCNICO
--    'evolucao' = evolução médica,        escrita pelo MÉDICO
-- ------------------------------------------------------------
CREATE TABLE registros (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id  INT      NOT NULL,
  internacao_id INT     NOT NULL,   -- de QUAL estadia é o registro
  usuario_id   INT      NOT NULL,
  tipo         ENUM('anotacao','evolucao') NOT NULL DEFAULT 'anotacao',
  data_hora    DATETIME NOT NULL,
  texto        TEXT     NOT NULL,
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_re_paciente   FOREIGN KEY (paciente_id)   REFERENCES pacientes(id),
  CONSTRAINT fk_re_internacao FOREIGN KEY (internacao_id) REFERENCES internacoes(id),
  CONSTRAINT fk_re_usuario    FOREIGN KEY (usuario_id)    REFERENCES usuarios(id),
  INDEX idx_re_paciente_data (paciente_id, data_hora)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. PRESCRIÇÕES  (lançadas pelo MÉDICO)
--     O medicamento vem do catálogo (medicamento_id). Dose, via,
--     frequência e horários são decisão do médico para AQUELE
--     paciente, então ficam aqui e não no catálogo.
-- ------------------------------------------------------------
CREATE TABLE prescricoes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id     INT NOT NULL,
  internacao_id   INT NOT NULL,                -- a prescrição pertence a UMA internação
  usuario_id      INT NOT NULL,                -- médico que prescreveu
  medicamento_id  INT NOT NULL,                -- escolhido do catálogo
  dose            VARCHAR(50)  NOT NULL,       -- "500 mg", "10 mL"
  via             ENUM('VO','IV','IM','SC','SL','TOP','INAL','RETAL','OFT') NOT NULL,
  frequencia      VARCHAR(50)  NOT NULL,       -- "8/8h", "1x ao dia", "se necessário"
  horarios        VARCHAR(100) NULL,           -- "06:00,14:00,22:00"
  data_inicio     DATE NOT NULL,
  data_fim        DATE NULL,
  observacao      VARCHAR(255) NULL,
  ativo           TINYINT(1) NOT NULL DEFAULT 1, -- 0 = suspensa pelo médico
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_paciente    FOREIGN KEY (paciente_id)    REFERENCES pacientes(id),
  CONSTRAINT fk_pr_internacao  FOREIGN KEY (internacao_id)  REFERENCES internacoes(id),
  CONSTRAINT fk_pr_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios(id),
  CONSTRAINT fk_pr_medicamento FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id),
  INDEX idx_pr_paciente (paciente_id, ativo)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. ADMINISTRAÇÕES  (a checagem, feita pelo TÉCNICO)
-- ------------------------------------------------------------
CREATE TABLE administracoes (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  prescricao_id      INT      NOT NULL,
  usuario_id         INT      NULL,            -- só preenche quando checar
  horario_previsto   DATETIME NOT NULL,
  data_hora_checagem DATETIME NULL,
  status             ENUM('pendente','administrado','nao_administrado') NOT NULL DEFAULT 'pendente',
  justificativa      VARCHAR(255) NULL,        -- obrigatória quando não administrado
  CONSTRAINT fk_ad_prescricao FOREIGN KEY (prescricao_id) REFERENCES prescricoes(id),
  CONSTRAINT fk_ad_usuario    FOREIGN KEY (usuario_id)    REFERENCES usuarios(id),
  INDEX idx_ad_horario (horario_previsto, status)
) ENGINE=InnoDB;


-- ============================================================
-- USUÁRIOS DE EXEMPLO (fictícios) — senha de todos: 123456
-- ============================================================
INSERT INTO usuarios (nome, login, senha, registro_profissional, perfil) VALUES
('Silas Santos (TI)',          'admin',    '$2y$12$wO.LCrJ30IrTtaYTqWNPmu68Rs01sp0Efj73VSVF2ga323gjiphOK', NULL,              'administrador'),
('Fernanda Kunz (Recepção)',   'fernanda', '$2y$12$wO.LCrJ30IrTtaYTqWNPmu68Rs01sp0Efj73VSVF2ga323gjiphOK', NULL,              'recepcao'),
('Dr. Ricardo Halmenschlager', 'ricardo',  '$2y$12$wO.LCrJ30IrTtaYTqWNPmu68Rs01sp0Efj73VSVF2ga323gjiphOK', 'CRM-RS 00001',    'medico'),
('Téc. Carlos Menezes',        'carlos',   '$2y$12$wO.LCrJ30IrTtaYTqWNPmu68Rs01sp0Efj73VSVF2ga323gjiphOK', 'COREN-RS 000002', 'tecnico'),
('Téc. Juliana Prado',         'juliana',  '$2y$12$wO.LCrJ30IrTtaYTqWNPmu68Rs01sp0Efj73VSVF2ga323gjiphOK', 'COREN-RS 000003', 'tecnico');


-- ============================================================
-- SETORES  (ids 1, 2 e 3)
-- ============================================================
INSERT INTO setores (nome, descricao) VALUES
('Clínica médica', 'Internação clínica geral'),
('Cirúrgica',      'Pré e pós-operatório'),
('Isolamento',     'Precaução de contato ou respiratória');


-- ============================================================
-- CIDS  (ids 1 a 12)
-- Catálogo de diagnósticos. Só de leitura no sistema.
-- Para acrescentar um código novo, insira aqui pelo Workbench.
-- ============================================================
INSERT INTO cids (codigo, descricao) VALUES
('J18.9', 'Pneumonia não especificada'),
('J44.1', 'Doença pulmonar obstrutiva crônica com exacerbação aguda'),
('E11.9', 'Diabetes mellitus tipo 2 sem complicações'),
('E10.1', 'Diabetes mellitus tipo 1 com cetoacidose'),
('I10',   'Hipertensão essencial (primária)'),
('I50.0', 'Insuficiência cardíaca congestiva'),
('N39.0', 'Infecção do trato urinário de localização não especificada'),
('K35.8', 'Apendicite aguda, outra e não especificada'),
('Z98.8', 'Outros estados pós-cirúrgicos especificados'),
('A09',   'Diarreia e gastroenterite de origem infecciosa presumível'),
('S72.0', 'Fratura do colo do fêmur'),
('L03.1', 'Celulite de outras partes do membro');


-- ============================================================
-- MEDICAMENTOS  (ids 1 a 12)
-- Catálogo da farmácia. Só de leitura no sistema.
-- ============================================================
INSERT INTO medicamentos (nome, apresentacao, via_padrao) VALUES
('Amoxicilina + Clavulanato', '500 mg comprimido',        'VO'),
('Paracetamol',               '750 mg comprimido',        'VO'),
('Dipirona',                  '500 mg/mL sol. injetável', 'IV'),
('Insulina regular',          '100 UI/mL frasco',         'SC'),
('Ceftriaxona',               '1 g pó para injeção',      'IV'),
('Omeprazol',                 '20 mg cápsula',            'VO'),
('Losartana',                 '50 mg comprimido',         'VO'),
('Metformina',                '850 mg comprimido',        'VO'),
('Enoxaparina',               '40 mg/0,4 mL seringa',     'SC'),
('Salbutamol',                '100 mcg aerossol',         'INAL'),
('Ondansetrona',              '4 mg/2 mL ampola',         'IV'),
('Morfina',                   '10 mg/mL ampola',          'SC');


-- ============================================================
-- LEITOS  (ids 1 a 14)
--   1 a  8 -> Clínica médica
--   9 a 12 -> Cirúrgica
--  13 e 14 -> Isolamento
-- ============================================================
INSERT INTO leitos (identificacao, setor_id, observacao) VALUES
('101-A', 1, NULL),
('101-B', 1, NULL),
('102-A', 1, NULL),
('102-B', 1, NULL),
('103-A', 1, NULL),
('103-B', 1, NULL),
('104-A', 1, NULL),
('104-B', 1, NULL),
('105-A', 2, NULL),
('105-B', 2, NULL),
('106-A', 2, NULL),
('106-B', 2, NULL),
('107-A', 3, 'Precaução respiratória'),
('107-B', 3, 'Precaução de contato');


-- ============================================================
-- PACIENTES E PLANTÃO DE EXEMPLO (fictícios)
--
-- As datas são RELATIVAS ao dia em que o script roda:
--   plantão de ONTEM  = já preenchido (os alunos leem o histórico)
--   plantão de HOJE   = em branco     (os alunos registram na prática)
-- Assim o exercício funciona em qualquer data, em qualquer ano.
-- ============================================================

-- O cadastro: só quem a pessoa é. (ids 1, 2 e 3)
INSERT INTO pacientes
  (nome, data_nascimento, sexo, cartao_sus, telefone, responsavel, alergias, cadastrado_por) VALUES
('Maria Aparecida Fontes', '1952-03-14', 'F', '700000000000001', '(55) 99999-0001', 'Cleusa Fontes (filha)', 'Dipirona',      2),
('João Batista Corrêa',    '1978-11-02', 'M', '700000000000002', '(55) 99999-0002', 'Marta Corrêa (esposa)', 'Nega alergias', 2),
('Helena Sartori',         '1995-06-27', 'F', '700000000000003', '(55) 99999-0003', 'Paulo Sartori (irmão)', 'Penicilina',    2);

-- A internação: leito, CID e datas. (ids 1, 2 e 3)
--   cid 1 = J18.9 Pneumonia         cid 3 = E11.9 Diabetes tipo 2
--   cid 9 = Z98.8 Pós-cirúrgico
INSERT INTO internacoes
  (paciente_id, leito_id, situacao, cid_id, data_admissao, admitido_por) VALUES
(1, 1, 'internado', 1, CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR + INTERVAL 30 MINUTE, 2),
(2, 4, 'internado', 3, CURDATE() - INTERVAL 1 DAY + INTERVAL 14 HOUR,                     2),
(3, 5, 'internado', 9, CURDATE() - INTERVAL 1 DAY + INTERVAL 15 HOUR,                     2);

-- Sinais vitais do PLANTÃO DE ONTEM
-- Cada linha leva paciente_id E internacao_id. Os pacientes 1, 2 e 3
-- estão nas internações 1, 2 e 3 respectivamente.
INSERT INTO sinais_vitais
  (paciente_id, internacao_id, usuario_id, data_hora, pa_sistolica, pa_diastolica, frequencia_cardiaca, frequencia_respiratoria, temperatura, saturacao, glicemia, escala_dor, observacao) VALUES
(1, 1, 4, CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR, 134, 84,  96, 24, 38.6, 92, NULL, 3, 'Paciente refere cansaço aos esforços'),
(1, 1, 5, CURDATE() - INTERVAL 1 DAY + INTERVAL 18 HOUR, 128, 78,  88, 20, 37.4, 95, NULL, 1, NULL),
(2, 2, 5, CURDATE() - INTERVAL 1 DAY + INTERVAL 18 HOUR, 148, 92,  80, 18, 36.5, 97,  212, 0, 'Glicemia capilar aferida antes do jantar'),
(3, 3, 4, CURDATE() - INTERVAL 1 DAY + INTERVAL 20 HOUR, 112, 72, 104, 20, 36.9, 98, NULL, 5, 'Pós-operatório imediato');

INSERT INTO registros (paciente_id, internacao_id, usuario_id, tipo, data_hora, texto) VALUES
(1, 1, 4, 'anotacao', CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR + INTERVAL 15 MINUTE, 'Paciente acordada, orientada, em ar ambiente. Refere tosse produtiva. Aceitou dieta parcialmente. Sinais vitais aferidos e registrados.'),
(1, 1, 3, 'evolucao', CURDATE() - INTERVAL 1 DAY + INTERVAL  9 HOUR,                      'Paciente com melhora do padrão respiratório, mantendo saturação acima de 92% em ar ambiente. Ausculta com estertores em base direita. Mantida antibioticoterapia. Reavaliar febre no turno da tarde.'),
(3, 3, 4, 'anotacao', CURDATE() - INTERVAL 1 DAY + INTERVAL 20 HOUR + INTERVAL 30 MINUTE, 'Curativo de ferida operatória em abdome inferior, limpo e seco, sem sinais flogísticos. Paciente refere dor 5/10, comunicado profissional responsável.');

-- O medicamento vem do catálogo:
--   1 = Amoxicilina + Clavulanato   2 = Paracetamol
--   3 = Dipirona                    4 = Insulina regular
-- A prescrição carrega paciente_id E internacao_id. Os dois, e não só o
-- segundo, porque a consulta mais comum é "as prescrições deste paciente"
-- e ela não deve precisar passar pela internação para chegar lá.
INSERT INTO prescricoes (paciente_id, internacao_id, usuario_id, medicamento_id, dose, via, frequencia, horarios, data_inicio, observacao) VALUES
(1, 1, 3, 1, '500 mg',            'VO', '8/8h',         '06:00,14:00,22:00',       CURDATE() - INTERVAL 2 DAY, 'Administrar após alimentação'),
(1, 1, 3, 2, '750 mg',            'VO', 'se necessário', NULL,                     CURDATE() - INTERVAL 2 DAY, 'Se temperatura axilar acima de 37,8 °C'),
(2, 2, 3, 4, 'conforme glicemia', 'SC', '6/6h',         '06:00,12:00,18:00,00:00', CURDATE() - INTERVAL 1 DAY, 'Seguir esquema prescrito'),
(3, 3, 3, 3, '1 g',               'IV', '6/6h',         '06:00,12:00,18:00,00:00', CURDATE() - INTERVAL 1 DAY, NULL);

-- ONTEM já checado (exemplos prontos) | HOJE pendente (exercício da turma)
INSERT INTO administracoes (prescricao_id, usuario_id, horario_previsto, data_hora_checagem, status, justificativa) VALUES
(1, 4,    CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR + INTERVAL  5 MINUTE, 'administrado',     NULL),
(1, 5,    CURDATE() - INTERVAL 1 DAY + INTERVAL 14 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL 14 HOUR + INTERVAL 15 MINUTE, 'administrado',     NULL),
(1, 5,    CURDATE() - INTERVAL 1 DAY + INTERVAL 22 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL 22 HOUR + INTERVAL 40 MINUTE, 'nao_administrado', 'Paciente recusou a medicação; profissional responsável comunicado'),
(1, NULL, CURDATE() + INTERVAL  6 HOUR, NULL, 'pendente', NULL),
(1, NULL, CURDATE() + INTERVAL 14 HOUR, NULL, 'pendente', NULL),
(1, NULL, CURDATE() + INTERVAL 22 HOUR, NULL, 'pendente', NULL),
(3, NULL, CURDATE() + INTERVAL  6 HOUR, NULL, 'pendente', NULL),
(3, NULL, CURDATE() + INTERVAL 12 HOUR, NULL, 'pendente', NULL),
(4, NULL, CURDATE() + INTERVAL  6 HOUR, NULL, 'pendente', NULL),
(4, NULL, CURDATE() + INTERVAL 12 HOUR, NULL, 'pendente', NULL);


-- ============================================================
-- CONFERÊNCIA
-- ============================================================
SELECT 'Banco criado.' AS status,
       (SELECT COUNT(*) FROM usuarios)     AS usuarios,
       (SELECT COUNT(*) FROM setores)      AS setores,
       (SELECT COUNT(*) FROM leitos)       AS leitos,
       (SELECT COUNT(*) FROM cids)         AS cids,
       (SELECT COUNT(*) FROM medicamentos) AS medicamentos,
       (SELECT COUNT(*) FROM pacientes)    AS pacientes,
       (SELECT COUNT(*) FROM internacoes)  AS internacoes;


-- ============================================================
-- CONSULTAS DE APOIO (para os alunos estudarem)
-- ============================================================

-- Quem está internado, com leito, setor e diagnóstico.
-- É o JOIN que substitui o antigo "SELECT ... FROM pacientes
-- WHERE situacao = 'internado'". Repare que já são CINCO tabelas.
-- SELECT p.nome, l.identificacao AS leito, s.nome AS setor,
--        c.codigo AS cid, c.descricao AS diagnostico,
--        DATE(i.data_admissao) AS admissao
-- FROM internacoes i
-- JOIN pacientes p ON p.id = i.paciente_id
-- LEFT JOIN leitos  l ON l.id = i.leito_id
-- LEFT JOIN setores s ON s.id = l.setor_id
-- LEFT JOIN cids    c ON c.id = i.cid_id
-- WHERE i.situacao = 'internado' AND i.ativo = 1
-- ORDER BY l.identificacao;

-- Prescrições ativas de um paciente, com o nome do medicamento.
-- SELECT m.nome, m.apresentacao, pr.dose, pr.via, pr.frequencia, pr.horarios
-- FROM prescricoes pr
-- JOIN medicamentos m ON m.id = pr.medicamento_id
-- WHERE pr.paciente_id = 1 AND pr.ativo = 1;

-- Quantas internações por diagnóstico — o relatório que só é
-- possível porque o CID é um código, e não texto digitado.
-- SELECT c.codigo, c.descricao, COUNT(*) AS internacoes
-- FROM internacoes i JOIN cids c ON c.id = i.cid_id
-- GROUP BY c.id, c.codigo, c.descricao
-- ORDER BY internacoes DESC;

-- Leitos livres: ativos e sem internação em andamento.
-- É a consulta que alimenta o <select> da tela de movimentação.
-- SELECT l.identificacao, s.nome AS setor
-- FROM leitos l
-- LEFT JOIN setores s ON s.id = l.setor_id
-- WHERE l.ativo = 1
--   AND l.id NOT IN (SELECT leito_id FROM internacoes
--                    WHERE situacao = 'internado' AND ativo = 1
--                      AND leito_id IS NOT NULL)
-- ORDER BY l.identificacao;

-- Pacientes cadastrados que NÃO estão internados agora.
-- É quem a recepção encontra na busca para reinternar.
-- SELECT p.nome, MAX(i.data_alta) AS ultima_alta
-- FROM pacientes p
-- LEFT JOIN internacoes i ON i.paciente_id = p.id
-- WHERE p.ativo = 1
--   AND p.id NOT IN (SELECT paciente_id FROM internacoes
--                    WHERE situacao = 'internado' AND ativo = 1)
-- GROUP BY p.id, p.nome;

-- Medicações pendentes de hoje (tela de checagem do técnico)
-- SELECT p.nome AS paciente, l.identificacao AS leito,
--        pr.medicamento, pr.dose, pr.via, a.horario_previsto
-- FROM administracoes a
-- JOIN prescricoes pr ON pr.id = a.prescricao_id
-- JOIN pacientes   p  ON p.id  = pr.paciente_id
-- LEFT JOIN internacoes i ON i.paciente_id = p.id AND i.situacao = 'internado'
-- LEFT JOIN leitos l ON l.id = i.leito_id
-- WHERE a.status = 'pendente' AND pr.ativo = 1
--   AND DATE(a.horario_previsto) = CURDATE()
-- ORDER BY a.horario_previsto;

-- Prontuário completo de um paciente (registros em ordem cronológica)
-- SELECT r.data_hora, r.tipo, u.nome AS autor, u.perfil, r.texto
-- FROM registros r
-- JOIN usuarios u ON u.id = r.usuario_id
-- WHERE r.paciente_id = 1
-- ORDER BY r.data_hora;

-- Conferir se algum leito está ocupado por dois pacientes.
-- O sistema impede isso no PHP; esta consulta serve para auditar.
-- SELECT l.identificacao, COUNT(*) AS quantos
-- FROM internacoes i
-- JOIN leitos l ON l.id = i.leito_id
-- WHERE i.situacao = 'internado' AND i.ativo = 1
-- GROUP BY l.identificacao HAVING quantos > 1;
