-- Roller tablosu
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) NOT NULL DEFAULT '#000000',
    permissions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kullanıcı-Rol ilişki tablosu
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan roller
INSERT INTO roles (name, color, permissions) VALUES
('admin', '#FF0000', 'admin.access,admin.settings,discussions.view,discussions.create,discussions.edit,discussions.delete,events.view,events.create,events.edit,events.delete,gallery.view,gallery.upload,gallery.delete,users.view,users.edit,users.delete'),
('moderator', '#00FF00', 'discussions.view,discussions.create,discussions.edit,discussions.delete,events.view,events.create,events.edit,events.delete,gallery.view,gallery.upload,gallery.delete,users.view'),
('member', '#0000FF', 'discussions.view,discussions.create,events.view,events.create,gallery.view,gallery.upload'),
('guest', '#808080', 'discussions.view,events.view,gallery.view'); 