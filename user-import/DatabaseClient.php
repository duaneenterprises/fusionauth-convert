<?php
/**
 * Database Client for MySQL
 * 
 * Handles all database interactions for user import
 */

declare(strict_types=1);

class DatabaseClient
{
    private PDO $pdo;
    
    public function __construct(array $config)
    {
        // Force TCP/IP connection by using explicit host:port format
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        
        // Add additional options to force TCP/IP connection
        $options = $config['options'];
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
        
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    }
    
    /**
     * Get a batch of users from the database
     */
    public function getUsersBatch(int $offset, int $limit): array
    {
        $sql = "
            SELECT 
                id,
                email,
                username,
                user_url,
                level,
                first_name,
                middle_name,
                last_name,
                avatar,
                salt,
                password,
                birthdate,
                gender,
                phone_number,
                company,
                street_address,
                city_address,
                state_address,
                zip_code,
                country,
                jersey_name,
                jersey_number,
                title,
                confirmed,
                active,
                approved,
                created,
                updated
            FROM users 
            ORDER BY id 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total count of users
     */
    public function getUserCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM users";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
    
    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?array
    {
        $sql = "
            SELECT 
                id,
                email,
                username,
                user_url,
                level,
                first_name,
                middle_name,
                last_name,
                avatar,
                salt,
                password,
                birthdate,
                gender,
                phone_number,
                company,
                street_address,
                city_address,
                state_address,
                zip_code,
                country,
                jersey_name,
                jersey_number,
                title,
                confirmed,
                active,
                approved,
                created,
                updated
            FROM users 
            WHERE id = :id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        $sql = "
            SELECT 
                id,
                email,
                username,
                user_url,
                level,
                first_name,
                middle_name,
                last_name,
                avatar,
                salt,
                password,
                birthdate,
                gender,
                phone_number,
                company,
                street_address,
                city_address,
                state_address,
                zip_code,
                country,
                jersey_name,
                jersey_number,
                title,
                confirmed,
                active,
                approved,
                created,
                updated
            FROM users 
            WHERE email = :email
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Test database connection
     */
    public function testConnection(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get database statistics
     */
    public function getStats(): array
    {
        $stats = [];
        
        // Total users
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM users');
        $stats['total_users'] = (int)$stmt->fetch()['count'];
        
        // Users by confirmation status
        $stmt = $this->pdo->query('SELECT confirmed, COUNT(*) as count FROM users GROUP BY confirmed');
        $stats['confirmed_users'] = 0;
        $stats['unconfirmed_users'] = 0;
        while ($row = $stmt->fetch()) {
            if ($row['confirmed']) {
                $stats['confirmed_users'] = (int)$row['count'];
            } else {
                $stats['unconfirmed_users'] = (int)$row['count'];
            }
        }
        
        // Users by level
        $stmt = $this->pdo->query('SELECT level, COUNT(*) as count FROM users GROUP BY level ORDER BY level');
        $stats['users_by_level'] = [];
        while ($row = $stmt->fetch()) {
            $stats['users_by_level'][(int)$row['level']] = (int)$row['count'];
        }
        
        return $stats;
    }
} 