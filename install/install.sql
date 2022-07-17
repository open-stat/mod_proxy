CREATE TABLE `mod_proxy` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `address` varchar(255) DEFAULT NULL,
    `port` varchar(255) DEFAULT NULL,
    `is_ssl_sw` enum('Y','N') NOT NULL DEFAULT 'N',
    `level_anonymity` enum('elite','anonymous','non_anonymous') DEFAULT 'non_anonymous',
    `country` varchar(255) DEFAULT NULL,
    `server_login` varchar(255) DEFAULT NULL,
    `server_password` varchar(255) DEFAULT NULL,
    `source_name` varchar(255) DEFAULT NULL,
    `connect_time` decimal(8,3) DEFAULT NULL,
    `pretransfer_time` decimal(8,3) DEFAULT NULL,
    `level_stability` varchar(10) DEFAULT NULL,
    `rating` int DEFAULT '0',
    `is_active_sw` enum('Y','N') NOT NULL DEFAULT 'N',
    `date_last_tested` timestamp NULL DEFAULT NULL,
    `date_last_used` timestamp NULL DEFAULT NULL,
    `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `date_last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `mod_proxy_domains` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `proxy_id` int unsigned NOT NULL,
    `domain` varchar(255) NOT NULL,
    `count_success` int unsigned DEFAULT '0',
    `count_failed` int unsigned DEFAULT '0',
    `date_used` date DEFAULT NULL,
    `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `date_last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `proxy_id` (`proxy_id`),
    CONSTRAINT `fk1_mod_proxy_domains` FOREIGN KEY (`proxy_id`) REFERENCES `mod_proxy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

