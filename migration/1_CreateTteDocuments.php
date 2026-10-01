<?php
/*
 * File: 1_CreateTteDocuments.php
 * Created on Thu Oct 01 2026
 * Last Updated: Thu Oct 01 2026 5:53:26 PM
 * Author: Erwan Setyo Budi
 * Email: erwans818@gmail.com
 * License: The GNU General Public License, Version 3 (GPL-3.0) - Copyright (C) 2026 Erwan Setyo Budi. This program is free software.
 */

use SLiMS\Migration\Migration;
class CreateTteDocuments extends Migration
{
    public function up()
    {
        \SLiMS\DB::getInstance()->query(<<<SQL
CREATE TABLE IF NOT EXISTS `tte_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `verification_id` varchar(50) NOT NULL,
  `document_title` varchar(255) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `original_file` varchar(255) NOT NULL,
  `signed_file` varchar(255) DEFAULT NULL,
  `signer_uid` int NOT NULL,
  `signer_name` varchar(100) NOT NULL,
  `signed_at` datetime DEFAULT NULL,
  `page_number` int NOT NULL DEFAULT 1,
  `position_x` decimal(10,4) DEFAULT NULL,
  `position_y` decimal(10,4) DEFAULT NULL,
  `qr_size` decimal(10,4) NOT NULL DEFAULT 18.0000,
  `document_hash` char(64) DEFAULT NULL,
  `status` enum('draft','signed','revoked') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_id` (`verification_id`),
  KEY `signer_uid` (`signer_uid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down()
    {
        \SLiMS\DB::getInstance()->query("DROP TABLE IF EXISTS `tte_documents`");
    }
}
