"""
Конфигурация для радио-сервера
"""
import os
from pathlib import Path


class Config:
    """Базовая конфигурация"""
    
    # Порт по умолчанию
    DEFAULT_PORT = 8080
    
    # Поддерживаемые форматы аудио
    SUPPORTED_FORMATS = {'.mp3', '.m4a', '.flac', '.ogg', '.wav', '.aac'}
    
    # Размер чанка для потоковой передачи (в байтах)
    CHUNK_SIZE = 8192
    
    # Таймаут для клиентских соединений (в секундах)
    CLIENT_TIMEOUT = 300
    
    # Интервал обновления метаданных (в секундах)
    METADATA_UPDATE_INTERVAL = 5


def get_music_directory():
    """Получить директорию с музыкой из переменной окружения"""
    return os.getenv('DIR_MUSIC', os.getcwd())


def get_port():
    """Получить порт из переменной окружения"""
    return int(os.getenv('PORT', Config.DEFAULT_PORT))

