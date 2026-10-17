-- Utilisateur dédié à Debezium avec les droits minimaux nécessaires
-- à la lecture du binlog et au snapshot initial.
CREATE USER IF NOT EXISTS 'debezium'@'%' IDENTIFIED WITH mysql_native_password BY 'dbz';

GRANT SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT, LOCK TABLES
    ON *.* TO 'debezium'@'%';

FLUSH PRIVILEGES;
