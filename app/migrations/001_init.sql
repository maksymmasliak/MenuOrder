-- 001_init.sql: initial schema for MenuOrder.
-- Tables are created parents first, because of foreign keys.

CREATE TABLE categories
(
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE dishes
(
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id   INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    description   VARCHAR(500) NULL,
    price_kopecks INT UNSIGNED NOT NULL,
    photo_path    VARCHAR(255) NULL,
    is_archived   TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order    INT          NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL,
    updated_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_dishes_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT chk_dishes_price CHECK (price_kopecks > 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE dining_tables
(
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    label      VARCHAR(50)  NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dining_tables_label (label)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE orders
(
    id                       INT UNSIGNED                       NOT NULL AUTO_INCREMENT,
    table_id                 INT UNSIGNED                       NOT NULL,
    total_kopecks            INT UNSIGNED                       NOT NULL,
    comment                  VARCHAR(200)                       NULL,
    client_ip_hash           CHAR(64)                           NOT NULL,
    telegram_status          ENUM ('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    telegram_attempts        TINYINT UNSIGNED                   NOT NULL DEFAULT 0,
    telegram_last_error      VARCHAR(500)                       NULL,
    telegram_last_attempt_at DATETIME                           NULL,
    telegram_sent_at         DATETIME                           NULL,
    created_at               DATETIME                           NOT NULL,
    PRIMARY KEY (id),
    KEY idx_orders_telegram_retry (telegram_status, telegram_last_attempt_at),
    CONSTRAINT fk_orders_table FOREIGN KEY (table_id)
        REFERENCES dining_tables (id) ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE order_items
(
    id                     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_id               INT UNSIGNED     NOT NULL,
    dish_id                INT UNSIGNED     NOT NULL,
    name_snapshot          VARCHAR(150)     NOT NULL,
    price_snapshot_kopecks INT UNSIGNED     NOT NULL,
    quantity               TINYINT UNSIGNED NOT NULL,
    line_total_kopecks     INT UNSIGNED     NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_dish FOREIGN KEY (dish_id)
        REFERENCES dishes (id) ON DELETE RESTRICT,
    CONSTRAINT chk_order_items_quantity CHECK (quantity BETWEEN 1 AND 20)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE admins
(
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL,
    last_login_at DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rate_limit_hits
(
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket     VARCHAR(100)    NOT NULL,
    created_at DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rate_limit_hits_bucket_created (bucket, created_at)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;