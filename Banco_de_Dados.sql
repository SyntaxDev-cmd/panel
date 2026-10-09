-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 26-09-2025 a las 11:47:14
-- Versión del servidor: 10.6.23-MariaDB-cll-lve
-- Versión de PHP: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `yorchapk_rokuadmin`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_admin`
--

CREATE TABLE `tbl_admin` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` text NOT NULL,
  `email` varchar(200) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 1,
  `admin_type` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_admin`
--

INSERT INTO `tbl_admin` (`id`, `username`, `password`, `email`, `image`, `status`, `admin_type`) VALUES
(1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'admin@gmail.com', '10308_profile.PNG', 1, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_app`
--

CREATE TABLE `tbl_app` (
  `app_name` varchar(255) NOT NULL,
  `app_logo` varchar(255) NOT NULL,
  `bg_main` varchar(255) DEFAULT NULL,
  `bg_login` varchar(255) DEFAULT NULL,
  `bg_banner` varchar(255) DEFAULT NULL,
  `app_titulo` varchar(255) NOT NULL,
  `cliente` varchar(255) NOT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_dns`
--

CREATE TABLE `tbl_dns` (
  `id` int(11) NOT NULL,
  `dns_title` varchar(255) NOT NULL,
  `dns_base` text NOT NULL,
  `status` int(1) NOT NULL DEFAULT 1,
  `cliente` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_notification`
--

CREATE TABLE `tbl_notification` (
  `id` int(11) NOT NULL,
  `notification_title` varchar(255) NOT NULL,
  `notification_msg` text NOT NULL,
  `notification_description` text NOT NULL,
  `notification_on` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_notification`
--

INSERT INTO `tbl_notification` (`id`, `notification_title`, `notification_msg`, `notification_description`, `notification_on`) VALUES
(5, 'Mathias27', 'Mathias27', '<p>Mathias27</p>', 1758843872);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_reports`
--

CREATE TABLE `tbl_reports` (
  `id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_pass` varchar(255) NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `report_msg` text NOT NULL,
  `report_on` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_settings`
--

CREATE TABLE `tbl_settings` (
  `id` int(11) NOT NULL,
  `app_name` varchar(100) NOT NULL,
  `app_logo` varchar(200) NOT NULL,
  `app_email` varchar(100) NOT NULL,
  `app_author` varchar(100) NOT NULL,
  `app_contact` varchar(100) NOT NULL,
  `app_website` varchar(150) NOT NULL,
  `app_description` text NOT NULL,
  `app_developed_by` varchar(150) NOT NULL,
  `app_privacy_policy` text NOT NULL,
  `app_terms` text NOT NULL,
  `purchase_code` text NOT NULL,
  `api_key` text NOT NULL,
  `onesignal_app_id` text NOT NULL,
  `onesignal_rest_key` text NOT NULL,
  `bg_login` text NOT NULL,
  `bg_main` text NOT NULL,
  `bg_banner` text NOT NULL,
  `bg_search` text NOT NULL,
  `bg_color` text NOT NULL,
  `bg_favorite` varchar(255) DEFAULT NULL,
  `bg_tv` varchar(255) DEFAULT NULL,
  `bg_series` varchar(255) DEFAULT NULL,
  `bg_movie` varchar(255) DEFAULT NULL,
  `app_titulo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_settings`
--

INSERT INTO `tbl_settings` (`id`, `app_name`, `app_logo`, `app_email`, `app_author`, `app_contact`, `app_website`, `app_description`, `app_developed_by`, `app_privacy_policy`, `app_terms`, `purchase_code`, `api_key`, `onesignal_app_id`, `onesignal_rest_key`, `bg_login`, `bg_main`, `bg_banner`, `bg_search`, `bg_color`, `bg_favorite`, `bg_tv`, `bg_series`, `bg_movie`, `app_titulo`) VALUES
(1, 'APP OFICIAL', '89953_logo.png', 'admin@gmail.com', 'admin', '000000000', 'admin', 'admin', 'admin', '<h3>What personal data we collect and why we collect it</h3><p><strong>Comments</strong></p><p>When visitors leave comments on the site we collect the data shown in the comments form, and also the visitor?s IP address and browser user agent string to help spam detection.</p><p>An anonymized string created from your email address (also called a hash) may be provided to the Gravatar service to see if you are using it. The Gravatar service privacy policy is available here: https://automattic.com/privacy/. After approval of your comment, your profile picture is visible to the public in the context of your comment.</p><h3><strong>Media</strong></h3><p>If you upload images to the website, you should avoid uploading images with embedded location data (EXIF GPS) included. Visitors to the website can download and extract any location data from images on the website.</p><h3><strong>Contact forms</strong></h3><h3><strong>Cookies</strong></h3><p>If you leave a comment on our site you may opt-in to saving your name, email address and website in cookies. These are for your convenience so that you do not have to fill in your details again when you leave another comment. These cookies will last for one year.</p><p>If you visit our login page, we will set a temporary cookie to determine if your browser accepts cookies. This cookie contains no personal data and is discarded when you close your browser.</p><p>When you log in, we will also set up several cookies to save your login information and your screen display choices. Login cookies last for two days, and screen options cookies last for a year. If you select ?Remember Me?, your login will persist for two weeks. If you log out of your account, the login cookies will be removed.</p><p>If you edit or publish an article, an additional cookie will be saved in your browser. This cookie includes no personal data and simply indicates the post ID of the article you just edited. It expires after 1 day.</p><h3><strong>Embedded content from other websites</strong></h3><p>Articles on this site may include embedded content (e.g. videos, images, articles, etc.). Embedded content from other websites behaves in the exact same way as if the visitor has visited the other website.</p><p>These websites may collect data about you, use cookies, embed additional third-party tracking, and monitor your interaction with that embedded content, including tracking your interaction with the embedded content if you have an account and are logged in to that website.</p><h3><strong>Analytics</strong></h3><h3><strong>Who we share your data with</strong></h3><h3><strong>How long we retain your data</strong></h3><p>If you leave a comment, the comment and its metadata are retained indefinitely. This is so we can recognize and approve any follow-up comments automatically instead of holding them in a moderation queue.</p><p>For users that register on our website (if any), we also store the personal information they provide in their user profile. All users can see, edit, or delete their personal information at any time (except they cannot change their username). Website administrators can also see and edit that information.</p><h3><strong>What rights you have over your data</strong></h3><p>If you have an account on this site, or have left comments, you can request to receive an exported file of the personal data we hold about you, including any data you have provided to us. You can also request that we erase any personal data we hold about you. This does not include any data we are obliged to keep for administrative, legal, or security purposes.</p><h3><strong>Where we send your data</strong></h3><p>Visitor comments may be checked through an automated spam detection service.</p>', '<h2><strong>Introduction</strong></h2><p>This document (the ?Terms?) together with the U.S. Privacy Policy (collectively the ?Agreement?) sets out the terms and conditions governing visits, access and use of the service by the end user (?you?).</p><p>The term ?you? includes additional registered users whenever permitted under the applicable subscription, visitors, and others who access or use any of the Services.</p><p>The ?Services? means the service branded our site, that are compatible for similarly situated digital music services. These may include, but are not limited to websites and applications for desktops, tablets and mobile handsets, set-top boxes and stereo equipment. The Services also include your ability to edit certain Service Content.</p><h2><br></h2><h2><strong>Content restrictions</strong></h2><p>The Services contains content, such as sound recordings, audiovisual works, other video or audio works, clips, images, graphics, text, software, works of authorship, files, documents, applications, artwork, trademarks, trade names, metadata, album titles, sound recording titles, artist names, intellectual property, or materials relating thereto or any other materials, and their selection, coordination and arrangement (collectively, the ?Service Content?). The Service Content is the property of our site and/or third parties and is protected by copyright under both United States and foreign laws.?</p><p>User content</p><p>To the extent allowed by the Service</p>', 'aMHznsg-cvoEV-GkJUd-nTUEB-ofHxlyRgq', 'q6ny5v4p-2536-rvj7-5jgc-ukp7qi56lx4w', '', '', '49008_bg_login.png', '67784_bg_main.jpg', '92947_bg_banner.png', '1766_bg_search.jpg', '#000000', '7130_bg_favorite.png', '19685_bg_tv.jpg', '83639_bg_series.jpg', '95602_bg_movie.jpg', 'Bienvenido!');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_smtp_settings`
--

CREATE TABLE `tbl_smtp_settings` (
  `id` int(5) NOT NULL,
  `smtp_type` varchar(20) NOT NULL DEFAULT 'server',
  `smtp_host` varchar(150) NOT NULL,
  `smtp_email` varchar(150) NOT NULL,
  `smtp_password` text NOT NULL,
  `smtp_secure` varchar(20) NOT NULL,
  `port_no` varchar(10) NOT NULL,
  `smtp_ghost` varchar(150) NOT NULL,
  `smtp_gemail` varchar(150) NOT NULL,
  `smtp_gpassword` text NOT NULL,
  `smtp_gsecure` varchar(20) NOT NULL,
  `gport_no` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_smtp_settings`
--

INSERT INTO `tbl_smtp_settings` (`id`, `smtp_type`, `smtp_host`, `smtp_email`, `smtp_password`, `smtp_secure`, `port_no`, `smtp_ghost`, `smtp_gemail`, `smtp_gpassword`, `smtp_gsecure`, `gport_no`) VALUES
(1, 'gmail', '', '', '', 'ssl', '465', '', '', '', 'tls', 587);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tbl_app`
--
ALTER TABLE `tbl_app`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tbl_dns`
--
ALTER TABLE `tbl_dns`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tbl_notification`
--
ALTER TABLE `tbl_notification`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tbl_reports`
--
ALTER TABLE `tbl_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tbl_settings`
--
ALTER TABLE `tbl_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tbl_smtp_settings`
--
ALTER TABLE `tbl_smtp_settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tbl_admin`
--
ALTER TABLE `tbl_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tbl_app`
--
ALTER TABLE `tbl_app`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tbl_dns`
--
ALTER TABLE `tbl_dns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `tbl_notification`
--
ALTER TABLE `tbl_notification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tbl_reports`
--
ALTER TABLE `tbl_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_settings`
--
ALTER TABLE `tbl_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tbl_smtp_settings`
--
ALTER TABLE `tbl_smtp_settings`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
--  PAINEL v2 - codigo de parceria, arvore de revendas, dispositivos
--  (Instalacao NOVA: ja incluido aqui. Banco ANTIGO: nao precisa rodar
--   nada, o painel cria estas colunas/tabelas sozinho no primeiro acesso.)
-- ============================================================
ALTER TABLE `tbl_admin`
  ADD COLUMN `parent_id` INT NOT NULL DEFAULT 0,
  ADD COLUMN `max_dns` INT NOT NULL DEFAULT 0,
  ADD COLUMN `max_devices` INT NOT NULL DEFAULT 0,
  ADD COLUMN `created_at` INT NOT NULL DEFAULT 0;

ALTER TABLE `tbl_dns`
  ADD COLUMN `owner_id` INT NOT NULL DEFAULT 0,
  ADD COLUMN `partner_code` VARCHAR(12) NOT NULL DEFAULT '',
  ADD COLUMN `created_at` INT NOT NULL DEFAULT 0,
  ADD INDEX `idx_partner_code` (`partner_code`),
  ADD INDEX `idx_owner` (`owner_id`);

ALTER TABLE `tbl_settings`
  ADD COLUMN `login_mode` VARCHAR(10) NOT NULL DEFAULT 'direct';

CREATE TABLE IF NOT EXISTS `tbl_devices` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `owner_id` INT NOT NULL DEFAULT 0,
  `dns_id` INT NOT NULL DEFAULT 0,
  `device_key` VARCHAR(80) NOT NULL,
  `mac` VARCHAR(40) NOT NULL DEFAULT '',
  `platform` VARCHAR(20) NOT NULL DEFAULT 'outro',
  `model` VARCHAR(120) NOT NULL DEFAULT '',
  `app_version` VARCHAR(30) NOT NULL DEFAULT '',
  `username` VARCHAR(120) NOT NULL DEFAULT '',
  `ip` VARCHAR(60) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `is_auto` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `first_seen` INT NOT NULL DEFAULT 0,
  `last_seen` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_owner_device` (`owner_id`, `device_key`),
  KEY `idx_dns` (`dns_id`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  v3: ativacao por MAC + nome do app (o painel tambem cria sozinho ao abrir)
-- ------------------------------------------------------------
ALTER TABLE `tbl_devices`
  ADD COLUMN `app_name` VARCHAR(60) NOT NULL DEFAULT '',
  ADD COLUMN `note` VARCHAR(120) NOT NULL DEFAULT '',
  ADD COLUMN `act_dns_id` INT NOT NULL DEFAULT 0,
  ADD COLUMN `act_user` VARCHAR(120) NOT NULL DEFAULT '',
  ADD COLUMN `act_pass` VARCHAR(190) NOT NULL DEFAULT '',
  ADD COLUMN `act_updated` INT NOT NULL DEFAULT 0,
  ADD INDEX `idx_mac` (`mac`);

-- ------------------------------------------------------------
--  v4: DNS reserva (secundaria) - o painel tambem cria sozinho ao abrir
-- ------------------------------------------------------------
ALTER TABLE `tbl_dns`
  ADD COLUMN `dns_backup` VARCHAR(255) NOT NULL DEFAULT '';

-- ------------------------------------------------------------
--  v5: landing page de ativacao (ativar.php) + Mercado Pago
--  (o painel tambem cria tudo sozinho ao abrir)
-- ------------------------------------------------------------
ALTER TABLE `tbl_devices`
  ADD COLUMN `act_expires` INT NOT NULL DEFAULT 0,
  ADD COLUMN `act_source` VARCHAR(20) NOT NULL DEFAULT '';

ALTER TABLE `tbl_dns`
  ADD COLUMN `origin` VARCHAR(10) NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS `tbl_lp_config` (
  `k` VARCHAR(60) NOT NULL,
  `v` TEXT NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbl_lp_plans` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `days` INT NOT NULL DEFAULT 30,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbl_lp_orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(40) NOT NULL,
  `mac` VARCHAR(40) NOT NULL DEFAULT '',
  `dns_base` VARCHAR(255) NOT NULL DEFAULT '',
  `m3u_user` VARCHAR(120) NOT NULL DEFAULT '',
  `m3u_pass` VARCHAR(190) NOT NULL DEFAULT '',
  `email` VARCHAR(150) NOT NULL DEFAULT '',
  `plan_id` INT NOT NULL DEFAULT 0,
  `plan_name` VARCHAR(80) NOT NULL DEFAULT '',
  `days` INT NOT NULL DEFAULT 0,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `method` VARCHAR(20) NOT NULL DEFAULT '',
  `mp_payment_id` VARCHAR(40) NOT NULL DEFAULT '',
  `mp_status` VARCHAR(40) NOT NULL DEFAULT '',
  `pix_qr` TEXT NULL,
  `pix_qr_b64` MEDIUMTEXT NULL,
  `pay_url` VARCHAR(500) NOT NULL DEFAULT '',
  `ip` VARCHAR(60) NOT NULL DEFAULT '',
  `device_id` INT NOT NULL DEFAULT 0,
  `expires_at` INT NOT NULL DEFAULT 0,
  `created_at` INT NOT NULL DEFAULT 0,
  `checked_at` INT NOT NULL DEFAULT 0,
  `paid_at` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token`),
  KEY `idx_mac` (`mac`),
  KEY `idx_status` (`status`),
  KEY `idx_mp_payment` (`mp_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tbl_lp_plans` (`name`, `days`, `price`, `status`, `sort`) VALUES
('Teste gratis', 1, 0.00, 0, 0),
('Mensal', 30, 20.00, 1, 1),
('Trimestral', 90, 50.00, 1, 2),
('Anual', 365, 150.00, 1, 3);
