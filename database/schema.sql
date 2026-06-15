
-- -----------------------------------------------------------------------------
-- Rollen (RBAC) — map naar OOP class: Role
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
    id          TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50)         NOT NULL,
    description VARCHAR(255)        NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, description) VALUES
    ('user',  'Standaard gebruiker — kan bestanden uploaden en delen');

-- -----------------------------------------------------------------------------
-- Gebruikers — map naar OOP class: User
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    id                      BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    role_id                 TINYINT UNSIGNED    NOT NULL DEFAULT 2,
    username                VARCHAR(50)         NOT NULL,
    email                   VARCHAR(255)        NOT NULL,
    password_hash           VARCHAR(255)        NOT NULL COMMENT 'Argon2id/bcrypt hash via password_hash()',
    is_active               TINYINT(1)          NOT NULL DEFAULT 1,
    email_verified_at       TIMESTAMP           NULL,
    failed_login_attempts   TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    locked_until            TIMESTAMP           NULL COMMENT 'Account tijdelijk geblokkeerd na te veel mislukte logins',
    last_login_at           TIMESTAMP           NULL,
    last_login_ip           VARCHAR(45)         NULL COMMENT 'IPv4 of IPv6',
    created_at              TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP           NULL COMMENT 'Soft delete — account gedeactiveerd zonder data te wissen',

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_id (role_id),
    KEY idx_users_is_active (is_active),

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sessies — map naar OOP class: UserSession
-- Sla NOOIT de ruwe sessietoken op; alleen SHA-256 hash.
-- -----------------------------------------------------------------------------
CREATE TABLE user_sessions (
    id              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED     NOT NULL,
    token_hash      CHAR(64)            NOT NULL COMMENT 'SHA-256 hash van de sessietoken',
    ip_address      VARCHAR(45)         NOT NULL,
    user_agent      VARCHAR(512)        NULL,
    expires_at      TIMESTAMP           NOT NULL,
    revoked_at      TIMESTAMP           NULL COMMENT 'Expliciet uitgelogd of sessie ingetrokken',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_sessions_token_hash (token_hash),
    KEY idx_user_sessions_user_id (user_id),
    KEY idx_user_sessions_expires_at (expires_at),

    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Wachtwoord reset — map naar OOP class: PasswordResetToken
-- Token wordt per e-mail verstuurd; in DB alleen de hash bewaren.
-- -----------------------------------------------------------------------------
CREATE TABLE password_reset_tokens (
    id              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED     NOT NULL,
    token_hash      CHAR(64)            NOT NULL,
    expires_at      TIMESTAMP           NOT NULL,
    used_at         TIMESTAMP           NULL,
    requested_ip    VARCHAR(45)         NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_tokens_hash (token_hash),
    KEY idx_password_reset_tokens_user_id (user_id),
    KEY idx_password_reset_tokens_expires_at (expires_at),

    CONSTRAINT fk_password_reset_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Bestanden — map naar OOP class: File
-- Bestandsnaam op schijf is een UUID (niet de originele naam) om path traversal te voorkomen.
-- -----------------------------------------------------------------------------
CREATE TABLE files (
    id                  BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    public_id           CHAR(36)            NOT NULL COMMENT 'UUID voor externe referenties (URLs, API)',
    owner_id            BIGINT UNSIGNED     NOT NULL,
    original_filename   VARCHAR(255)        NOT NULL,
    stored_filename     CHAR(36)            NOT NULL COMMENT 'UUID-bestandsnaam op de server',
    mime_type           VARCHAR(127)        NOT NULL,
    file_size_bytes     BIGINT UNSIGNED     NOT NULL,
    file_hash_sha256    CHAR(64)            NOT NULL COMMENT 'Integriteitscontrole bij upload/download',
    is_encrypted        TINYINT(1)          NOT NULL DEFAULT 0,
    encryption_iv       VARBINARY(16)       NULL COMMENT 'Alleen invullen als is_encrypted = 1',
    scan_status         ENUM('pending', 'clean', 'infected', 'failed') NOT NULL DEFAULT 'pending',
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP           NULL COMMENT 'Soft delete',

    PRIMARY KEY (id),
    UNIQUE KEY uq_files_public_id (public_id),
    UNIQUE KEY uq_files_stored_filename (stored_filename),
    KEY idx_files_owner_id (owner_id),
    KEY idx_files_scan_status (scan_status),
    KEY idx_files_deleted_at (deleted_at),

    CONSTRAINT fk_files_owner
        FOREIGN KEY (owner_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Bestandsdeling — map naar OOP class: FileShare
-- Eigenaar deelt een bestand met een andere geregistreerde gebruiker.
-- -----------------------------------------------------------------------------
CREATE TABLE file_shares (
    id                  BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    file_id             BIGINT UNSIGNED     NOT NULL,
    shared_by_user_id   BIGINT UNSIGNED     NOT NULL,
    shared_with_user_id BIGINT UNSIGNED     NOT NULL,
    permission          ENUM('view', 'download') NOT NULL DEFAULT 'download',
    expires_at          TIMESTAMP           NULL COMMENT 'NULL = geen vervaldatum',
    revoked_at          TIMESTAMP           NULL,
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_file_shares_file_recipient (file_id, shared_with_user_id),
    KEY idx_file_shares_shared_with (shared_with_user_id),
    KEY idx_file_shares_expires_at (expires_at),

    CONSTRAINT fk_file_shares_file
        FOREIGN KEY (file_id) REFERENCES files (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_file_shares_shared_by
        FOREIGN KEY (shared_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_file_shares_shared_with
        FOREIGN KEY (shared_with_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT chk_file_shares_not_self
        CHECK (shared_by_user_id <> shared_with_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tijdelijke downloadlinks — map naar OOP class: FileDownloadToken
-- Voor eenmalige of tijdelijke links zonder login (optioneel, met gehashte token).
-- -----------------------------------------------------------------------------
CREATE TABLE file_download_tokens (
    id              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    file_id         BIGINT UNSIGNED     NOT NULL,
    created_by_id   BIGINT UNSIGNED     NOT NULL,
    token_hash      CHAR(64)            NOT NULL,
    max_downloads   TINYINT UNSIGNED    NOT NULL DEFAULT 1,
    download_count  TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    expires_at      TIMESTAMP           NOT NULL,
    revoked_at      TIMESTAMP           NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_file_download_tokens_hash (token_hash),
    KEY idx_file_download_tokens_file_id (file_id),
    KEY idx_file_download_tokens_expires_at (expires_at),

    CONSTRAINT fk_file_download_tokens_file
        FOREIGN KEY (file_id) REFERENCES files (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_file_download_tokens_created_by
        FOREIGN KEY (created_by_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Audit log — map naar OOP class: AuditLog
-- Essentieel voor security monitoring en forensisch onderzoek.
-- -----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED     NULL COMMENT 'NULL bij mislukte login of anonieme actie',
    action          VARCHAR(100)        NOT NULL COMMENT 'bijv. login_success, login_failed, file_upload, file_download',
    resource_type   VARCHAR(50)         NULL COMMENT 'bijv. user, file, session',
    resource_id     BIGINT UNSIGNED     NULL,
    ip_address      VARCHAR(45)         NOT NULL,
    user_agent      VARCHAR(512)        NULL,
    status          ENUM('success', 'failure', 'denied') NOT NULL DEFAULT 'success',
    details         JSON                NULL COMMENT 'Extra context, geen gevoelige data',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_action (action),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_resource (resource_type, resource_id),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Database gebruiker met minimale rechten (pas aan naar jullie omgeving)
-- Voer dit alleen uit als je een aparte app-user wilt aanmaken.
-- -----------------------------------------------------------------------------
-- CREATE USER IF NOT EXISTS 'file_transfer_app'@'localhost' IDENTIFIED BY 'kies_een_sterk_wachtwoord';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON file_transfer.* TO 'file_transfer_app'@'localhost';
-- FLUSH PRIVILEGES;
