DROP TABLE IF EXISTS user_tracks;
DROP TABLE IF EXISTS tracks;
DROP TABLE IF EXISTS artists;
DROP TABLE IF EXISTS users;

-- Criação da tabela de Usuários
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    bio TEXT,
    profile_image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Criação da tabela de Artistas (para evitar redundância de nomes)
CREATE TABLE artists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    bio TEXT,
    country VARCHAR(100),
    image_url VARCHAR(500), -- URL da foto do artista
    external_id VARCHAR(255),
    provider VARCHAR(20) DEFAULT 'deezer',
    UNIQUE KEY idx_artist_external (external_id, provider)
) ENGINE=InnoDB;

-- Criação da tabela de Músicas (Tracks)
-- Aqui armazenamos as estatísticas globais solicitadas
CREATE TABLE tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    artist_id INT,
    album_name VARCHAR(255),
    release_date DATE,
    image_url VARCHAR(500), -- URL da capa do álbum
    duration_seconds INTEGER,
    external_id VARCHAR(255), -- ID da música na API externa
    provider VARCHAR(20) DEFAULT 'deezer',
    
    -- Estatísticas agregadas (facilita a leitura em massa)
    average_rating DECIMAL(3, 1) DEFAULT 0.0, -- Média das notas
    favorite_count INTEGER DEFAULT 0,         -- Total de favoritos
    listen_count INTEGER DEFAULT 0,           -- Total de pessoas que ouviram

    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
    UNIQUE KEY idx_track_external (external_id, provider)
) ENGINE=InnoDB;

-- Tabela de relacionamento: A "Lista" do Usuário
-- Onde cada pessoa coleciona e avalia suas músicas
CREATE TABLE user_tracks (
    user_id INT,
    track_id INT,
    rating INTEGER CHECK (rating >= 1 AND rating <= 10), -- Avaliação de 1 a 10
    is_favorite BOOLEAN DEFAULT FALSE,
    listened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (user_id, track_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Índices para performance (Crucial para bancos "gigantescos")
CREATE INDEX idx_tracks_average_rating ON tracks(average_rating DESC);
CREATE INDEX idx_user_tracks_user ON user_tracks(user_id);
CREATE INDEX idx_user_tracks_track ON user_tracks(track_id);
