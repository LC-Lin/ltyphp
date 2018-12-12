CREATE TABLE IF NOT EXISTS setqq_users (
    id int NOT NULL AUTO_INCREMENT,
    uin bigint unsigned NOT NULL,
    last_login timestamp NOT NULL,
    CONSTRAINT pk__user PRIMARY KEY (id, uin)
);
