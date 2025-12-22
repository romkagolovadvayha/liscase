import mysql from 'mysql2/promise';

let pool: mysql.Pool | null = null;

function getPool(): mysql.Pool {
  if (pool) {
    return pool;
  }

  const config = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'prostoj4',
    charset: 'utf8mb4', // Поддержка эмодзи и всех UTF-8 символов
    waitForConnections: true,
    connectionLimit: parseInt(process.env.DB_CONNECTION_LIMIT || '100', 10), // До 100 соединений для поддержки высокой нагрузки
    queueLimit: 1000, // Очередь до 1000 запросов при перегрузке
    enableKeepAlive: true,
    keepAliveInitialDelay: 0,
    // Оптимизация для высокой нагрузки
    maxIdle: 10, // Максимум простаивающих соединений
    idleTimeout: 600000, // 10 минут простоя перед закрытием
  };

  pool = mysql.createPool(config);
  return pool;
}

export async function getDbConnection(): Promise<mysql.PoolConnection> {
  const pool = getPool();
  // Используем timeout в опциях getConnection вместо устаревшего acquireTimeout
  return await pool.getConnection();
}

export async function query<T = any>(sql: string, params?: any[]): Promise<T[]> {
  let connection: mysql.PoolConnection | null = null;
  try {
    connection = await getDbConnection();
    // Устанавливаем кодировку для поддержки эмодзи
    await connection.execute('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    const [rows] = await connection.execute(sql, params || []);
    return rows as T[];
  } catch (error) {
    console.error('Database query error:', error);
    throw error;
  } finally {
    if (connection) {
      try {
        connection.release();
      } catch (releaseError) {
        console.error('Error releasing connection:', releaseError);
      }
      connection = null; // Явно обнуляем для безопасности
    }
  }
}

/**
 * Выполняет INSERT запрос и возвращает insertId
 */
export async function insert(sql: string, params?: any[]): Promise<number> {
  let connection: mysql.PoolConnection | null = null;
  try {
    connection = await getDbConnection();
    // Устанавливаем кодировку для поддержки эмодзи
    await connection.execute('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    const [result] = await connection.execute(sql, params || []) as any;
    return result.insertId;
  } catch (error) {
    console.error('Database insert error:', error);
    throw error;
  } finally {
    if (connection) {
      try {
        connection.release();
      } catch (releaseError) {
        console.error('Error releasing connection:', releaseError);
      }
      connection = null; // Явно обнуляем для безопасности
    }
  }
}

/**
 * Выполняет запрос без возврата результата (для транзакций)
 */
export async function execute(sql: string, params?: any[]): Promise<void> {
  let connection: mysql.PoolConnection | null = null;
  try {
    connection = await getDbConnection();
    // Устанавливаем кодировку для поддержки эмодзи
    await connection.execute('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    await connection.execute(sql, params || []);
  } catch (error) {
    console.error('Database execute error:', error);
    throw error;
  } finally {
    if (connection) {
      try {
        connection.release();
      } catch (releaseError) {
        console.error('Error releasing connection:', releaseError);
      }
      connection = null; // Явно обнуляем для безопасности
    }
  }
}

