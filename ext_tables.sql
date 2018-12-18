CREATE TABLE cf_celum_connect_fal (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(250) DEFAULT '' NOT NULL,
    crdate INT(11) UNSIGNED DEFAULT 0 NOT NULL,
    content mediumblob,
    expires INT(11) UNSIGNED DEFAULT 0 NOT NULL,
    PRIMARY KEY (id),
    KEY cache_id (identifier)
) ENGINE=InnoDB;