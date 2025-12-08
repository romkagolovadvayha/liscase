#!/usr/bin/env python3
"""
Главный файл для запуска радио-сервера

Использование:
    python main.py --port 8080 --dir /path/to/music --shuffle

Или через переменные окружения:
    PORT=8080 DIR_MUSIC=/path/to/music python main.py
"""
import argparse
import sys
from pathlib import Path

# Добавляем текущую директорию в путь для импорта
sys.path.insert(0, str(Path(__file__).parent))

from server import RadioServer
from config import get_music_directory, get_port


def main():
    """Главная функция"""
    parser = argparse.ArgumentParser(
        description='Радио-сервер для потоковой передачи музыки',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Примеры использования:

  # Запуск с параметрами командной строки
  python main.py --port 8080 --dir /path/to/music

  # Запуск с перемешиванием
  python main.py --port 8080 --dir /path/to/music --shuffle

  # Запуск через переменные окружения
  PORT=8080 DIR_MUSIC=/path/to/music python main.py

  # Запуск нескольких серверов на разных портах
  python main.py --port 8081 --dir /path/to/music1 &
  python main.py --port 8082 --dir /path/to/music2 &
        """
    )
    
    parser.add_argument(
        '--port', '-p',
        type=int,
        default=None,
        help='Порт для прослушивания (по умолчанию: из переменной PORT или 8080)'
    )
    
    parser.add_argument(
        '--dir', '-d',
        type=str,
        default=None,
        help='Директория с музыкой (по умолчанию: из переменной DIR_MUSIC или текущая директория)'
    )
    
    parser.add_argument(
        '--shuffle', '-s',
        action='store_true',
        help='Включить перемешивание треков'
    )
    
    parser.add_argument(
        '--host',
        type=str,
        default='0.0.0.0',
        help='Хост для прослушивания (по умолчанию: 0.0.0.0)'
    )
    
    parser.add_argument(
        '--debug',
        action='store_true',
        help='Включить режим отладки'
    )
    
    args = parser.parse_args()
    
    # Получаем параметры из аргументов или переменных окружения
    port = args.port or get_port()
    music_directory = args.dir or get_music_directory()
    
    # Проверяем существование директории
    music_path = Path(music_directory)
    if not music_path.exists():
        print(f"❌ Ошибка: Директория {music_directory} не существует")
        sys.exit(1)
    
    if not music_path.is_dir():
        print(f"❌ Ошибка: {music_directory} не является директорией")
        sys.exit(1)
    
    try:
        # Создаем и запускаем сервер
        server = RadioServer(
            port=port,
            music_directory=str(music_path.absolute()),
            shuffle=args.shuffle
        )
        server.run(host=args.host, debug=args.debug)
    except KeyboardInterrupt:
        print("\n\n👋 Остановка сервера...")
        sys.exit(0)
    except Exception as e:
        print(f"❌ Ошибка при запуске сервера: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()

