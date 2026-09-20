-- ============================================================
-- Philippine History Memory Card Game
-- Database Schema + Seed Data
-- Setup: Create DB "ph_memory_game" in phpMyAdmin, then import this file.
-- Default admin  → username: admin   | password: password
-- Default student→ username: demo    | password: password
-- To change credentials, run install.php after importing this file.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ─── Drop existing tables ────────────────────────────────────
DROP TABLE IF EXISTS performance_records;
DROP TABLE IF EXISTS session_quiz_responses;
DROP TABLE IF EXISTS session_matches;
DROP TABLE IF EXISTS game_sessions;
DROP TABLE IF EXISTS quiz_answers;
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS cards;
DROP TABLE IF EXISTS periods;
DROP TABLE IF EXISTS users;

-- ─── Create Database ─────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `ph_memory_game`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ph_memory_game`;

-- ─── users ───────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  UNIQUE NOT NULL,
  `email`         VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('student','admin') DEFAULT 'student',
  `is_active`     TINYINT(1) DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── periods ─────────────────────────────────────────────────
CREATE TABLE `periods` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100) NOT NULL,
  `description`   TEXT,
  `color`         VARCHAR(7)  DEFAULT '#FFD700',
  `icon`          VARCHAR(50) DEFAULT '📜',
  `display_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── cards ───────────────────────────────────────────────────
-- Each row = one unique matching pair type in the game grid
CREATE TABLE `cards` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(150) NOT NULL,
  `description` TEXT,
  `icon`       VARCHAR(50)  DEFAULT '📜',
  `period_id`  INT,
  `difficulty` ENUM('easy','medium','hard') DEFAULT 'easy',
  `is_active`  TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`period_id`) REFERENCES `periods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── quiz_questions ──────────────────────────────────────────
CREATE TABLE `quiz_questions` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `card_id`       INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `difficulty`    ENUM('easy','medium','hard') DEFAULT 'easy',
  `period_id`     INT,
  `is_active`     TINYINT(1) DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`card_id`)   REFERENCES `cards`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`period_id`) REFERENCES `periods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── quiz_answers ────────────────────────────────────────────
CREATE TABLE `quiz_answers` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `question_id`   INT NOT NULL,
  `answer_text`   TEXT NOT NULL,
  `is_correct`    TINYINT(1) DEFAULT 0,
  `display_order` INT DEFAULT 0,
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── game_sessions ───────────────────────────────────────────
CREATE TABLE `game_sessions` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`            INT NOT NULL,
  `difficulty`         ENUM('easy','medium','hard') DEFAULT 'easy',
  `card_ids`           TEXT,       -- JSON-encoded array of selected card IDs
  `started_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at`       TIMESTAMP NULL,
  `total_time_seconds` INT DEFAULT 0,
  `is_completed`       TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── session_matches ─────────────────────────────────────────
CREATE TABLE `session_matches` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `session_id`  INT NOT NULL,
  `card_id`     INT NOT NULL,
  `question_id` INT,
  `matched_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`session_id`)  REFERENCES `game_sessions`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`card_id`)     REFERENCES `cards`(`id`)           ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── session_quiz_responses ──────────────────────────────────
CREATE TABLE `session_quiz_responses` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `session_id`   INT NOT NULL,
  `question_id`  INT NOT NULL,
  `answer_id`    INT,
  `is_correct`   TINYINT(1) DEFAULT 0,
  `responded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`session_id`)  REFERENCES `game_sessions`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`answer_id`)   REFERENCES `quiz_answers`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── performance_records ─────────────────────────────────────
CREATE TABLE `performance_records` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT NOT NULL,
  `session_id`       INT NOT NULL,
  `difficulty`       ENUM('easy','medium','hard'),
  `total_pairs`      INT DEFAULT 0,
  `matched_pairs`    INT DEFAULT 0,
  `correct_answers`  INT DEFAULT 0,
  `total_questions`  INT DEFAULT 0,
  `score`            INT DEFAULT 0,
  `time_seconds`     INT DEFAULT 0,
  `completed_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)         ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `game_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ─── Periods ─────────────────────────────────────────────────
INSERT INTO `periods` (`name`,`description`,`color`,`icon`,`display_order`) VALUES
('Spanish Colonization','The Spanish colonial period from 1565 to 1898, spanning over 300 years','#8B4513','⚓',1),
('Philippine Revolution','The revolutionary period 1896–1898 leading to Philippine independence','#DC143C','✊',2),
('American Period','American colonial rule from 1898 to 1946','#1E3A8A','🦅',3),
('World War II','Japanese occupation and liberation of the Philippines 1941–1945','#556B2F','⚔️',4),
('Contemporary Period','Post-independence Philippines from 1946 to present','#B8860B','🇵🇭',5);

-- ─── Default Users ────────────────────────────────────────────
-- Passwords are bcrypt hash of "password"
-- Run install.php to change credentials
INSERT INTO `users` (`username`,`email`,`password_hash`,`role`) VALUES
('admin','admin@phgame.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
('demo','demo@phgame.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student');

-- ─── Cards ───────────────────────────────────────────────────
-- SPANISH COLONIZATION – EASY (IDs 1–10)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Ferdinand Magellan','Portuguese explorer who led the 1521 Spanish expedition that first reached the Philippine archipelago, killed at the Battle of Mactan','⚓',1,'easy'),
('Lapulapu','Chieftain of Mactan Island who defeated and killed Ferdinand Magellan in 1521, considered the first Filipino hero','⚔️',1,'easy'),
('Battle of Mactan','The 1521 battle where Filipino warriors under Lapulapu defeated Spanish forces and killed Magellan, halting the initial conquest','🏹',1,'easy'),
('Miguel Lopez de Legazpi','First Spanish Governor-General of the Philippines who established permanent colonial rule in 1565 and founded Manila in 1571','👑',1,'easy'),
('Manila (Colonial Capital)','Capital city established by Legazpi in 1571, built on the site of the Muslim settlement of Maynila beside the Pasig River','🏙️',1,'easy'),
('Galleon Trade','The Manila-Acapulco trade route that operated 1565–1815, making Manila a global trading hub for silk, spices, and silver','⛵',1,'easy'),
('Encomienda System','Spanish colonial labor system granting colonizers (encomenderos) tribute rights and authority over indigenous communities','📜',1,'easy'),
('Intramuros','The walled stone city of Manila built by Spanish colonizers in 1571, serving as the administrative and religious center of colonial power','🏰',1,'easy'),
('Catholic Friars','Members of religious orders (Augustinians, Dominicans, Franciscans, Jesuits) who dominated education, politics, and society in colonial Philippines','✝️',1,'easy'),
('Reduccion Policy','Spanish colonial policy that consolidated scattered indigenous communities into organized towns (pueblos) centered around a church','🏘️',1,'easy');

-- SPANISH COLONIZATION – MEDIUM (IDs 11–15)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Propaganda Movement','Late-19th century reform campaign by educated Filipinos (ilustrados) in Europe demanding equal rights and representation under Spain','✍️',1,'medium'),
('La Liga Filipina','Civic organization founded by Jose Rizal in Manila on July 3, 1892, dissolved by Spanish authorities just days later, leading to the Katipunan','🤝',1,'medium'),
('Ilustrado Class','The educated Filipino elite who had studied in Europe and led peaceful reform campaigns; figures include Rizal, del Pilar, and Lopez Jaena','📚',1,'medium'),
('Basi Revolt (1807)','Revolt in Ilocos Norte led by Pedro Mateo against the Spanish government\'s monopoly on basi (sugarcane wine), brutally suppressed','🍶',1,'medium'),
('Dagohoy Rebellion','The longest revolt in Philippine colonial history (1744–1829), lasting 85 years in Bohol, led by Francisco Dagohoy against Spanish authority','⚡',1,'medium');

-- SPANISH COLONIZATION – HARD (IDs 16–20)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Pact of Biak-na-Bato','1897 peace agreement where Aguinaldo and other leaders accepted exile to Hong Kong in exchange for monetary indemnity and promised reforms','🕊️',1,'hard'),
('Diego Silang','Ilocano revolutionary leader who revolted against Spanish rule in 1762 with British support; assassinated in 1763 by a hired killer','🗡️',1,'hard'),
('Gabriela Silang','Wife of Diego Silang who continued the Ilocos revolution after her husband\'s assassination, becoming the first woman to lead a Philippine armed uprising','💪',1,'hard'),
('Real Audiencia','The highest court and advisory body established by Spain in Manila; served judicial, executive, and legislative advisory functions in colonial governance','⚖️',1,'hard'),
('Polo y Servicios','Forced labor system requiring Filipino males aged 16–60 to render 40 days of unpaid labor per year for colonial public works projects','⛏️',1,'hard');

-- PHILIPPINE REVOLUTION – EASY (IDs 21–25)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Andres Bonifacio','Father of the Philippine Revolution, self-educated leader from Manila who founded the Katipunan in 1892 and launched the 1896 uprising','✊',2,'easy'),
('Katipunan','Secret revolutionary society (Kataas-taasang Kagalang-galangang Katipunan ng mga Anak ng Bayan) founded by Bonifacio in 1892 to fight Spanish rule','🔰',2,'easy'),
('Cry of Pugad Lawin','August 1896 event where Katipuneros tore their cedulas (tax certificates), formally beginning the Philippine Revolution against Spain','📢',2,'easy'),
('Philippine Independence 1898','Formal proclamation of Philippine independence by Emilio Aguinaldo on June 12, 1898 in Kawit, Cavite, with the first waving of the Philippine flag','🇵🇭',2,'easy'),
('Malolos Constitution','First Philippine constitution ratified in 1899 by the Malolos Congress, establishing a democratic republic with separation of church and state','📋',2,'easy');

-- PHILIPPINE REVOLUTION – MEDIUM (IDs 26–30)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Emilio Aguinaldo','First President of the Philippines, leader of the Philippine Revolution against Spain, and later the Philippine-American War against the United States','🎖️',2,'medium'),
('Apolinario Mabini','Called "The Brains of the Revolution" and "The Sublime Paralytic," he was Aguinaldo\'s chief adviser and drafter of the Malolos Constitution','🧠',2,'medium'),
('Antonio Luna','Brilliant and fierce Filipino general who commanded forces against Americans in the Philippine-American War; assassinated on June 5, 1899','⚔️',2,'medium'),
('Tejeros Convention','March 22, 1897 assembly in Cavite where Aguinaldo was elected President, effectively replacing Bonifacio who questioned its legality','🗳️',2,'medium'),
('Jose Rizal','Philippine national hero; novelist, doctor, and polymath executed by Spain on December 30, 1896; his execution helped spark the Revolution','📖',2,'medium');

-- PHILIPPINE REVOLUTION – HARD (IDs 31–33)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Gregorio del Pilar','Called "The Boy General," youngest general of the Revolution who died defending Tirad Pass on December 2, 1899, at only 24 years of age','🌟',2,'hard'),
('Battle of Tirad Pass','December 2, 1899 battle where del Pilar and ~60 Filipino soldiers held off ~500 American troops, sacrificing themselves to allow Aguinaldo to escape','🏔️',2,'hard'),
('Noli Me Tangere','Rizal\'s 1887 social novel written in Spanish meaning "Touch Me Not"; exposed abuses of Spanish friars and colonial authorities, inspiring nationalism','📗',2,'hard');

-- AMERICAN PERIOD – EASY (IDs 34–38)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Treaty of Paris 1898','Treaty signed December 10, 1898 where Spain ceded the Philippines, Cuba, Guam, and Puerto Rico to the United States for $20 million','📝',3,'easy'),
('Philippine-American War','1899–1902 war between the First Philippine Republic and United States forces; sometimes called the Philippine War of Independence','💥',3,'easy'),
('Manuel Quezon','First President of the Philippine Commonwealth (1935–1944); established the government-in-exile in Washington D.C. during World War II','🏛️',3,'easy'),
('Commonwealth of the Philippines','Transitional semi-autonomous government (1935–1946) established by the Tydings-McDuffie Act to prepare Filipinos for full independence','🏦',3,'easy'),
('Thomasites','Around 500 American volunteer teachers who arrived aboard the USS Thomas in 1901 to establish the Philippine public school system','🎓',3,'easy');

-- AMERICAN PERIOD – MEDIUM (IDs 39–43)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Jones Act 1916','The Philippine Autonomy Act of 1916 that promised eventual Philippine independence and created a fully elective bicameral Philippine legislature','⚖️',3,'medium'),
('Pensionados Program','Government-sponsored scholarship program launched in 1903 sending Filipino students to American universities to prepare future Philippine leaders','✈️',3,'medium'),
('William Howard Taft','First civil Governor-General of the Philippines (1901–1903); later became the 27th US President; championed the "Philippines for Filipinos" policy','👔',3,'medium'),
('Philippine Commission','US-appointed legislative body (1900–1916) that governed the Philippines; the Second Commission under Taft enacted hundreds of laws','🏛️',3,'medium'),
('Tydings-McDuffie Act','1934 Philippine Independence Act that authorized a 10-year Commonwealth period ending with full independence; signed by President Roosevelt','📜',3,'medium');

-- AMERICAN PERIOD – HARD (IDs 44–45)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Balangiga Massacre','September 28, 1901 surprise attack by Filipino guerrillas in Samar killing 48 US soldiers; led to brutal US reprisals against civilians','💀',3,'hard'),
('Water Cure Torture','Controversial interrogation method — forced water ingestion — used by American soldiers against Filipino prisoners, exposing harsh US colonial practices','🚿',3,'hard');

-- WORLD WAR II – EASY (IDs 46–50)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Japanese Invasion 1941','Japan invaded the Philippines on December 8, 1941 (December 7 in Hawaii), one day after the Pearl Harbor attack, beginning a brutal occupation','⚡',4,'easy'),
('Bataan Death March','Forced 65-mile march of approximately 75,000 Filipino and American prisoners of war in April 1942 under brutal Japanese conditions; thousands died','💀',4,'easy'),
('Douglas MacArthur','US General who commanded Allied forces in the Philippines; after leaving Bataan he famously declared "I shall return" and led the 1944 liberation','🎖️',4,'easy'),
('Battle of Leyte Gulf','Largest naval battle in history (October 23–26, 1944), fought in Philippine waters; Allied victory that opened the way for liberating the Philippines','⛵',4,'easy'),
('Manila Massacre (1945)','February 1945 atrocity committed by retreating Japanese forces who killed over 100,000 Filipino civilians in Manila; comparable to the Nanjing Massacre','😢',4,'easy');

-- WORLD WAR II – MEDIUM (IDs 51–53)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('HUKBALAHAP Movement','People\'s Anti-Japanese Army (Hukbo ng Bayan Laban sa Hapon), a communist-led guerrilla movement that fought the Japanese occupation from 1942–1945','✊',4,'medium'),
('Jose P. Laurel','President of the Japanese-sponsored Philippine Republic (1943–1945); controversially refused to declare war on the US and resisted sending Filipinos to fight','🎩',4,'medium'),
('Fall of Corregidor','May 6, 1942: the island fortress of Corregidor fell after a siege, forcing General Wainwright\'s surrender and completing Japanese control of the Philippines','🏝️',4,'medium');

-- CONTEMPORARY PERIOD – EASY (IDs 54–56)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Philippine Independence 1946','Full independence granted on July 4, 1946, with Manuel Roxas as the first President of the fully independent Third Republic of the Philippines','🎊',5,'easy'),
('People Power Revolution','February 22–25, 1986 peaceful civilian uprising along Epifanio de los Santos Avenue (EDSA) that ousted Ferdinand Marcos and restored democracy','✊',5,'easy'),
('Ferdinand Marcos','President who declared Martial Law on September 21, 1972; ruled as dictator until ousted by the People Power Revolution in February 1986','📣',5,'easy');

-- CONTEMPORARY PERIOD – MEDIUM (IDs 57–58)
INSERT INTO `cards` (`title`,`description`,`icon`,`period_id`,`difficulty`) VALUES
('Corazon Aquino','First female President of the Philippines (1986–1992); restored democratic institutions after Marcos, though her term faced multiple coup attempts','💛',5,'medium'),
('Benigno Aquino Jr.','Opposition senator and Marcos critic assassinated at Manila International Airport on August 21, 1983; his death galvanized the opposition that led to EDSA','🕊️',5,'medium');

-- ─── Quiz Questions ──────────────────────────────────────────
-- One question per card (card_id 1–58 → question_id 1–58)
INSERT INTO `quiz_questions` (`card_id`,`question_text`,`difficulty`,`period_id`) VALUES
-- 1: Ferdinand Magellan
(1,'Ferdinand Magellan was a Portuguese explorer serving Spain. In what year did he first arrive in the Philippine archipelago?','easy',1),
-- 2: Lapulapu
(2,'Lapulapu is considered the first Filipino hero for resisting foreign conquest. He was the chieftain of which island?','easy',1),
-- 3: Battle of Mactan
(3,'The Battle of Mactan took place on April 27, 1521. Who commanded the Filipino forces that defeated Magellan?','easy',1),
-- 4: Miguel Lopez de Legazpi
(4,'Miguel Lopez de Legazpi established permanent Spanish rule in the Philippines. In which year did he formally found the city of Manila?','easy',1),
-- 5: Manila (Colonial Capital)
(5,'Colonial Manila was built on the site of an existing Muslim settlement. What was the original name of that settlement?','easy',1),
-- 6: Galleon Trade
(6,'The Manila Galleon Trade connected Manila with which port city in present-day Mexico?','easy',1),
-- 7: Encomienda System
(7,'Under the Encomienda System, what were indigenous Filipinos primarily required to give their Spanish encomenderos?','easy',1),
-- 8: Intramuros
(8,'Intramuros is the historic walled city of Manila. What does the Latin word "Intramuros" mean in English?','easy',1),
-- 9: Catholic Friars
(9,'Which Catholic religious order was the FIRST to arrive in the Philippines and begin evangelizing the local population?','easy',1),
-- 10: Reduccion Policy
(10,'The Reduccion policy was implemented by Spain across the Philippines. What was its primary purpose?','easy',1),
-- 11: Propaganda Movement
(11,'The Propaganda Movement was led by Filipino reformists in Europe. Which of the following was NOT a prominent leader of this movement?','medium',1),
-- 12: La Liga Filipina
(12,'La Liga Filipina was founded by Jose Rizal. In what year was it established in Manila?','medium',1),
-- 13: Ilustrado Class
(13,'The Ilustrado class were educated Filipino elites. The term "ilustrado" comes from Spanish meaning what?','medium',1),
-- 14: Basi Revolt
(14,'The Basi Revolt of 1807 occurred in which Philippine province?','medium',1),
-- 15: Dagohoy Rebellion
(15,'The Dagohoy Rebellion is the longest revolt in Philippine colonial history. Approximately how long did it last?','medium',1),
-- 16: Pact of Biak-na-Bato
(16,'Under the Pact of Biak-na-Bato (December 1897), what did Emilio Aguinaldo and the revolutionary leaders agree to do?','hard',1),
-- 17: Diego Silang
(17,'Diego Silang led an Ilocos revolt in 1762 with the support of which European power that was then at war with Spain?','hard',1),
-- 18: Gabriela Silang
(18,'After Diego Silang\'s assassination, Gabriela Silang continued the revolt. How was she ultimately captured and executed?','hard',1),
-- 19: Real Audiencia
(19,'The Real Audiencia de Manila was established in 1583. Which of the following best describes its primary function?','hard',1),
-- 20: Polo y Servicios
(20,'Under the Polo y Servicios system, how many days of forced labor per year were Filipino men required to render?','hard',1),
-- 21: Andres Bonifacio
(21,'Andres Bonifacio, the Father of the Philippine Revolution, worked as what occupation before becoming a revolutionary leader?','easy',2),
-- 22: Katipunan
(22,'The Katipunan was a secret revolutionary society. What does "KKK" stand for in its full Filipino name?','easy',2),
-- 23: Cry of Pugad Lawin
(23,'During the Cry of Pugad Lawin in August 1896, what did the Katipuneros dramatically tear to symbolize rejection of Spanish authority?','easy',2),
-- 24: Philippine Independence 1898
(24,'Philippine Independence was proclaimed on June 12, 1898 in which town in Cavite?','easy',2),
-- 25: Malolos Constitution
(25,'The Malolos Constitution of 1899 was significant as the first Philippine constitution. What type of separation did it establish that was revolutionary for the time?','easy',2),
-- 26: Emilio Aguinaldo
(26,'Emilio Aguinaldo served as the First President of the Philippines. In which year was he finally captured by American forces, effectively ending the Philippine-American War?','medium',2),
-- 27: Apolinario Mabini
(27,'Apolinario Mabini was called "The Brains of the Revolution." Despite being paralyzed from the waist down, what important document did he help draft?','medium',2),
-- 28: Antonio Luna
(28,'General Antonio Luna was assassinated on June 5, 1899. He was known for his strict military discipline. What country did he study in before returning to the Philippines?','medium',2),
-- 29: Tejeros Convention
(29,'The Tejeros Convention (March 1897) resulted in Aguinaldo being elected President. What did Andres Bonifacio do in response to this result?','medium',2),
-- 30: Jose Rizal
(30,'Jose Rizal was executed by firing squad on December 30, 1896. At which location in Manila was he killed?','medium',2),
-- 31: Gregorio del Pilar
(31,'Gregorio del Pilar, "The Boy General," died at the Battle of Tirad Pass. How old was he at the time of his death in 1899?','hard',2),
-- 32: Battle of Tirad Pass
(32,'At the Battle of Tirad Pass (December 2, 1899), approximately how many Filipino soldiers under del Pilar faced the American forces?','hard',2),
-- 33: Noli Me Tangere
(33,'Jose Rizal\'s novel "Noli Me Tangere" was published in 1887. In which language was it originally written?','hard',2),
-- 34: Treaty of Paris 1898
(34,'Under the Treaty of Paris of 1898, the United States paid Spain how much for the Philippines?','easy',3),
-- 35: Philippine-American War
(35,'The Philippine-American War began when a Filipino soldier was shot near which bridge in Manila on February 4, 1899?','easy',3),
-- 36: Manuel Quezon
(36,'Manuel Quezon is famous for which statement about preferring Philippine governance even if imperfect?','easy',3),
-- 37: Commonwealth of the Philippines
(37,'The Commonwealth of the Philippines was established in 1935. Under which US act was it created?','easy',3),
-- 38: Thomasites
(38,'The American teachers called Thomasites arrived in 1901. They were named after the USS Thomas. How many teachers approximately arrived on this ship?','easy',3),
-- 39: Jones Act 1916
(39,'The Jones Act of 1916 (Philippine Autonomy Act) was significant because it was the first US law to formally promise what to the Philippines?','medium',3),
-- 40: Pensionados Program
(40,'The Pensionados Program started in 1903. What was the primary goal of sending Filipino scholars to study in America?','medium',3),
-- 41: William Howard Taft
(41,'William Howard Taft, as Governor-General of the Philippines, reportedly referred to Filipinos using which controversial phrase?','medium',3),
-- 42: Philippine Commission
(42,'The Second Philippine Commission (Taft Commission, 1900) was tasked mainly with which responsibility?','medium',3),
-- 43: Tydings-McDuffie Act
(43,'The Tydings-McDuffie Act of 1934 set the Commonwealth period before full independence. How many years did it specify?','medium',3),
-- 44: Balangiga Massacre
(44,'The bells from the Balangiga church were taken by US forces as trophies after the 1901 massacre. In what year were they finally returned to the Philippines?','hard',3),
-- 45: Water Cure Torture
(45,'The Water Cure torture used by US forces in the Philippines was exposed by which type of investigation in the United States?','hard',3),
-- 46: Japanese Invasion 1941
(46,'Japan attacked the Philippines on December 8, 1941 (Philippine time). What US military base in the Philippines was among the first to be bombed?','easy',4),
-- 47: Bataan Death March
(47,'The Bataan Death March began in April 1942 after the fall of Bataan. Approximately how many Filipino and American soldiers were forced to march?','easy',4),
-- 48: Douglas MacArthur
(48,'General Douglas MacArthur made his famous "I shall return" pledge after leaving which location in the Philippines?','easy',4),
-- 49: Battle of Leyte Gulf
(49,'The Battle of Leyte Gulf in October 1944 saw the first large-scale use of which Japanese tactic involving pilots deliberately crashing their planes?','easy',4),
-- 50: Manila Massacre (1945)
(50,'The Manila Massacre of February 1945 resulted in massive civilian casualties. Which military unit was primarily responsible for the atrocities?','easy',4),
-- 51: HUKBALAHAP
(51,'The HUKBALAHAP was an anti-Japanese guerrilla movement. After World War II, it transformed into an insurgency against what?','medium',4),
-- 52: Jose P. Laurel
(52,'Jose Laurel, president under the Japanese-sponsored Republic, made a controversial decision regarding war. What did he do?','medium',4),
-- 53: Fall of Corregidor
(53,'After the Fall of Corregidor on May 6, 1942, General Jonathan Wainwright was forced to broadcast what to Filipino forces still fighting?','medium',4),
-- 54: Philippine Independence 1946
(54,'The Philippines gained independence on July 4, 1946. Who was the first President of the fully independent Philippines?','easy',5),
-- 55: People Power Revolution
(55,'The People Power Revolution took place on EDSA (Epifanio de los Santos Avenue). What peaceful method did civilians use to stop military tanks?','easy',5),
-- 56: Ferdinand Marcos
(56,'Ferdinand Marcos declared Martial Law on September 21, 1972. Under Martial Law, which of the following did NOT occur?','easy',5),
-- 57: Corazon Aquino
(57,'Corazon Aquino became President after the 1986 People Power Revolution. Her presidency faced how many coup attempts?','medium',5),
-- 58: Benigno Aquino Jr.
(58,'Benigno "Ninoy" Aquino Jr. was assassinated at Manila International Airport on August 21, 1983. His death is credited with energizing which movement?','medium',5);

-- ─── Quiz Answers ────────────────────────────────────────────
-- 4 answers per question; is_correct=1 marks the correct answer

-- Q1: Ferdinand Magellan arrival year
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(1,'1492',0,1),(1,'1521',1,2),(1,'1565',0,3),(1,'1543',0,4);
-- Q2: Lapulapu's island
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(2,'Cebu',0,1),(2,'Leyte',0,2),(2,'Mactan',1,3),(2,'Bohol',0,4);
-- Q3: Battle of Mactan commander
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(3,'Rajah Humabon',0,1),(3,'Lapulapu',1,2),(3,'Andres Bonifacio',0,3),(3,'Emilio Aguinaldo',0,4);
-- Q4: Legazpi founded Manila
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(4,'1565',0,1),(4,'1570',0,2),(4,'1571',1,3),(4,'1580',0,4);
-- Q5: Original name of Manila
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(5,'Tondo',0,1),(5,'Maynila',1,2),(5,'Selurong',0,3),(5,'Namayan',0,4);
-- Q6: Manila Galleon destination
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(6,'Veracruz',0,1),(6,'Cartagena',0,2),(6,'Acapulco',1,3),(6,'Panama City',0,4);
-- Q7: Encomienda tribute
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(7,'Military service only',0,1),(7,'Tribute (taxes) and forced labor',1,2),(7,'Land and crops only',0,3),(7,'Religious conversion only',0,4);
-- Q8: Intramuros meaning
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(8,'Inner Harbor',0,1),(8,'Within the Walls',1,2),(8,'City of Light',0,3),(8,'Royal Fort',0,4);
-- Q9: First religious order
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(9,'Jesuits',0,1),(9,'Dominicans',0,2),(9,'Augustinians',1,3),(9,'Franciscans',0,4);
-- Q10: Reduccion purpose
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(10,'To build more churches faster',0,1),(10,'To centralize natives into towns for easier control and conversion',1,2),(10,'To encourage inter-island trade',0,3),(10,'To teach the Spanish language',0,4);
-- Q11: NOT a Propaganda Movement leader
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(11,'Marcelo del Pilar',0,1),(11,'Graciano Lopez Jaena',0,2),(11,'Andres Bonifacio',1,3),(11,'Jose Rizal',0,4);
-- Q12: La Liga year founded
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(12,'1890',0,1),(12,'1891',0,2),(12,'1892',1,3),(12,'1894',0,4);
-- Q13: Ilustrado meaning
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(13,'Wealthy landowner',0,1),(13,'Enlightened or educated',1,2),(13,'Born of noble blood',0,3),(13,'Loyal to Spain',0,4);
-- Q14: Basi Revolt province
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(14,'Ilocos Sur',0,1),(14,'Ilocos Norte',1,2),(14,'La Union',0,3),(14,'Pangasinan',0,4);
-- Q15: Dagohoy duration
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(15,'50 years',0,1),(15,'65 years',0,2),(15,'85 years',1,3),(15,'100 years',0,4);
-- Q16: Pact of Biak-na-Bato
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(16,'They agreed to launch a new uprising',0,1),(16,'They accepted exile to Hong Kong and monetary indemnity',1,2),(16,'They surrendered unconditionally to Spain',0,3),(16,'They agreed to govern the Philippines jointly with Spain',0,4);
-- Q17: Diego Silang supporter
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(17,'France',0,1),(17,'Netherlands',0,2),(17,'Great Britain',1,3),(17,'Portugal',0,4);
-- Q18: Gabriela Silang capture
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(18,'She was captured in battle and publicly hanged in Vigan',1,1),(18,'She escaped to Cebu and surrendered peacefully',0,2),(18,'She was captured and imprisoned in Intramuros',0,3),(18,'She was exiled to Guam',0,4);
-- Q19: Real Audiencia function
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(19,'A trading company controlling Manila commerce',0,1),(19,'The highest court and advisory body in colonial Philippines',1,2),(19,'A military headquarters for colonial defense',0,3),(19,'A religious council overseeing the friars',0,4);
-- Q20: Polo y Servicios days
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(20,'20 days',0,1),(20,'30 days',0,2),(20,'40 days',1,3),(20,'60 days',0,4);
-- Q21: Bonifacio's occupation
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(21,'Rice trader',0,1),(21,'Lawyer',0,2),(21,'Warehouse worker and bodega agent',1,3),(21,'Schoolteacher',0,4);
-- Q22: Katipunan full name meaning
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(22,'Highest and Most Venerable Association of the Children of the Nation',1,1),(22,'Brotherhood of Filipino Patriots Fighting for Freedom',0,2),(22,'Supreme Council of Philippine Revolutionary Sons',0,3),(22,'Secret Society of Enlightened Filipino Youth',0,4);
-- Q23: What was torn at Cry of Pugad Lawin
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(23,'Spanish flags',0,1),(23,'Church documents',0,2),(23,'Their cedulas (community tax certificates)',1,3),(23,'Their military IDs',0,4);
-- Q24: Town of independence proclamation
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(24,'Imus',0,1),(24,'Kawit',1,2),(24,'Noveleta',0,3),(24,'Naic',0,4);
-- Q25: Malolos Constitution separation
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(25,'Separation of military and civilian powers',0,1),(25,'Separation of executive and legislative',0,2),(25,'Separation of Church and State',1,3),(25,'Separation of Spanish and Filipino governance',0,4);
-- Q26: Aguinaldo captured year
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(26,'1899',0,1),(26,'1900',0,2),(26,'1901',1,3),(26,'1902',0,4);
-- Q27: Mabini's document
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(27,'The Noli Me Tangere',0,1),(27,'The Katipunan charter',0,2),(27,'The Malolos Constitution',1,3),(27,'The Philippine-American peace treaty',0,4);
-- Q28: Luna's country of study
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(28,'United States',0,1),(28,'France',1,2),(28,'Germany',0,3),(28,'Spain',0,4);
-- Q29: Bonifacio's response to Tejeros
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(29,'He immediately accepted the results and supported Aguinaldo',0,1),(29,'He questioned the election\'s legality and refused to recognize the results',1,2),(29,'He surrendered to Spanish authorities',0,3),(29,'He formed a rival revolutionary government in Batangas',0,4);
-- Q30: Rizal execution site
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(30,'Fort Santiago',0,1),(30,'Bagumbayan (now Luneta/Rizal Park)',1,2),(30,'Intramuros wall',0,3),(30,'Malacañang Palace grounds',0,4);
-- Q31: Del Pilar's age at death
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(31,'19 years old',0,1),(31,'21 years old',0,2),(31,'24 years old',1,3),(31,'28 years old',0,4);
-- Q32: Filipino soldiers at Tirad Pass
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(32,'About 200',0,1),(32,'About 100',0,2),(32,'About 60',1,3),(32,'About 30',0,4);
-- Q33: Noli Me Tangere language
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(33,'Filipino (Tagalog)',0,1),(33,'English',0,2),(33,'Spanish',1,3),(33,'Latin',0,4);
-- Q34: Treaty of Paris payment
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(34,'$10 million',0,1),(34,'$20 million',1,2),(34,'$50 million',0,3),(34,'$5 million',0,4);
-- Q35: First shot of Philippine-American War
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(35,'San Juan Bridge',1,1),(35,'Jones Bridge',0,2),(35,'Macarthur Bridge',0,3),(35,'Quezon Bridge',0,4);
-- Q36: Quezon famous quote
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(36,'"I prefer a government run like hell by Filipinos to one run like heaven by Americans"',1,1),(36,'"The Philippines for Filipinos above all"',0,2),(36,'"Better dead than enslaved by a foreign power"',0,3),(36,'"Independence or nothing"',0,4);
-- Q37: Commonwealth act
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(37,'Jones Act',0,1),(37,'Harrison Act',0,2),(37,'Tydings-McDuffie Act',1,3),(37,'Philippine Autonomy Act',0,4);
-- Q38: Number of Thomasites
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(38,'About 200',0,1),(38,'About 300',0,2),(38,'About 500',1,3),(38,'About 1,000',0,4);
-- Q39: Jones Act promise
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(39,'American citizenship to Filipinos',0,1),(39,'Future Philippine independence',1,2),(39,'Equal pay for Filipino workers',0,3),(39,'Full voting rights in US elections',0,4);
-- Q40: Pensionados goal
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(40,'To train Filipino military officers',0,1),(40,'To Americanize Filipino culture',0,2),(40,'To prepare Filipinos for eventual self-governance',1,3),(40,'To study American agricultural practices',0,4);
-- Q41: Taft's phrase
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(41,'"Our little brown brothers"',1,1),(41,'"Our Filipino partners"',0,2),(41,'"Our island allies"',0,3),(41,'"Our Pacific subjects"',0,4);
-- Q42: Second Philippine Commission role
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(42,'Conducting a census of Filipinos',0,1),(42,'Acting as the legislative body and enacting laws',1,2),(42,'Leading military campaigns against guerrillas',0,3),(42,'Managing the Galleon Trade routes',0,4);
-- Q43: Tydings-McDuffie years
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(43,'5 years',0,1),(43,'8 years',0,2),(43,'10 years',1,3),(43,'15 years',0,4);
-- Q44: Balangiga bells return year
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(44,'1998',0,1),(44,'2005',0,2),(44,'2012',0,3),(44,'2018',1,4);
-- Q45: Water Cure exposed by
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(45,'A Filipino revolutionary pamphlet',0,1),(45,'US Senate hearings investigating Army conduct',1,2),(45,'A Spanish diplomatic protest',0,3),(45,'A New York Times investigative report',0,4);
-- Q46: First base bombed
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(46,'Corregidor Island',0,1),(46,'Clark Air Base',1,2),(46,'Subic Bay Naval Station',0,3),(46,'Fort Stotsenburg',0,4);
-- Q47: Bataan Death March prisoners
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(47,'About 30,000',0,1),(47,'About 50,000',0,2),(47,'About 75,000',1,3),(47,'About 100,000',0,4);
-- Q48: MacArthur's departure point
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(48,'Corregidor Island',1,1),(48,'Bataan Peninsula',0,2),(48,'Manila',0,3),(48,'Leyte',0,4);
-- Q49: First Kamikaze use
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(49,'Human torpedo attacks',0,1),(49,'Kamikaze (divine wind) suicide air attacks',1,2),(49,'Banzai infantry charges',0,3),(49,'Submarine warfare',0,4);
-- Q50: Manila Massacre perpetrators
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(50,'Japanese Imperial Army — Shimbu Group',1,1),(50,'Japanese Air Force units',0,2),(50,'Korean conscript troops',0,3),(50,'Japanese Navy Marines',0,4);
-- Q51: Huk post-war transformation
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(51,'An insurgency against the post-war Philippine government',1,1),(51,'A political party in Congress',0,2),(51,'A veterans\' welfare organization',0,3),(51,'An anti-American protest movement',0,4);
-- Q52: Laurel on declaring war
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(52,'He eagerly sent Filipino troops to fight alongside Japan',0,1),(52,'He declared war on paper but refused to draft Filipinos into combat',1,2),(52,'He surrendered to MacArthur immediately',0,3),(52,'He allied with the HUKBALAHAP guerrillas',0,4);
-- Q53: Wainwright's broadcast
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(53,'A call to continue guerrilla resistance',0,1),(53,'A declaration of Philippine independence',0,2),(53,'An order for all remaining Filipino forces to surrender',1,3),(53,'A message requesting American reinforcements',0,4);
-- Q54: First independent Philippine president
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(54,'Manuel Quezon',0,1),(54,'Sergio Osmeña',0,2),(54,'Manuel Roxas',1,3),(54,'Ramon Magsaysay',0,4);
-- Q55: People Power method
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(55,'Armed civilians fighting the military',0,1),(55,'Civilians forming human chains, praying, and offering flowers to soldiers',1,2),(55,'A general strike that paralyzed the economy',0,3),(55,'A mass march on Malacañang Palace',0,4);
-- Q56: Martial Law did NOT
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(56,'Suppress of press freedom and imprison opposition',0,1),(56,'Abolish the bicameral Congress',0,2),(56,'Grant the Philippines full independence from the US',1,3),(56,'Suspend the writ of habeas corpus',0,4);
-- Q57: Aquino coup attempts
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(57,'3 coup attempts',0,1),(57,'5 coup attempts',0,2),(57,'6 coup attempts',1,3),(57,'10 coup attempts',0,4);
-- Q58: Ninoy Aquino's movement
INSERT INTO `quiz_answers` (`question_id`,`answer_text`,`is_correct`,`display_order`) VALUES
(58,'The founding of the Katipunan',0,1),(58,'The People Power Revolution of 1986',1,2),(58,'The proclamation of Martial Law',0,3),(58,'The Commonwealth period reforms',0,4);
